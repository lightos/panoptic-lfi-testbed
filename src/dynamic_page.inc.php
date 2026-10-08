<?php
// Shared layout for the dynamic-response cases. Every response carries a fresh
// random request token (repeated as a CSP nonce and a CSRF field, as real
// frameworks do) and a microsecond timestamp, so no two responses are equal.
// Requesting this file directly only defines the function.
function render_dynamic_page(string $title, callable $body): void
{
    $token = bin2hex(random_bytes(16));
    $timestamp = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:s.uP');
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Request-Id: ' . $token);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="csrf-token" content="<?php echo $token; ?>">
    <style nonce="<?php echo $token; ?>">
        body { font-family: system-ui, sans-serif; margin: 40px; color: #17212b; }
        nav a { margin-right: 12px; }
        footer { margin-top: 32px; color: #52606d; font-size: 0.9em; }
    </style>
</head>
<body>
    <nav>
        <a href="/">Home</a>
        <a href="/docs/">Documentation</a>
        <a href="/support/">Support</a>
        <a href="/status/">Service status</a>
    </nav>
    <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php $body(); ?>
    <form method="post" action="/feedback">
        <input type="hidden" name="csrf" value="<?php echo $token; ?>">
        <label>Was this page helpful? <input name="feedback"></label>
        <button type="submit">Send</button>
    </form>
    <footer>
        Request <?php echo $token; ?> rendered at <?php echo $timestamp; ?>.
        Panoptic LFI testbed dynamic-response fixture.
    </footer>
</body>
</html>
<?php
}
