#!/bin/bash

# Minimal post-deploy script for Buddy.works pipeline
# Use this version for simpler deployments or when you need faster execution

set -e

echo "🚀 Starting minimal post-deploy tasks..."

# Set permissions
chmod -R 755 storage bootstrap/cache
chmod 644 artisan

# Install dependencies
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --production=false
npm run build

# Laravel optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Restart queues
php artisan queue:restart

echo "✅ Minimal post-deploy completed!"
