<?php
/*
 * Include guard.
 *
 * Loaded from several call sites, some of which use plain include.
 *
 * The declarations sit inside this conditional on purpose. PHP binds an
 * unconditional top-level function at compile time, so an early return would
 * not prevent the redeclaration. A function declared inside a conditional block
 * is bound at runtime instead, which is what makes the guard effective.
 */
if (!function_exists('hash_password')) {

// Include guard. See the note in config.php.
if (function_exists('hash_password')) { return; }
/*
 * Security helpers.
 *
 * The original application had no file like this. Password comparison happened
 * inline inside three separate SQL queries and nothing was ever logged, so an
 * authentication bypass left no trace at all. Collecting these concerns in one
 * place means the rules are stated once and every caller inherits them.
 */

require_once __DIR__ . '/connection.php';

/*
 * Write a security-relevant event to the application log.
 *
 * Deliberately never receives a password. The identifier is recorded so a
 * failed-login pattern can be traced to an account, but the secret itself is
 * not written anywhere. This addresses the Repudiation category of STRIDE,
 * which the original application could not satisfy at all.
 */
function log_security_event(string $event, string $identifier = '', array $extra = []): void
{
    $record = [
        'time'  => date('c'),
        'event' => $event,
        'ip'    => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        'id'    => $identifier,
    ] + $extra;

    error_log('[security] ' . json_encode($record));
}

/*
 * Hash a password for storage.
 *
 * PASSWORD_BCRYPT applies a random per-password salt automatically, so two
 * users who choose the same password still produce different stored values and
 * a precomputed rainbow table is useless. The cost factor controls how long a
 * single hash takes, which is what makes offline brute forcing expensive.
 */
function hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
}

/*
 * Detect a stored value that is not a hash.
 *
 * Every bcrypt digest produced by PHP begins with $2y$ and is 60 characters
 * long. Anything else in the password column is a legacy plaintext value left
 * over from the original schema.
 */
function is_legacy_plaintext(string $stored): bool
{
    return strncmp($stored, '$2y$', 4) !== 0;
}

/*
 * Verify a submitted password against whatever is currently stored, and upgrade
 * the stored value in place when it turns out to be legacy plaintext.
 *
 * This is the migration path promised in the proposal. A user with an
 * unconverted password logs in exactly as before, the plaintext branch matches
 * once, and the row is immediately rewritten as a hash. From the user's side
 * nothing changes; from the database's side the row is never plaintext again.
 *
 * hash_equals() is used for the legacy comparison rather than == so that the
 * comparison takes the same time regardless of how many leading characters
 * match, which removes a timing side channel. PHP's == would also perform type
 * juggling on numeric-looking strings, which is how "0e123" style values end up
 * comparing equal to each other.
 */
function password_update_sql(string $table): string
{
    /*
     * The table name cannot be a bound parameter, only values can. Rather than
     * interpolate it, the statement is selected from a fixed set. Nothing the
     * caller supplies ever reaches the query text.
     */
    switch ($table) {
        case 'employee':
            return 'UPDATE employee SET password = ? WHERE emp_id = ?';
        case 'customer':
            return 'UPDATE customer SET password = ? WHERE customer_id = ?';
    }

    throw new InvalidArgumentException('Unknown credential table: ' . $table);
}

function verify_and_upgrade_password(string $table, $idValue, string $submitted, string $stored): bool
{
    if (is_legacy_plaintext($stored)) {
        if (!hash_equals($stored, $submitted)) {
            return false;
        }

        // Correct plaintext password. Replace it with a hash before returning.
        db_query(password_update_sql($table), 'si', [hash_password($submitted), (int) $idValue]);
        log_security_event('password_upgraded_to_hash', (string) $idValue, ['table' => $table]);

        return true;
    }

    if (!password_verify($submitted, $stored)) {
        return false;
    }

    // Stored hash is valid but was produced with an older cost factor. Refresh it.
    if (password_needs_rehash($stored, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST])) {
        db_query(password_update_sql($table), 'si', [hash_password($submitted), (int) $idValue]);
    }

    return true;
}

/*
 * Establish an authenticated session.
 *
 * session_regenerate_id(true) issues a new session identifier and destroys the
 * old one. Without it, an identifier planted before login stays valid after
 * login, which is session fixation: the attacker sets the victim's session id,
 * waits for them to authenticate, then reuses that same id with the victim's
 * privileges. The original code never regenerated.
 */
