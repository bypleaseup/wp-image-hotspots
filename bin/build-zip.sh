#!/usr/bin/env bash
#
# Build a WordPress.org-ready distribution zip.
#
# Reads .distignore for exclusions and produces:
#   build/wp-image-hotspots-<VERSION>.zip
#
# Requires: rsync, zip.

set -euo pipefail

PLUGIN_SLUG="wp-image-hotspots"
PLUGIN_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="${PLUGIN_ROOT}/build"
STAGING="${BUILD_DIR}/${PLUGIN_SLUG}"

VERSION="$(grep -E "^[[:space:]]*\*[[:space:]]+Version:" "${PLUGIN_ROOT}/${PLUGIN_SLUG}.php" | head -1 | awk '{print $NF}')"

if [[ -z "${VERSION}" ]]; then
    echo "Could not parse plugin version from header." >&2
    exit 1
fi

rm -rf "${BUILD_DIR}"
mkdir -p "${STAGING}"

EXCLUDES=()
while IFS= read -r line; do
    [[ -z "$line" || "$line" =~ ^# ]] && continue
    EXCLUDES+=("--exclude=$line")
done < "${PLUGIN_ROOT}/.distignore"

rsync -a "${EXCLUDES[@]}" "${PLUGIN_ROOT}/" "${STAGING}/"

cd "${BUILD_DIR}"
zip -rq "${PLUGIN_SLUG}-${VERSION}.zip" "${PLUGIN_SLUG}"

echo "Built: ${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"
