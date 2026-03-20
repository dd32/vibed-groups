#!/bin/bash
#
# WordPress Community Groups — Local Development Setup
#
# Creates the multisite structure:
#   Main site (/) → groups-directory theme → group discovery
#   Sub-site (/melbourne/) → groups-site theme → events, RSVPs, members
#
set -e

echo "🔧 Building blocks..."
npm run build:blocks 2>/dev/null || echo "Block build skipped (may need npm install first)"

echo ""
echo "🔌 Activating plugin network-wide..."
npx wp-env run cli wp plugin activate wordpress-groups --network 2>/dev/null || true

echo ""
echo "🎨 Setting up main site (groups-directory theme)..."
npx wp-env run cli wp theme activate groups-directory
npx wp-env run cli wp option update blogname "WordPress Community Groups"
npx wp-env run cli wp option update blogdescription "Find your local WordPress community"

# Create front page with group directory block
FRONT_PAGE=$(npx wp-env run cli wp post list --post_type=page --name=home --field=ID 2>/dev/null || echo "")
if [ -z "$FRONT_PAGE" ] || [ "$FRONT_PAGE" = "" ]; then
  FRONT_PAGE=$(npx wp-env run cli wp post create --post_type=page --post_title="Home" --post_status=publish --post_name=home --post_content='<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Find Your Local WordPress Community</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Join one of hundreds of local WordPress groups around the world.</p><!-- /wp:paragraph --><!-- wp:groups/group-directory /-->' --porcelain)
  echo "  Created front page: $FRONT_PAGE"
fi
npx wp-env run cli wp option update show_on_front page
npx wp-env run cli wp option update page_on_front "$FRONT_PAGE"

echo ""
echo "🌏 Creating Melbourne sub-site..."
# Get the site URL dynamically.
SITE_URL=$(npx wp-env run cli wp option get siteurl 2>/dev/null | grep -oE 'http[^ ]+' | head -1)
echo "  Site URL: $SITE_URL"

SITE_EXISTS=$(npx wp-env run cli wp site list --field=url 2>/dev/null | grep -c "/melbourne/" || echo "0")
if [ "$SITE_EXISTS" = "0" ]; then
  npx wp-env run cli wp site create --slug=melbourne --title="WordPress Melbourne" --email=organizer@example.com
  echo "  Created /melbourne/ sub-site"
else
  echo "  /melbourne/ already exists"
fi

echo ""
echo "🎨 Setting up Melbourne sub-site..."
MELBOURNE_URL="${SITE_URL}/melbourne/"

npx wp-env run cli wp theme activate groups-site --url="$MELBOURNE_URL"
npx wp-env run cli wp option update blogname "WordPress Melbourne" --url="$MELBOURNE_URL"
npx wp-env run cli wp option update blogdescription "Melbourne WordPress Community Group" --url="$MELBOURNE_URL"

echo ""
echo "🌱 Seeding Melbourne with sample data..."
npx wp-env run cli wp eval-file wp-content/plugins/wordpress-groups/seed-data.php --url="$MELBOURNE_URL"

echo ""
echo "✅ Setup complete!"
echo ""
echo "  Main site:     $SITE_URL/"
echo "  Melbourne:     $MELBOURNE_URL"
echo "  Admin:         $SITE_URL/wp-admin/"
echo "  Login:         admin / password"
echo ""
