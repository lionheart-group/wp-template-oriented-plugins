#!/usr/bin/env bash
# PostToolUse hook (Edit|Write): run PHPStan on a single edited file when it
# is a .php file under plugins/<slug>/src/, using that plugin's own PHPStan and
# phpstan.neon. Exits 2 (blocking, stderr fed back to Claude) on PHPStan
# errors; exits 0 silently otherwise.
set -euo pipefail

input="$(cat)"
if command -v jq >/dev/null 2>&1; then
  file_path="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null || true)"
else
  # Fallback without jq: crude extraction of "file_path":"..." from the JSON.
  file_path="$(printf '%s' "$input" | grep -o '"file_path"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | sed -E 's/.*"file_path"[[:space:]]*:[[:space:]]*"([^"]*)"/\1/')"
fi

if [ -z "$file_path" ]; then
  exit 0
fi

case "$file_path" in
  */plugins/*/src/*.php|plugins/*/src/*.php) ;;
  *) exit 0 ;;
esac

if [ ! -f "$file_path" ]; then
  exit 0
fi

# The plugin folder is everything up to plugins/<slug>.
plugin_dir="$(printf '%s' "$file_path" | sed -E 's#^(.*plugins/[^/]+)/src/.*$#\1#')"
case "$plugin_dir" in
  /*) ;;
  *) plugin_dir="${CLAUDE_PROJECT_DIR:-$(pwd)}/$plugin_dir" ;;
esac
phpstan_bin="$plugin_dir/vendor/bin/phpstan"

if [ ! -x "$phpstan_bin" ]; then
  exit 0
fi

output="$("$phpstan_bin" analyse "$file_path" \
  --configuration="$plugin_dir/phpstan.neon" \
  --no-progress \
  --memory-limit=1024M \
  --error-format=raw 2>&1)" && exit 0

echo "PHPStan found issues in $file_path:" >&2
echo "$output" >&2
exit 2
