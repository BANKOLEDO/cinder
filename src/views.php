<?php
declare(strict_types=1);

function icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . e($class) . '" aria-hidden="true"><use href="/assets/icons.svg#' . e($name)
        . '"></use></svg>';
}

function short_age(int $timestamp): string
{
    $delta = max(0, time() - $timestamp);
    if ($delta < 60) {
        return 'just now';
    }
    if ($delta < 3600) {
        return max(1, intdiv($delta, 60)) . ' min ago';
    }
    return 'over an hour ago';
}

function ttl_segments(): string
{
    $labels = [3600 => '1 hour', 86400 => '1 day', 604800 => '7 days', 2592000 => '30 days'];
    $html = '';
    foreach ($labels as $seconds => $label) {
        $checked = $seconds === 86400 ? ' checked' : '';
        $html .= '<label class="seg-opt"><input type="radio" name="ttl" value="' . e((string) $seconds) . '"'
            . $checked . '><span>' . e($label) . '</span></label>';
    }
    return $html;
}

function reads_segments(): string
{
    $html = '';
    foreach (NOTE_READS as $reads => $label) {
        $checked = $reads === 1 ? ' checked' : '';
        $html .= '<label class="seg-opt"><input type="radio" name="reads" value="' . e((string) $reads) . '"'
            . $checked . '><span>' . e((string) $reads) . '</span></label>';
    }
    return $html;
}

function composer(bool $autofocus = false): string
{
    $focus = $autofocus ? ' autofocus' : '';

    return '<form class="compose-grid" method="post" action="/create" autocomplete="off">'
        . csrf_field()
        . '<div class="panel editor">'
        . '<div class="panel-head"><span class="panel-title">' . icon('file-text') . 'note.txt</span>'
        . '<span class="panel-meta" data-charcount>0 / 32,768 bytes</span></div>'
        . '<label class="sr-only" for="body">Note</label>'
        . '<textarea id="body" name="body" maxlength="' . NOTE_MAX_BYTES . '" required' . $focus
        . ' placeholder="A token, a password, a connection string, a private message."></textarea>'
        . '</div>'
        . '<aside class="panel settings">'
        . '<div class="panel-head"><span class="panel-title">' . icon('key-round') . 'Seal settings</span>'
        . '<span class="panel-meta" data-reads-meta>1 read</span></div>'
        . '<div class="settings-body">'
        . '<div class="settings-el">'
        . '<div class="label-row"><span class="label">' . icon('timer', 'icon icon-sm') . 'Expires</span>'
        . '<span class="hint" data-expiry-preview></span></div>'
        . '<fieldset class="seg"><legend class="sr-only">Expiry</legend>' . ttl_segments() . '</fieldset>'
        . '</div>'
        . '<div class="settings-el">'
        . '<div class="label-row"><span class="label">' . icon('eye', 'icon icon-sm') . 'Reads</span>'
        . '<span class="hint" data-reads-preview>1 read</span></div>'
        . '<fieldset class="seg seg-reads"><legend class="sr-only">Reads allowed</legend>'
        . reads_segments() . '</fieldset>'
        . '</div>'
        . '<div class="settings-el">'
        . '<div class="label-row"><label class="label" for="password">Passphrase</label>'
        . '<button type="button" class="text-btn" data-generate>Generate</button></div>'
        . '<div class="pw-row">'
        . '<input class="input" id="password" type="password" name="password" minlength="' . PASSWORD_MIN . '" '
        . 'maxlength="' . PASSWORD_MAX . '" required autocomplete="new-password" spellcheck="false" '
        . 'placeholder="' . PASSWORD_MIN . '+ characters">'
        . '<button type="button" class="text-btn" data-toggle-pw aria-pressed="false" '
        . 'aria-controls="password">Show</button>'
        . '</div>'
        . '<div class="meter" data-meter data-level="0">'
        . '<span class="meter-track"><span class="meter-fill"></span></span>'
        . '<span class="meter-text" data-meter-text>Add 10+ characters</span></div>'
        . '</div>'
        . '<button type="submit" class="btn btn-accent btn-seal" data-seal>' . icon('lock')
        . '<span class="btn-label">Seal note</span></button>'
        . '<dl class="summary">'
        . '<div><dt>' . icon('eye', 'icon icon-sm') . 'reads</dt><dd data-reads-summary>1 allowed</dd></div>'
        . '<div><dt>' . icon('database', 'icon icon-sm') . 'storage</dt><dd>ciphertext</dd></div>'
        . '<div><dt>' . icon('shield-off', 'icon icon-sm') . 'recovery</dt>'
        . '<dd class="dim">none</dd></div>'
        . '</dl>'
        . '</div></aside>'
        . '<div class="compose-bar"><span>Plaintext never written to disk. Send the passphrase '
        . 'separately.</span><span class="kbd-group"><span class="kbd">Ctrl</span>'
        . '<span class="kbd">Enter</span></span></div>'
        . '</form>';
}

