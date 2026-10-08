<?php
// Negative control: a not-found page that reflects the "file" parameter
// verbatim as received on the wire, URL-decoded, and Base64-decoded. It never
// touches the filesystem, so every reflection must be ignored by Panoptic.
$received = '';
foreach (explode('&', $_SERVER['QUERY_STRING'] ?? '') as $pair) {
    $parts = explode('=', $pair, 2);
    if (rawurldecode($parts[0]) === 'file') {
        $received = $parts[1] ?? '';
        break;
    }
}
$urlDecoded = rawurldecode($received);
$base64Decoded = base64_decode($urlDecoded, true);

// text/plain keeps the verbatim reflection from being interpreted as markup.
header('Content-Type: text/plain; charset=UTF-8');
http_response_code(404);
echo "Document not found\n";
echo "==================\n\n";
echo "The requested document could not be located on this server.\n";
echo "Check the name and try again, or browse the document index.\n\n";
echo "Received: " . $received . "\n";
echo "URL-decoded: " . $urlDecoded . "\n";
echo "Base64-decoded: " . ($base64Decoded === false ? '(not valid Base64)' : $base64Decoded) . "\n";
?>
