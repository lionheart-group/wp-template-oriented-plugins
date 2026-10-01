#!/bin/sh
# Release a plugin to WordPress.org: build, sync build/ into the SVN trunk, tag it, and commit after confirmation.
# Usage: scripts/svn-release.sh <slug>
# The SVN working copy is ../svn/<slug>/ next to this repository.
set -e
root=$(cd "$(dirname "$0")/.." && pwd)
slug=${1:?usage: scripts/svn-release.sh <slug>}
plugin="$root/plugins/$slug"
svn_dir="$root/../svn/$slug"
[ -d "$plugin" ] || { echo "No such plugin: $slug" >&2; exit 1; }
[ -d "$svn_dir/.svn" ] || { echo "No SVN working copy at $svn_dir (svn checkout https://plugins.svn.wordpress.org/$slug $svn_dir)" >&2; exit 1; }

(cd "$plugin" && composer check && php scripts/build-release.php)

version=$(sed -n 's/^[ *]*Version:[[:space:]]*//p' "$plugin/build/$slug.php" | head -n 1 | tr -d '[:space:]')
stable=$(sed -n 's/^Stable tag:[[:space:]]*//p' "$plugin/build/readme.txt" | head -n 1 | tr -d '[:space:]')
[ -n "$version" ] || { echo "Could not read Version from $slug.php" >&2; exit 1; }
[ "$version" = "$stable" ] || { echo "Version ($version) and readme Stable tag ($stable) differ" >&2; exit 1; }

cd "$svn_dir"
svn update -q
[ ! -e "tags/$version" ] || { echo "tags/$version already exists in SVN" >&2; exit 1; }
[ -z "$(svn status)" ] || { echo "The SVN working copy has local changes; clean it first" >&2; svn status; exit 1; }

rsync -a --delete --exclude='*.zip' --exclude='.svn' "$plugin/build/" trunk/
svn status trunk | awk '/^\?/ {print $2}' | while read -r path; do svn add -q "$path"; done
svn status trunk | awk '/^!/ {print $2}' | while read -r path; do svn rm -q "$path"; done
svn cp -q trunk "tags/$version"

echo
svn status | sed 's/^/  /'
echo
printf 'Commit %s %s to WordPress.org? [y/N] ' "$slug" "$version"
read -r answer
case "$answer" in
    y|Y) svn ci -m "Release $version" ;;
    *) echo "Not committed. Revert with: svn revert -R $svn_dir && rm -rf $svn_dir/tags/$version" ;;
esac
