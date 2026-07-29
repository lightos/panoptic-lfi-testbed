<?php
// Nested JSON LFI vulnerability:
// {"request":{"template":"FUZZ"}}
$input = json_decode(file_get_contents('php://input'), true);
$file = $input['request']['template'] ?? null;

if (is_string($file) && $file !== '') {
    echo "<h2>Nested JSON Template: "
        . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</h2>";
    echo "<pre>";
    include($file); // Deliberately vulnerable
    echo "</pre>";
} else {
    echo 'Send JSON body: {"request":{"template":"filename"}}';
}
?>
