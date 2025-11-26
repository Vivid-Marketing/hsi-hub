#!/bin/bash

# Post-deploy script for Buddy.works pipeline
# This script runs after files are transferred to production
#
# Improvements:
# - Automatic Composer executable detection with multiple path checks
# - Retry logic for Composer install (handles network issues)
# - Better error handling and informative messages
# - Automatic Node.js executable detection
# - Directory creation before permission setting
# - Checks for required files (composer.json, package.json, .env)
# - Fallback mechanisms for npm operations
# - Better handling of first-time deployments

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

# Function to find composer executable
find_composer() {
    # Try common composer paths
    local possible_paths=(
        "composer"
        "/usr/local/bin/composer"
        "/usr/bin/composer"
        "$HOME/.composer/vendor/bin/composer"
        "$HOME/.config/composer/vendor/bin/composer"
        "/opt/homebrew/bin/composer"
    )
    
    for path in "${possible_paths[@]}"; do
        if command -v "$path" &> /dev/null; then
            if "$path" --version &> /dev/null; then
                echo "$path"
                return 0
            fi
        fi
    done
    
    # Try to find via which
    local which_composer=$(which composer 2>/dev/null)
    if [ -n "$which_composer" ] && [ -x "$which_composer" ]; then
        if "$which_composer" --version &> /dev/null; then
            echo "$which_composer"
            return 0
        fi
    fi
    
    return 1
}

# Navigate to the correct directory if needed
# Production path: /mnt/BLOCKSTORAGE/home/master/applications/rnpeauzkjg/public_html
# SSH initial directory is typically: /mnt/BLOCKSTORAGE/home/master
INITIAL_DIR=$(pwd)
print_status "Initial directory: $INITIAL_DIR"

# Check if we're already in the correct directory
if [ ! -f "artisan" ]; then
    print_status "artisan file not found in current directory. Searching for Laravel root..."
    
    # Try common paths relative to master directory
    POSSIBLE_PATHS=(
        "applications/rnpeauzkjg/public_html"
        "public_html"
        "../applications/rnpeauzkjg/public_html"
        "/mnt/BLOCKSTORAGE/home/master/applications/rnpeauzkjg/public_html"
    )
    
    FOUND_PATH=""
    for path in "${POSSIBLE_PATHS[@]}"; do
        if [ -f "$path/artisan" ]; then
            FOUND_PATH="$path"
            break
        fi
    done
    
    if [ -n "$FOUND_PATH" ]; then
        print_status "Found Laravel root at: $FOUND_PATH"
        cd "$FOUND_PATH" || {
            print_error "Failed to change directory to: $FOUND_PATH"
            exit 1
        }
        print_success "Changed to Laravel root directory"
    else
        print_error "artisan file not found. Are we in the Laravel root directory?"
        print_error "Current directory: $(pwd)"
        print_error "Expected to be in: /mnt/BLOCKSTORAGE/home/master/applications/rnpeauzkjg/public_html"
        print_error "Searched in: ${POSSIBLE_PATHS[*]}"
        exit 1
    fi
fi

# Ensure we're in the project root
PROJECT_ROOT=$(pwd)
print_status "Working directory: $PROJECT_ROOT"

# Verify we're in the expected production location (informational only)
if [[ "$PROJECT_ROOT" == *"public_html"* ]]; then
    print_success "Detected production environment (public_html path)"
fi

# Check for composer.json
if [ ! -f "composer.json" ]; then
    print_error "composer.json not found. Cannot install dependencies."
    exit 1
fi

# Find and verify composer
print_status "Locating Composer executable..."
COMPOSER_CMD=$(find_composer)
if [ -z "$COMPOSER_CMD" ]; then
    print_error "Composer not found. Please install Composer on your server."
    print_error "Install instructions: https://getcomposer.org/download/"
    exit 1
fi

print_success "Found Composer at: $COMPOSER_CMD"
$COMPOSER_CMD --version

