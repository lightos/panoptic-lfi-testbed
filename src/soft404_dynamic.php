<?php
// Negative control: a soft-404 page that always answers HTTP 200 with a random
// token and a timestamp, and never touches the filesystem.
require __DIR__ . '/dynamic_page.inc.php';

render_dynamic_page('Page not found', function (): void {
    echo "<p>Sorry, the page you requested is not available. It may have been\n";
    echo "moved, renamed, or removed. Use the navigation above to continue.</p>\n";
});
?>
