#!/bin/bash
# Loads every Jack control-panel screen as a logged-in admin and reports the status code.
# A Twig template only breaks when it is rendered, and no amount of PHP linting catches that.
#
#   ddev exec bash /var/www/craft-jack/tests/integration/cp-smoke.sh
#
# Creates a document of every type, walks its screens, then deletes what it created.

# Per-run files: the harness is shared, and a second run clobbering the jar logs this one out.
JAR=$(mktemp /tmp/jack-cp-jar.XXXXXX)
PAGE=$(mktemp /tmp/jack-page.XXXXXX)
rm -f "$JAR" /tmp/jack-smoke-failures

CSRF=$(curl -s -b "$JAR" -c "$JAR" "http://localhost/index.php?p=actions/users/session-info" \
    -H "Accept: application/json" | python3 -c 'import sys,json; print(json.load(sys.stdin).get("csrfTokenValue",""))')

LOGIN=$(curl -s -b "$JAR" -c "$JAR" -X POST "http://localhost/index.php?p=actions/users/login" \
    -H "Accept: application/json" -H "X-Requested-With: XMLHttpRequest" \
    --data-urlencode "loginName=${JACK_CP_USER:-admin}" \
    --data-urlencode "password=${JACK_CP_PASS:-claudepassword}" \
    --data-urlencode "CRAFT_CSRF_TOKEN=$CSRF")

echo "login: $(echo "$LOGIN" | head -c 80)"
echo

cd /var/www/html || exit 1

# Pro, so that every screen is reachable rather than 403ing on an edition check.
EDITION_BEFORE=$(php craft plugin/list 2>/dev/null | grep -oE "jack.*" | head -1)
php craft plugin/switch-edition jack pro >/dev/null 2>&1

# Something to look at on the edit screens.
IDS=$(php /var/www/craft-jack/tests/integration/seed.php)
echo "$IDS" | sed 's/^/  seeded: /'
echo

FAILED=0

visit() {
    CODE=$(curl -s -b "$JAR" -c "$JAR" -o "$PAGE" -w "%{http_code}" "http://localhost/admin/$1")
    TITLE=$(grep -oE "<title>[^<]*" "$PAGE" | head -1 | sed "s/<title>//")

    if [ "$CODE" = "200" ]; then
        echo "  ✓ $CODE  $1  — $TITLE"
    else
        FAILED=$((FAILED + 1))
        echo "  ✗ $CODE  $1  — $TITLE"
        grep -oiE "(exception|Unable to|error)[^<]{0,180}" "$PAGE" | head -3
    fi
}

for SCREEN in "jack" "jack/documents" "jack/documents/new" "jack/profile" "jack/profile/default" \
              "jack/inventory" "jack/inventory/detect" "jack/inventory/server_logs" \
              "jack/audit" "settings/plugins/jack"; do
    visit "$SCREEN"
done

for ID in $(echo "$IDS" | awk '{print $2}'); do
    visit "jack/documents/$ID"
    visit "jack/documents/$ID/history"
    visit "jack/documents/$ID/compare"
done

# The front end, which is a different renderer entirely.
for URI in $(echo "$IDS" | awk '{print $3}'); do
    [ "$URI" = "-" ] && continue
    CODE=$(curl -s -o /tmp/jack-front.html -w "%{http_code}" "http://localhost/$URI")
    if [ "$CODE" = "200" ] && grep -q "jack-document" /tmp/jack-front.html; then
        echo "  ✓ $CODE  /$URI (front end)"
    else
        FAILED=$((FAILED + 1))
        echo "  ✗ $CODE  /$URI (front end)"
        grep -oiE "(exception|error)[^<]{0,140}" /tmp/jack-front.html | head -2
    fi
done

# ---------------------------------------------------------------------------------------------
# Write paths. A screen that renders is not a screen that saves — the settings form posted shapes
# the settings model rejected for a while, and every GET in this file was green throughout.
# ---------------------------------------------------------------------------------------------

echo
echo "  write paths"

