<?php
// Explicit simulator for legacy sinks that truncated paths at a NUL byte.
// Modern PHP correctly rejects NUL-containing include paths, so the truncation
// is performed here to keep the default runtime supported and deterministic.
$input = $_GET['file'] ?? null;

if (is_string($input) && $input !== '') {
    $nullPosition = strpos($input, "\0");
    $file = $nullPosition === false ? $input : substr($input, 0, $nullPosition);
    echo "<h2>Legacy-Truncated File: "
        . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</h2>";
    echo "<pre>";
    include($file); // Deliberately vulnerable after simulated truncation
    echo "</pre>";
} else {
    echo "No file specified";
}
?>
