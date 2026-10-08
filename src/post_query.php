<?php
// POST sink gated by a query parameter: the file comes from the form body but
// is only included when the URL carries ?action=view. Exercises Panoptic
// keeping the configured query string on POST requests.
if (($_GET['action'] ?? '') !== 'view') {
    echo "Unsupported action";
    exit;
}

$file = $_POST['file'] ?? null;
if (is_string($file) && $file !== '') {
    echo "<h2>Viewing: " . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($file); // Deliberately vulnerable
    echo "</pre>";
} else {
    echo "No file specified via POST";
}
?>