function render(
    string $title,
    string $content,
    string $meta = 'Encrypted notes with a passphrase and a read limit. The link deletes itself after the last read. No accounts, no telemetry.',
    bool $indexable = true
): void {
    $name = e(APP_NAME);
    $assetRoot = APP_ROOT . '/public/assets/';
    $cssVersion = @filemtime($assetRoot . 'app.css') ?: 1;
    $jsVersion = @filemtime($assetRoot . 'app.js') ?: 1;

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!is_string($requestPath) || $requestPath === '') {
        $requestPath = '/';
    }
    $canonical = site_url($requestPath);
    $ogImage = site_url('/assets/og.png');
    $shareTitle = $title . ' - ' . APP_NAME;
    $robots = $indexable
        ? 'index, follow, max-image-preview:large'
        : 'noindex, nofollow, noarchive';

    if (!$indexable) {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="referrer" content="no-referrer">'
        . '<meta name="theme-color" content="#0a0a0a">'
        . '<meta name="color-scheme" content="dark">'
        . '<meta name="description" content="' . e($meta) . '">'
        . '<meta name="robots" content="' . $robots . '">'
        . '<meta name="application-name" content="' . $name . '">'
        . '<title>' . e($title) . ' &middot; ' . $name . '</title>'
        . ($indexable ? '<link rel="canonical" href="' . e($canonical) . '">' : '')
        . '<link rel="icon" href="/favicon.ico" sizes="32x32">'
        . '<link rel="icon" href="/favicon.svg" type="image/svg+xml">'
        . '<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">'
        . '<link rel="manifest" href="/site.webmanifest">'
        . '<link rel="sitemap" type="application/xml" title="Sitemap" href="/sitemap.xml">'
        . '<meta property="og:type" content="website">'
        . '<meta property="og:site_name" content="' . $name . '">'
        . '<meta property="og:title" content="' . e($shareTitle) . '">'
        . '<meta property="og:description" content="' . e($meta) . '">'
        . '<meta property="og:url" content="' . e($canonical) . '">'
        . '<meta property="og:image" content="' . e($ogImage) . '">'
        . '<meta property="og:image:type" content="image/png">'
        . '<meta property="og:image:width" content="1200">'
        . '<meta property="og:image:height" content="630">'
        . '<meta property="og:image:alt" content="Cinder - encrypted notes that disappear">'
        . '<meta name="twitter:card" content="summary_large_image">'
        . '<meta name="twitter:title" content="' . e($shareTitle) . '">'
        . '<meta name="twitter:description" content="' . e($meta) . '">'
        . '<meta name="twitter:image" content="' . e($ogImage) . '">'
        . '<link rel="preload" href="/assets/fonts/ibm-plex-sans-var.woff2" as="font" '
        . 'type="font/woff2" crossorigin>'
        . '<link rel="preload" href="/assets/fonts/ibm-plex-serif-italic.woff2" as="font" '
        . 'type="font/woff2" crossorigin>'
        . '<link rel="stylesheet" href="/assets/app.css?v=' . $cssVersion . '"></head><body class="app">'
        . '<a class="skip" href="#main">Skip to content</a>'
        . '<header class="site-head"><div class="site-head-in">'
        . '<a class="site-brand" href="/">'
        . '<svg class="brand-mark" viewBox="0 0 16 16" aria-hidden="true">'
        . '<path d="M2 4h8M6 8h8M2 12h6" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="square"/></svg>' . $name . '</a>'
        . '<div class="site-nav">'
        . '<span class="site-runtime"><span class="dot" aria-hidden="true"></span>Patched &middot; PHP 8.4.27</span>'
        . '<a class="site-link" href="/security">' . icon('shield-check') . 'Security model</a>'
        . '<a class="site-link" href="/history">' . icon('link-2') . 'Your notes</a>'
        . '<button type="button" class="cmdk" data-cmdk aria-label="Open command palette">'
        . icon('command') . '<span class="kbd">K</span></button>'
        . '<a class="nav-cta" href="/#compose">Write a note</a>'
        . '</div></div></header>'
        . '<main id="main">' . $content . '</main>'
        . '<footer class="foot"><div class="foot-in">'
        . '<p>' . $name . ' &middot; PHP, SQLite, libsodium</p>'
        . '<div class="foot-links"><a href="/">New note</a><a href="/security">Security model</a>'
        . '<a href="/history">Your notes</a><span>Patched for PHP2Shell</span>'
        . '<a href="https://lucide.dev" rel="noopener noreferrer">Icons: Lucide (ISC)</a></div>'
        . '</div></footer>'
        . '<div class="palette" data-palette hidden>'
        . '<div class="palette-box" role="dialog" aria-modal="true" aria-label="Command palette">'
        . '<div class="palette-head"><span class="palette-title">Commands</span>'
        . '<span class="kbd">Esc</span></div>'
        . '<input class="palette-input" data-palette-input type="text" placeholder="Search commands" '
        . 'autocomplete="off" spellcheck="false" aria-label="Search commands">'
        . '<ul class="palette-list" data-palette-list role="listbox" aria-label="Commands"></ul>'
        . '<p class="palette-empty" data-palette-empty hidden>No matching command.</p>'
        . '</div></div>'
        . '<script src="/assets/app.js?v=' . $jsVersion . '" defer></script></body></html>';
}

