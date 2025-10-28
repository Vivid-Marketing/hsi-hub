# Production Server Setup Guide for Laravel + Puppeteer

## Prerequisites

Your production server needs Node.js 18+ to run Puppeteer. Here are the installation instructions for common server environments:

## Ubuntu/Debian Server Setup

```bash
# Update package list
sudo apt update

# Install Node.js 20.x (LTS)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# Verify installation
node --version
npm --version

# Install additional dependencies for Puppeteer
sudo apt-get install -y \
    ca-certificates \
    fonts-liberation \
    libappindicator3-1 \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libc6 \
    libcairo2 \
    libcups2 \
    libdbus-1-3 \
    libexpat1 \
    libfontconfig1 \
    libgbm1 \
    libgcc1 \
    libglib2.0-0 \
    libgtk-3-0 \
    libnspr4 \
    libnss3 \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libstdc++6 \
    libx11-6 \
    libx11-xcb1 \
    libxcb1 \
    libxcomposite1 \
    libxcursor1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxi6 \
    libxrandr2 \
    libxrender1 \
    libxss1 \
    libxtst6 \
    lsb-release \
    wget \
    xdg-utils
```

## CentOS/RHEL Server Setup

```bash
# Install Node.js 20.x
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo yum install -y nodejs

# Install additional dependencies for Puppeteer
sudo yum install -y \
    alsa-lib \
    atk \
    cups-libs \
    gtk3 \
    libXcomposite \
    libXcursor \
    libXdamage \
    libXext \
    libXi \
    libXrandr \
    libXScrnSaver \
    libXtst \
    pango \
    xorg-x11-fonts-100dpi \
    xorg-x11-fonts-75dpi \
    xorg-x11-fonts-cyrillic \
    xorg-x11-fonts-ethiopic \
    xorg-x11-fonts-misc \
    xorg-x11-fonts-Type1 \
    xorg-x11-utils
```

## Docker Setup (Alternative)

If you prefer using Docker, here's a Dockerfile example:

```dockerfile
FROM php:8.2-fpm

# Install Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install Puppeteer dependencies
RUN apt-get update && apt-get install -y \
    ca-certificates \
    fonts-liberation \
    libappindicator3-1 \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libc6 \
    libcairo2 \
    libcups2 \
    libdbus-1-3 \
    libexpat1 \
    libfontconfig1 \
    libgbm1 \
    libgcc1 \
    libglib2.0-0 \
    libgtk-3-0 \
    libnspr4 \
    libnss3 \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libstdc++6 \
    libx11-6 \
    libx11-xcb1 \
    libxcb1 \
    libxcomposite1 \
    libxcursor1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxi6 \
    libxrandr2 \
    libxrender1 \
    libxss1 \
    libxtst6 \
    lsb-release \
    wget \
    xdg-utils \
    && rm -rf /var/lib/apt/lists/*

# Your Laravel application setup...
```

## Buddy.works Pipeline Configuration

### Option 1: Pre-install Node.js on Server
1. Install Node.js on your production server using the commands above
2. Use the provided deployment scripts as-is

### Option 2: Install Node.js via Buddy.works
Add a "Run Script" action before your deployment with:

```bash
# Install Node.js if not present
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
fi

# Install Puppeteer dependencies
sudo apt-get update
sudo apt-get install -y ca-certificates fonts-liberation libappindicator3-1 libasound2 libatk-bridge2.0-0 libatk1.0-0 libc6 libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgbm1 libgcc1 libglib2.0-0 libgtk-3-0 libnspr4 libnss3 libpango-1.0-0 libpangocairo-1.0-0 libstdc++6 libx11-6 libx11-xcb1 libxcb1 libxcomposite1 libxcursor1 libxdamage1 libxext6 libxfixes3 libxi6 libxrandr2 libxrender1 libxss1 libxtst6 lsb-release wget xdg-utils
```

## Environment Variables

Make sure these are set in your Buddy.works environment:

```bash
# Laravel
APP_ENV=production
APP_DEBUG=false
APP_KEY=your-app-key

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

# Puppeteer (optional, for custom Chrome path)
PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome-stable
```

## Testing Puppeteer Installation

After deployment, test if Puppeteer works:

```bash
# SSH into your server and run:
cd /path/to/your/laravel/app
node -e "const puppeteer = require('puppeteer'); puppeteer.launch().then(browser => { console.log('✅ Puppeteer works!'); browser.close(); }).catch(err => console.error('❌ Puppeteer error:', err));"
```

## Troubleshooting

### Common Issues:

1. **Missing Chrome dependencies**: Install the additional packages listed above
2. **Permission issues**: Ensure your web server user can access Node.js and Chrome
3. **Memory issues**: Puppeteer can be memory-intensive; consider increasing server RAM
4. **Sandbox issues**: The scripts already include `--no-sandbox` flag for server environments

### Performance Tips:

1. **Use headless mode** (already configured in your script)
2. **Close browsers properly** (already handled in your script)
3. **Consider using a Chrome pool** for high-traffic applications
4. **Monitor memory usage** and restart workers if needed
