#!/bin/sh
# Hostinger Custom cron: /bin/sh /home/u101822986/domains/mazurestate.pl/mls/bin/run-import.sh [esticrm]
# Keep --sync after the import even when the importer fails; do not use set -e.

if [ "$#" -gt 1 ]; then
    printf '%s\n' 'Usage: run-import.sh [mls|esticrm]' >&2
    exit 2
fi
feed_source=${1-mls}
case "$feed_source" in
    mls|esticrm) ;;
    *) printf '%s\n' 'Usage: run-import.sh [mls|esticrm]' >&2; exit 2 ;;
esac

php_bin='/usr/bin/php'
import_script='/home/u101822986/domains/mazurestate.pl/mls/bin/import.php'
sync_script='/home/u101822986/domains/mazurestate.pl/mls/bin/translate-offers.php'
monitor_script='/home/u101822986/domains/mazurestate.pl/mls/bin/check-integrations.php'

printf 'MazurEstate runner: source=%s\n' "$feed_source"
if [ "$feed_source" = 'esticrm' ]; then
    "$php_bin" "$import_script" --source=esticrm
else
    "$php_bin" "$import_script"
fi
import_result=$?

# Alert failures must not prevent translation or change the import exit status.
"$php_bin" "$monitor_script"
monitor_result=$?
if [ "$monitor_result" -ne 0 ]; then
    printf 'MazurEstate integration monitor failed: exit=%s\n' "$monitor_result" >&2
fi

"$php_bin" "$sync_script" --sync
sync_result=$?
printf 'MazurEstate runner finished: source=%s import_exit=%s sync_exit=%s\n' "$feed_source" "$import_result" "$sync_result"

if [ "$import_result" -ne 0 ]; then
    exit "$import_result"
fi
exit "$sync_result"
