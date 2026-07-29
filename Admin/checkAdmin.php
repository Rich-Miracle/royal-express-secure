<?php
/*
 * Administrative page guard.
 *
 * BEFORE
 * The original compared the admin session key against a hardcoded role string
 * and issued a Location redirect when the comparison failed.
 *
 * Two faults in four lines.
 *
 * The comparison was wrong. getLoginAdmin() stores an email address in
 * $_SESSION['admin'], so for a real administrator the condition was true and
 * the redirect fired on every request. The guard never authorised anybody.
 *
 * The redirect did not stop execution. PHP sends the Location header and then
 * carries on, so the protected page was still rendered and sent. A browser
 * follows the redirect and hides this, which is why the fault went unnoticed,
 * but curl or an intercepting proxy receives the full page body.
 *
 * AFTER
 * The role is read from $_SESSION['role'], which the login routine now sets
 * explicitly, and the request halts on failure.
 */

require_once __DIR__ . '/../server/inc/config.php';

if (session_id() == '') {
    session_start();
}

require_once __DIR__ . '/../server/inc/authz.php';

require_admin_page('login.php');
