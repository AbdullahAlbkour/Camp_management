.DEFAULT_GOAL := help
.PHONY: help setup install fresh seed serve test lint fix check digest prune up down logs shell

help: ## Show the available targets
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

setup: ## First-time setup: env file, dependencies, key, schema and demo data
	@test -f .env || cp .env.example .env
	composer install
	php artisan key:generate
	php artisan migrate --seed
	@echo "Ready. Run 'make serve' and sign in as admin@camp.local."

install: ## Install PHP dependencies
	composer install

fresh: ## Drop and rebuild the database with demo data
	php artisan migrate:fresh --seed

seed: ## Re-run the seeders
	php artisan db:seed

serve: ## Start the development server on http://localhost:8000
	php artisan serve

test: ## Run the test suite
	vendor/bin/phpunit

lint: ## Check code style without changing files
	vendor/bin/pint --test

fix: ## Apply code style fixes
	vendor/bin/pint

check: lint test ## Run everything CI runs

digest: ## Run the daily digest now
	php artisan camps:daily-digest

prune: ## Preview the audit-log retention sweep
	php artisan camps:prune-audit-logs --dry-run

up: ## Start the Docker stack
	docker compose up -d --build

down: ## Stop the Docker stack
	docker compose down

logs: ## Follow the application logs
	docker compose logs -f app

shell: ## Open a shell in the application container
	docker compose exec app sh