# Create necessary directories before setting permissions
print_status "Creating necessary directories..."
mkdir -p storage/logs || {
    print_error "Failed to create storage/logs directory. Check permissions."
    exit 1
}
mkdir -p storage/framework/cache || {
    print_error "Failed to create storage/framework/cache directory. Check permissions."
    exit 1
}
mkdir -p storage/framework/sessions || {
    print_error "Failed to create storage/framework/sessions directory. Check permissions."
    exit 1
}
mkdir -p storage/framework/views || {
    print_error "Failed to create storage/framework/views directory. Check permissions."
    exit 1
}
mkdir -p bootstrap/cache || {
    print_error "Failed to create bootstrap/cache directory. Check permissions."
    exit 1
}
mkdir -p vendor || {
    print_error "Failed to create vendor directory. Check permissions."
    exit 1
}

# Verify directories were created and are writable
print_status "Verifying directory permissions..."
for dir in storage/logs storage/framework bootstrap/cache vendor; do
    if [ ! -w "$dir" ]; then
        print_warning "Directory $dir may not be writable. Attempting to fix permissions..."
        chmod -R 775 "$dir" 2>/dev/null || true
    fi
done

# Set proper permissions (non-fatal - some files may be owned by other users)
# Production ownership: master_mmpdmpxcnt:www-data
# Storage directories need to be writable by www-data group
print_status "Setting file permissions..."
print_warning "Note: Some permission changes may fail if files are owned by www-data user. This is normal."

# Temporarily disable exit on error for permission setting (we expect some failures)
set +e

# Set permissions on key directories
# Storage needs 775 so www-data group can write (files created by web server)
chmod 775 storage 2>/dev/null
chmod 755 bootstrap/cache 2>/dev/null
chmod 775 storage/logs 2>/dev/null
chmod 775 storage/framework 2>/dev/null
chmod 775 storage/framework/cache 2>/dev/null
chmod 775 storage/framework/sessions 2>/dev/null
chmod 775 storage/framework/views 2>/dev/null
chmod 644 artisan 2>/dev/null
chmod 775 vendor 2>/dev/null

# Try to set permissions on subdirectories we created, but don't fail if we can't
print_status "Setting permissions on subdirectories (non-critical)..."
find storage/logs -type d -exec chmod 775 {} \; 2>/dev/null >/dev/null
find storage/framework -type d -exec chmod 775 {} \; 2>/dev/null >/dev/null
find bootstrap/cache -type d -exec chmod 755 {} \; 2>/dev/null >/dev/null

# Re-enable exit on error
set -e

# Optional: Set ownership (uncomment if you have sudo access and want to ensure ownership)
# Production setup: owner=master_mmpdmpxcnt, group=www-data
# Uncomment the following lines if you need to fix ownership:
# print_status "Setting file ownership to master_mmpdmpxcnt:www-data..."
# sudo chown -R master_mmpdmpxcnt:www-data storage bootstrap/cache vendor 2>/dev/null || {
#     print_warning "Could not set ownership (may require sudo or different user)"
# }

print_success "Permission setting completed (some errors are expected and harmless)"

# Set proper ownership (adjust user/group as needed for your server)
# Production setup: owner=master_mmpdmpxcnt, group=www-data
# Uncomment and modify these lines if you need to fix ownership (may require sudo):
# print_status "Setting file ownership to master_mmpdmpxcnt:www-data..."
# sudo chown -R master_mmpdmpxcnt:www-data storage bootstrap/cache vendor 2>/dev/null || true
# sudo chown -R master_mmpdmpxcnt:www-data public/build 2>/dev/null || true

# Check available disk space before Composer install
print_status "Checking available disk space..."
if command -v df &> /dev/null; then
    # Get available space in MB
    AVAILABLE_MB=$(df -m "$PROJECT_ROOT" 2>/dev/null | awk 'NR==2 {print $4}' || echo "")
    if [ -n "$AVAILABLE_MB" ] && [ "$AVAILABLE_MB" -lt 500 ]; then
        print_warning "Low disk space: ${AVAILABLE_MB}MB available. Composer install may fail if less than 200MB free."
    elif [ -n "$AVAILABLE_MB" ]; then
        print_success "Disk space check: ${AVAILABLE_MB}MB available"
    fi
fi

