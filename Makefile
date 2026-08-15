.PHONY: help \
        install update \
        docker-up docker-up-all docker-down docker-logs \
        console backend backend-test frontend cache-clean db-reset fixtures fixtures-test serve serve-test \
        test phpunit phpunit-unit phpunit-non-unit behat behat-js \
        static rector rector-fix phpstan ecs fix lint-container validate \
        verify pre-commit install-hooks \
        setup app ci

# Building the full Sylius container exceeds PHP's default 128M CLI limit, so every console call
# has to lift it - `make backend` on a clean checkout dies otherwise.
CONSOLE = php -d memory_limit=-1 vendor/bin/console
BEHAT   = APP_ENV=test vendor/bin/behat --colors --strict --no-interaction -f progress

# Default target: list the available commands.
help:
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| sort \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

## --- Dependencies -----------------------------------------------------------

install: ## Install composer dependencies
	composer install --no-interaction

update: ## Update composer dependencies
	composer update --no-interaction

## --- Docker -----------------------------------------------------------------

docker-up: ## Start the MySQL container (host port 3307)
	docker compose up -d mysql

docker-up-all: ## Start MySQL + headless Chrome (for @javascript Behat)
	docker compose up -d

docker-down: ## Stop and remove the containers
	docker compose down

docker-logs: ## Tail the container logs
	docker compose logs -f

## --- Test application -------------------------------------------------------

backend: ## Create the dev database and run the migrations (APP_ENV=dev)
	APP_ENV=dev $(CONSOLE) doctrine:database:create --if-not-exists --no-interaction
	APP_ENV=dev $(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

backend-test: ## Create the test database and run the migrations (APP_ENV=test; used by Behat and CI)
	APP_ENV=test $(CONSOLE) doctrine:database:create --if-not-exists --no-interaction
	APP_ENV=test $(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

frontend: ## Install and build the test application assets (needs Node 20+)
	# Sylius' admin assets require Node >= 20; on an older Node yarn refuses the install and every
	# page then 500s on the missing Webpack entrypoints file.
	(cd vendor/sylius/test-application && yarn install && yarn build)
	$(CONSOLE) assets:install --no-interaction

cache-clean: ## Remove the test application cache (all environments)
	rm -rf vendor/sylius/test-application/var/cache/*

db-reset: ## Drop and recreate the database, then run the migrations
	$(CONSOLE) doctrine:database:drop --force --if-exists --no-interaction
	$(CONSOLE) doctrine:database:create --no-interaction
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

fixtures: ## (Re)load the default Sylius fixtures plus the plugin's demo gift cards
	$(CONSOLE) sylius:fixtures:load default --no-interaction
	$(CONSOLE) sylius:fixtures:load madcoders_gift_card --no-interaction

fixtures-test: ## Load the fixtures into the test database (used by CI)
	APP_ENV=test $(CONSOLE) sylius:fixtures:load default --no-interaction
	APP_ENV=test $(CONSOLE) sylius:fixtures:load madcoders_gift_card --no-interaction

serve: ## Serve the DEV application on http://127.0.0.1:8080 (dev database)
	APP_ENV=dev $(CONSOLE) cache:clear
	(cd vendor/sylius/test-application && APP_ENV=dev symfony serve --port=8080)

serve-test: ## Serve the TEST application on http://127.0.0.1:8080 (test database; for @javascript Behat)
	APP_ENV=test $(CONSOLE) cache:clear
	(cd vendor/sylius/test-application && APP_ENV=test symfony serve --port=8080)

## --- Setup ------------------------------------------------------------------

setup: install docker-up frontend backend ## One-shot local setup (deps + docker + assets + db)
	@echo "Setup complete. Run 'make verify' to run the quality gate."

app: docker-up frontend db-reset fixtures ## Spin up the local app: docker + assets + fresh schema + fixtures
	@echo "Local app ready. Start it with 'make serve' (http://127.0.0.1:8080)."

## --- Tests ------------------------------------------------------------------

test: phpunit behat ## Run PHPUnit and the non-JS Behat suite

phpunit: ## Run the full PHPUnit suite
	vendor/bin/phpunit --colors=always

phpunit-unit: ## Run the unit tests only (no database, no kernel boot)
	vendor/bin/phpunit --colors=always --testsuite=unit

phpunit-non-unit: ## Run the functional and integration tests (needs a database)
	vendor/bin/phpunit --colors=always --testsuite=non-unit

behat: ## Run the non-JavaScript Behat suite
	$(BEHAT) --tags="~@javascript"

behat-js: ## Run the JavaScript Behat suite (needs docker-up-all + serve-test)
	$(BEHAT) --tags="@javascript"

## --- Static analysis & code style -------------------------------------------

static: phpstan ecs lint-container ## Run all static analysis and code style checks

phpstan: ## Run PHPStan
	vendor/bin/phpstan analyse -c phpstan.neon

ecs: ## Check code style (no changes)
	vendor/bin/ecs check

fix: ## Auto-fix code style with ECS
	vendor/bin/ecs check --fix

rector: ## Report pending Rector changes (no writes)
	vendor/bin/rector process --dry-run

rector-fix: ## Apply Rector (PHP 8.3 modernization of src)
	vendor/bin/rector process

lint-container: ## Validate the service container of the test application
	APP_ENV=test $(CONSOLE) lint:container

validate: ## Validate composer.json
	composer validate --ansi --strict

## --- Quality gate / hooks ---------------------------------------------------

verify: validate phpstan ecs phpunit-unit ## Run the fast quality gate (no database required)

pre-commit: fix phpstan ecs phpunit-unit ## Auto-fix style, then verify static analysis + unit tests

install-hooks: ## Point git at the repo hooks (.githooks) and set the commit template
	git config core.hooksPath .githooks
	git config commit.template .gitmessage
	@echo "Git hooks installed. 'make pre-commit' will run on every commit."
	@echo "Commit template set (.gitmessage); commits follow Conventional Commits."

## --- CI ---------------------------------------------------------------------

install-test: ## Install the plugin into a throwaway Sylius app by following docs/INSTALLATION.md
	# Proves the *documentation* works, which nothing else does: the rest of the suite runs against
	# sylius/test-application, which is pre-wired through SYLIUS_TEST_APP_* variables.
	DATABASE_URL=$${DATABASE_URL:-mysql://root@127.0.0.1:3307/gift_card_install_test} \
		tests/Installation/install.sh

ci: validate static phpunit behat ## Full CI pipeline (expects a prepared test application)
