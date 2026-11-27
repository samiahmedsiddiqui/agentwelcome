#!/usr/bin/env bash
# ./deploy.sh [repo path]  Builds the zip from the repo's HEAD (committed files only, as CI and wp.org do) and installs it into the test site.
KIT=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd); ROOT=$KIT/wp-test; REPO=${1:-$(cd "$KIT/../.." && pwd)}
ZIP=$ROOT/plugin.zip
source "$KIT/wpcli.sh"
wp_site() { wpcli "$@" --path=$ROOT/wp; }
if [[ ! -f $ROOT/wp/wp-config.php ]]; then
  echo "deploy.sh: no test site in $ROOT. Run setup.sh first." >&2
  exit 1
fi
rm -f $ZIP
git -C $REPO archive --format=zip --prefix=agentwelcome/ -o $ZIP HEAD
if [[ -d $ROOT/wp/wp-content/plugins/agentwelcome ]]; then wp_site plugin deactivate agentwelcome >/dev/null; fi
rm -rf $ROOT/wp/wp-content/plugins/agentwelcome
unzip -q $ZIP -d $ROOT/wp/wp-content/plugins
cp $KIT/mu-plugins/*.php $ROOT/wp/wp-content/mu-plugins/
rm -f $ROOT/wp/wp-content/kit-env.json $ROOT/wp/wp-content/mu-plugins/zz-filters.php
wp_site plugin activate agentwelcome | tail -1
# The option does not exist on a new site, which some WP-CLI versions report as an error.
wp_site option delete agentwelcome_settings >/dev/null 2>&1 || true
: > $ROOT/wp/wp-content/debug.log
