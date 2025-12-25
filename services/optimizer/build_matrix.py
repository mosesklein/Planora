#!/usr/bin/env python3
"""CLI utility for building a travel time matrix using OSRM.

This script reads ``geocoded_stops.csv`` rows containing latitude/longitude
values and calls the OSRM Table API to compute driving durations between every
pair of stops. Results are stored as a JSON 2D array that can be consumed by
later optimization layers.
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Iterable, List, Sequence, Tuple

import pandas as pd
import requests
from dotenv import load_dotenv

DEFAULT_OSRM_URL = "http://localhost:5000"
DEFAULT_PENALTY = 1_000_000  # seconds


def read_coordinates(stops_path: Path) -> List[Tuple[float, float]]:
    """Load coordinates from the geocoded stops CSV.

    Args:
        stops_path: Path to the ``geocoded_stops.csv`` file.

    Returns:
        A list of ``(lat, lng)`` tuples for each stop.
    """

    if not stops_path.exists():
        raise FileNotFoundError(f"Could not find geocoded stops file at {stops_path}")

    df = pd.read_csv(stops_path)
    if not {"lat", "lng"}.issubset(df.columns):
        raise ValueError("Input CSV must include 'lat' and 'lng' columns")

    return list(df[["lat", "lng"]].itertuples(index=False, name=None))


def format_coordinates(coords: Sequence[Tuple[float, float]]) -> str:
    """Format coordinates for the OSRM request path (lng,lat pairs)."""

    return ";".join(f"{lng},{lat}" for lat, lng in coords)


def index_param(values: Iterable[int]) -> str:
    """Return a semicolon-separated index list for OSRM sources/destinations."""

    return ";".join(str(i) for i in values)


def call_osrm_table(
    base_url: str, coordinates: Sequence[Tuple[float, float]], timeout: int = 10
) -> List[List[float | None]]:
    """Call the OSRM Table API and return the durations matrix."""

    if not coordinates:
        return []

    coord_path = format_coordinates(coordinates)
    indices = index_param(range(len(coordinates)))
    url = f"{base_url.rstrip('/')}/table/v1/driving/{coord_path}"
    params = {"sources": indices, "destinations": indices}

    response = requests.get(url, params=params, timeout=timeout)
    response.raise_for_status()
    data = response.json()

    durations = data.get("durations")
    if durations is None:
        raise ValueError("OSRM response missing 'durations' field")

    return durations


def normalize_durations(
    durations: Sequence[Sequence[float | None]], penalty: float
) -> List[List[float]]:
    """Replace missing entries with a penalty value."""

    normalized: List[List[float]] = []
    for row in durations:
        normalized.append([float(value) if value is not None else penalty for value in row])
    return normalized


def build_travel_matrix(
    stops_path: Path, output_path: Path, base_url: str, penalty: float
) -> List[List[float]]:
    """Create a travel time matrix from geocoded stops."""

    coordinates = read_coordinates(stops_path)
    durations = call_osrm_table(base_url, coordinates)
    matrix = normalize_durations(durations, penalty)

    output = {"matrix": matrix}
    output_path.write_text(json.dumps(output, indent=2))
    return matrix


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Build a travel time matrix between stops using the OSRM Table API."
        )
    )
    parser.add_argument(
        "--geocoded",
        type=Path,
        default=Path("geocoded_stops.csv"),
        help="Path to the geocoded stops CSV (expects 'lat' and 'lng' columns).",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("travel_matrix.json"),
        help="Destination for the travel time matrix JSON file.",
    )
    parser.add_argument(
        "--osrm-base-url",
        type=str,
        default=DEFAULT_OSRM_URL,
        help="Base URL of the OSRM service (e.g., http://localhost:5000).",
    )
    parser.add_argument(
        "--penalty",
        type=float,
        default=DEFAULT_PENALTY,
        help="Penalty value in seconds used when no route is returned.",
    )
    return parser.parse_args()


def main() -> None:
    load_dotenv()
    args = parse_args()

    matrix = build_travel_matrix(
        stops_path=args.geocoded,
        output_path=args.output,
        base_url=args.osrm_base_url,
        penalty=args.penalty,
    )

    print(
        f"Generated travel time matrix for {len(matrix)} stops -> {args.output}"
    )


if __name__ == "__main__":
    main()
