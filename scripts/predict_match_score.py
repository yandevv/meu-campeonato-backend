#!/usr/bin/env python3

import json
import random
import sys


def main() -> None:
    payload = json.load(sys.stdin)

    required_fields = {
        "tournament_id",
        "round_type",
        "home_team_id",
        "away_team_id",
    }

    missing_fields = sorted(required_fields.difference(payload.keys()))

    if missing_fields:
        raise ValueError(f"Missing required fields: {', '.join(missing_fields)}")

    response = {
        "home_goals": random.randrange(0, 8, 1),
        "away_goals": random.randrange(0, 8, 1),
        "source": "mock-ml",
        "version": "v1",
        "confidence": round(random.uniform(0.5, 0.99), 2),
    }

    print(json.dumps(response))


if __name__ == "__main__":
    main()
