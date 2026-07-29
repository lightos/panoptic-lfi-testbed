<?php
// Classic LFI vulnerability
if (isset($_GET['file'])) {
    $file = $_GET['file'];
    echo "<h2>File: " . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($file); // Vulnerable inclusion
    echo "</pre>";
} else {
    echo "No file specified";
}
?>
