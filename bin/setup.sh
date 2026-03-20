#!/usr/bin/env bash
#
# Setup script for the WordPress Groups wp-env development environment.
#
# Called automatically by wp-env afterStart, or manually via:
#   npm run env:setup
#
set -euo pipefail

echo "=== WordPress Groups: Environment Setup ==="

# 1. Build blocks so the plugin's block assets are available.
echo ""
echo "--- Building blocks ---"
npm run build:blocks

# 2. Network-activate the plugin.
echo ""
echo "--- Activating plugin (network-wide) ---"
npx wp-env run cli wp plugin activate wordpress-groups --network

# 3. Activate the directory theme on the main site (site 1).
echo ""
echo "--- Activating groups-directory theme on main site ---"
npx wp-env run cli wp theme activate groups-directory

# 4. Create the /melbourne/ sub-site (skip if it already exists).
echo ""
echo "--- Creating /melbourne/ sub-site ---"
if npx wp-env run cli wp site list --field=url 2>/dev/null | grep -q '/melbourne/'; then
	echo "Sub-site /melbourne/ already exists, skipping creation."
	MELBOURNE_BLOG_ID=$(npx wp-env run cli wp site list --field=blog_id --path=/melbourne/)
else
	MELBOURNE_BLOG_ID=$(npx wp-env run cli wp site create --slug=melbourne --title="WordPress Melbourne" --porcelain)
	echo "Created sub-site /melbourne/ (blog_id: ${MELBOURNE_BLOG_ID})"
fi

# 5. Activate groups-site theme on the Melbourne sub-site.
echo ""
echo "--- Activating groups-site theme on Melbourne sub-site ---"
npx wp-env run cli wp theme activate groups-site --url='http://localhost:8888/melbourne/'

# 6. Run seed data on the Melbourne sub-site.
echo ""
echo "--- Seeding data on /melbourne/ sub-site ---"
npx wp-env run cli wp eval-file wp-content/plugins/wordpress-groups/seed-data.php --url='http://localhost:8888/melbourne/'

# 7. Configure the main site.
echo ""
echo "--- Configuring main site ---"
npx wp-env run cli wp option update blogname "WordPress Community Groups"
npx wp-env run cli wp option update blogdescription "Discover and join WordPress community groups around the world"

# 8. Create a front page on the main site with the group-directory block.
echo ""
echo "--- Creating main site front page ---"
EXISTING_FRONT=$(npx wp-env run cli wp option get page_on_front 2>/dev/null || echo "0")
if [ "$EXISTING_FRONT" = "0" ] || [ -z "$EXISTING_FRONT" ]; then
	FRONT_PAGE_ID=$(npx wp-env run cli wp post create \
		--post_type=page \
		--post_title="Groups Directory" \
		--post_status=publish \
		--post_content='<!-- wp:groups/group-directory {"align":"wide"} /-->' \
		--porcelain)
	npx wp-env run cli wp option update show_on_front page
	npx wp-env run cli wp option update page_on_front "$FRONT_PAGE_ID"
	echo "Front page created (ID: ${FRONT_PAGE_ID})"
else
	echo "Front page already set (ID: ${EXISTING_FRONT}), skipping."
fi

echo ""
echo "=== Setup complete! ==="
echo ""
echo "  Main site:     http://localhost:8888/"
echo "  Melbourne:     http://localhost:8888/melbourne/"
echo "  WP Admin:      http://localhost:8888/wp-admin/"
echo "  Login:         admin / password"
echo ""
