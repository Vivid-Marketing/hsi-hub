<?php
// header('Access-Control-Allow-Origin: *');
// ===== CORS BEGIN =====
$allowed_origins = [
    'https://hsi.com',
    'https://www.hsi.com',
    'https://hsi.test',
    'https://staging.hsi.com',
    'https://apismd.hsi.com',
    'https://craft4.hsi.com'
    // add others if needed, e.g. staging
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin'); // caches play nicer
    // If you send cookies/Authorization from the browser, also:
    // header('Access-Control-Allow-Credentials: true');
}

// Tell the browser what we’ll accept on the actual request
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight quickly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // no content
    exit;
}
// ===== CORS END =====
require_once __DIR__ . '/classes/pdf-report.class.php';
$pdfReport->createHtmlPage();