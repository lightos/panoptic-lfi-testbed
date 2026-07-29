<?php
// Query-parameter traversal that applies a second URL decode.
$encodedFile = $_GET['file'] ?? null;

if (is_string($encodedFile) && $encodedFile !== '') {
    $file = rawurldecode($encodedFile);
    echo "<h2>Double-Decoded File: "
        . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</h2>";
    echo "<pre>";
    include($file); // Deliberately vulnerable
    echo "</pre>";
} else {
    echo "No file specified";
}
?>
