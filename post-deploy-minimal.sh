#!/bin/bash

# Minimal post-deploy script for Buddy.works pipeline
# Use this version for simpler deployments or when you need faster execution

set -e

echo "🚀 Starting minimal post-deploy tasks..."

# Set permissions
chmod -R 755 storage bootstrap/cache
chmod 644 artisan

# Check Node.js installation
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js 18+ on your server."
    exit 1
fi

echo "✅ Node.js $(node --version) is installed"

# Install dependencies
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --production=false
npm run build

# Install Puppeteer browser dependencies
npx puppeteer browsers install chrome 2>/dev/null || true

# Laravel optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Restart queues
php artisan queue:restart

echo "✅ Minimal post-deploy completed!"
