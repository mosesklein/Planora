#!/usr/bin/env python3
"""CLI utility for geocoding stop addresses with SQLite caching.

This script reads a ``stops.csv`` file containing ``name`` and ``address``
columns, normalizes each address, and returns cached coordinates when
available. A placeholder geocoder is used for now to avoid external API
costs. All newly geocoded addresses are persisted to ``cache.db`` so the
same address is never geocoded twice.
"""
from __future__ import annotations

import argparse
import sqlite3
from pathlib import Path
from typing import Optional, Tuple

import pandas as pd
from dotenv import load_dotenv

CACHE_TABLE = "geocoded_addresses"


def canonicalize_address(address: str) -> str:
    """Normalize an address string for deduplication.

    The normalization trims surrounding whitespace, collapses internal
    whitespace to single spaces, and lowercases the address to enforce a
    canonical representation in the cache.
    """

    collapsed = " ".join(address.strip().split())
    return collapsed.lower()


def ensure_cache_table(connection: sqlite3.Connection) -> None:
    """Create the cache table if it does not already exist."""

    connection.execute(
        f"
        CREATE TABLE IF NOT EXISTS {CACHE_TABLE} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            canonical_address TEXT NOT NULL UNIQUE,
            original_address TEXT,
            lat REAL NOT NULL,
            lng REAL NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        ""
    )
    connection.commit()


def get_cached_coordinates(
    connection: sqlite3.Connection, canonical_address: str
) -> Optional[Tuple[float, float]]:
    """Return cached coordinates for a canonical address if present."""

    cursor = connection.execute(
        f"SELECT lat, lng FROM {CACHE_TABLE} WHERE canonical_address = ?",
        (canonical_address,),
    )
    row = cursor.fetchone()
    return (float(row[0]), float(row[1])) if row else None


def placeholder_geocode(address: str) -> Tuple[float, float]:
    """Temporary geocoder returning fixed Brooklyn coordinates."""

    return 40.6782, -73.9442


def save_geocode_result(
    connection: sqlite3.Connection,
    canonical_address: str,
    original_address: str,
    lat: float,
    lng: float,
) -> None:
    """Persist a geocoding result to the cache."""

    connection.execute(
        f"
        INSERT OR IGNORE INTO {CACHE_TABLE} (
            canonical_address, original_address, lat, lng
        ) VALUES (?, ?, ?, ?);
        ",
        (canonical_address, original_address, lat, lng),
    )
    connection.commit()


def geocode_addresses(
    stops_path: Path, cache_path: Path, output_path: Path
) -> pd.DataFrame:
    """Geocode addresses from ``stops_path`` and write ``output_path``."""

    if not stops_path.exists():
        raise FileNotFoundError(f"Could not find stops file at {stops_path}")

    stops_df = pd.read_csv(stops_path)
    if "address" not in stops_df.columns:
        raise ValueError("Input CSV must include an 'address' column")

    connection = sqlite3.connect(cache_path)
    ensure_cache_table(connection)

    results = []
    for _, row in stops_df.iterrows():
        raw_address = str(row["address"])
        canonical_address = canonicalize_address(raw_address)

        cached = get_cached_coordinates(connection, canonical_address)
        if cached:
            lat, lng = cached
        else:
            lat, lng = placeholder_geocode(raw_address)
            save_geocode_result(connection, canonical_address, raw_address, lat, lng)

        results.append(
            {
                "address": canonical_address,
                "lat": lat,
                "lng": lng,
            }
        )

    output_df = pd.DataFrame(results)
    output_df.insert(0, "id", range(1, len(output_df) + 1))
    output_df.to_csv(output_path, index=False)

    connection.close()
    return output_df


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Geocode stops from a CSV file, leveraging a SQLite cache to avoid "
            "duplicate lookups."
        )
    )
    parser.add_argument(
        "--stops",
        type=Path,
        default=Path("stops.csv"),
        help="Path to the input stops CSV (expects an 'address' column).",
    )
    parser.add_argument(
        "--cache",
        type=Path,
        default=Path("cache.db"),
        help="Path to the SQLite cache database.",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("geocoded_stops.csv"),
        help="Destination for the geocoded stops CSV output.",
    )
    return parser.parse_args()


def main() -> None:
    load_dotenv()
    args = parse_args()

    geocoded_df = geocode_addresses(args.stops, args.cache, args.output)
    print(f"Geocoded {len(geocoded_df)} stops -> {args.output}")


if __name__ == "__main__":
    main()
