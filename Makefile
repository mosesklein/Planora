COMPOSE_CMD ?= docker compose
DEV_COMPOSE = api/compose.yaml
PROD_COMPOSE = api/compose.prod.yaml
STACK ?= dev
COMPOSE_FILE = $(if $(filter $(STACK),prod),$(PROD_COMPOSE),$(DEV_COMPOSE))
APP_SERVICE = $(if $(filter $(STACK),prod),app,laravel.test)
SERVICE ?= $(APP_SERVICE)

OSRM_BASE_URL ?= http://osrm:5000

.PHONY: optimizer-smoke dev-up dev-down prod-up prod-down logs shell migrate

optimizer-smoke:
	python3 services/optimizer/geocode_stops.py \
		--stops services/optimizer/sample_data/stops.csv \
		--cache services/optimizer/sample_data/cache.db \
		--output services/optimizer/sample_data/geocoded_stops.csv
	python3 services/optimizer/build_matrix.py \
		--geocoded services/optimizer/sample_data/geocoded_stops.csv \
		--output services/optimizer/sample_data/travel_matrix.json \
		--osrm-base-url $(OSRM_BASE_URL)

dev-up:
	$(COMPOSE_CMD) -f $(DEV_COMPOSE) up -d

dev-down:
	$(COMPOSE_CMD) -f $(DEV_COMPOSE) down

prod-up:
	$(COMPOSE_CMD) -f $(PROD_COMPOSE) up -d --build

prod-down:
	$(COMPOSE_CMD) -f $(PROD_COMPOSE) down

logs:
	$(COMPOSE_CMD) -f $(COMPOSE_FILE) logs -f

shell:
	$(COMPOSE_CMD) -f $(COMPOSE_FILE) exec $(SERVICE) sh

migrate:
	$(COMPOSE_CMD) -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan migrate --force
