<?php
declare(strict_types=1);

function icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . $class . '" aria-hidden="true"><use href="/assets/icons.svg#' . $name
        . '"></use></svg>';
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
        . '<span class="panel-meta">1 read</span></div>'
        . '<div class="settings-body">'
        . '<div class="settings-el">'
        . '<div class="label-row"><span class="label">' . icon('timer', 'icon icon-sm') . 'Expires</span>'
        . '<span class="hint" data-expiry-preview></span></div>'
        . '<fieldset class="seg"><legend class="sr-only">Expiry</legend>' . ttl_segments() . '</fieldset>'
        . '</div>'
        . '<div class="settings-el">'
        . '<div class="label-row"><label class="label" for="password">Passphrase</label>'
        . '<button type="button" class="text-btn" data-generate>Generate</button></div>'
        . '<input class="input" id="password" type="password" name="password" minlength="' . PASSWORD_MIN . '" '
        . 'maxlength="' . PASSWORD_MAX . '" required autocomplete="new-password" spellcheck="false" '
        . 'placeholder="' . PASSWORD_MIN . '+ characters">'
        . '<div class="meter" data-meter data-level="0">'
        . '<span class="meter-track"><span class="meter-fill"></span></span>'
        . '<span class="meter-text" data-meter-text>Add 10+ characters</span></div>'
        . '</div>'
        . '<button type="submit" class="btn btn-accent btn-seal" data-seal>' . icon('lock')
        . '<span class="btn-label">Seal note</span></button>'
        . '<dl class="summary">'
        . '<div><dt>' . icon('eye', 'icon icon-sm') . 'reads</dt><dd>1 of 1</dd></div>'
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
    string $meta = 'one-time encrypted notes'
): void {
    $name = e(APP_NAME);
    $assetRoot = APP_ROOT . '/public/assets/';
    $cssVersion = @filemtime($assetRoot . 'app.css') ?: 1;
    $jsVersion = @filemtime($assetRoot . 'app.js') ?: 1;

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="referrer" content="no-referrer">'
        . '<meta name="theme-color" content="#0a0a0a">'
        . '<meta name="description" content="' . e($meta) . '">'
        . '<title>' . e($title) . ' &middot; ' . $name . '</title>'
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
        . '<button type="button" class="cmdk" data-cmdk aria-label="Open command palette">'
        . icon('command') . '<span class="kbd">K</span></button>'
        . '<a class="nav-cta" href="/#compose">Write a note</a>'
        . '</div></div></header>'
        . '<main id="main">' . $content . '</main>'
        . '<footer class="foot"><div class="foot-in">'
        . '<p>' . $name . ' &middot; PHP, SQLite, libsodium</p>'
        . '<div class="foot-links"><a href="/">New note</a><a href="/security">Security model</a>'
        . '<span>Patched for PHP2Shell</span>'
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
        . 'One read, then deleted</a>'
        . '<h1>Send a secret that<br><em>disappears</em>.</h1>'
        . '<p class="hero-sub">Encrypt a note with a passphrase you choose. The link opens once, '
        . 'then the server deletes it. No account, no history, nothing left behind.</p>'
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
        . '<span class="demo-sep">/</span>one read</span>'
        . '<span class="demo-meta"><span class="live"><span class="dot" aria-hidden="true"></span>'
        . 'encrypted in flight</span><span class="chip">1 read</span></span>'
        . '</div>'
        . composer()
        . '</div></div>'
        . '<section class="sec" id="how">'
        . '<header class="sec-head"><p class="sec-label">How it works</p>'
        . '<h2 class="sec-title">Three moves, no trace</h2>'
        . '<p class="sec-copy">The server sees ciphertext from the moment the note is created up to '
        . 'the moment it is opened.</p></header>'
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
        . '<h3>One read, then gone</h3>'
        . '<p>A correct passphrase decrypts and deletes the note in the same transaction. Wrong '
        . 'ones do not burn it.</p></article>'
        . '</div></section>'
        . '<section class="sec" id="recipient">'
        . '<header class="sec-head"><p class="sec-label">Recipient</p>'
        . '<h2 class="sec-title">A link, a passphrase, one rule</h2>'
        . '<p class="sec-copy">The other side sees the minimum, and gets exactly one attempt.</p>'
        . '</header>'
        . '<div class="split">'
        . '<div class="split-copy"><ul class="ticks">'
        . '<li>A passphrase prompt and nothing else.</li>'
        . '<li>No account, no history, no second chance.</li>'
        . '<li>A wrong passphrase leaves the note intact.</li>'
        . '<li>Refreshing after a read returns a 404.</li>'
        . '</ul></div>'
        . '<div class="panel sample">'
        . '<div class="panel-head"><span class="panel-title">' . icon('link-2', 'icon icon-sm')
        . 'note / 7Kd2mQ4pLt9xVb1n</span><span class="chip">1 read</span></div>'
        . '<div class="panel-body sample-stack">'
        . '<div><p class="label">Link</p><p class="sample-input">cinder.app/note/7Kd2mQ4pLt9xVb1n</p></div>'
        . '<div><p class="label">Passphrase</p><p class="sample-input">Enter the passphrase</p></div>'
        . '<p class="sample-note">A correct passphrase decrypts the note and deletes it from the '
        . 'server in the same request. A wrong one leaves the note intact.</p>'
        . '</div></div></div></section>'
        . '<section class="sec" id="guarantees">'
        . '<header class="sec-head"><p class="sec-label">Guarantees</p>'
        . '<h2 class="sec-title">What is true on every note</h2>'
        . '<p class="sec-copy">Properties that hold regardless of size, recipient or expiry.</p>'
        . '</header>'
        . '<dl class="matrix">'
        . '<div><dt>Plaintext</dt><dd>Never written to disk, never logged.</dd></div>'
        . '<div><dt>Key</dt><dd>Derived per request from the passphrase, never stored.</dd></div>'
        . '<div><dt>Reads</dt><dd>One. Decryption and deletion share a transaction.</dd></div>'
        . '<div><dt>Accounts</dt><dd>None. No email, profile or history.</dd></div>'
        . '<div><dt>Scripts</dt><dd>First-party only. No third-party code or telemetry.</dd></div>'
        . '<div><dt>Abuse</dt><dd>Rate limits, per-note lockouts, CSRF tokens.</dd></div>'
        . '<div><dt>Runtime</dt><dd>PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or newer.</dd></div>'
        . '</dl></section>'
        . '<section class="cta">'
        . '<div class="cta-glow" aria-hidden="true"></div>'
        . '<h2>Write it once. Send it once.</h2>'
        . '<p>The passphrase goes one way, the link goes another. After the first read, there is '
        . 'nothing left to leak.</p>'
        . '<a class="btn btn-accent btn-lg" href="#compose">' . icon('send')
        . '<span class="btn-label">Write a note</span></a>'
        . '<p class="cta-fine">No account. No recovery. No second read.</p>'
        . '</section>'
        . '</div>';

    render('One-time encrypted notes', $content, 'encrypted one-time notes');
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
        . '<a href="#burn">One-time deletion</a>'
        . '<a href="#abuse">Abuse protections</a>'
        . '<a href="#runtime">Runtime policy</a>'
        . '<a href="#limits">What it cannot do</a></nav>'
        . '<article class="prose">'
        . '<section id="stored"><h2>What the server stores</h2><ul>'
        . '<li>An encrypted payload (XSalsa20-Poly1305), a random per-note salt and nonce.</li>'
        . '<li>Creation and expiry timestamps, and a note identifier.</li>'
        . '<li>A failed-attempt counter, used to lock a note after repeated guessing.</li>'
        . '</ul><p>It does not store your passphrase, a derived key, your note in plaintext, or '
        . 'your IP address. Rate limiting uses a keyed hash of the address, which cannot be reversed '
        . 'without the server key.</p></section>'
        . '<section id="kdf"><h2>Key derivation</h2>'
        . '<p>The passphrase is stretched with Argon2id (three iterations, 32 MiB) into a 32-byte '
        . 'key, which encrypts the note. The passphrase exists in memory only while a request is '
        . 'being handled and is never written to disk or to the log.</p></section>'
        . '<section id="burn"><h2>One-time deletion</h2>'
        . '<p>Deletion happens inside the same transaction that decrypts the note. A wrong '
        . 'passphrase does not destroy the note, so nobody can burn it by guessing. After five '
        . 'failed attempts a note is locked for fifteen minutes.</p></section>'
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

    render('Security model', $content, 'threat model');
}