function view_landing(): void
{
    $content = '<div class="page">'
        . '<section class="hero">'
        . '<canvas class="hero-gl" data-gl aria-hidden="true"></canvas>'
        . '<div class="hero-inner">'
        . '<a class="hero-badge" href="#compose">' . icon('lock', 'icon icon-sm')
        . 'Deleted after the last read</a>'
        . '<h1>Send a secret that<br><em>disappears</em>.</h1>'
        . '<p class="hero-sub">Encrypt a note with a passphrase you choose. Set how many reads the '
        . 'link allows; the server deletes it after the last one. No account, no history.</p>'
        . '<div class="hero-cta">'
        . '<a class="btn btn-accent btn-lg" href="#compose">' . icon('send')
        . '<span class="btn-label">Write a note</span></a>'
        . '<a class="btn btn-outline btn-lg" href="/security">' . icon('shield-check')
        . '<span class="btn-label">Read the security model</span></a>'
        . '</div>'
        . '<ul class="proof">'
        . '<li>Argon2id &middot; 32 MiB</li><li>XSalsa20-Poly1305</li>'
        . '<li>No accounts</li><li>No telemetry</li>'
        . '</ul>'
        . '</div>'
        . icon('lock', 'spark spark-a')
        . icon('lock', 'spark spark-b')
        . '<span class="hero-float hero-float-l" aria-hidden="true">argon2id</span>'
        . '<span class="hero-float hero-float-r" aria-hidden="true">xsalsa20-poly1305</span>'
        . '</section>'
        . '<section class="demo" id="compose">'
        . '<div class="demo-glow" aria-hidden="true"></div>'
        . '<div class="demo-frame">'
        . '<div class="demo-bar">'
        . '<span class="demo-title">' . icon('lock', 'icon icon-sm') . 'new note'
        . '<span class="demo-sep">/</span>read limit</span>'
        . '<span class="demo-meta"><span class="live"><span class="dot" aria-hidden="true"></span>'
        . 'encrypted in flight</span><span class="chip" data-reads-chip>1 read</span></span>'
        . '</div>'
        . composer()
        . '</div></div>'
        . '<section class="sec" id="how">'
        . '<header class="sec-head"><p class="sec-label">How it works</p>'
        . '<h2 class="sec-title">Three moves, no trace</h2>'
        . '<p class="sec-copy">The server sees ciphertext from the moment the note is created up to '
        . 'the moment the last read is opened.</p></header>'
        . '<div class="steps">'
        . '<article class="step"><span class="step-icon">' . icon('file-text') . '</span>'
        . '<h3>Write in the browser</h3>'
        . '<p>The note is encrypted in the request that creates it. Nothing is written to disk in '
        . 'plaintext.</p></article>'
        . '<article class="step"><span class="step-icon">' . icon('key-round') . '</span>'
        . '<h3>Sealed with Argon2id</h3>'
        . '<p>Your passphrase is stretched into a 32-byte key. Only ciphertext, salt and nonce are '
        . 'stored.</p></article>'
        . '<article class="step"><span class="step-icon">' . icon('trash-2') . '</span>'
        . '<h3>Last read, then gone</h3>'
        . '<p>Each correct read counts down. The note is deleted when the limit is reached; wrong '
        . 'attempts do not burn it.</p></article>'
        . '</div></section>'
        . '<section class="sec" id="recipient">'
        . '<header class="sec-head"><p class="sec-label">Recipient</p>'
        . '<h2 class="sec-title">A link, a passphrase, one rule</h2>'
        . '<p class="sec-copy">The other side sees the minimum, and gets exactly the reads you allow.</p>'
        . '</header>'
        . '<div class="split">'
        . '<div class="split-copy"><ul class="ticks">'
        . '<li>A passphrase prompt and nothing else.</li>'
        . '<li>No account, no history, no undo.</li>'
        . '<li>A wrong passphrase leaves the note intact.</li>'
        . '<li>Refreshing after the last read returns a 404.</li>'
        . '</ul></div>'
        . '<div class="panel sample">'
        . '<div class="panel-head"><span class="panel-title">' . icon('link-2', 'icon icon-sm')
        . 'note / 7Kd2mQ4pLt9xVb1n</span><span class="chip">3 reads</span></div>'
        . '<div class="panel-body sample-stack">'
        . '<div><p class="label">Link</p><p class="sample-input">cinder.app/note/7Kd2mQ4pLt9xVb1n</p></div>'
        . '<div><p class="label">Passphrase</p><p class="sample-input">Enter the passphrase</p></div>'
        . '<p class="sample-note">A correct passphrase decrypts the note and uses one read. A wrong '
        . 'one leaves it intact. The note is deleted after the last read.</p>'
        . '</div></div></div></section>'
        . '<section class="sec" id="guarantees">'
        . '<header class="sec-head"><p class="sec-label">Guarantees</p>'
        . '<h2 class="sec-title">What is true on every note</h2>'
        . '<p class="sec-copy">Properties that hold regardless of size, recipient or expiry.</p>'
        . '</header>'
        . '<dl class="matrix">'
        . '<div><dt>Plaintext</dt><dd>Never written to disk, never logged.</dd></div>'
        . '<div><dt>Key</dt><dd>Derived per request from the passphrase, never stored.</dd></div>'
        . '<div><dt>Reads</dt><dd>Set at creation. The note is deleted after the last allowed read.</dd></div>'
        . '<div><dt>Accounts</dt><dd>None. No email, profile or history.</dd></div>'
        . '<div><dt>Scripts</dt><dd>First-party only. No third-party code or telemetry.</dd></div>'
        . '<div><dt>Abuse</dt><dd>Rate limits, per-note lockouts, CSRF tokens.</dd></div>'
        . '<div><dt>Runtime</dt><dd>PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or newer.</dd></div>'
        . '</dl></section>'
        . '<section class="cta">'
        . '<div class="cta-glow" aria-hidden="true"></div>'
        . '<h2>Write it once. Send it once.</h2>'
        . '<p>The passphrase goes one way, the link goes another. After the last read, there is '
        . 'nothing left to leak.</p>'
        . '<a class="btn btn-accent btn-lg" href="#compose">' . icon('send')
        . '<span class="btn-label">Write a note</span></a>'
        . '<p class="cta-fine">No account. No recovery. No second chance.</p>'
        . '</section>'
        . '</div>';

    render(
        'Encrypted notes that disappear',
        $content,
        'Encrypted notes with a passphrase and a read limit. The link deletes itself after the last read. No accounts, no telemetry.'
    );
}

