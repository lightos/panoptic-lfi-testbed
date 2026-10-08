<?php
// XML body sink for: --data '<req><file>FUZZ</file></req>'
//                    --header 'Content-Type: application/xml'
// Requests without an XML content type are rejected with 415. External
// entities and network access stay disabled: this endpoint tests path
// injection through an XML body, not XXE.
$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0]));
if ($contentType !== 'application/xml') {
    http_response_code(415);
    echo "Content-Type must be application/xml";
    exit;
}

$previous = libxml_use_internal_errors(true);
$xml = simplexml_load_string(file_get_contents('php://input'), 'SimpleXMLElement', LIBXML_NONET);
libxml_use_internal_errors($previous);

$file = $xml !== false && isset($xml->file) ? (string) $xml->file : '';
if ($file !== '') {
    echo "<h2>XML File: " . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($file); // Deliberately vulnerable
    echo "</pre>";
} else {
    echo "Send <req><file>filename</file></req>";
}
?>
