#!/usr/bin/env bash
#
# Sync a tagged GitHub Release to the WordPress.org SVN repository.
#
# Workflow this script encodes:
#
#   1. CI on GitHub builds the release zip and attaches it to a
#      GitHub Release (the existing .github/workflows/release.yml).
#   2. This script downloads that zip, replaces SVN /trunk with its
#      contents and creates an SVN /tags/<version> copy.
#   3. The user runs `svn commit` from the produced working copy
#      with their own WP.org SVN credentials.
#
# Why a manual `svn commit` at the end: the WP.org SVN credentials
# must never live in CI or in this repository. Keep them on your
# workstation, in your SVN client's keychain.
#
# Usage:
#
#   bin/svn-release.sh <version>            # e.g. 3.0.3
#   bin/svn-release.sh <version> --no-tag   # update trunk only, no /tags
#
# Optional env vars:
#
#   WPHS_SVN_USERNAME    SVN username (defaults to your WP.org username).
#                        Only used for the final hint message.
#   WPHS_SVN_WORK        Path to the SVN working copy. Defaults to
#                        $HOME/.wphs-svn/pleaseup-hotspots — will be
#                        created on first run with `svn checkout`.
#   WPHS_GITHUB_REPO     owner/repo, default bypleaseup/wp-image-hotspots.
#
# Requires: svn, gh (or curl), unzip, rsync.

set -euo pipefail

PLUGIN_SLUG="pleaseup-hotspots"
SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}"
GITHUB_REPO="${WPHS_GITHUB_REPO:-bypleaseup/wp-image-hotspots}"
SVN_WORK="${WPHS_SVN_WORK:-${HOME}/.wphs-svn/${PLUGIN_SLUG}}"
SVN_USERNAME="${WPHS_SVN_USERNAME:-}"

VERSION="${1:-}"
NO_TAG=0
if [[ "${2:-}" == "--no-tag" ]]; then NO_TAG=1; fi

if [[ -z "${VERSION}" ]]; then
    echo "Usage: $0 <version> [--no-tag]" >&2
    echo "Example: $0 3.0.3" >&2
    exit 1
fi

if ! [[ "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Version must look like X.Y.Z, got '${VERSION}'" >&2
    exit 1
fi

# -- 0. dependencies ---------------------------------------------------------

for cmd in svn unzip rsync; do
    command -v "${cmd}" >/dev/null 2>&1 || { echo "Missing dependency: ${cmd}" >&2; exit 1; }
done

# -- 1. fetch the release zip from GitHub ------------------------------------

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
DOWNLOAD_DIR="$(mktemp -d)"
trap 'rm -rf "${DOWNLOAD_DIR}"' EXIT

ZIP_URL="https://github.com/${GITHUB_REPO}/releases/download/v${VERSION}/${ZIP_NAME}"
echo ">> Downloading ${ZIP_URL}"

if command -v gh >/dev/null 2>&1; then
    gh release download "v${VERSION}" \
        --repo "${GITHUB_REPO}" \
        --pattern "${ZIP_NAME}" \
        --dir "${DOWNLOAD_DIR}" \
        --clobber
else
    curl -L --fail -o "${DOWNLOAD_DIR}/${ZIP_NAME}" "${ZIP_URL}"
fi

unzip -q "${DOWNLOAD_DIR}/${ZIP_NAME}" -d "${DOWNLOAD_DIR}/extracted"
SRC_DIR="${DOWNLOAD_DIR}/extracted/${PLUGIN_SLUG}"

if [[ ! -d "${SRC_DIR}" ]]; then
    echo "Zip did not contain ${PLUGIN_SLUG}/ root directory" >&2
    exit 1
fi

# -- 2. checkout (or update) the SVN working copy ----------------------------

if [[ ! -d "${SVN_WORK}/.svn" ]]; then
    echo ">> First run: svn checkout ${SVN_URL} into ${SVN_WORK}"
    mkdir -p "$(dirname "${SVN_WORK}")"
    svn checkout "${SVN_URL}" "${SVN_WORK}"
else
    echo ">> svn update on existing working copy"
    svn update "${SVN_WORK}"
fi

cd "${SVN_WORK}"

# -- 3. replace trunk with the new release content ---------------------------

echo ">> Refreshing trunk/ from the release zip"
mkdir -p trunk
# Use rsync --delete so files removed in the new release are also removed
# from trunk. Keep SVN metadata.
rsync -a --delete \
    --exclude='.svn' \
    "${SRC_DIR}/" trunk/

# Stage every file change (additions, deletions, modifications).
svn add --force trunk
# Remove anything svn knows about but is no longer on disk.
svn status trunk | awk '/^!/ { print $2 }' | xargs -r svn rm

# -- 4. create the tag (svn cp trunk tags/VERSION) ---------------------------

if [[ "${NO_TAG}" -eq 0 ]]; then
    if [[ -d "tags/${VERSION}" ]]; then
        echo ">> tags/${VERSION} already exists, skipping copy"
    else
        echo ">> svn copy trunk -> tags/${VERSION}"
        mkdir -p tags
        svn add --force tags
        svn copy trunk "tags/${VERSION}"
    fi
fi

# -- 5. summary --------------------------------------------------------------

echo
echo "================================================================"
echo "Staged for commit. Review with:"
echo "  cd ${SVN_WORK} && svn status"
echo
echo "Then commit with your WP.org SVN credentials:"
if [[ "${NO_TAG}" -eq 0 ]]; then
    COMMIT_MSG="Release ${VERSION}"
else
    COMMIT_MSG="Update trunk to ${VERSION}"
fi
if [[ -n "${SVN_USERNAME}" ]]; then
    echo "  svn commit -m \"${COMMIT_MSG}\" --username ${SVN_USERNAME}"
else
    echo "  svn commit -m \"${COMMIT_MSG}\" --username YOUR_WP_ORG_USERNAME"
fi
echo
echo "Public page will refresh at:"
echo "  https://wordpress.org/plugins/${PLUGIN_SLUG}/  (~15 min after commit)"
echo "================================================================"
