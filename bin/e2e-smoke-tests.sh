#!/bin/bash
#
# E2E Smoke Tests for WordPress Community Groups
#
# Runs after bin/setup.sh to verify all critical user paths work.
# Uses wp-cli (via wp-env) and curl against the local dev environment.
#
# Exit codes:
#   0 — all tests passed
#   1 — one or more tests failed
#

PASS=0
FAIL=0
ERRORS=""

# ── Helpers ──────────────────────────────────────────────────────────────────

pass() {
  echo "  ✓ $1"
  PASS=$((PASS + 1))
}

fail() {
  echo "  ✗ $1"
  FAIL=$((FAIL + 1))
  ERRORS="${ERRORS}\n  - $1"
}

wp_cli() {
  # Run a wp-cli command inside the wp-env container.
  # wp-env sends its own wrapper messages to stderr; stdout has the wp-cli output.
  npx wp-env run cli wp "$@" 2>/dev/null
}

http_status() {
  # Return the HTTP status code for a URL. Follows redirects.
  curl -s -o /dev/null -w "%{http_code}" -L "$1" 2>/dev/null
}

http_body() {
  # Return the response body. Follows redirects.
  curl -s -L "$1" 2>/dev/null
}

json_length() {
  # Count items in a JSON array returned by a URL.
  curl -s -L "$1" 2>/dev/null | node -e "
    let d='';
    process.stdin.on('data',c=>d+=c);
    process.stdin.on('end',()=>{
      try { const a=JSON.parse(d); console.log(Array.isArray(a)?a.length:0); }
      catch(e) { console.log(0); }
    });
  "
}

# ── Get site URL dynamically ────────────────────────────────────────────────

SITE_URL=$(wp_cli option get siteurl | grep -oE 'http://[^ ]+' | head -1)
if [ -z "$SITE_URL" ]; then
  echo "ERROR: Could not determine site URL. Is wp-env running?"
  exit 1
fi

MELBOURNE_URL="${SITE_URL}/melbourne"
TOKYO_URL="${SITE_URL}/tokyo"
echo ""
echo "E2E Smoke Tests"
echo "================"
echo "  Site URL:  $SITE_URL"
echo "  Melbourne: $MELBOURNE_URL"
echo "  Tokyo:     $TOKYO_URL"
echo ""

# ── Environment Tests ────────────────────────────────────────────────────────

echo "Environment"
echo "───────────"

# Main site loads
STATUS=$(http_status "$SITE_URL/")
if [ "$STATUS" = "200" ]; then
  pass "Main site loads (HTTP $STATUS)"
else
  fail "Main site loads (HTTP $STATUS, expected 200)"
fi

# Melbourne sub-site loads
STATUS=$(http_status "$MELBOURNE_URL/")
if [ "$STATUS" = "200" ]; then
  pass "Melbourne sub-site loads (HTTP $STATUS)"
else
  fail "Melbourne sub-site loads (HTTP $STATUS, expected 200)"
fi

# Tokyo sub-site loads
STATUS=$(http_status "$TOKYO_URL/")
if [ "$STATUS" = "200" ]; then
  pass "Tokyo sub-site loads (HTTP $STATUS)"
else
  fail "Tokyo sub-site loads (HTTP $STATUS, expected 200)"
fi

# Plugin is active network-wide
PLUGIN_STATUS=$(wp_cli plugin list --fields=name,status --format=csv | grep 'wordpress-groups' | head -1)
if echo "$PLUGIN_STATUS" | grep -q 'active'; then
  pass "Plugin is active ($PLUGIN_STATUS)"
else
  fail "Plugin is not active ($PLUGIN_STATUS)"
fi

# Correct theme on main site
MAIN_THEME=$(wp_cli theme list --url="$SITE_URL" --status=active --field=name | head -1)
if [ "$MAIN_THEME" = "groups-directory" ]; then
  pass "Correct theme active on main site ($MAIN_THEME)"
else
  fail "Wrong theme on main site (got '$MAIN_THEME', expected 'groups-directory')"
fi

# Correct theme on Melbourne site
MELB_THEME=$(wp_cli theme list --url="$MELBOURNE_URL" --status=active --field=name | head -1)
if [ "$MELB_THEME" = "groups-site" ]; then
  pass "Correct theme active on Melbourne site ($MELB_THEME)"
else
  fail "Wrong theme on Melbourne site (got '$MELB_THEME', expected 'groups-site')"
fi

# Correct theme on Tokyo site
TOKYO_THEME=$(wp_cli theme list --url="$TOKYO_URL" --status=active --field=name | head -1)
if [ "$TOKYO_THEME" = "groups-site" ]; then
  pass "Correct theme active on Tokyo site ($TOKYO_THEME)"
