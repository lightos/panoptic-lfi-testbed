<?php
// LFI vulnerability with base64 encoded path parameter
if (isset($_GET['file'])) {
    $encodedFile = $_GET['file'];

    // Attempt to decode the base64 parameter
    $file = base64_decode($encodedFile, true);

    // Check if decoding was successful
    if ($file === false) {
        echo "Error: The file parameter must be base64 encoded";
        exit;
    }

    echo "<h2>Base64 Decoded File: " . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($file); // Vulnerable inclusion
    echo "</pre>";
} else {
    echo "No file specified. Use ?file=[base64 encoded filename]";
}
?>
