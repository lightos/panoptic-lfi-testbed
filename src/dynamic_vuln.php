<?php
// Positive dynamic case: the same randomized layout as soft404_dynamic.php,
// but the "file" parameter is included into the page.
require __DIR__ . '/dynamic_page.inc.php';

$file = $_GET['file'] ?? '';
render_dynamic_page('Document viewer', function () use ($file): void {
    if (!is_string($file) || $file === '') {
        echo "<p>No document selected.</p>\n";
        return;
    }
    echo "<pre>";
    include($file); // Deliberately vulnerable
    echo "</pre>\n";
});
?>
