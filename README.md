# Cinder

Cinder is a small encrypted note service written in plain PHP. You write a note, choose
a passphrase, pick an expiry between one hour and thirty days, and set how many reads the
link allows (1, 2, 3, 5, or 10). The note is encrypted with a key derived from the
passphrase, and it is deleted after the last allowed read.

The design goal is a small, auditable surface: no accounts, no client-side encryption,
no third-party scripts, and no plaintext on disk.

## How it works

1. The browser posts the note, passphrase, expiry, and read limit to `POST /create` over
   HTTPS.
2. The server derives a 32-byte key with Argon2id (3 passes, 32 MiB, a random 16-byte
   salt per note) and encrypts the body with XSalsa20-Poly1305 and a random 24-byte
   nonce.
3. Only the id, salt, nonce, ciphertext, timestamps, read limit, read count, and failure
   counters are stored in SQLite. The plaintext is discarded after the response.
4. Opening the link shows a passphrase form. A correct passphrase decrypts the note and
   increments the read count in the same SQLite transaction. When the count reaches the
   limit, the note is deleted.
5. A wrong passphrase does not use a read or consume the note. Five failures lock that
   note for fifteen minutes. Expired notes are removed by opportunistic garbage
   collection.

Because the passphrase is processed by the server, this is server-side encryption, not
end-to-end encryption. The passphrase and plaintext exist in server memory during a
request, so TLS is mandatory in production and the operator is trusted with the running
process. What the operator cannot recover later is the plaintext at rest.

## Requirements

- A patched PHP build: 8.2.35, 8.3.36, 8.4.27, 8.5.12, or newer. The app refuses to
  serve on builds affected by the PHP2Shell flaws (CVE-2026-109618 and CVE-2026-109619).
- PHP extensions: `sodium`, `pdo_sqlite`, and `mbstring`.
- HTTPS in any deployment that is not local.

## Quick start (Windows)

The included scripts install a pinned, checksum-verified PHP 8.4.27 build into
`%LOCALAPPDATA%\Cinder` and start the built-in server.

```powershell
.\run.ps1
```

Then open `http://127.0.0.1:8099`.

To use your own PHP build:

```powershell
php -c php.ini -d extension=pdo_sqlite -d extension=sodium -d extension=mbstring `
  -S 127.0.0.1:8099 -t public router.php
```

## Production notes

- Point the document root at `public/` and keep `src/`, `data/`, `router.php`, and the
  setup scripts outside it.
- Serve over HTTPS. Set `APP_HTTPS=1` when TLS terminates in front of PHP so session
  cookies are marked secure.
- Make `data/` writable by the PHP user and readable by nobody else. The app creates
  `notes.sqlite`, `app.key`, and `php-error.log` there.
- `app.key` is generated on first use and is used only to key HMAC buckets for rate
  limiting. It is not a note encryption key.
- `public/.htaccess` adds directory protection and the front-controller rewrite for
  Apache. For nginx, route missing files to `public/index.php` yourself.

## Configuration

Constants live in `src/bootstrap.php`.

| Constant | Default | Purpose |
| --- | --- | --- |
| `NOTE_MAX_BYTES` | 32768 | Maximum note size |
| `PASSWORD_MIN` / `PASSWORD_MAX` | 10 / 1024 | Passphrase bounds |
| `NOTE_TTLS` | 1 hour to 30 days | Allowed expiries |
| `NOTE_READS` | 1, 2, 3, 5, 10 | Allowed read limits |
| `ARGON_OPS` / `ARGON_MEM` | 3 / 32 MiB | Argon2id cost |
| `FAIL_LIMIT` / `LOCK_SECONDS` | 5 / 900 | Per-note lockout |
| `RATE_WINDOW` | 600 | Rate-limit window in seconds |
| `RATE_CREATE_MAX` / `RATE_REVEAL_MAX` | 20 / 20 | Requests per window per address |

Environment variables:

- `APP_DATA_DIR` overrides the data directory (default: `data/`).
- `APP_HTTPS=1` marks cookies secure when HTTPS is terminated upstream.
- `APP_URL` pins absolute URLs (canonical, Open Graph, sitemap, robots) to a fixed
  origin, for example `https://notes.example.com`. Set this in production so a forged
  `Host` header cannot change the origin the app advertises.

## Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/` | Landing page and composer |
| GET | `/new` | Redirect to the composer |
| GET | `/security` | Security model |
| GET | `/history` | Notes created in this browser session |
| POST | `/history` | Forget the session history |
| POST | `/create` | Encrypt and store a note |
| GET | `/created/{id}` | Creator's one-hour confirmation page |
| GET | `/note/{id}` | Passphrase form |
| POST | `/note/{id}` | Decrypt and count a read |
| GET | `/robots.txt` | Crawler policy |
| GET | `/sitemap.xml` | Public page sitemap |
| GET | `/site.webmanifest` | Web app manifest |
| GET | `/healthz` | Database health check |

## Project layout

```
public/            document root, front controller, assets
  assets/          CSS, JS, icon sprite, self-hosted fonts
src/bootstrap.php  constants, sessions, CSP, helpers
src/store.php      SQLite schema, encryption, rate limits, reveal
src/views.php      HTML views and the shared shell
router.php         static-file router for the built-in server
php.ini            hardened runtime configuration
run.ps1            local server launcher
setup-php.ps1      pinned PHP download and checksum verification
data/              runtime only, ignored by git
```

## Verification

There is no separate test suite in the repository. The checks used during development
were syntax checks plus end-to-end requests against a running instance:

```powershell
php -l src/bootstrap.php
php -l src/store.php
php -l src/views.php
php -l public/index.php
node --check public/assets/app.js
```

Create a note with a read limit of two, open it once with the wrong passphrase, then
twice with the right one. The first correct read reports one read left; the second
deletes the note, and a further request returns 404. `/history` lists links created in
the same session, and `/robots.txt`, `/sitemap.xml`, and `/site.webmanifest` each return
their own content type.

## Data and secrets

`data/` is ignored by git, including `app.key`, `notes.sqlite`, WAL files, and logs.
No credentials or environment files are part of this repository. If you deploy this,
keep the data directory out of backups you do not control, and treat the database as
ciphertext only.

## Credits

- Icons: [Lucide](https://lucide.dev), ISC license. See
  `public/assets/icons.LICENSE.txt`.
- Fonts: IBM Plex Sans, IBM Plex Mono, and IBM Plex Serif, SIL Open Font License 1.1.
  See `public/assets/fonts/LICENSE.txt`.

## Limitations

This project has not been independently audited. It protects notes at rest and limits
brute force, but it cannot protect against a compromised host, a malicious operator, or
a passphrase shared over the same channel as the link. A read limit is a count, not a
guarantee that no recipient copied the plaintext. Use a separate channel for the
passphrase, and do not use this for data whose disclosure would be catastrophic.
