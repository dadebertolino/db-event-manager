#!/usr/bin/env bash
#
# Prepara l'ambiente wp-env per gli E2E. Fallisce (set -e) sui passi essenziali.
#
set -euo pipefail

run() { npx wp-env run cli wp "$@"; }

echo "→ Permalink pretty"
run rewrite structure '/%postname%/' --hard

echo "→ Fuso orario con ora legale: le date degli eventi sono in ora locale"
run option update timezone_string 'Europe/Rome'

echo "→ Plugin attivi"
for plugin in db-event-manager db-form-builder db-privacy-hub; do
	run plugin activate "$plugin" || true
done
if ! run plugin is-active db-event-manager 2>/dev/null; then
	echo "::error::DB Event Manager non attivo. Setup fallito." >&2
	run plugin list
	exit 1
fi

echo "→ Flush rewrite finale"
run rewrite flush --hard

echo "→ Stato baseline"
run eval 'var_export( dbem_e2e_reset_state() );'

echo "Setup E2E completato."
