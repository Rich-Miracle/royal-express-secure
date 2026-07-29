<?php
/*
 * Include guard. api.php uses plain include rather than include_once, so this
 * file can be pulled in more than once during a single request. Returning early
 * on the second pass keeps the constants from being redefined.
 */
if (defined('DB_HOST')) { return; }
/*
 * Application configuration.
 *
 * WHY THIS FILE EXISTS
 * The original connection.php carried the database host, user and password as
 * literals on a single line inside the web root. Anyone who obtained the source,
 * or who triggered a condition that caused PHP to serve the file as plain text,
 * obtained the database credentials. Separating configuration from code lets the
 * credentials be supplied by the environment instead of being committed.
 *
 * The getenv() fallbacks keep the application runnable on a stock XAMPP install
 * without extra setup. In a production deployment this file would sit outside
 * the document root entirely and the fallbacks would be removed.
 */

define('DB_HOST', getenv('RE_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('RE_DB_USER') ?: 'root');
define('DB_PASS', getenv('RE_DB_PASS') ?: '');
define('DB_NAME', getenv('RE_DB_NAME') ?: 'royal_express_db');

/*
 * Error handling.
 *
 * Displaying PHP or MySQL errors to the browser tells an attacker the query
 * structure, the table names and the file paths. Errors are written to a log
 * outside the browser's reach instead, and the user receives a generic message.
 */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/app_error.log');
error_reporting(E_ALL);

/*
 * Session cookie hardening. Must run before session_start().
 *
 * HttpOnly stops JavaScript reading the session identifier, which limits what a
 * successful XSS payload can steal. SameSite=Lax stops the cookie riding along
 * on cross-site requests, which blunts CSRF. Secure is left conditional because
 * the assessment environment runs over plain HTTP on localhost; over HTTPS it
 * would be set unconditionally.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']),
    ]);
    ini_set('session.use_strict_mode', '1');
}

// Idle session timeout, in seconds.
define('SESSION_IDLE_TIMEOUT', 1800);

// Cost factor for password hashing. Higher is slower to brute force.
define('PASSWORD_COST', 12);
