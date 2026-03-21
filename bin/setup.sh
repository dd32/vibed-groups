#!/bin/bash
#
# WordPress Community Groups — Local Development Setup
#
# Creates the multisite structure:
#   Main site (/) → groups-directory theme → group discovery
#   Sub-site (/melbourne/) → groups-site theme → events, RSVPs, members
#

echo "🔧 Building blocks..."
npm run build:blocks 2>/dev/null || echo "  (skipped — run npm install first if needed)"

echo ""
echo "🔌 Activating plugin network-wide..."
npx wp-env run cli wp plugin activate wordpress-groups --network 2>/dev/null || true

echo ""
echo "🎨 Setting up main site (groups-directory theme)..."
npx wp-env run cli wp theme activate groups-directory 2>/dev/null || true
npx wp-env run cli wp option update blogname "WordPress Community Groups" 2>/dev/null || true
npx wp-env run cli wp option update blogdescription "Find your local WordPress community" 2>/dev/null || true
npx wp-env run cli wp option update avatar_default "robohash" 2>/dev/null || true

# Create front page.
npx wp-env run cli wp post list --post_type=page --name=home --field=ID 2>/dev/null | grep -q "[0-9]" || {
  npx wp-env run cli wp post create --post_type=page --post_title="Home" --post_status=publish --post_name=home --post_content='<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Find Your Local WordPress Community</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Join one of hundreds of local WordPress groups around the world.</p><!-- /wp:paragraph --><!-- wp:groups/group-directory /-->' 2>/dev/null || true
  echo "  Created front page"
}
FRONT_PAGE_ID=$(npx wp-env run cli wp post list --post_type=page --name=home --field=ID 2>/dev/null | grep -oE '[0-9]+' | head -1)
if [ -n "$FRONT_PAGE_ID" ]; then
  npx wp-env run cli wp option update show_on_front page 2>/dev/null || true
  npx wp-env run cli wp option update page_on_front "$FRONT_PAGE_ID" 2>/dev/null || true
fi

echo ""
echo "🌏 Creating Melbourne sub-site..."
# Always try to create — wp-cli will error if it exists, which is fine.
npx wp-env run cli wp site create --slug=melbourne --title="WordPress Melbourne" --email=test1@example.com 2>/dev/null || echo "  (already exists or failed)"

# Get the actual site URL for the Melbourne site.
SITE_URL=$(npx wp-env run cli wp option get siteurl 2>/dev/null | grep -oE 'http://[^ ]+' | head -1)
MELBOURNE_URL="${SITE_URL}/melbourne/"
echo "  Melbourne URL: $MELBOURNE_URL"

echo ""
echo "🎨 Setting up Melbourne sub-site..."
npx wp-env run cli wp theme activate groups-site --url="$MELBOURNE_URL" 2>/dev/null || true
npx wp-env run cli wp option update blogname "WordPress Melbourne" --url="$MELBOURNE_URL" 2>/dev/null || true
npx wp-env run cli wp option update blogdescription "Melbourne WordPress Community Group" --url="$MELBOURNE_URL" 2>/dev/null || true

echo ""
echo "🌱 Seeding Melbourne with sample data..."
npx wp-env run cli wp eval-file wp-content/plugins/wordpress-groups/seed-data.php --url="$MELBOURNE_URL" 2>/dev/null || true
npx wp-env run cli wp rewrite flush --url="$MELBOURNE_URL" 2>/dev/null || true

echo ""
echo "🌏 Creating Tokyo sub-site..."
npx wp-env run cli wp site create --slug=tokyo --title="WordPress Tokyo" --email=organizer-tokyo@example.com 2>/dev/null || echo "  (already exists)"

TOKYO_URL="${SITE_URL}/tokyo/"
echo "  Tokyo URL: $TOKYO_URL"

echo ""
echo "🎨 Setting up Tokyo sub-site..."
npx wp-env run cli wp theme activate groups-site --url="$TOKYO_URL" 2>/dev/null || true
npx wp-env run cli wp option update blogname "WordPress Tokyo" --url="$TOKYO_URL" 2>/dev/null || true
npx wp-env run cli wp option update blogdescription "Tokyo WordPress Community Group" --url="$TOKYO_URL" 2>/dev/null || true

echo ""
echo "🌱 Seeding Tokyo with sample data..."
npx wp-env run cli wp eval-file wp-content/plugins/wordpress-groups/seed-data-tokyo.php --url="$TOKYO_URL" 2>/dev/null || true
npx wp-env run cli wp rewrite flush --url="$TOKYO_URL" 2>/dev/null || true

echo ""
echo "🔄 Flushing rewrite rules..."
npx wp-env run cli wp rewrite flush 2>/dev/null || true

echo ""
echo "✅ Setup complete!"
echo ""
echo "  Main site:     $SITE_URL/"
echo "  Melbourne:     $MELBOURNE_URL"
echo "  Tokyo:         $TOKYO_URL"
echo "  Admin:         $SITE_URL/wp-admin/"
echo "  Login:         admin / password"
echo ""
