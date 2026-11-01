#!/bin/bash
set -e

# Base directories
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="${PROJECT_DIR}/dist"
TMP_DIR="${DIST_DIR}/.tmp/class-manager-pro"

# Extract version from class-manager-pro.php
VERSION=$(grep -m1 "Version:" "${PROJECT_DIR}/class-manager-pro.php" | awk '{print $NF}' | tr -d '\r\n')
if [ -z "$VERSION" ]; then
    VERSION="latest"
fi

ZIP_VERSIONED_NAME="class-manager-pro-v${VERSION}.zip"
ZIP_LATEST_NAME="class-manager-pro.zip"

echo "Building Class Manager Pro v${VERSION} ZIP..."

# Clean up any previous temp directory
rm -rf "${DIST_DIR}/.tmp"
mkdir -p "${TMP_DIR}"

# Copy plugin files
cp -r "${PROJECT_DIR}/admin" "${TMP_DIR}/"
cp -r "${PROJECT_DIR}/assets" "${TMP_DIR}/"
cp -r "${PROJECT_DIR}/includes" "${TMP_DIR}/"
cp "${PROJECT_DIR}/class-manager-pro.php" "${TMP_DIR}/"

if [ -f "${PROJECT_DIR}/README.md" ]; then
    cp "${PROJECT_DIR}/README.md" "${TMP_DIR}/"
fi
if [ -f "${PROJECT_DIR}/CHANGELOG.md" ]; then
    cp "${PROJECT_DIR}/CHANGELOG.md" "${TMP_DIR}/"
fi
if [ -f "${PROJECT_DIR}/DEVELOPMENT.md" ]; then
    cp "${PROJECT_DIR}/DEVELOPMENT.md" "${TMP_DIR}/"
fi

# Remove any unwanted OS files
find "${TMP_DIR}" -name ".DS_Store" -type f -delete 2>/dev/null || true
find "${TMP_DIR}" -name "Thumbs.db" -type f -delete 2>/dev/null || true

# Create the ZIP files inside dist/
cd "${DIST_DIR}/.tmp"
rm -f "${DIST_DIR}/${ZIP_VERSIONED_NAME}" "${DIST_DIR}/${ZIP_LATEST_NAME}"

zip -rq "${DIST_DIR}/${ZIP_VERSIONED_NAME}" class-manager-pro
cp "${DIST_DIR}/${ZIP_VERSIONED_NAME}" "${DIST_DIR}/${ZIP_LATEST_NAME}"

# Clean up temporary directory
rm -rf "${DIST_DIR}/.tmp"

echo "Success! Plugin ZIP created in dist/:"
ls -lh "${DIST_DIR}/${ZIP_VERSIONED_NAME}"
ls -lh "${DIST_DIR}/${ZIP_LATEST_NAME}"
