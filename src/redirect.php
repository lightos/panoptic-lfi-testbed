<?php
// Redirects to the classic vulnerable sink while preserving the candidate.
$file = $_GET['file'] ?? 'test.txt';
header('Location: classic.php?file=' . rawurlencode($file), true, 302);
exit;
?>
