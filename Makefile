# One entry point for the tasks this repository has, on the host or in the container.
#
# The container targets exist because the generated-data checks only mean something on a host
# carrying the tz release the committed files were built against — see docs/tools/generated-data.md.
# Everything else runs perfectly well on your own PHP.

SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose -f docker/compose.yaml
# Compose reads these to run the container as you, so files it writes to the mounted tree do not
# come back owned by root.
DOCKER_ENV := UID=$(shell id -u) GID=$(shell id -g)
RUN := $(DOCKER_ENV) $(COMPOSE) run --rm chrono

.PHONY: help
help: ## Show this help
	@echo "Host targets — run on your own PHP:"
	@grep -E '^[a-z][a-z-]*:.*?## ' $(MAKEFILE_LIST) | grep -v '^docker' \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Container targets — a PHP carrying tzdata $$(cat resources/tzdata-version.txt):"
	@grep -E '^docker[a-z-]*:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

# ── host ────────────────────────────────────────────────────────────────────────────────────

.PHONY: install
install: ## Install dependencies
	composer install

.PHONY: test
test: ## Run the suite
	composer test

.PHONY: test-all
test-all: ## Run the suite including the decree-driven tzdata group
	vendor/bin/pest

.PHONY: lint
lint: ## Pint, PHPStan, deptrac and Rector, all in check mode
	composer lint

.PHONY: fix
fix: ## Apply Pint and Rector fixes
	composer pint-fix
	composer rector-fix

.PHONY: sync
sync: ## Regenerate the enums and alias map from this host's tz database
	composer sync

.PHONY: sync-check
sync-check: ## Verify the generated data matches the pinned tz release
	composer sync-check

.PHONY: check
check: lint test sync-check ## Everything CI runs

.PHONY: tzdata
tzdata: ## Report the pinned tz release and what PECL publishes
	@php tools/tzdata-release.php --check || true

# ── container ───────────────────────────────────────────────────────────────────────────────

.PHONY: docker-build
docker-build: ## Build the development image
	$(DOCKER_ENV) $(COMPOSE) build

.PHONY: docker-test
docker-test: ## Run the suite in the container
	$(RUN) composer test

.PHONY: docker-test-all
docker-test-all: ## Run the suite in the container, tzdata group included
	$(RUN) vendor/bin/pest

.PHONY: docker-lint
docker-lint: ## Run the static analysis stack in the container
	$(RUN) composer lint

.PHONY: docker-sync
docker-sync: ## Regenerate against the pinned tz release — the reason the container exists
	$(RUN) composer sync

.PHONY: docker-sync-check
docker-sync-check: ## Verify the generated data, for real, against the pinned release
	$(RUN) composer sync-check

.PHONY: docker-check
docker-check: ## Everything CI runs, in the container
	$(RUN) composer lint
	$(RUN) composer test
	$(RUN) composer sync-check

.PHONY: docker-shell
docker-shell: ## Open a shell in the container
	$(RUN) bash

.PHONY: docker-clean
docker-clean: ## Remove the image and its volumes
	$(DOCKER_ENV) $(COMPOSE) down --volumes --rmi local
