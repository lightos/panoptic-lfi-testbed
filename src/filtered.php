<?php
// Filtered but still vulnerable to LFI with traversal sequences
if (isset($_GET['file'])) {
    $file = $_GET['file'];

    // Base directory to force relative paths
    $baseDir = "themes/";

    // Naive one-pass filtering can be bypassed with nested ....// sequences.
    $file = str_replace('../', '', $file);

    // Prepend base directory to force relative paths
    $filePath = $baseDir . $file;

    echo "<h2>Filtered File: " . htmlspecialchars($filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($filePath); // Still vulnerable to traversal with ....//
    echo "</pre>";
} else {
    echo "No file specified";
}
?>
