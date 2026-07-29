<?php
// Path-based LFI vulnerability using PATH_INFO
// URL: /pathinfo.php/test.txt → PATH_INFO = /test.txt
$file = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null;

if ($file) {
    echo "<h2>Path Info File: " . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($file); // Vulnerable inclusion
    echo "</pre>";
} else {
    echo "No path specified. Use /pathinfo.php/path/to/file";
}
?>