function view_created(string $id, ?int $expires = null): void
{
    $path = '/note/' . $id;
    $expiry = $expires !== null
        ? '<dd data-countdown data-expires="' . e((string) $expires) . '">counting</dd>'
        : '<dd>on expiry</dd>';

    $content = '<div class="page"><div class="narrow">'
        . '<header class="state-head"><span class="state-icon">' . icon('circle-check') . '</span>'
        . '<p class="sec-label">Sealed</p>'
        . '<h1 class="page-title">Note sealed.</h1>'
        . '<p class="page-sub">The link opens once. Send the passphrase on a different channel; '
        . 'the note disappears after the first correct read.</p></header>'
        . '<div class="panel">'
        . '<div class="panel-head"><span class="panel-title">' . icon('lock', 'icon icon-sm')
        . 'Secret link</span><span class="chip">1 read</span></div>'
        . '<div class="panel-body">'
        . '<div class="linkrow"><label class="sr-only" for="note-link">Secret link</label>'
        . '<input class="input" id="note-link" type="text" readonly value="' . e($path) . '" data-absolutize>'
        . '<button type="button" class="btn btn-accent" data-copy-target="note-link">' . icon('copy')
        . '<span class="btn-label">Copy</span></button></div>'
        . '<dl class="tri">'
        . '<div><dt>' . icon('eye', 'icon icon-sm') . 'reads</dt><dd>1 of 1</dd></div>'
        . '<div><dt>' . icon('timer', 'icon icon-sm') . 'expires</dt>' . $expiry . '</div>'
        . '<div><dt>' . icon('shield-off', 'icon icon-sm') . 'recovery</dt>'
        . '<dd class="dim">none</dd></div>'
        . '</dl>'
        . '<div class="note-warn"><strong>Send the passphrase separately.</strong> Anyone with the '
        . 'link still needs it, and there is no reset.</div>'
        . '<div class="actions"><a class="btn btn-outline" href="/">Write another</a>'
        . '<a class="more-link" href="/security">Security model</a></div>'
        . '</div></div></div></div>';

    render('Note sealed', $content, 'one read only');
}