else
  fail "Wrong theme on Tokyo site (got '$TOKYO_THEME', expected 'groups-site')"
fi

echo ""

# ── Page Tests ───────────────────────────────────────────────────────────────

echo "Pages"
echo "─────"

# /melbourne/events/ — may be the front page and redirect to /melbourne/.
# Follow redirects and confirm we get 200.
STATUS=$(http_status "$MELBOURNE_URL/events/")
if [ "$STATUS" = "200" ]; then
  pass "/melbourne/events/ returns 200"
else
  fail "/melbourne/events/ returns $STATUS (expected 200)"
fi

STATUS=$(http_status "$MELBOURNE_URL/members/")
if [ "$STATUS" = "200" ]; then
  pass "/melbourne/members/ returns 200"
else
  fail "/melbourne/members/ returns $STATUS (expected 200)"
fi

STATUS=$(http_status "$MELBOURNE_URL/about/")
if [ "$STATUS" = "200" ]; then
  pass "/melbourne/about/ returns 200"
else
  fail "/melbourne/about/ returns $STATUS (expected 200)"
fi

echo ""

# ── Event Tests ──────────────────────────────────────────────────────────────

echo "Events"
echo "──────"

# Event CPT has posts on Melbourne site
EVENT_COUNT=$(wp_cli post list --post_type=event --post_status=any --url="$MELBOURNE_URL" --format=count | grep -oE '[0-9]+' | head -1)
if [ -n "$EVENT_COUNT" ] && [ "$EVENT_COUNT" -ge 1 ]; then
  pass "Event CPT has posts on Melbourne site ($EVENT_COUNT events)"
else
  fail "Event CPT has no posts on Melbourne site"
fi

# Get first scheduled event for later tests
FIRST_EVENT_ID=$(wp_cli post list --post_type=event --post_status=event-scheduled --url="$MELBOURNE_URL" --field=ID 2>/dev/null | grep -oE '[0-9]+' | head -1)
EVENT_SLUG=""

if [ -n "$FIRST_EVENT_ID" ]; then
  EVENT_SLUG=$(wp_cli post get "$FIRST_EVENT_ID" --url="$MELBOURNE_URL" --field=post_name 2>/dev/null | head -1)
  EVENT_PAGE_URL="$MELBOURNE_URL/event/$EVENT_SLUG/"
  STATUS=$(http_status "$EVENT_PAGE_URL")
  if [ "$STATUS" = "200" ]; then
    pass "Event page returns 200 ($EVENT_PAGE_URL)"
  else
    fail "Event page returns $STATUS (expected 200) for $EVENT_PAGE_URL"
  fi
else
  fail "No scheduled events found to test event page"
fi

echo ""

# ── REST API Tests ───────────────────────────────────────────────────────────

echo "REST API"
echo "────────"

# GET /groups/v1/events on Melbourne site
EVENTS_COUNT=$(json_length "$MELBOURNE_URL/wp-json/groups/v1/events")
if [ "$EVENTS_COUNT" -ge 1 ]; then
  pass "GET /groups/v1/events returns events ($EVENTS_COUNT)"
else
  fail "GET /groups/v1/events returned no events"
fi

# GET /groups/v1/members on Melbourne site
MEMBERS_COUNT=$(json_length "$MELBOURNE_URL/wp-json/groups/v1/members")
if [ "$MEMBERS_COUNT" -ge 1 ]; then
  pass "GET /groups/v1/members returns members ($MEMBERS_COUNT)"
else
  fail "GET /groups/v1/members returned no members"
fi

# GET /groups/v1/groups on main site
# The directory queries wp_meetup posts. It may return an empty array if none
# exist, but the endpoint itself must respond with 200 and valid JSON.
GROUPS_STATUS=$(http_status "$SITE_URL/wp-json/groups/v1/groups")
if [ "$GROUPS_STATUS" = "200" ]; then
  pass "GET /groups/v1/groups returns 200"
else
  fail "GET /groups/v1/groups returns $GROUPS_STATUS (expected 200)"
fi

# GET /groups/v1/integration/events-feed
FEED_COUNT=$(json_length "$SITE_URL/wp-json/groups/v1/integration/events-feed")
if [ "$FEED_COUNT" -ge 1 ]; then
  pass "GET /groups/v1/integration/events-feed returns data ($FEED_COUNT events)"
else
  fail "GET /groups/v1/integration/events-feed returned no data"
fi

echo ""

# ── RSVP Tests ───────────────────────────────────────────────────────────────

echo "RSVPs"
echo "─────"

# RSVPs exist as comments on events
RSVP_COUNT=$(wp_cli comment list --type=groups_rsvp --url="$MELBOURNE_URL" --format=count | grep -oE '[0-9]+' | head -1)
if [ -n "$RSVP_COUNT" ] && [ "$RSVP_COUNT" -ge 1 ]; then
  pass "RSVPs exist as comments on events ($RSVP_COUNT RSVPs)"
