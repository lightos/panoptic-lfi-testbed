<?php
// Rewritten path vulnerability with a deliberate second URL decode. Apache
// performs the first decode; this application-level decode makes a %252F
// traversal payload become a slash at the vulnerable sink.
$encodedFile = isset($_GET['file']) ? $_GET['file'] : 'default.txt';
$file = rawurldecode($encodedFile);
$type = isset($_GET['type']) ? $_GET['type'] : 'view';

echo "<h2>Viewing: " . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " (Type: " . htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ")</h2>";
echo "<pre>";
include($file); // Vulnerable inclusion
echo "</pre>";
?>
