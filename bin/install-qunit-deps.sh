#!/usr/bin/env bash
#
# Fetches the WordPress core scripts the QUnit page loads.
#
# The suite runs against the same jQuery, Underscore, Backbone and wp.Backbone
# that WordPress ships, rather than npm builds of them, so that a failure means
# something about o2 and not about a version WordPress never serves.
#
# usage: bin/install-qunit-deps.sh [wp-version]

set -e

WP_VERSION=${1-latest}
VENDOR_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )/tests/qunit/vendor"

FILES=(
	"wp-includes/js/jquery/jquery.js"
	"wp-includes/js/underscore.min.js"
	"wp-includes/js/backbone.min.js"
	"wp-includes/js/wp-backbone.js"
)

download() {
	if [ "$( which curl )" ]; then
		curl -f -s "$1" > "$2"
	elif [ "$( which wget )" ]; then
		wget -nv -O "$2" "$1"
	else
		echo "Neither curl nor wget is available." >&2
		exit 1
	fi
}

# Writes through a temporary file so a failed fetch cannot leave a truncated
# one behind for the version stamp to later declare good.
download_atomic() {
	local tmp="$2.part"
	download "$1" "$tmp"
	mv "$tmp" "$2"
}

resolve_version() {
	if [[ $WP_VERSION == 'trunk' || $WP_VERSION == 'nightly' ]]; then
		echo 'trunk'
		return
	fi

	if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
		echo "tags/$WP_VERSION"
		return
	fi

	# http serves a single offer, whereas https serves multiple. We only want one.
	local latest
	latest=$( download http://api.wordpress.org/core/version-check/1.7/ /dev/stdout \
		| grep -o '"version":"[^"]*' | head -1 | sed 's/"version":"//' )

	if [ -z "$latest" ]; then
		echo "Latest WordPress version could not be found." >&2
		exit 1
	fi

	echo "tags/$latest"
}

TAG=$( resolve_version )
STAMP="$VENDOR_DIR/.wp-version"

if [ -f "$STAMP" ] && [ "$( cat "$STAMP" )" = "$TAG" ]; then
	echo "QUnit dependencies already at $TAG."
	exit 0
fi

mkdir -p "$VENDOR_DIR"

trap 'rm -f "$VENDOR_DIR"/*.part' EXIT

# The stamp goes last, so an interrupted run re-fetches rather than passing off
# a half-populated directory as complete.
rm -f "$STAMP"

for file in "${FILES[@]}"; do
	echo "Fetching $file from $TAG"
	download_atomic "https://core.svn.wordpress.org/$TAG/$file" "$VENDOR_DIR/$( basename "$file" )"
done

echo "$TAG" > "$STAMP"
echo "QUnit dependencies installed from $TAG into tests/qunit/vendor."
