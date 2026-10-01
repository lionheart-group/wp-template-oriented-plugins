#!/bin/sh
# Run Plugin Check's PHPCS rulesets (WordPress.org's rules) against a plugin's build/.
# Usage: scripts/plugin-check.sh <slug>
#   PLUGIN_CHECK_DIR  Path to the plugin-check plugin (default: the wordpress-develop site next to this repo).
set -e
root=$(cd "$(dirname "$0")/.." && pwd)
slug=${1:?usage: scripts/plugin-check.sh <slug>}
plugin="$root/plugins/$slug"
[ -d "$plugin" ] || { echo "No such plugin: $slug" >&2; exit 1; }

pcp=${PLUGIN_CHECK_DIR:-"$root/../../wordpress-develop/htdocs/wp-content/plugins/plugin-check"}
[ -f "$pcp/vendor/bin/phpcs" ] || { echo "Plugin Check not found at $pcp (set PLUGIN_CHECK_DIR)" >&2; exit 1; }

(cd "$plugin" && php scripts/build-release.php >/dev/null)

status=0
for ruleset in plugin-check.ruleset.xml plugin-review.xml; do
    echo "==> $slug: $ruleset"
    php "$pcp/vendor/bin/phpcs" --standard="$pcp/phpcs-rulesets/$ruleset" \
        --extensions=php --ignore=vendor/ --report=summary "$plugin/build" || status=1
done
exit $status
