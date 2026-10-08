<?php
$title = "Panoptic LFI Testbed";
$proofPath = "/opt/panoptic-fixtures/proof.txt";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?></title>
    <style>
        :root {
            color-scheme: light;
            --ink: #17212b;
            --muted: #52606d;
            --paper: #f7f8fa;
            --card: #ffffff;
            --danger: #b42318;
            --line: #d8dee6;
            --code: #eef2f6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font: 16px/1.55 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        main { width: min(1080px, calc(100% - 32px)); margin: 48px auto; }
        h1 { margin-bottom: 8px; font-size: clamp(2rem, 6vw, 3.6rem); letter-spacing: -0.04em; }
        h2 { margin-top: 48px; }
        .lede { max-width: 760px; color: var(--muted); font-size: 1.08rem; }
        .warning {
            margin: 28px 0;
            padding: 16px 18px;
            border: 1px solid #f2b8b5;
            border-left: 5px solid var(--danger);
            border-radius: 8px;
            background: #fff4f2;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 16px;
        }
        article {
            min-width: 0;
            padding: 20px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--card);
            box-shadow: 0 1px 2px rgb(23 33 43 / 0.05);
        }
        article h3 { margin: 0 0 8px; }
        article p { color: var(--muted); }
        code {
            overflow-wrap: anywhere;
            border-radius: 5px;
            background: var(--code);
            padding: 2px 5px;
            font: 0.88em/1.45 ui-monospace, SFMono-Regular, Consolas, monospace;
        }
        a { color: #175cd3; }
        footer { margin-top: 48px; color: var(--muted); }
    </style>
</head>
<body>
<main>
    <h1><?php echo $title; ?></h1>
    <p class="lede">
        Deliberately vulnerable, deterministic endpoints for black-box testing
        Panoptic's path traversal transports, transformations, and response heuristics.
    </p>

    <div class="warning">
        <strong>Never deploy this application.</strong>
        Run it only in the provided localhost-bound container. Every endpoint below
        is intentionally unsafe unless explicitly marked as a negative control.
    </div>

    <h2>Core injection transports</h2>
    <div class="grid">
        <article>
            <h3>GET query</h3>
            <p>Direct vulnerable query parameter.</p>
            <a href="classic.php?file=test.txt"><code>classic.php?file=test.txt</code></a>
        </article>
        <article>
            <h3>POST form</h3>
            <p>Form field named <code>file</code>.</p>
            <code>--data "file=test.txt" --param file</code>
        </article>
        <article>
            <h3>JSON and nested JSON</h3>
            <p>Top-level and nested <code>FUZZ</code> replacement.</p>
            <code>{"request":{"template":"FUZZ"}}</code>
        </article>
        <article>
            <h3>Opaque body</h3>
            <p>The complete request body is the path.</p>
            <code>--data "FUZZ"</code>
        </article>
        <article>
            <h3>Cookie</h3>
            <p>Inject through the <code>lang</code> cookie.</p>
            <code>--header "Cookie: lang=FUZZ"</code>
        </article>
        <article>
            <h3>Custom header</h3>
            <p>Inject through <code>X-Template</code>.</p>
            <code>--header "X-Template: FUZZ"</code>
        </article>
    </div>

    <h2>Path transformations</h2>
    <div class="grid">
        <article>
            <h3>PATH_INFO</h3>
            <p>Absolute path carried in PATH_INFO.</p>
            <a href="pathinfo.php/opt/panoptic-fixtures/proof.txt">
                <code>pathinfo.php/opt/panoptic-fixtures/proof.txt</code>
            </a>
        </article>
        <article>
            <h3>Rewritten double encoding</h3>
            <p>Apache rewrite plus one deliberate application decode.</p>
            <code>--path-based --replace-slash "%252F"</code>
        </article>
        <article>
            <h3>Naive filter bypass</h3>
            <p>One-pass <code>../</code> removal bypassed by nested sequences.</p>
            <code>--prefix "....//" --multiplier 4</code>
        </article>
        <article>
            <h3>Base64</h3>
            <p>Strict Base64 decoding before inclusion.</p>
            <a href="base64.php?file=<?php echo rawurlencode(base64_encode($proofPath)); ?>">
                <code>base64.php?file=encoded</code>
            </a>
        </article>
        <article>
            <h3>Split extension</h3>
            <p>Filename and extension supplied separately.</p>
            <a href="param.php?file=test&amp;type=txt"><code>param.php?file=test&amp;type=txt</code></a>
        </article>
        <article>
            <h3>Legacy NUL simulator</h3>
            <p>Explicitly simulates old NUL truncation on supported PHP.</p>
            <code>--postfix "%00"</code>
        </article>
    </div>

    <h2>Detection and workflow controls</h2>
    <div class="grid">
        <article>
            <h3>Status codes</h3>
            <p>Returns 200 for readable paths and 404 for missing paths.</p>
            <a href="status.php?file=<?php echo rawurlencode($proofPath); ?>">
                <code>status.php?file=...</code>
            </a>
        </article>
        <article>
            <h3>Redirect</h3>
            <p>Tests <code>--follow-redirects</code>.</p>
            <a href="redirect.php?file=test.txt"><code>redirect.php?file=test.txt</code></a>
        </article>
        <article>
            <h3>Authenticated query</h3>
            <p>Requires <code>panoptic_auth=allowed</code> through <code>--cookie</code>.</p>
            <code>auth.php?file=test.txt</code>
        </article>
        <article>
            <h3>Safe negative control</h3>
            <p>Reflects encoded paths without touching the filesystem.</p>
            <a href="safe.php?file=test.txt"><code>safe.php?file=test.txt</code></a>
        </article>
        <article>
            <h3>Dynamic parsers</h3>
            <p>Controlled passwd/home and MySQL binlog expansion fixtures.</p>
            <code>parser.php?file=test.txt</code>
        </article>
        <article>
            <h3>Windows JSON simulator</h3>
            <p>Exercises backslash escaping without a Windows container.</p>
            <code>{"request":{"path":"FUZZ"}}</code>
        </article>
    </div>

    <h2>Raw paths, request shapes, and hostile responses</h2>
    <div class="grid">
        <article>
            <h3>Raw path traversal</h3>
            <p>Served by the separate <code>raw</code> service on port 8081 (PHP's
            built-in server), which sees literal <code>../</code> before normalization.</p>
            <code>:8081/view/placeholder.txt --path-based --prefix "../" --multiplier 4</code>
        </article>
        <article>
            <h3>POST with query gate</h3>
            <p>Body field <code>file</code> is used only when <code>?action=view</code> is present.</p>
            <code>post_query.php?action=view --data "file=FUZZ"</code>
        </article>
        <article>
            <h3>XML body</h3>
            <p>Requires <code>Content-Type: application/xml</code>; XXE stays disabled.</p>
            <code>--data "&lt;req&gt;&lt;file&gt;FUZZ&lt;/file&gt;&lt;/req&gt;"</code>
        </article>
        <article>
            <h3>Backslash traversal simulator</h3>
            <p>Strips <code>../</code>, then converts <code>\</code> to <code>/</code>.</p>
            <code>backslash.php --prefix "..\" --multiplier 4</code>
        </article>
        <article>
            <h3>Reflected encodings (negative)</h3>
            <p>Echoes the parameter verbatim, URL-decoded, and Base64-decoded.</p>
            <a href="reflected_encoded.php?file=test.txt"><code>reflected_encoded.php?file=test.txt</code></a>
        </article>
        <article>
            <h3>Dynamic soft 404 (negative)</h3>
            <p>HTTP 200 with a random token and timestamp; never reads files.</p>
            <a href="soft404_dynamic.php?file=test.txt"><code>soft404_dynamic.php?file=test.txt</code></a>
        </article>
        <article>
            <h3>Dynamic page</h3>
            <p>The same randomized layout around a real inclusion sink.</p>
            <a href="dynamic_vuln.php?file=test.txt"><code>dynamic_vuln.php?file=test.txt</code></a>
        </article>
        <article>
            <h3>Hostile passwd</h3>
            <p>Homes with terminal escapes, a relative path, and a formula, plus one
            real home. Requests are logged in <code>hostile_log.php</code>.</p>
            <code>hostile_passwd.php?file=test.txt</code>
        </article>
    </div>

    <footer>
        Deterministic proof path: <code><?php echo $proofPath; ?></code>.
        See the repository README for the complete E2E matrix.
    </footer>
</main>
</body>
</html>
