<?php
// Split filename/extension parameter vulnerability
$file = isset($_GET['file']) ? $_GET['file'] : 'default.txt';
$type = isset($_GET['type']) ? $_GET['type'] : 'txt';

$fullPath = $file . '.' . $type;
echo "<h2>Split Extension: " . htmlspecialchars($fullPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
echo "<pre>";
include($fullPath); // Vulnerable
echo "</pre>";
?>
