<?php
// Negative control: reflects the candidate in common encodings but never
// performs a filesystem operation with it.
$file = $_GET['file'] ?? '';
?>
<!DOCTYPE html>
<html>
<head><title>Safe negative control</title></head>
<body>
    <h1>No file access occurs here</h1>
    <p>Requested: <?php echo htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <p>URL encoded: <?php echo rawurlencode($file); ?></p>
    <p>This response is intentionally safe.</p>
</body>
</html>
