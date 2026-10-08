<?php
// Windows-style filter simulator. The filter strips "../" and only then
// normalizes "\" to "/", so "..\" traversal survives the filter. The
// normalization is done explicitly, keeping the case deterministic on Linux.
$file = $_GET['file'] ?? null;

if (is_string($file) && $file !== '') {
    $filtered = str_replace('../', '', $file);
    $filePath = 'themes/' . str_replace('\\', '/', $filtered);

    echo "<h2>Normalized File: " . htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($filePath); // Deliberately vulnerable after backslash normalization
    echo "</pre>";
} else {
    echo "No file specified";
}
?>
