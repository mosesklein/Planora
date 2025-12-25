# Planora - Smart Bus Routing & Optimization

Planora is a decision-support system for school transportation planners.
Its primary goal is to **validate routing logic, avoid external API cost traps, and scale cleanly across districts**.

The system is designed **headless-first**: routing correctness and reproducibility come before UI or visualization.

---

## 🏗 Architecture Overview

Planora is organized into **three strictly separated layers**. Each layer has a single responsibility and no hidden coupling.

### Layer 1 - Core Routing Engine (Python)

The authoritative computation layer.

* Address normalization and validation
* Geocoding (controlled, cached, reproducible)
* Distance and travel-time matrix generation
* Route optimization using OR-Tools
* CLI-driven and headless by design

This layer **never depends on Laravel or the frontend**.

---

### Layer 2 - Control Plane (Laravel API)

The orchestration and persistence layer.

* Multi-school and multi-company data models
* Job lifecycle tracking
* Permissions and auditability
* File uploads and validation
* Queue and job state management

This layer **never performs routing math**. It only schedules, stores, and coordinates.

---

### Layer 3 - Presentation Layer (Next.js)

The visualization and interaction layer.

* CSV uploads
* Job triggering
* Route and stop visualization
* Map rendering using MapLibre / Leaflet

This layer is **replaceable** and never authoritative.

---

## 🛠 Technology Stack

**Routing & Math**

* Python
* OR-Tools
* Self-hosted OSRM (Docker)

**Backend**

* Laravel API
* PostgreSQL + PostGIS
* Redis (queues and job state)

**Frontend**

* Next.js

**Infrastructure**

* Docker Compose

---

## ⚖️ Core Invariants

These rules are non-negotiable and define the system:

* **Stops are immutable**
  A stop only changes if the physical address changes.

* **Jobs are ephemeral**
  Any optimization run can be recomputed at any time.

* **Routes are disposable outputs**
  They are derived artifacts, not state.

* **OSRM only**
  Google Distance Matrix is intentionally forbidden to avoid cost explosions.

---

## 🚀 Current Implementation Status

* Docker Compose environment initialized
* Laravel models created for `Company` and `Stop`
* Database seeded with Brooklyn-based test stops
* Self-hosted OSRM running locally
* Service-to-service routing verified
* GitHub repository connected and synchronized

This milestone confirms **end-to-end routing correctness without UI**.

---

## 📂 Repository Structure

```
/api
  Laravel control plane (Layer 2)

/web
  Next.js presentation layer (Layer 3)

/services/optimizer
  Python routing engine (Layer 1)
```

---

## 🗺 OSRM Development Setup

### Start services

```bash
cd api
./vendor/bin/sail up -d
```

---

### Host test (OSRM exposed on port 5001)

```bash
curl -s "http://localhost:5001/route/v1/driving/-73.9857,40.7484;-73.9712,40.7831?overview=false" | head
```

---

### Inside Laravel container (service-to-service networking)

```bash
./vendor/bin/sail exec laravel.test \
  curl -s "http://osrm:5000/route/v1/driving/-73.9857,40.7484;-73.9712,40.7831?overview=false" | head
```

A valid JSON response confirms that OSRM is reachable from the application layer.

---

## ▶️ Run the API and Web UI Together (local dev)

1. **Start the Laravel API**
   * With Sail: `cd api && ./vendor/bin/sail up`
   * Or directly: `cd api && php artisan serve --host=0.0.0.0 --port=8000`
2. **Configure the frontend to reach the API**
   * Create `web/.env.local` (or copy from `.env.local.example`) with `NEXT_PUBLIC_API_BASE_URL=http://localhost:8000`
3. **Run the Next.js app**
   * `cd web && npm install`
   * `npm run dev`
4. **Test end-to-end**
   * Visit `http://localhost:3000/routing-jobs` to check health, upload CSVs, view jobs, and trigger synchronous processing against the local API.

---

## 📌 What This README Represents

This document is the **baseline contract** for Planora.

* Architecture is locked
* Layer boundaries are fixed
* OSRM is the single routing authority
* UI remains secondary until routing is proven correct

All future work builds forward from here.

---
