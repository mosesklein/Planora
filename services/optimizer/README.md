# Optimizer utilities

This folder contains helper scripts for geocoding stops and building a travel-time matrix using OSRM.

## Quickstart

1. Start the OSRM backend (exposed on port 5000 inside the compose network):

   ```bash
   docker compose -f api/compose.yaml up osrm
   ```

2. In a separate shell, run the end-to-end smoke test on the bundled sample data:

   ```bash
   make optimizer-smoke
   ```

   If you are running the scripts from your host instead of inside Docker, override the OSRM host published on localhost:

   ```bash
   OSRM_BASE_URL=http://localhost:5000 make optimizer-smoke
   ```

This will generate `services/optimizer/sample_data/geocoded_stops.csv` and `services/optimizer/sample_data/travel_matrix.json`.
