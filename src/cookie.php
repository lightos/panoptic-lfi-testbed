<?php
// Cookie-based LFI vulnerability
// The "lang" cookie value is used to include a language file
$lang = isset($_COOKIE['lang']) ? $_COOKIE['lang'] : null;

if ($lang) {
    echo "<h2>Language File: " . htmlspecialchars($lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
    echo "<pre>";
    include($lang); // Vulnerable inclusion from cookie
    echo "</pre>";
} else {
    echo "No language cookie set. Set Cookie: lang=filename";
}
?>