post() {
    LABEL=$1; ACTION=$2; shift 2
    CSRF=$(curl -s -b "$JAR" -c "$JAR" "http://localhost/index.php?p=actions/users/session-info" \
        -H "Accept: application/json" | python3 -c 'import sys,json; print(json.load(sys.stdin).get("csrfTokenValue",""))')

    ARGS=()
    for PAIR in "$@"; do ARGS+=(--data-urlencode "$PAIR"); done

    CODE=$(curl -s -b "$JAR" -c "$JAR" -o /tmp/jack-post.html -w "%{http_code}" \
        -X POST "http://localhost/index.php?p=actions/$ACTION" \
        -H "Accept: application/json" -H "X-Requested-With: XMLHttpRequest" \
        --data-urlencode "CRAFT_CSRF_TOKEN=$CSRF" "${ARGS[@]}")

    if [ "$CODE" = "200" ] || [ "$CODE" = "302" ]; then
        echo "    ✓ $CODE  $LABEL"
    else
        FAILED=$((FAILED + 1))
        echo "    ✗ $CODE  $LABEL"
        head -c 300 /tmp/jack-post.html; echo
    fi
}

FIRST_ID=$(echo "$IDS" | head -1 | awk '{print $2}')

post "save the plugin settings" "plugins/save-plugin-settings" \
    "pluginHandle=jack" \
    "settings[autoCompile]=1" \
    "settings[showLastUpdated]=1" \
    "settings[purifyOutput]=1" \
    "settings[enableDocumentUrls]=1" \
    "settings[documentTemplate]=" \
    "settings[detectorPaths][0][path]=templates" \
    "settings[detectorExtensions]=twig, html, js" \
    "settings[detectorMaxFileSize]=512"

post "save the profile" "jack/profile/save" \
    "facts[company.name]=Smoke Test GmbH" \
    "facts[company.street]=Teststraße 1" \
    "facts[company.postalCode]=10115" \
    "facts[company.city]=Berlin" \
    "facts[contact.email]=smoke@example.test" \
    "facts[hosting.provider]=Hetzner Online GmbH" \
    "facts[site.sellsOnline]=1" \
    "facts[terms.governingLaw]=Recht der Bundesrepublik Deutschland" \
    "facts[accessibility.feedbackEmail]=smoke@example.test"

post "add a service to the inventory" "jack/inventory/add" "serviceKey=ga4"
post "edit that service" "jack/inventory/save" "serviceKey=ga4" "enabled=1" "legalBasis=consent" "transfer=dpf" "purpose[de]=" "purpose[en]="
post "save a document with an override" "jack/documents/save" \
    "documentId=$FIRST_ID" "siteId=1" "title=Impressum" "slug=legal-notice" "enabled=1" "jurisdiction=de" \
    "overrides[imprint.copyright][body]=<p>Alles gehört uns.</p>"
post "recompile it" "jack/documents/compile" "documentId=$FIRST_ID" "siteId=1"
post "publish it" "jack/documents/publish" "documentId=$FIRST_ID" "siteId=1" "note=smoke test"
post "remove the service again" "jack/inventory/remove" "serviceKey=ga4"

# The override has to have survived the round trip through the form, and publishing is what puts
# it on the page. Ask for the document's own URI: the shared harness has other plugins' entries
# sitting on /legal-notice, so the seeded imprint is often /legal-notice-2.
FIRST_URI=$(mysql -h db -udb -pdb db -N -e "SELECT uri FROM elements_sites WHERE elementId = $FIRST_ID AND siteId = 1" 2>/dev/null)
if curl -s "http://localhost/${FIRST_URI:-legal-notice}" | grep -q "Alles gehört uns."; then
    echo "    ✓ the override reached the published page"
else
    FAILED=$((FAILED + 1))
    echo "    ✗ the override did not reach the published page"
fi

php /var/www/craft-jack/tests/integration/seed.php --clean >/dev/null

echo
if [ "$FAILED" = "0" ]; then
    echo "All screens loaded."
else
    echo "$FAILED screen(s) failed."
fi

exit $FAILED
