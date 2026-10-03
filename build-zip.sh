#!/bin/bash
# Build script for creating distribution zip
# Only includes production files and runtime dependencies

set -e

PLUGIN_SLUG="webberzone-grok-account"
BUILD_DIR="build"
TEMP_DIR="$BUILD_DIR/$PLUGIN_SLUG"

echo "Creating distribution zip for $PLUGIN_SLUG..."

# Clean build directory
rm -rf "$BUILD_DIR"
mkdir -p "$TEMP_DIR"

# Copy plugin files (excluding dev/build artifacts and all of vendor)
echo "Copying plugin files..."
rsync -av --exclude-from=- . "$TEMP_DIR/" <<EOF
.*
.git/
.github/
node_modules/
phpcompat-tools/
phpunit/
/build/
vendor/
docs/
_layouts/
_includes/
/site-assets/
/_site/
_config.yml
Gemfile
Gemfile.lock
favicon.ico
/index.md
eslint.config.*
*.dist
*.neon
composer.json
composer.lock
phpstan-bootstrap.php
build-zip.sh
build-assets.js
package.json
pnpm-lock.yaml
pnpm-workspace.yaml
CLAUDE.md
AGENTS.md
EOF

# Create zip
echo "Creating zip file..."
cd "$BUILD_DIR"
zip -r "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG/" -q

echo "✓ Distribution zip created: $BUILD_DIR/$PLUGIN_SLUG.zip"
cd ..

# Show zip contents summary
echo ""
echo "Zip contents summary:"
unzip -l "$BUILD_DIR/$PLUGIN_SLUG.zip" | tail -1
