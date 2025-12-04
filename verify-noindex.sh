#!/bin/bash

# Script to verify that the site is properly configured to prevent indexing
# Usage: ./verify-noindex.sh [URL]
# Example: ./verify-noindex.sh https://hub.hsi.com

URL="${1:-https://hub.hsi.com}"
echo "Verifying no-index configuration for: $URL"
echo "=========================================="
echo ""

# Check 1: robots.txt
echo "1. Checking robots.txt..."
ROBOTS_RESPONSE=$(curl -s "$URL/robots.txt")
echo "Response:"
echo "$ROBOTS_RESPONSE"
echo ""

if echo "$ROBOTS_RESPONSE" | grep -q "Disallow: /"; then
    echo "PASS: robots.txt correctly disallows all crawling"
else
    echo "FAIL: robots.txt does NOT disallow all crawling"
fi
echo ""

# Check 2: X-Robots-Tag HTTP Header
echo "2. Checking X-Robots-Tag HTTP header..."
HEADERS=$(curl -sI "$URL")
X_ROBOTS=$(echo "$HEADERS" | grep -i "x-robots-tag" || echo "Not found")

echo "X-Robots-Tag header: $X_ROBOTS"
if echo "$X_ROBOTS" | grep -qi "noindex.*nofollow\|nofollow.*noindex"; then
    echo "PASS: X-Robots-Tag header is correctly set"
else
    echo "FAIL: X-Robots-Tag header is missing or incorrect"
fi
echo ""

# Check 3: Meta robots tag in HTML
echo "3. Checking meta robots tag in HTML..."
HTML=$(curl -s "$URL")
META_ROBOTS=$(echo "$HTML" | grep -i 'meta.*robots' || echo "Not found")

echo "Meta robots tag: $META_ROBOTS"
if echo "$META_ROBOTS" | grep -qi 'noindex.*nofollow\|nofollow.*noindex'; then
    echo "PASS: Meta robots tag is present in HTML"
else
    echo "FAIL: Meta robots tag is missing or incorrect"
fi
echo ""

# Summary
echo "=========================================="
echo "Summary:"
ALL_CHECKS_PASSED=true

if ! echo "$ROBOTS_RESPONSE" | grep -q "Disallow: /"; then
    echo "  FAIL: robots.txt check failed"
    ALL_CHECKS_PASSED=false
fi

if ! echo "$HEADERS" | grep -qi "x-robots-tag.*noindex.*nofollow\|x-robots-tag.*nofollow.*noindex"; then
    echo "  FAIL: X-Robots-Tag header check failed"
    ALL_CHECKS_PASSED=false
fi

if ! echo "$HTML" | grep -qi 'meta.*robots.*noindex.*nofollow\|meta.*robots.*nofollow.*noindex'; then
    echo "  FAIL: Meta robots tag check failed"
    ALL_CHECKS_PASSED=false
fi

if [ "$ALL_CHECKS_PASSED" = true ]; then
    echo "  PASS: All checks passed! Site is properly locked down."
    exit 0
else
    echo "  WARNING: Some checks failed. Please review the output above."
    exit 1
fi

