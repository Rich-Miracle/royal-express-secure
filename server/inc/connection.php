<?php
/*
 * Include guard.
 *
 * api.php pulls this file in with a plain include, not include_once, so it can
 * be loaded more than once in a single request.
 *
 * The declarations sit inside this conditional on purpose. PHP binds an
 * unconditional top-level function at compile time, so an early return would
 * not prevent the redeclaration. A function declared inside a conditional block
 * is bound at runtime instead, which is what makes the guard effective.
 */
if (!function_exists('db')) {

/*
 * Database connection.
 *
 * BEFORE
 *   <?php $con = mysqli_connect("localhost","root","","royal_express_db")
 *              or die("Database Connection Fail"); ?>
 *
 * Three problems. The credentials were literals in the web root. The or die()
 * printed a message straight to the browser on failure. And because every
 * function in get.php, add.php, update.php and delete.php did
 * include 'connection.php' inside its own body, a fresh TCP connection was
 * opened for every function call, so a single page could open dozens.
 *
 * AFTER
 * A single connection is created on first use and reused thereafter. Callers
 * ask for it with $con = db(); which is a one-line substitution for the old
 * include and keeps the diff in the other files minimal.
 *
 * mysqli_report() is set so driver errors raise exceptions instead of being
 * silently ignored. Prepared statements below rely on this: if a bind or an
 * execute fails, it fails loudly into the log rather than quietly returning
 * false and letting the caller treat it as an empty result set.
 */

require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $con = null;

    if ($con === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $con = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $con->set_charset('utf8mb4');
        } catch (Throwable $e) {
            // Detail to the log, nothing useful to the client.
            error_log('[db] connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('A server error occurred. Please try again later.');
        }
    }

    return $con;
}

/*
 * Helper used by every rewritten query in this codebase.
 *
 * $sql    holds only the query structure, with ? placeholders for values.
 * $types  is the mysqli type string, for example 'si' for string then integer.
 * $params are the values, sent to the server separately from the structure.
 *
 * Because structure and values travel on different paths, a value can never be
 * reinterpreted as SQL syntax. A payload such as ' OR '1'='1' -- is compared as
 * a literal string and matches nothing.
 */
function db_query(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $con  = db();
    $stmt = $con->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    return $stmt;
}

// Convenience wrapper: run a prepared SELECT and return the result set.
function db_select(string $sql, string $types = '', array $params = []): mysqli_result
{
    return db_query($sql, $types, $params)->get_result();
}

// Convenience wrapper: run a prepared SELECT and return the first row or null.
function db_select_one(string $sql, string $types = '', array $params = []): ?array
{
    $row = db_select($sql, $types, $params)->fetch_assoc();
    return $row ?: null;
}

}