# Install/Update Composer dependencies with retry logic
print_status "Installing Composer dependencies..."
MAX_RETRIES=3
RETRY_COUNT=0
COMPOSER_SUCCESS=false

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if [ $RETRY_COUNT -gt 0 ]; then
        print_warning "Retrying Composer install (attempt $((RETRY_COUNT + 1))/$MAX_RETRIES)..."
        sleep 2
    fi
    
    # Increase memory limit for composer if possible
    if $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1; then
        COMPOSER_SUCCESS=true
        break
    else
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
            print_warning "Composer install failed, will retry..."
        fi
    fi
done

if [ "$COMPOSER_SUCCESS" = false ]; then
    print_error "Composer install failed after $MAX_RETRIES attempts"
    print_error "Please check:"
    print_error "  1. Network connectivity"
    print_error "  2. Composer authentication (if using private repos)"
    print_error "  3. Server memory limits"
    print_error "  4. Disk space availability"
    exit 1
fi

print_success "Composer dependencies installed successfully"

# Check Node.js version
print_status "Checking Node.js installation..."

# Function to find node executable
find_node() {
    local possible_paths=(
        "node"
        "/usr/bin/node"
        "/usr/local/bin/node"
        "/opt/homebrew/bin/node"
    )
    
    for path in "${possible_paths[@]}"; do
        if command -v "$path" &> /dev/null; then
            if "$path" --version &> /dev/null; then
                echo "$path"
                return 0
            fi
        fi
    done
    
    local which_node=$(which node 2>/dev/null)
    if [ -n "$which_node" ] && [ -x "$which_node" ]; then
        echo "$which_node"
        return 0
    fi
    
    return 1
}

NODE_CMD=$(find_node)
if [ -z "$NODE_CMD" ]; then
    print_error "Node.js is not installed. Please install Node.js 18+ on your server."
    exit 1
fi

NODE_VERSION=$($NODE_CMD --version | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 18 ]; then
    print_error "Node.js version $NODE_VERSION is too old. Please install Node.js 18+ on your server."
    exit 1
fi

print_success "Node.js $($NODE_CMD --version) is installed at: $NODE_CMD"

# Check for package.json
if [ ! -f "package.json" ]; then
    print_warning "package.json not found. Skipping NPM steps..."
else
    # Install/Update NPM dependencies and build assets
    print_status "Installing NPM dependencies and building assets..."
    
    if ! npm ci --production=false; then
        print_warning "npm ci failed, trying npm install as fallback..."
        npm install --production=false || {
            print_error "NPM install failed"
            exit 1
        }
    fi
    
    if ! npm run build; then
        print_error "NPM build failed"
        exit 1
    fi
    
    print_success "NPM dependencies installed and assets built"
fi

# Install Puppeteer dependencies for production (if package.json exists and puppeteer is installed)
if [ -f "package.json" ] && grep -q "puppeteer" package.json 2>/dev/null; then
    print_status "Installing Puppeteer browser dependencies..."
    # Try to install to default location (node_modules/puppeteer/.local-chromium)
    # This is better than /usr/local/lib as it's within the project directory
    npx puppeteer browsers install chrome 2>/dev/null || {
        print_warning "Puppeteer browser installation failed, but continuing..."
    }
    
    # Verify installation
    if [ -d "node_modules/puppeteer/.local-chromium" ]; then
        print_success "Puppeteer browser installed successfully"
    else
        print_warning "Puppeteer browser directory not found (may install on first use)"
    fi
fi

# Set permissions for Node.js scripts and node_modules
print_status "Setting permissions for Node.js scripts and dependencies..."
set +e  # Temporarily disable exit on error for permission setting

# Set permissions on extract-mp3.js if it exists
if [ -f "extract-mp3.js" ]; then
    chmod 644 extract-mp3.js 2>/dev/null || true
    print_success "Set permissions on extract-mp3.js"
else
    print_warning "extract-mp3.js not found (may be normal if not using MP3 tools)"
fi

