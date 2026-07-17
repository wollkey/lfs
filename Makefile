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
	@mkdir -p data var/logs backups
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

seed-members: ## Insert or update the club roster from data/roster.json
	@$(CONSOLE) members:seed
.PHONY: seed-members

seed: ## Parse local HTML into the DB (films, ratings, round structure)
	@$(CONSOLE) seed $(DIR)
.PHONY: seed

fetch-posters: ## DEV: download posters from data/list.html into public/posters/
	@$(CONSOLE) posters:fetch
.PHONY: fetch-posters

fresh: ## Recreate empty DB + seed roster (DROPS DATA)
	@rm -f $(DB)
	@$(MAKE) migrate
	@$(MAKE) seed-members
	@echo -e "$(GREEN)✓ Fresh database ready$(RESET)"
.PHONY: fresh

##
## Data entry
## ----------

rate: ## Add a member's ratings interactively (film by title, score 1–10)
	@$(CONSOLE_TTY) rating:add
.PHONY: rate

pick: ## Assign film pickers interactively (films without a picker)
	@$(CONSOLE_TTY) rounds:pick
.PHONY: pick

fetch-fixtures: ## DEV: scrape HTML (needs LBXD_USER/LBXD_PASS; may be blocked)
	@$(CONSOLE) fixtures:fetch
.PHONY: fetch-fixtures

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

deploy-db: backup ## Push a fresh DB dump to production and restart the app
	@DUMP=backups/lfs-$(shell date +%F).sql; \
	scp $$DUMP $(DEPLOY_SSH):/tmp/lfs.sql; \
	ssh $(DEPLOY_SSH) 'rm -f $(REMOTE_DIR)/data/lfs.sqlite \
		&& sqlite3 $(REMOTE_DIR)/data/lfs.sqlite < /tmp/lfs.sql \
		&& chmod 644 $(REMOTE_DIR)/data/lfs.sqlite \
		&& rm /tmp/lfs.sql \
		&& docker restart lfs-app'
	@echo -e "$(GREEN)✓ Database deployed$(RESET)"
.PHONY: deploy-db

deploy-posters: ## Sync local posters to production
	@rsync -az --delete public/posters/ $(DEPLOY_SSH):$(REMOTE_DIR)/posters/
	@echo -e "$(GREEN)✓ Posters deployed$(RESET)"
.PHONY: deploy-posters
