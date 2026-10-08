<?php
// Raw request-target traversal sink, served by PHP's built-in web server
// (the "raw" Compose service), never by Apache.
//
// Apache resolves "." and ".." segments in the request path before PHP runs,
// so a literal "../" in the URL path can never reach an Apache-hosted script.
// PHP's built-in server normalizes SCRIPT_NAME and PHP_SELF but leaves
// REQUEST_URI exactly as received on the wire, so this router reads only
// REQUEST_URI. The path is deliberately not percent-decoded: only literal
// "../" segments, as sent by Panoptic's --path-based mode, traverse.
//
//   GET /view/placeholder.txt                         -> base file
//   GET /view/../../../../opt/panoptic-fixtures/proof.txt -> proof (vulnerable)
//   GET /opt/panoptic-fixtures/proof.txt              -> 404 (what a client
//                                                        that collapses ../ sends)
const ROUTE_PREFIX = '/view/';
const BASE_DIR = '/opt/panoptic-raw/files';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = explode('?', $uri, 2)[0];

header('Content-Type: text/plain; charset=UTF-8');

if ($path === '/health') {
    echo "ok\n";
    return true;
}

if (!str_starts_with($path, ROUTE_PREFIX)) {
    http_response_code(404);
    echo "Unknown route\n";
    return true;
}

$relative = substr($path, strlen(ROUTE_PREFIX));
$file = BASE_DIR . '/' . $relative;

if ($relative === '' || !is_file($file) || !is_readable($file)) {
    http_response_code(404);
    echo "File not found: " . htmlspecialchars($relative, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
    return true;
}

include($file); // Deliberately vulnerable: raw ../ escapes BASE_DIR
return true;
