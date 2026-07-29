<?php
// Opaque request-body LFI vulnerability for: --data "FUZZ"
$file = file_get_contents('php://input');

if ($file !== '') {
    echo "<h2>Raw Body File: " . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($file); // Deliberately vulnerable
    echo "</pre>";
} else {
    echo "Send a filename as the raw request body";
}
?>
