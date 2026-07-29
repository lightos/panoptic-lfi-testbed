<?php
// Path traversal sink with meaningful found/not-found status codes.
$file = $_GET['file'] ?? null;

if (!is_string($file) || $file === '' || !is_file($file) || !is_readable($file)) {
    http_response_code(404);
    echo "File not found: " . htmlspecialchars((string) $file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
readfile($file); // Deliberately vulnerable arbitrary file read
?>
