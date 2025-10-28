#!/bin/bash

# Post-deploy script for Buddy.works pipeline
# This script runs after files are transferred to production

set -e  # Exit on any error

echo "🚀 Starting post-deploy tasks..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the correct directory
if [ ! -f "artisan" ]; then
    print_error "artisan file not found. Are we in the Laravel root directory?"
    exit 1
fi

# Set proper permissions
print_status "Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs
chmod 644 artisan

# Install/Update Composer dependencies
print_status "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install/Update NPM dependencies and build assets
print_status "Installing NPM dependencies and building assets..."
npm ci --production=false
npm run build

# Clear and cache Laravel configurations
print_status "Optimizing Laravel for production..."

# Clear all caches first
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
print_status "Running database migrations..."
php artisan migrate --force

# Clear and restart queues (if using queues)
print_status "Restarting queue workers..."
php artisan queue:restart

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    print_warning "APP_KEY not set, generating new key..."
    php artisan key:generate --force
fi

# Optimize autoloader
print_status "Optimizing Composer autoloader..."
composer dump-autoload --optimize

# Set proper ownership (adjust user/group as needed for your server)
# Uncomment and modify these lines based on your server setup:
# print_status "Setting file ownership..."
# chown -R www-data:www-data storage bootstrap/cache
# chown -R www-data:www-data public/build

# Verify installation
print_status "Verifying installation..."
if php artisan --version > /dev/null 2>&1; then
    print_success "Laravel application is working correctly"
else
    print_error "Laravel application verification failed"
    exit 1
fi

# Optional: Run tests in production (uncomment if needed)
# print_status "Running production tests..."
# php artisan test --env=production

# Optional: Warm up the application cache
print_status "Warming up application cache..."
php artisan optimize

# Optional: Clear old logs (keep last 7 days)
print_status "Cleaning up old log files..."
find storage/logs -name "*.log" -mtime +7 -delete 2>/dev/null || true

# Optional: Clear old cache files
print_status "Cleaning up old cache files..."
php artisan cache:prune 2>/dev/null || true

print_success "✅ Post-deploy tasks completed successfully!"
print_status "Application is ready for production use."

# Optional: Send notification (uncomment and modify as needed)
# curl -X POST "https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK" \
#      -H "Content-Type: application/json" \
#      -d '{"text":"🚀 Production deployment completed successfully!"}'

echo "🎉 Deployment finished at $(date)"