else
  fail "No RSVPs found as comments on events"
fi

# RSVP count matches expected (seed data creates 4 users x 4 events = 16 RSVPs,
# but only 3 non-organizer users get RSVPs = at least 12 expected.)
if [ -n "$RSVP_COUNT" ] && [ "$RSVP_COUNT" -ge 12 ]; then
  pass "RSVP count matches expected (>= 12, got $RSVP_COUNT)"
else
  fail "RSVP count lower than expected (got ${RSVP_COUNT:-0}, expected >= 12)"
fi

# POST /groups/v1/events/{id}/rsvp works for authenticated user.
# Use wp-cli eval to simulate an authenticated RSVP via REST internal dispatch.
if [ -n "$FIRST_EVENT_ID" ]; then
  # Create a test user for RSVP (idempotent). Multisite requires lowercase alpha/num.
  wp_cli user create e2etester e2etester@example.com --role=subscriber --user_pass=testpass123 --url="$MELBOURNE_URL" >/dev/null 2>&1 || true

  # Dispatch the RSVP request inside the container as the test user.
  RSVP_RESULT=$(wp_cli eval "
    \$user = get_user_by( 'login', 'e2etester' );
    if ( ! \$user ) { echo 'NO_USER'; return; }
    wp_set_current_user( \$user->ID );
    \$existing = get_comments( [ 'post_id' => $FIRST_EVENT_ID, 'user_id' => \$user->ID, 'type' => 'groups_rsvp', 'status' => 'any' ] );
    foreach ( \$existing as \$c ) { wp_delete_comment( \$c->comment_ID, true ); }
    \$request = new WP_REST_Request( 'POST', '/groups/v1/events/$FIRST_EVENT_ID/rsvp' );
    \$request->set_param( 'event_id', $FIRST_EVENT_ID );
    \$request->set_param( 'guests', 0 );
    \$response = rest_do_request( \$request );
    echo \$response->get_status();
  " --url="$MELBOURNE_URL" 2>/dev/null | tr -d '[:space:]')

  if [ "$RSVP_RESULT" = "201" ]; then
    pass "POST /groups/v1/events/$FIRST_EVENT_ID/rsvp works (HTTP 201)"
  else
    fail "POST /groups/v1/events/$FIRST_EVENT_ID/rsvp failed (got '$RSVP_RESULT', expected 201)"
  fi

  # Clean up: delete the test RSVP.
  wp_cli eval "
    \$user = get_user_by( 'login', 'e2etester' );
    if ( \$user ) {
      \$comments = get_comments( [ 'post_id' => $FIRST_EVENT_ID, 'user_id' => \$user->ID, 'type' => 'groups_rsvp', 'status' => 'any' ] );
      foreach ( \$comments as \$c ) { wp_delete_comment( \$c->comment_ID, true ); }
    }
  " --url="$MELBOURNE_URL" 2>/dev/null || true
else
  fail "No scheduled events found to test RSVP POST"
fi

echo ""

# ── Content Verification ─────────────────────────────────────────────────────

echo "Content Verification"
echo "────────────────────"

# Event page contains RSVP button markup
if [ -n "$FIRST_EVENT_ID" ] && [ -n "$EVENT_SLUG" ]; then
  EVENT_PAGE_URL="$MELBOURNE_URL/event/$EVENT_SLUG/"
  BODY=$(http_body "$EVENT_PAGE_URL")
  if echo "$BODY" | grep -q 'rsvp-button'; then
    pass "Event page contains RSVP button markup"
  else
    fail "Event page missing RSVP button markup"
  fi
else
  fail "No event found to verify RSVP button markup"
fi

# Members page contains the members heading/block
MEMBERS_BODY=$(http_body "$MELBOURNE_URL/members/")
if echo "$MEMBERS_BODY" | grep -qi 'member'; then
  pass "Members page contains member content"
else
  fail "Members page missing member content"
fi

# Homepage contains group/community content
HOME_BODY=$(http_body "$SITE_URL/")
if echo "$HOME_BODY" | grep -qiE 'group|community|directory|event'; then
  pass "Homepage contains group directory or event listing content"
else
  fail "Homepage missing expected group/event content"
fi

echo ""

# ── Summary ──────────────────────────────────────────────────────────────────

TOTAL=$((PASS + FAIL))
echo "──────────────────────"
echo "Results: $PASS/$TOTAL passed, $FAIL failed"

if [ "$FAIL" -gt 0 ]; then
  echo ""
  echo "Failures:"
  echo -e "$ERRORS"
  echo ""
  exit 1
fi

echo ""
echo "All smoke tests passed!"
echo ""
exit 0
