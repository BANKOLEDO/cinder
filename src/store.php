<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = APP_DATA_DIR . '/notes.sqlite';
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA synchronous=NORMAL');
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notes (
            id TEXT PRIMARY KEY,
            salt BLOB NOT NULL,
            nonce BLOB NOT NULL,
            ciphertext BLOB NOT NULL,
            created_at INTEGER NOT NULL,
            expires_at INTEGER NOT NULL,
            fail_count INTEGER NOT NULL DEFAULT 0,
            locked_until INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notes_expires ON notes(expires_at)');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rate_limits (
            bucket TEXT PRIMARY KEY,
            window_start INTEGER NOT NULL,
            count INTEGER NOT NULL
        )'
    );
    @chmod($path, 0600);
    return $pdo;
}

function gc_notes(): void
{
    $pdo = db();
    $now = time();
    $pdo->prepare('DELETE FROM notes WHERE expires_at <= ?')->execute([$now]);
    $pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$now - 86400]);
}

function rate_limit(string $kind, int $max): bool
{
    $bucket = ip_bucket($kind);
    $now = time();
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $statement = $pdo->prepare('SELECT window_start, count FROM rate_limits WHERE bucket = ?');
        $statement->execute([$bucket]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            $pdo->prepare('INSERT INTO rate_limits (bucket, window_start, count) VALUES (?, ?, 1)')
                ->execute([$bucket, $now]);
        } elseif ($now - (int) $row['window_start'] >= RATE_WINDOW) {
            $pdo->prepare('UPDATE rate_limits SET window_start = ?, count = 1 WHERE bucket = ?')
                ->execute([$now, $bucket]);
        } elseif ((int) $row['count'] >= $max) {
            $pdo->exec('COMMIT');
            return false;
        } else {
            $pdo->prepare('UPDATE rate_limits SET count = count + 1 WHERE bucket = ?')
                ->execute([$bucket]);
        }
        $pdo->exec('COMMIT');
        return true;
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

function derive_note_key(string $password, string $salt): string
{
    return sodium_crypto_pwhash(
        SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
        $password,
        $salt,
        ARGON_OPS,
        ARGON_MEM,
        SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
    );
}

function create_note(string $body, string $password, int $ttl): string
{
    $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $key = derive_note_key($password, $salt);
    try {
        $ciphertext = sodium_crypto_secretbox($body, $nonce, $key);
    } finally {
        sodium_memzero($key);
    }

    $pdo = db();
    $now = time();
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $id = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
        try {
            $pdo->prepare(
                'INSERT INTO notes (id, salt, nonce, ciphertext, created_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$id, $salt, $nonce, $ciphertext, $now, $now + $ttl]);
            return $id;
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
        }
    }
    throw new RuntimeException('Could not allocate a note id.');
}

function note_available(string $id): bool
{
    $statement = db()->prepare('SELECT 1 FROM notes WHERE id = ? AND expires_at > ?');
    $statement->execute([$id, time()]);
    return $statement->fetchColumn() !== false;
}

function reveal_note(string $id, string $password): array
{
    $pdo = db();
    $now = time();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $statement = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
        $statement->execute([$id]);
        $note = $statement->fetch();

        if (!is_array($note) || (int) $note['expires_at'] <= $now) {
            if (is_array($note)) {
                $pdo->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]);
            }
            $pdo->exec('COMMIT');
            return ['status' => 'gone'];
        }

        if ((int) $note['locked_until'] > $now) {
            $pdo->exec('COMMIT');
            return ['status' => 'bad'];
        }

        $key = derive_note_key($password, $note['salt']);
        $plain = sodium_crypto_secretbox_open($note['ciphertext'], $note['nonce'], $key);
        sodium_memzero($key);

        if (!is_string($plain)) {
            $fails = (int) $note['fail_count'] + 1;
            $lockedUntil = $fails >= FAIL_LIMIT ? $now + LOCK_SECONDS : 0;
            $pdo->prepare('UPDATE notes SET fail_count = ?, locked_until = ? WHERE id = ?')
                ->execute([$fails, $lockedUntil, $id]);
            $pdo->exec('COMMIT');
            return ['status' => 'bad'];
        }

        $pdo->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]);
        $pdo->exec('COMMIT');
        return ['status' => 'ok', 'body' => $plain];
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}