function view_reveal(string $id, ?string $error = null): void
{
    $alert = is_string($error) ? '<div class="alert" role="alert">' . e($error) . '</div>' : '';

    $content = '<div class="page"><div class="narrow">'
        . '<header class="state-head"><span class="state-icon">' . icon('key-round') . '</span>'
        . '<p class="sec-label">Sealed note</p>'
        . '<h1 class="page-title">Open a sealed note.</h1>'
        . '<p class="page-sub">A correct passphrase decrypts this note and deletes it from the '
        . 'server in the same request. There is no undo.</p></header>'
        . '<div class="panel">'
        . '<div class="panel-head"><span class="panel-title">' . icon('lock', 'icon icon-sm')
        . 'note / ' . e($id) . '</span><span class="chip chip-alert">1 read</span></div>'
        . '<div class="panel-body">' . $alert
        . '<form method="post" action="/note/' . e($id) . '" autocomplete="off">'
        . csrf_field()
        . '<label class="label" for="password">Passphrase</label>'
        . '<div class="pw-row">'
        . '<input class="input" id="password" type="password" name="password" maxlength="' . PASSWORD_MAX . '" '
        . 'required autofocus autocomplete="off" spellcheck="false">'
        . '<button type="button" class="text-btn" data-toggle-pw aria-pressed="false">Show</button>'
        . '</div>'
        . '<p class="hint">One attempt consumes the note.</p>'
        . '<button type="submit" class="btn btn-accent btn-block btn-open">' . icon('trash-2')
        . '<span class="btn-label">Open and destroy</span></button>'
        . '</form>'
        . '<ul class="rules">'
        . '<li>Decryption and deletion happen in one transaction.</li>'
        . '<li>A wrong passphrase does not destroy the note.</li>'
        . '<li>Five wrong attempts lock it for fifteen minutes.</li>'
        . '</ul></div></div></div></div>';

    render('Open a note', $content, 'one read only');
}

function view_note(string $body): void
{
    $content = '<div class="page"><div class="narrow">'
        . '<header class="state-head"><span class="state-icon">' . icon('check') . '</span>'
        . '<p class="sec-label label-alert">Destroyed on read</p>'
        . '<h1 class="page-title">Here it is, once.</h1>'
        . '<p class="page-sub">This note has been deleted from the server. Copy it now; refreshing '
        . 'will not bring it back.</p></header>'
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

    render('Note revealed', $content, 'gone from the server');
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

    render($title, $content, 'request failed');
}
