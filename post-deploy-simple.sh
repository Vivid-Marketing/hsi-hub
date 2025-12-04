#!/bin/bash

# Simplified post-deploy script for Buddy.works pipeline
# This script runs after files are transferred to production

set -e  # Exit on any error

echo "🚀 Starting post-deploy tasks..."

# Navigate to Laravel root if needed
if [ ! -f "artisan" ]; then
    # Try common paths
    if [ -f "applications/rnpeauzkjg/public_html/artisan" ]; then
        cd "applications/rnpeauzkjg/public_html"
    elif [ -f "/mnt/BLOCKSTORAGE/home/master/applications/rnpeauzkjg/public_html/artisan" ]; then
        cd "/mnt/BLOCKSTORAGE/home/master/applications/rnpeauzkjg/public_html"
    else
        echo "❌ artisan file not found. Please run this script from Laravel root."
        exit 1
    fi
fi

# Create necessary directories
echo "📁 Creating storage directories..."
mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache

# Set basic permissions on storage
echo "🔐 Setting storage permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install and build NPM assets if package.json exists
if [ -f "package.json" ]; then
    echo "📦 Installing NPM dependencies..."
    npm ci --production=false || npm install --production=false
    
    echo "🏗️  Building assets..."
    npm run build
fi

# Clear Laravel caches
echo "🧹 Clearing Laravel caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Cache Laravel configurations for production
if [ -f ".env" ]; then
    echo "⚡ Caching Laravel configurations..."
    php artisan config:cache
    php artisan route:cache 2>/dev/null || true
    php artisan view:cache 2>/dev/null || true
fi

# Run database migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Restart queue workers
echo "🔄 Restarting queue workers..."
php artisan queue:restart

echo "✅ Post-deploy tasks completed successfully!"
echo "🎉 Deployment finished at $(date)"