function view_security(): void
{
    $content = '<div class="page">'
        . '<header class="page-hero"><p class="sec-label">Security</p>'
        . '<h1 class="page-title">Security model</h1>'
        . '<p class="page-sub">What Cinder stores, what it protects against, and what it cannot '
        . 'protect you from.</p></header>'
        . '<div class="doc">'
        . '<nav class="toc" aria-label="On this page"><p class="toc-label">On this page</p>'
        . '<a href="#stored">What the server stores</a>'
        . '<a href="#kdf">Key derivation</a>'
        . '<a href="#burn">Read limit and deletion</a>'
        . '<a href="#abuse">Abuse protections</a>'
        . '<a href="#runtime">Runtime policy</a>'
        . '<a href="#limits">What it cannot do</a></nav>'
        . '<article class="prose">'
        . '<section id="stored"><h2>What the server stores</h2><ul>'
        . '<li>An encrypted payload (XSalsa20-Poly1305), a random per-note salt and nonce.</li>'
        . '<li>Creation and expiry timestamps, a note identifier, and the read limit with a count.</li>'
        . '<li>A failed-attempt counter, used to lock a note after repeated guessing.</li>'
        . '</ul><p>It does not store your passphrase, a derived key, your note in plaintext, or '
        . 'your IP address. Rate limiting uses a keyed hash of the address, which cannot be reversed '
        . 'without the server key.</p></section>'
        . '<section id="kdf"><h2>Key derivation</h2>'
        . '<p>The passphrase is stretched with Argon2id (three iterations, 32 MiB) into a 32-byte '
        . 'key, which encrypts the note. The passphrase exists in memory only while a request is '
        . 'being handled and is never written to disk or to the log.</p></section>'
        . '<section id="burn"><h2>Read limit and deletion</h2>'
        . '<p>A note is created with a read limit. A correct passphrase decrypts the note and '
        . 'increments the read count in the same transaction. When the count reaches the limit, the '
        . 'row is deleted. A wrong passphrase neither uses a read nor destroys the note, so nobody '
        . 'can burn it by guessing. After five failed attempts a note is locked for fifteen minutes.</p></section>'
        . '<section id="abuse"><h2>Abuse protections</h2><ul>'
        . '<li>Content Security Policy with no inline script or style, and no third-party origins.</li>'
        . '<li>Strict same-site, HTTP-only session cookies; CSRF tokens on every form.</li>'
        . '<li>Prepared statements everywhere, strict validation, a 64 KB request ceiling.</li>'
        . '<li>Rate limits on note creation and on reveal attempts.</li></ul></section>'
        . '<section id="runtime"><h2>Runtime policy</h2>'
        . '<p>Cinder refuses to boot on PHP versions affected by the PHP2Shell remote code '
        . 'execution flaws (CVE-2026-109618 and CVE-2026-109619, disclosed 21 October 2026). It '
        . 'requires PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or newer.</p></section>'
        . '<section id="limits"><h2>What it cannot protect against</h2><ul>'
        . '<li>A fully compromised server: plaintext passes through memory at write and read time.</li>'
        . '<li>Someone who has both the link and the passphrase.</li>'
        . '<li>Metadata: note size, creation time and expiry are visible.</li>'
        . '<li>A recipient who copies the note before it is destroyed.</li>'
        . '<li>Traffic without TLS, or a recipient on a compromised device.</li></ul></section>'
        . '<p class="prose-more"><a class="more-link" href="/">Create a note</a></p>'
        . '</article></div></div>';

    render('Security model', $content, 'How Cinder encrypts notes, enforces read limits and protects against abuse.');
}

