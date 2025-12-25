Here is a **clean, final, paste-ready README** you can drop directly into GitHub.
This version is intentionally **locked as a baseline** and clearly communicates where the project is and what comes next.

You can paste this exactly as-is.

---

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

## 📌 What This README Represents

This document is the **baseline contract** for Planora.

* Architecture is locked
* Layer boundaries are fixed
* OSRM is the single routing authority
* UI remains secondary until routing is proven correct

All future work builds forward from here.

---

### What to do next (recommended)

1. Commit this README change
2. Open a PR titled:
   **“Docs: lock baseline architecture and OSRM setup”**
3. Merge once reviewed
4. Move on to Codex-driven tasks for Layer 1 and Layer 2 logic

If you want, next I can:

* Define the **next Codex task list**
* Write the **first routing engine spec**
* Help you create the **PR description**
* Or map the **Phase 0 CLI tool** step-by-step

Just tell me where you want to go next.
