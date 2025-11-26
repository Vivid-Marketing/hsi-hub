# No-Index Deployment Checklist

This document outlines what needs to be deployed to prevent search engine indexing.

## Files Changed

1. ✅ `public/robots.txt` - Blocks all crawlers
2. ✅ `public/.htaccess` - Sets X-Robots-Tag header via Apache
3. ✅ `resources/views/welcome.blade.php` - Added meta robots tag
4. ✅ `resources/views/layouts/app.blade.php` - Added meta robots tag
5. ✅ `resources/views/layouts/guest.blade.php` - Added meta robots tag
6. ✅ `app/Http/Middleware/PreventIndexing.php` - NEW FILE - Sets X-Robots-Tag header
7. ✅ `bootstrap/app.php` - Registered PreventIndexing middleware

## After Deployment, Run These Commands on Production

```bash
# Clear Laravel cache
php artisan cache:clear

# Clear view cache (important - views are cached!)
php artisan view:clear

# Clear config cache
php artisan config:clear

# Clear route cache (if you're using route caching)
php artisan route:clear

# Rebuild bootstrap cache
php artisan optimize:clear
```

## Verification

After deployment and cache clearing, run:
```bash
./verify-noindex.sh https://hub.hsi.com
```

All three checks should pass:
- ✅ robots.txt should show `Disallow: /`
- ✅ X-Robots-Tag header should be present
- ✅ Meta robots tag should be in HTML

## If Still Not Working

1. **Check if changes are deployed**: Verify the files exist on production with the changes
2. **Check Cloudflare cache**: If using Cloudflare, purge the cache
3. **Check Apache mod_headers**: The .htaccess header might not work if mod_headers isn't enabled (but the Laravel middleware should handle it)
4. **Check middleware is running**: The middleware should set the header regardless of .htaccess

