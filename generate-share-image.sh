#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUT_FILE="${1:-$ROOT_DIR/storage/share-preview.png}"

mkdir -p "$(dirname "$OUT_FILE")"

php "$ROOT_DIR/share-image.php" > "$OUT_FILE"

echo "Preview image generated:"
echo "$OUT_FILE"
