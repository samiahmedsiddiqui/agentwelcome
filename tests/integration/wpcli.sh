# Sourced by setup.sh and deploy.sh: stops on the first failed command, and defines wpcli.
set -eo pipefail
trap 'echo "${0##*/}: stopped, line $LINENO failed." >&2' ERR
WP_CLI=${WP_CLI:-/usr/local/bin/wp}
if [[ ! -f $WP_CLI ]]; then
  echo "${0##*/}: WP-CLI not found at $WP_CLI. Install it, or set WP_CLI to its path." >&2
  exit 1
fi

# Runs WP-CLI without PHP deprecation notices. Prints its output to stderr and returns its status when it fails.
wpcli() {
  local out code=0
  out=$(php -d error_reporting=0 -d display_errors=0 "$WP_CLI" "$@" 2>&1) || code=$?
  out=$(printf '%s' "$out" | grep -v "^Deprecated\|^PHP Deprecated" || true)
  if [[ $code -ne 0 ]]; then
    printf '%s: "wp %s %s" failed with status %s.\n%s\n' "${0##*/}" "$1" "$2" "$code" "$out" >&2
    return $code
  fi
  [[ -z $out ]] || printf '%s\n' "$out"
}
