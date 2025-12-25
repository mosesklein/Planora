OSRM_BASE_URL ?= http://osrm:5000

.PHONY: optimizer-smoke

optimizer-smoke:
	python3 services/optimizer/geocode_stops.py \
		--stops services/optimizer/sample_data/stops.csv \
		--cache services/optimizer/sample_data/cache.db \
		--output services/optimizer/sample_data/geocoded_stops.csv
	python3 services/optimizer/build_matrix.py \
		--geocoded services/optimizer/sample_data/geocoded_stops.csv \
		--output services/optimizer/sample_data/travel_matrix.json \
		--osrm-base-url $(OSRM_BASE_URL)