function view_created(string $id, ?int $expires = null, int $reads = 1): void
{
    $path = '/note/' . $id;
    $readLabel = $reads === 1 ? '1 read' : $reads . ' reads';
    $expiry = $expires !== null
        ? '<dd data-countdown data-expires="' . e((string) $expires) . '">counting</dd>'
        : '<dd>on expiry</dd>';

    $content = '<div class="page"><div class="narrow">'
        . '<header class="state-head"><span class="state-icon">' . icon('circle-check') . '</span>'
        . '<p class="sec-label">Sealed</p>'
        . '<h1 class="page-title">Note sealed.</h1>'
        . '<p class="page-sub">The link allows ' . e($readLabel) . '. Send the passphrase on a '
        . 'different channel; the note is deleted after the last read.</p></header>'
        . '<div class="panel">'
        . '<div class="panel-head"><span class="panel-title">' . icon('lock', 'icon icon-sm')
        . 'Secret link</span><span class="chip">' . e($readLabel) . '</span></div>'
        . '<div class="panel-body">'
        . '<div class="linkrow"><label class="sr-only" for="note-link">Secret link</label>'
        . '<input class="input" id="note-link" type="text" readonly value="' . e($path) . '" data-absolutize>'
        . '<button type="button" class="btn btn-accent" data-copy-target="note-link">' . icon('copy')
        . '<span class="btn-label">Copy</span></button></div>'
        . '<dl class="tri">'
        . '<div><dt>' . icon('eye', 'icon icon-sm') . 'reads</dt><dd>' . e($reads . ' allowed') . '</dd></div>'
        . '<div><dt>' . icon('timer', 'icon icon-sm') . 'expires</dt>' . $expiry . '</div>'
        . '<div><dt>' . icon('shield-off', 'icon icon-sm') . 'recovery</dt>'
        . '<dd class="dim">none</dd></div>'
        . '</dl>'
        . '<div class="note-warn"><strong>Send the passphrase separately.</strong> Anyone with the '
        . 'link still needs it, and there is no reset.</div>'
        . '<div class="actions"><a class="btn btn-outline" href="/">Write another</a>'
        . '<a class="more-link" href="/security">Security model</a>'
        . '<a class="more-link" href="/history">All your notes</a></div>'
        . '</div></div></div></div>';

    render('Note sealed', $content, 'Sealed note confirmation', false);
}

