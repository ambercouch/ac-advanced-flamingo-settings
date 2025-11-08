#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="ac-advanced-flamingo-settings"
OUTDIR="build"
ZIP="${OUTDIR}/${PLUGIN_SLUG}.zip"

rm -rf "${OUTDIR}"
mkdir -p "${OUTDIR}"

# Clean copy excluding dev files (honours .gitattributes export-ignore)
git archive --format=zip --output="${ZIP}" HEAD

echo "Built ${ZIP}"