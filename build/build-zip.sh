#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# No implicit ZIP creation. Pass --build-zip OUTPUT only after release approval.
exec python3 "$ROOT/scripts/build-dist.py" "$@"