function view_reveal(string $id, ?string $error = null, int $reads = 1): void
{
    $alert = is_string($error) ? '<div class="alert" role="alert">' . e($error) . '</div>' : '';
    $readLabel = $reads === 1 ? '1 read' : $reads . ' reads';
    $sub = $reads === 1
        ? 'A correct passphrase decrypts this note and deletes it from the server in the same '
            . 'request. There is no undo.'
        : 'A correct passphrase decrypts this note and uses one of its ' . $reads . ' reads. The '
            . 'note is deleted after the last one.';
    $hint = $reads === 1
        ? 'One attempt consumes the note.'
        : 'Each correct read uses one of ' . $reads . '.';
    $action = $reads === 1 ? 'Open and destroy' : 'Open note';

    $content = '<div class="page"><div class="narrow">'
        . '<header class="state-head"><span class="state-icon">' . icon('key-round') . '</span>'
        . '<p class="sec-label">Sealed note</p>'
        . '<h1 class="page-title">Open a sealed note.</h1>'
        . '<p class="page-sub">' . e($sub) . '</p></header>'
        . '<div class="panel">'
        . '<div class="panel-head"><span class="panel-title">' . icon('lock', 'icon icon-sm')
        . 'note / ' . e($id) . '</span><span class="chip chip-alert">' . e($readLabel) . '</span></div>'
        . '<div class="panel-body">' . $alert
        . '<form method="post" action="/note/' . e($id) . '" autocomplete="off">'
        . csrf_field()
        . '<label class="label" for="password">Passphrase</label>'
        . '<div class="pw-row">'
        . '<input class="input" id="password" type="password" name="password" maxlength="' . PASSWORD_MAX . '" '
        . 'required autofocus autocomplete="off" spellcheck="false">'
        . '<button type="button" class="text-btn" data-toggle-pw aria-pressed="false">Show</button>'
        . '</div>'
        . '<p class="hint">' . e($hint) . '</p>'
        . '<button type="submit" class="btn btn-accent btn-block btn-open">' . icon('trash-2')
        . '<span class="btn-label">' . e($action) . '</span></button>'
        . '</form>'
        . '<ul class="rules">'
        . '<li>Decryption and the read count share one transaction.</li>'
        . '<li>A wrong passphrase does not use a read or destroy the note.</li>'
        . '<li>Five wrong attempts lock it for fifteen minutes.</li>'
        . '</ul></div></div></div></div>';

    render('Open a note', $content, 'Open a sealed note', false);
}

