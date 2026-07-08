"""
FargoRate Proxy API
-------------------
A lightweight FastAPI proxy that forwards player search requests to the
undocumented FargoRate dashboard API and returns clean, structured JSON
suitable for consumption by the RailShot TV Swift/iOS app.

Endpoint:
    GET /search?name=<player_name>

Example:
    GET /search?name=Shane%20Van%20Boening
"""

from fastapi import FastAPI, Query, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import httpx
import logging
from typing import Optional

# ---------------------------------------------------------------------------
# App setup
# ---------------------------------------------------------------------------

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="FargoRate Proxy API",
    description=(
        "Proxy service that queries the FargoRate dashboard for player "
        "ratings and returns structured JSON for use in the RailShot TV app."
    ),
    version="1.0.0",
)

# Allow requests from any origin so the Swift app (and any web front-end)
# can call this service without CORS issues.
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["GET"],
    allow_headers=["*"],
)

# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------

FARGO_SEARCH_URL = "https://dashboard.fargorate.com/api/indexsearch"

# Headers that mimic a normal browser request to avoid being blocked.
REQUEST_HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) "
        "AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1"
    ),
    "Accept": "application/json, text/plain, */*",
    "Referer": "https://fairmatch.fargorate.com/",
    "Origin": "https://fairmatch.fargorate.com",
}

# ---------------------------------------------------------------------------
# Data model helpers
# ---------------------------------------------------------------------------

def parse_player(raw: dict) -> dict:
    """
    Convert a raw FargoRate API player record into a clean, app-friendly dict.

    The FargoRate API returns all numeric fields as strings, so we coerce them
    to integers/floats where appropriate.  The 'effectiveRating' field is used
    as the primary displayed rating because it already accounts for provisional
    adjustments (i.e. low-robustness players get a blended rating).
    """
    def safe_int(val, default=0) -> int:
        try:
            return int(float(val)) if val else default
        except (ValueError, TypeError):
            return default

    rating       = safe_int(raw.get("rating"))
    robustness   = safe_int(raw.get("robustness"))
    provisional  = safe_int(raw.get("provisionalRating"))
    effective    = safe_int(raw.get("effectiveRating"))

    return {
        "id":               raw.get("id", ""),
        "readableId":       raw.get("readableId", ""),
        "membershipId":     raw.get("membershipId", ""),
        "firstName":        (raw.get("firstName") or "").strip().title(),
        "lastName":         (raw.get("lastName")  or "").strip().title(),
        "fullName":         f"{(raw.get('firstName') or '').strip().title()} {(raw.get('lastName') or '').strip().title()}".strip(),
        "location":         (raw.get("location") or "").strip(),
        "fargoRating":      effective,   # The rating to display in your app
        "officialRating":   rating,      # The fully-robust official rating
        "provisionalRating": provisional,
        "robustness":       robustness,
        # Robustness < 200 means the rating is still provisional / building up
        "isProvisional":    robustness < 200,
    }


# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------

@app.get("/")
async def root():
    """Health-check / welcome endpoint."""
    return {
        "service": "FargoRate Proxy API",
        "version": "1.0.0",
        "status": "running",
        "usage": "GET /search?name=<player_name>",
    }


@app.get("/search")
async def search_player(
    name: str = Query(
        ...,
        min_length=2,
        description="Player's full or partial name to search for",
        example="Shane Van Boening",
    ),
    limit: Optional[int] = Query(
        default=10,
        ge=1,
        le=50,
        description="Maximum number of results to return (1-50, default 10)",
    ),
):
    """
    Search FargoRate for players by name.

    Returns a list of matching players with their Fargo ratings, location,
    robustness scores, and membership IDs.  Results are sorted by Fargo
    rating descending so the highest-rated player appears first.

    - **name**: Full or partial player name (minimum 2 characters)
    - **limit**: Max results to return (default 10, max 50)
    """
    logger.info(f"Searching FargoRate for: '{name}' (limit={limit})")

    try:
        async with httpx.AsyncClient(timeout=10.0) as client:
            response = await client.get(
                FARGO_SEARCH_URL,
                params={"q": name},
                headers=REQUEST_HEADERS,
            )
            response.raise_for_status()
    except httpx.TimeoutException:
        logger.error("FargoRate API timed out")
        raise HTTPException(
            status_code=504,
            detail="The FargoRate service did not respond in time. Please try again.",
        )
    except httpx.HTTPStatusError as exc:
        logger.error(f"FargoRate API returned HTTP {exc.response.status_code}")
        raise HTTPException(
            status_code=502,
            detail=f"FargoRate service returned an error: HTTP {exc.response.status_code}",
        )
    except httpx.RequestError as exc:
        logger.error(f"Network error reaching FargoRate: {exc}")
        raise HTTPException(
            status_code=502,
            detail="Could not reach the FargoRate service. Check your internet connection.",
        )

    try:
        data = response.json()
    except Exception:
        logger.error("FargoRate returned non-JSON response")
        raise HTTPException(
            status_code=502,
            detail="FargoRate returned an unexpected response format.",
        )

    raw_players = data.get("value", [])

    if not isinstance(raw_players, list):
        raise HTTPException(
            status_code=502,
            detail="Unexpected response structure from FargoRate.",
        )

    # Parse and clean each player record
    players = [parse_player(p) for p in raw_players]

    # Sort by fargoRating descending (highest rated first)
    players.sort(key=lambda p: p["fargoRating"], reverse=True)

    # Apply limit
    players = players[:limit]

    return {
        "query":        name,
        "totalResults": len(players),
        "players":      players,
    }
