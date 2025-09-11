#!/bin/bash

echo "=== GCS Media Sync Plugin Installation ==="
echo

# Check if composer exists
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first:"
    echo "   https://getcomposer.org/download/"
    exit 1
fi

echo "✅ Composer found"

# Install dependencies
echo "📦 Installing Google Cloud Storage library..."
composer install --no-dev --optimize-autoloader

if [ $? -eq 0 ]; then
    echo "✅ Dependencies installed successfully"
    echo
    echo "🎉 Plugin is ready!"
    echo
    echo "Next steps:"
    echo "1. Copy this folder to your WordPress plugins directory: wp-content/plugins/"
    echo "2. Activate the plugin in WordPress admin"
    echo "3. Configure GCS settings in WordPress admin → Settings → GCS Media Sync"
    echo "4. Test the configuration using Tools → GCS Check"
else
    echo "❌ Failed to install dependencies"
    exit 1
fi
