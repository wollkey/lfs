SHELL := /bin/bash

DC := docker compose

## CLI xdebug is off by default for speed; `make debug <target>` turns it on.
XDEBUG_ENV := -e XDEBUG_MODE=off
ifeq (debug,$(findstring debug,$(MAKECMDGOALS)))
    XDEBUG_ENV :=
endif

## Run natively in CI, inside the container locally.
ifdef CI
    PHP      :=
    PHP_TTY  :=
    COMPOSER := composer
else
    PHP      := $(DC) exec -T $(XDEBUG_ENV) web
    PHP_TTY  := $(DC) exec $(XDEBUG_ENV) web
    COMPOSER := $(PHP) composer
endif

CONSOLE     := $(PHP) php bin/console
CONSOLE_TTY := $(PHP_TTY) php bin/console
DB          := data/lfs.sqlite

GREEN  := \033[0;32m
YELLOW := \033[0;33m
BLUE   := \033[0;34m
RESET  := \033[0m

##
## Project
## -------

help: ## Show this help message
	@echo -e "$(BLUE)Available commands:$(RESET)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
	   awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-16s$(RESET) %s\n", $$1, $$2}'
.PHONY: help

debug: ## Turn on CLI xdebug for the next target (usage: make debug test)
	@:
.PHONY: debug

install: ## Install Composer dependencies
	$(COMPOSER) install
.PHONY: install

init: ## Create data/, var/logs/ and backups/ directories
	@mkdir -p data var/logs var/telegram backups
.PHONY: init

##
## Docker
## ------

build: ## Build the Docker image
	$(DC) build --pull
.PHONY: build

up: ## Start the container
	$(DC) up -d
	@echo -e "$(GREEN)✓ Container started$(RESET)"
.PHONY: up

down: ## Stop the container
	$(DC) down
	@echo -e "$(GREEN)✓ Container stopped$(RESET)"
.PHONY: down

restart: down up ## Restart the container
.PHONY: restart

logs: ## Follow container logs
	$(DC) logs -f
.PHONY: logs

sh: ## Open a shell in the container
	$(PHP_TTY) sh
.PHONY: sh

##
## Database
## --------

migrate: init ## Apply pending migrations
	@$(CONSOLE) migrate
.PHONY: migrate

seed: ## Update the DB from data/: new films, ratings, missing posters
	@$(CONSOLE) seed $(DIR)
.PHONY: seed

refresh-posters: ## Re-download every poster at full resolution
	@$(CONSOLE) posters:fetch --force
.PHONY: refresh-posters

##
## Data entry
## ----------

rate: ## Add a member's ratings interactively (film by title, score 1–10)
	@$(CONSOLE_TTY) rating:add
.PHONY: rate

## Runs on the host, not in the container: it drives the `claude` CLI and your session.
reviews: pull-updates ## Import reviews from the captured Telegram log
	@php bin/console reviews:import $(ARGS)
.PHONY: reviews

pull-updates: init ## Fetch the Telegram capture log from production
	@rsync -az $(DEPLOY_SSH):$(REMOTE_DIR)/telegram/ var/telegram/
	@echo -e "$(GREEN)✓ Capture log pulled$(RESET)"
.PHONY: pull-updates

pick: ## Assign film pickers interactively (films without a picker)
	@$(CONSOLE_TTY) rounds:pick
.PHONY: pick

queue: ## Move a member in the picking queue (usage: make queue, or make queue ARGS="lenka end")
	@$(CONSOLE_TTY) members:move $(ARGS)
.PHONY: queue

##
## Backups
## -------

backup: init ## Dump DB to backups/lfs-DATE.sql
	@$(PHP) sqlite3 $(DB) ".dump" > backups/lfs-$(shell date +%F).sql
	@echo -e "$(GREEN)✓ Backup: backups/lfs-$(shell date +%F).sql$(RESET)"
.PHONY: backup

restore: ## Restore from a dump: make restore FILE=backups/lfs-2026-07-05.sql
	@test -n "$(FILE)" || (echo "Usage: make restore FILE=backups/xxx.sql" && exit 1)
	@rm -f $(DB)
	@$(PHP) sqlite3 $(DB) < $(FILE)
	@echo -e "$(GREEN)✓ Restored $(DB) from $(FILE)$(RESET)"
.PHONY: restore

##
## Code quality
## ------------

lint: ## Fix code style with PHP CS Fixer
	@$(PHP) vendor/bin/php-cs-fixer fix --verbose
.PHONY: lint

lint-check: ## Check code style (dry run)
	@$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff --verbose
.PHONY: lint-check

stan: ## Run PHPStan static analysis
	@$(PHP) vendor/bin/phpstan analyse
.PHONY: stan

test: ## Run PHPUnit
	@$(PHP) vendor/bin/phpunit $(ARGS)
.PHONY: test

check: lint-check stan rector-check test ## Run all quality checks
	@echo -e "$(GREEN)✓ All checks passed$(RESET)"
.PHONY: check

rector: ## Run Rector refactoring
	@$(PHP) vendor/bin/rector
.PHONY: rector

rector-check: ## Check Rector rules (dry run)
	@$(PHP) vendor/bin/rector --dry-run
.PHONY: rector-check

##
## Deployment
## ----------

DEPLOY_SSH ?= lfs-vds
REMOTE_DIR := /var/www/lfs

deploy: deploy-db deploy-posters ## Deploy the database and the posters
.PHONY: deploy

deploy-db: backup ## Copy the DB file to production and restart the app
	@SNAP=backups/lfs-$(shell date +%F).sqlite; \
	rm -f $$SNAP; \
	$(PHP) sqlite3 $(DB) "VACUUM INTO '$$SNAP'"; \
	scp $$SNAP $(DEPLOY_SSH):/tmp/lfs.sqlite; \
	ssh $(DEPLOY_SSH) 'rm -f $(REMOTE_DIR)/data/lfs.sqlite $(REMOTE_DIR)/data/lfs.sqlite-wal $(REMOTE_DIR)/data/lfs.sqlite-shm \
		&& mv /tmp/lfs.sqlite $(REMOTE_DIR)/data/lfs.sqlite \
		&& chmod 644 $(REMOTE_DIR)/data/lfs.sqlite \
		&& docker restart lfs-app'
	@echo -e "$(GREEN)✓ Database deployed$(RESET)"
.PHONY: deploy-db

deploy-posters: ## Sync local posters to production
	@rsync -az --delete public/posters/ $(DEPLOY_SSH):$(REMOTE_DIR)/posters/
	@echo -e "$(GREEN)✓ Posters deployed$(RESET)"
.PHONY: deploy-posters

##
## Git Hooks
## ---------

hooks: ## Install git hooks
	git config core.hooksPath .githooks
	chmod +x .githooks/pre-push
	@echo -e "$(GREEN)✓ Git hooks installed$(RESET)"
.PHONY: hooks
