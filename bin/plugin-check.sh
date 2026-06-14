#!/usr/bin/env bash
# Run the official WordPress Plugin Check against the DISTRIBUTION build
# (dev/QA files stripped per .distignore) inside the isolated SWY Lando site.
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"
SLUG="sitemap-whitelist-for-yoast"
LANDO_DIR="${LANDO_PROJECT_DIR:-$(cd -- "$PLUGIN_ROOT/.." && pwd)/swy.lndo.site}"

die() { echo "Error: $*" >&2; exit 1; }

[ -f "$LANDO_DIR/.lando.yml" ] || die "No Lando site at $LANDO_DIR (set LANDO_PROJECT_DIR)."

echo "Building distribution package..."
"$PLUGIN_ROOT/bin/build-zip.sh" -q
DIST_SRC="$PLUGIN_ROOT/zips/$SLUG"
[ -d "$DIST_SRC" ] || die "Build did not produce $DIST_SRC"

# The Lando wp tree is container-side (not a bidirectional bind mount), so stage
# the dist INTO the container by piping a tar through `lando ssh`.
echo "Staging distribution into the Lando container..."
cd "$LANDO_DIR"
( cd "$PLUGIN_ROOT/zips" && tar -cf - "$SLUG" ) | lando ssh -c "rm -rf /app/wp/wp-content/plugins/$SLUG && tar -C /app/wp/wp-content/plugins -xf -"

echo "Running Plugin Check against the distribution build..."
OUT="$(lando wp plugin check "$SLUG" --format=csv 2>&1 || true)"
echo "$OUT"

# Fail on any ERROR-type result; WARNING rows are advisory.
if printf '%s\n' "$OUT" | grep -iqE '(^|,)"?ERROR"?(,|$)'; then
	die "Plugin Check reported ERROR-level issues."
fi
echo "Plugin Check: no ERROR-level issues."