# Set permissions on node_modules directory (readable and executable for web server)
if [ -d "node_modules" ]; then
    # Make node_modules readable
    chmod -R 755 node_modules 2>/dev/null || true
    
    # Ensure all .js files in node_modules are readable
    find node_modules -type f -name "*.js" -exec chmod 644 {} \; 2>/dev/null >/dev/null || true
    
    # Ensure all executables in node_modules are executable
    find node_modules/.bin -type f -exec chmod 755 {} \; 2>/dev/null >/dev/null || true
    
    # Set permissions on puppeteer browser binaries if they exist
    if [ -d "node_modules/puppeteer/.local-chromium" ]; then
        chmod -R 755 node_modules/puppeteer/.local-chromium 2>/dev/null || true
        # Make chromium executable (find with proper grouping)
        find node_modules/puppeteer/.local-chromium -type f \( -name "chrome" -o -name "chromium" -o -name "chrome.exe" \) | while read -r chrome_bin; do
            chmod 755 "$chrome_bin" 2>/dev/null || true
        done
        print_success "Set permissions on Puppeteer browser binaries"
    fi
    
    print_success "Set permissions on node_modules directory"
else
    print_warning "node_modules directory not found"
fi

set -e  # Re-enable exit on error

# Clear and cache Laravel configurations
print_status "Optimizing Laravel for production..."

# Verify PHP and artisan are working
if ! php artisan --version &> /dev/null; then
    print_error "PHP artisan command failed. Check PHP installation and dependencies."
    exit 1
fi

# Clear all caches first
php artisan config:clear || print_warning "config:clear failed (may be normal on first deploy)"
php artisan route:clear || print_warning "route:clear failed (may be normal on first deploy)"
php artisan view:clear || print_warning "view:clear failed (may be normal on first deploy)"
php artisan cache:clear || print_warning "cache:clear failed (may be normal on first deploy)"

# Check for .env file before caching config
if [ ! -f ".env" ]; then
    print_warning ".env file not found. Skipping config:cache (you may need to create .env manually)"
else
    # Optimize for production
    php artisan config:cache || {
        print_error "config:cache failed. Check your .env file and configuration."
        exit 1
    }
fi
php artisan route:cache || {
    print_warning "route:cache failed (may be normal if routes are dynamic)"
}
php artisan view:cache || {
    print_warning "view:cache failed"
}

# Run database migrations
print_status "Running database migrations..."
if ! php artisan migrate --force; then
    print_error "Database migration failed. Please check your database connection and migrations."
    exit 1
fi

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
$COMPOSER_CMD dump-autoload --optimize || {
    print_warning "Composer dump-autoload failed, but continuing..."
}

# Set proper ownership (adjust user/group as needed for your server)
# Production setup: owner=master_mmpdmpxcnt, group=www-data
# Uncomment and modify these lines if you need to fix ownership (may require sudo):
# print_status "Setting file ownership to master_mmpdmpxcnt:www-data..."
# sudo chown -R master_mmpdmpxcnt:www-data storage bootstrap/cache 2>/dev/null || true
# sudo chown -R master_mmpdmpxcnt:www-data public/build 2>/dev/null || true

# Verify installation
print_status "Verifying installation..."
if php artisan --version > /dev/null 2>&1; then
    print_success "Laravel application is working correctly"
else
    print_error "Laravel application verification failed"
    exit 1
fi

# Verify MP3 extraction tool setup (if extract-mp3.js exists)
if [ -f "extract-mp3.js" ]; then
    print_status "Verifying MP3 extraction tool setup..."
    
    # Check if file is readable
    if [ -r "extract-mp3.js" ]; then
        print_success "extract-mp3.js is readable"
    else
        print_warning "extract-mp3.js may not be readable by web server"
    fi
    
    # Check if Node.js can execute the script (dry run)
    if [ -n "$NODE_CMD" ]; then
        if $NODE_CMD --check extract-mp3.js > /dev/null 2>&1; then
            print_success "extract-mp3.js syntax is valid"
        else
            print_warning "extract-mp3.js syntax check failed (may still work)"
        fi
    fi
    
    # Check if puppeteer is available
    if [ -d "node_modules/puppeteer" ]; then
        print_success "Puppeteer is installed"
    else
        print_warning "Puppeteer not found in node_modules (may cause MP3 extraction to fail)"
    fi
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
