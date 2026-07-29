<?php
// Deterministic authenticated LFI endpoint. This avoids coupling the scanner
// E2E test to PHP's session storage while exercising Panoptic's --cookie flag.
if (($_COOKIE['panoptic_auth'] ?? '') !== 'allowed') {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$file = $_GET['file'] ?? null;
if (is_string($file) && $file !== '') {
    echo "<h2>Authenticated File: "
        . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</h2>";
    echo "<pre>";
    include($file); // Deliberately vulnerable
    echo "</pre>";
} else {
    echo "No file specified";
}
?>
