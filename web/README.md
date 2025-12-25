# Planora - Smart Bus Routing & Optimization

[cite_start]Planora is a decision-support system for school transportation planners designed to validate routing logic, avoid API cost traps, and scale cleanly across multiple organizations[cite: 4, 7]. [cite_start]It is built on a "headless first" principle where routing correctness is prioritized over UI[cite: 13, 161].

## 🏗 Three-Layer Architecture

[cite_start]Planora is structured into three distinct layers to separate math, data, and UI cleanly[cite: 8, 22, 23]:

1. [cite_start]**Layer 1: Core Routing Engine (Python)** The authoritative layer responsible for address normalization, geocoding, matrix generation, and optimization using OR-Tools[cite: 24, 27, 28, 68]. [cite_start]It operates via CLI and headless jobs[cite: 35, 38, 39].

2. [cite_start]**Layer 2: Control Plane (Laravel API)** The orchestration layer that manages multi-school data models, job persistence, permissions, and file uploads[cite: 40, 43, 45, 46]. [cite_start]It does not touch optimization math directly[cite: 51].

3. [cite_start]**Layer 3: Presentation Layer (Next.js Web UI)** The visualization layer used for importing CSVs, triggering runs, and map visualization via MapLibre/Leaflet[cite: 52, 53, 55, 61, 75].

## 🛠 Technology Stack

- [cite_start]**Routing & Math:** Python, OR-Tools, Self-hosted OSRM (Docker)[cite: 65, 67, 68].
- [cite_start]**Backend:** Laravel API, PostgreSQL + PostGIS, Redis (Queues + Job State)[cite: 69, 70, 71, 72].
- [cite_start]**Frontend:** Next.js[cite: 73, 74].
- [cite_start]**Infrastructure:** Docker Compose[cite: 76, 77].

## ⚖️ Core Invariants

- [cite_start]**Stops are immutable:** They never change unless the physical address changes[cite: 127, 128].
- [cite_start]**Jobs are ephemeral:** Optimization runs can be recomputed at any time[cite: 127, 129].
- [cite_start]**Routes are disposable:** They are considered outputs, not state[cite: 127, 130].
- [cite_start]**OSRM Only:** Google Distance Matrix is explicitly forbidden to prevent cost traps[cite: 154].

## 🚀 Current Implementation Progress

- [x] [cite_start]Docker Compose environment initialized[cite: 77].
- [x] Layer 2 (Laravel) models for `Company` and `Stop` created.
- [x] Database seeded with 20 Brooklyn-based stops for validation.
- [x] GitHub repository connected and synchronized.

## 📂 Folder Structure
- `/api`: Layer 2 - Laravel Control Plane.
- `/web`: Layer 3 - Next.js Presentation Layer.
- `/services/optimizer` (Planned): Layer 1 - Python Routing Engine.
