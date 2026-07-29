<?php
// JSON API LFI vulnerability
$input = json_decode(file_get_contents('php://input'), true);
$file = isset($input['file']) ? $input['file'] : null;

if ($file) {
    echo "<h2>API File: " . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($file); // Vulnerable inclusion from JSON body
    echo "</pre>";
} else {
    echo "Send JSON body: {\"file\": \"filename\"}";
}
?>
