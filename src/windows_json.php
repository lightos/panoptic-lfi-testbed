<?php
// Explicit Windows-path simulator. It validates Panoptic's JSON escaping and
// Windows case handling without requiring a Windows/IIS container.
$input = json_decode(file_get_contents('php://input'), true);
$file = $input['request']['path'] ?? null;
$knownPath = 'C:\\Windows\\win.ini';

if ($file === $knownPath) {
    header('Content-Type: text/plain; charset=UTF-8');
    readfile('/opt/panoptic-fixtures/windows-win.ini');
    exit;
}

echo "Windows path not found: "
    . htmlspecialchars(is_string($file) ? $file : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
