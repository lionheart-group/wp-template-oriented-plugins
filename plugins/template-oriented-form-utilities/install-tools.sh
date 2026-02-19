#!/bin/bash

set -e

TOOLS_DIR="$(dirname "$0")/tools"
SCOPER_PHAR="$TOOLS_DIR/php-scoper.phar"
SCOPER_URL="https://github.com/humbug/php-scoper/releases/latest/download/php-scoper.phar"

# Create tools directory if it doesn't exist
mkdir -p "$TOOLS_DIR"

# Download PHP Scoper PHAR
echo "Downloading PHP Scoper..."
curl -L -o "$SCOPER_PHAR" "$SCOPER_URL"

# Make PHAR executable
chmod +x "$SCOPER_PHAR"

echo "PHP Scoper installed at $SCOPER_PHAR"