<?php
// Single-decode filter in front of a double-decoding sink. PHP decodes the
// query once; the filter rejects any "../" it can see at that point, then the
// application decodes again before including the file relative to themes/.
// A plain "../" is blocked, while "..%252f" only becomes "../" after the
// second decode and reaches the sink.
$file = $_GET['file'] ?? null;

if (!is_string($file) || $file === '') {
    echo "No file specified";
    exit;
}

if (str_contains($file, '../')) {
    http_response_code(403);
    echo "Blocked: traversal sequence detected";
    exit;
}

$filePath = 'themes/' . rawurldecode($file);

echo "<h2>Decoded File: " . htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
echo "<pre>";
include($filePath); // Deliberately vulnerable after the second decode
echo "</pre>";
?>