function start_authenticated_session(string $role, array $account): void
{
    session_regenerate_id(true);

    $_SESSION = [];
    $_SESSION['role']          = $role;
    $_SESSION['last_activity'] = time();

    if ($role === 'admin') {
        $_SESSION['admin']  = $account['email'];
        $_SESSION['emp_id'] = (int) $account['emp_id'];
    } else {
        $_SESSION['customer'] = (int) $account['customer_id'];
    }
}


/*
 * Output encoding.
 *
 * Short name because it is used inline in templates, where a long name makes the
 * markup unreadable and encoding gets skipped as a result. ENT_QUOTES covers both
 * quote styles, so the value is safe inside an attribute as well as in element
 * text. Encoding happens at the point of output rather than on input, because the
 * same stored value may be written into HTML, into a URL, or into JSON, and each
 * needs a different escape.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
 * CSRF protection.
 *
 * The original application had none, which the Part 1 ZAP scan reported. Every
 * state-changing request is authorised purely by the session cookie, and a
 * browser attaches that cookie to any request to this origin regardless of which
 * page caused it. A page on another site could therefore submit a request that
 * the application would treat as a legitimate administrative action.
 *
 * The token is a per-session secret that an attacking page cannot read, because
 * the same-origin policy stops it reading this site's HTML. Requiring the token
 * on every state-changing request means an off-site form cannot produce a valid
 * one.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/*
 * Validate the token on an incoming request.
 *
 * Accepts it from the X-CSRF-Token header, which is what the front-end
 * JavaScript sends, or from a posted field for ordinary form submissions.
 * hash_equals() is used rather than === so the comparison does not leak
 * information through its timing.
 */
function verify_csrf_token(): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

    if (!is_string($sent) || $sent === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $sent);
}


/*
 * Secure image upload.
 *
 * The original handler took the filename the browser supplied, read the
 * extension off it, checked that extension against an allowlist, and wrote the
 * file into a web-served directory under its original name. Four faults follow
 * from that.
 *
 * The extension is attacker-controlled and says nothing about the contents. A
 * file of any type renamed to .png passed the check.
 *
 * svg was on the allowlist. SVG is XML, it may contain a <script> element, and
 * browsers execute it when the file is served as an image. Gallery images render
 * on the public home page, so one uploaded SVG runs script for every visitor.
 *
 * Keeping the original name lets an upload overwrite an existing file, and lets
 * the attacker choose a path that is predictable and therefore easy to request.
 *
 * Nothing checked the size, so a single request could fill the disk.
 *
 * The replacement verifies the file's real type by reading its contents, drops
 * svg from the permitted set, generates a random name, and caps the size.
 */
function store_uploaded_image(array $file, string $targetDir): ?string
{
    // A path under $_FILES that did not arrive through an HTTP upload is a sign
    // of tampering with the request.
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        log_security_event('upload_rejected_not_an_upload');
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        log_security_event('upload_rejected_error_code', '', ['code' => $file['error']]);
        return null;
    }

    if (($file['size'] ?? 0) > UPLOAD_MAX_BYTES || ($file['size'] ?? 0) === 0) {
        log_security_event('upload_rejected_size', '', ['size' => $file['size'] ?? 0]);
        return null;
    }

    /*
     * The type is read from the file's own bytes rather than from its name or
     * from the Content-Type header, both of which the client controls. svg is
     * absent from this map deliberately.
     */
    $permitted = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!isset($permitted[$mime])) {
        log_security_event('upload_rejected_mime', '', ['mime' => (string) $mime]);
        return null;
    }

    // Second check: the bytes have to parse as an actual image, not merely start
    // with a plausible magic number.
    if (@getimagesize($file['tmp_name']) === false) {
        log_security_event('upload_rejected_not_an_image', '', ['mime' => (string) $mime]);
        return null;
    }

    /*
     * A random name, with the extension decided by the verified type rather than
     * by anything the client sent. This removes the overwrite, removes the
     * path-traversal potential in the original name, and makes the stored
     * location unpredictable.
     */
    $name = bin2hex(random_bytes(16)) . '.' . $permitted[$mime];
    $dest = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $name;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        log_security_event('upload_failed_move');
        return null;
    }

    log_security_event('upload_accepted', '', ['stored_as' => $name, 'mime' => $mime]);
    return $name;
}

}
