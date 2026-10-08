<?php
// Request log for hostile_passwd.php, kept on the container's /tmp tmpfs.
// GET returns the logged paths as a JSON array; POST clears the log.
const LOG_FILE = '/tmp/panoptic-hostile-requests.log';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(LOG_FILE, '', LOCK_EX);
    echo "[]";
    exit;
}

$entries = [];
$lines = is_file(LOG_FILE) ? file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
foreach ($lines as $line) {
    $entries[] = json_decode($line);
}
echo json_encode($entries, JSON_INVALID_UTF8_SUBSTITUTE);
?>
