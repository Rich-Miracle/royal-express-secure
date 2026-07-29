<?php
/*
 * One-off password migration.
 *
 * Run once from the command line:
 *     php tools/migrate_passwords.php
 *
 * The proposal committed to rehashing each password at the user's next
 * successful login, and that path is implemented in
 * security.php::verify_and_upgrade_password(). It has one drawback for an
 * assessment: rows only convert as users log in, so the password column stays
 * visibly plaintext for any account nobody touches, and the evidence screenshot
 * shows a half-converted table.
 *
 * This script converts everything in one pass so the before and after states of
 * the table are unambiguous. Both mechanisms are kept: the script handles the
 * accounts that exist today, the login path handles anything it misses.
 *
 * The script is CLI-only. Serving it over HTTP would hand an unauthenticated
 * visitor a way to rewrite every credential in the database.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../server/inc/security.php';

$targets = [
    ['table' => 'employee', 'select' => 'SELECT emp_id AS id, password FROM employee'],
    ['table' => 'customer', 'select' => 'SELECT customer_id AS id, password FROM customer'],
];

$converted = 0;
$skipped   = 0;

foreach ($targets as $t) {
    $rows = db_select($t['select']);

    while ($row = $rows->fetch_assoc()) {
        if (!is_legacy_plaintext((string) $row['password'])) {
            $skipped++;
            continue;
        }

        db_query(
            password_update_sql($t['table']),
            'si',
            [hash_password((string) $row['password']), (int) $row['id']]
        );

        printf("  %-9s id=%-4d converted%s", $t['table'], $row['id'], PHP_EOL);
        $converted++;
    }
}

printf("%sDone. %d converted, %d already hashed.%s", PHP_EOL, $converted, $skipped, PHP_EOL);
