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
function verify_and_upgrade_password(string $table, string $idColumn, $idValue, string $submitted, string $stored): bool
{
    if (is_legacy_plaintext($stored)) {
        if (!hash_equals($stored, $submitted)) {
            return false;
        }

        // Correct plaintext password. Replace it with a hash before returning.
        $sql = "UPDATE `$table` SET password = ? WHERE `$idColumn` = ?";
        db_query($sql, 'si', [hash_password($submitted), (int) $idValue]);
        log_security_event('password_upgraded_to_hash', (string) $idValue, ['table' => $table]);

        return true;
    }

    if (!password_verify($submitted, $stored)) {
        return false;
    }

    // Stored hash is valid but was produced with an older cost factor. Refresh it.
    if (password_needs_rehash($stored, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST])) {
        $sql = "UPDATE `$table` SET password = ? WHERE `$idColumn` = ?";
        db_query($sql, 'si', [hash_password($submitted), (int) $idValue]);
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

}
