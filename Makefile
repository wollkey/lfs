SHELL := /bin/bash

GREEN  := \033[0;32m
YELLOW := \033[0;33m
BLUE   := \033[0;34m
RESET  := \033[0m

CONSOLE := php bin/console
DB      := data/lfs.sqlite

##
## Project
## -------

help: ## Show this help message
	@echo -e "$(BLUE)Available commands:$(RESET)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-16s$(RESET) %s\n", $$1, $$2}'
.PHONY: help

install: ## Install Composer dependencies
	composer install
.PHONY: install

init: ## Create data/ and var/ directories
	@mkdir -p data var
.PHONY: init

##
## Database
## --------

migrate: init ## Apply pending migrations
	@$(CONSOLE) migrate
.PHONY: migrate

seed-members: ## Insert or update the club roster
	@$(CONSOLE) members:seed
.PHONY: seed-members

seed-build: ## DEV: parse local HTML into data/ratings.seed.json (make seed-build DIR=path/to/html)
	@$(CONSOLE) seed:build $(DIR)
.PHONY: seed-build

seed-load: ## Load seed JSON into the database (safe on prod)
	@$(CONSOLE) seed:load
.PHONY: seed-load

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

rate: ## Set a rating: make rate FILM=slug MEMBER=user SCORE=9
	@$(CONSOLE) rating:set $(FILM) $(MEMBER) $(SCORE)
.PHONY: rate

show: ## Show ratings for a film: make show FILM=slug
	@$(CONSOLE) film:show $(FILM)
.PHONY: show

import: ## Rebuild from saved HTML: make import SRC=tests/Fixtures
	@$(CONSOLE) import:html $(SRC)
.PHONY: import

fetch-fixtures: ## DEV: scrape HTML (needs LBXD_USER/LBXD_PASS; may be blocked)
	@$(CONSOLE) fixtures:fetch
.PHONY: fetch-fixtures

##
## Backups
## -------

backup: init ## Dump DB to backups/lfs-DATE.sql
	@sqlite3 $(DB) ".dump" > backups/lfs-$(shell date +%F).sql
	@echo -e "$(GREEN)✓ Backup: backups/lfs-$(shell date +%F).sql$(RESET)"
.PHONY: backup

restore: ## Restore from a dump: make restore FILE=backups/lfs-2026-07-05.sql
	@test -n "$(FILE)" || (echo "Usage: make restore FILE=backups/xxx.sql" && exit 1)
	@rm -f $(DB)
	@sqlite3 $(DB) < $(FILE)
	@echo -e "$(GREEN)✓ Restored $(DB) from $(FILE)$(RESET)"
.PHONY: restore

##
## Code quality
## ------------

lint: ## Fix code style with PHP CS Fixer
	@vendor/bin/php-cs-fixer fix --verbose
.PHONY: lint

lint-check: ## Check code style (dry run)
	@vendor/bin/php-cs-fixer fix --dry-run --diff --verbose
.PHONY: lint-check

stan: ## Run PHPStan static analysis
	@vendor/bin/phpstan analyse
.PHONY: stan

test: ## Run PHPUnit
	@vendor/bin/phpunit $(ARGS)
.PHONY: test

check: lint-check stan test ## Run all quality checks
	@echo -e "$(GREEN)✓ All checks passed$(RESET)"
.PHONY: check

# rector: ## Run Rector refactoring
# 	@vendor/bin/rector process
# .PHONY: rector
#
# rector-check: ## Check Rector rules (dry run)
# 	@vendor/bin/rector process --dry-run
# .PHONY: rector-check
