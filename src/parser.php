<?php
// Deterministic arbitrary-file-read sink for Panoptic's dynamic parsers. The
// passwd response is a controlled fixture with one user, preventing an E2E run
// from expanding every system account in the base image.
$file = $_GET['file'] ?? null;

if ($file === '/etc/passwd') {
    header('Content-Type: text/plain; charset=UTF-8');
    readfile('/opt/panoptic-fixtures/passwd');
    exit;
}

if (!is_string($file) || $file === '' || !is_file($file) || !is_readable($file)) {
    http_response_code(404);
    echo "File not found: " . htmlspecialchars((string) $file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
readfile($file); // Deliberately vulnerable arbitrary file read
?>
