<?php
// Custom-header LFI vulnerability for: --header "X-Template: FUZZ"
$file = $_SERVER['HTTP_X_TEMPLATE'] ?? null;

if ($file) {
    echo "<h2>Header Template: " . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($file); // Deliberately vulnerable
    echo "</pre>";
} else {
    echo "Set the X-Template request header to a filename";
}
?>
