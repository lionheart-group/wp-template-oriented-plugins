#!/bin/sh
# Run a command in every plugin folder, stopping at the first failure.
# Usage: scripts/each.sh <command...>
set -e
root=$(cd "$(dirname "$0")/.." && pwd)
for dir in "$root"/plugins/*/; do
    echo "==> $(basename "$dir"): $*"
    (cd "$dir" && "$@")
done