function view_note(string $body, int $remaining = 0): void
{
    if ($remaining === 0) {
        $label = 'Destroyed on read';
        $title = 'Here it is, once.';
        $sub = 'This note has been deleted from the server. Copy it now; refreshing will not bring '
            . 'it back.';
    } else {
        $left = $remaining === 1 ? 'One read left' : $remaining . ' reads left';
        $label = 'Read counted';
        $title = 'Here it is.';
        $sub = 'This read has been counted. ' . $left . '. The note is deleted after the last one '
            . 'or when it expires.';
    }

    $content = '<div class="page"><div class="narrow">'
        . '<header class="state-head"><span class="state-icon">' . icon('check') . '</span>'
        . '<p class="sec-label label-alert">' . e($label) . '</p>'
        . '<h1 class="page-title">' . e($title) . '</h1>'
        . '<p class="page-sub">' . e($sub) . '</p></header>'
        . '<div class="panel">'
        . '<div class="panel-head"><span class="panel-title">' . icon('terminal', 'icon icon-sm')
        . 'plaintext &middot; no copy on disk</span>'
        . '<button type="button" class="btn btn-outline btn-sm" data-copy-target="note-body">'
        . icon('copy', 'icon icon-sm') . '<span class="btn-label">Copy</span></button></div>'
        . '<pre class="note-body" id="note-body">' . e($body) . '</pre>'
        . '</div>'
        . '<div class="actions actions-center"><a class="btn btn-outline" href="/">Write another</a>'
        . '<a class="more-link" href="/security">Security model</a></div>'
        . '</div></div>';

    render('Note revealed', $content, 'Decrypted note', false);
}

function view_history(array $notes): void
{
    $rows = '';
    foreach ($notes as $id => $meta) {
        $alive = note_available($id);
        $reads = $meta['reads'] === 1 ? '1 read' : $meta['reads'] . ' reads';
        $inputId = 'hist-' . $id;
        $rows .= '<div class="hist-item' . ($alive ? '' : ' is-gone') . '">'
            . '<div class="hist-top"><span class="hist-id">note / ' . e($id) . '</span>'
            . '<span class="chip' . ($alive ? '' : ' chip-dim') . '">'
            . ($alive ? e($reads) : 'gone') . '</span></div>'
            . '<div class="linkrow"><label class="sr-only" for="' . e($inputId) . '">Secret link</label>'
            . '<input class="input" id="' . e($inputId) . '" type="text" readonly '
            . 'value="/note/' . e($id) . '" data-absolutize>'
            . '<button type="button" class="btn btn-outline btn-sm" data-copy-target="' . e($inputId) . '">'
            . icon('copy', 'icon icon-sm') . '<span class="btn-label">Copy</span></button></div>'
            . '<div class="hist-meta"><span>created ' . e(short_age($meta['created'])) . '</span>'
            . ($alive
                ? '<span>expires in <span data-countdown data-expires="' . e((string) $meta['expires'])
                    . '">counting</span></span><span class="hist-state ok">ready</span>'
                : '<span class="hist-state">read or expired</span>')
            . '</div></div>';
    }

    if ($notes === []) {
        $body = '<div class="empty"><p>Nothing here yet. Notes you seal in this browser show up '
            . 'in this list for one hour.</p><a class="btn btn-accent" href="/#compose">Write a note</a></div>';
    } else {
        $body = '<div class="panel"><div class="panel-head">'
            . '<span class="panel-title">' . icon('link-2', 'icon icon-sm') . 'Created here</span>'
            . '<span class="chip">' . count($notes) . '</span></div>'
            . '<div class="hist-list">' . $rows . '</div></div>'
            . '<form class="hist-clear" method="post" action="/history">' . csrf_field()
            . '<button type="submit" class="text-btn" name="action" value="clear">Forget these links</button></form>';
    }

    $content = '<div class="page"><div class="narrow">'
        . '<header class="state-head"><span class="state-icon">' . icon('link-2') . '</span>'
        . '<p class="sec-label">This session</p>'
        . '<h1 class="page-title">Your notes.</h1>'
        . '<p class="page-sub">Links you created in this browser. This list stays for one hour; '
        . 'clearing it never deletes a note.</p></header>'
        . $body . '</div></div>';

    render('Your notes', $content, 'Session history of notes created in this browser.', false);
}

function view_error(int $code, string $title, string $message, ?int $retryAfter = null): void
{
    http_response_code($code);
    if ($retryAfter !== null) {
        header('Retry-After: ' . $retryAfter);
    }

    $content = '<div class="page"><div class="narrow">'
        . '<header class="state-head"><span class="state-icon">' . icon('shield-off') . '</span>'
        . '<p class="sec-label">Error ' . e((string) $code) . '</p>'
        . '<h1 class="page-title">' . e($title) . '</h1>'
        . '<p class="page-sub">' . e($message) . '</p></header>'
        . '<div class="actions actions-center"><a class="btn btn-outline" href="/">Back to '
        . e(APP_NAME) . '</a></div>'
        . '</div></div>';

    render($title, $content, 'The request could not be completed.', false);
}
