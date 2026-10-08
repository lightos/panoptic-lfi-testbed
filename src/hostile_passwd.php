<?php
// Arbitrary-file-read sink that serves a hostile /etc/passwd fixture. Its home
// directories contain terminal escapes, a relative path, and a spreadsheet
// formula; one normal home holds a real file. Every requested path is logged
// (JSON-encoded, one per line) so the runner can prove which homes Panoptic
// expanded; read or reset the log through hostile_log.php.
$file = $_GET['file'] ?? null;

file_put_contents(
    '/tmp/panoptic-hostile-requests.log',
    json_encode(is_string($file) ? $file : null, JSON_INVALID_UTF8_SUBSTITUTE) . "\n",
    FILE_APPEND | LOCK_EX
);

if ($file === '/etc/passwd') {
    header('Content-Type: text/plain; charset=UTF-8');
    readfile('/opt/panoptic-fixtures/passwd-hostile');
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
