<?php
// CORS config
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
    $allowedOrigins = ['https://stage.hsi.com', 'https://craft4.hsi.test', 'https://hsi.com', 'https://craft5.hsi.com'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header('Access-Control-Max-Age: 86400');

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }
    }

    // Stop preflight requests cleanly
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $sharedSecret = $_ENV['COURSE_LIBRARY_PDF_SHARED_SECRET'] ?? '';
    $rawBody = file_get_contents('php://input');
    $timestamp = $_SERVER['HTTP_X_INTERNAL_TIMESTAMP'] ?? '';
    $signature = $_SERVER['HTTP_X_INTERNAL_SIGNATURE'] ?? '';

    if (!$sharedSecret) {
        http_response_code(500);
        exit('Missing shared secret configuration.');
    }

    if (!$timestamp || !$signature) {
        http_response_code(401);
        exit('Missing signature headers.');
    }

    if (abs(time() - (int) $timestamp) > 300) {
        http_response_code(401);
        exit('Expired signature.');
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $sharedSecret);
    if (!hash_equals($expected, $signature)) {
        http_response_code(401);
        exit('Invalid signature.');
    }
}

// Continue to your normal code
require_once __DIR__ . '/classes/courses-parser.class.php';
$courseparser->parseCoursesAndInputDBBatches();