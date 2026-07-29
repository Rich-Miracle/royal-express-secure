<?php
/*
 * Central authorisation.
 *
 * The original api.php routed twenty-one function codes to database functions
 * without ever asking who was calling. A request to
 * api.php?function_code=getCustomerTbleData returned the whole customer table,
 * passwords included, to anyone who typed the URL.
 *
 * The fix is a single check that every request passes through before any
 * function runs, and which denies the request unless the caller's role is
 * explicitly permitted. Deny by default matters here: adding a new function
 * code to api.php in future gives it administrative protection automatically,
 * because anything absent from the two lists below falls through to the
 * administrator requirement rather than to open access.
 */

if (!function_exists('require_authorisation')) {

require_once __DIR__ . '/security.php';

/*
 * Function codes that must work without a session.
 *
 * Every entry here is a deliberate decision, not an oversight. A blanket
 * "must be logged in" check on api.php would break the application, because
 * nobody holds a session at the moment they submit the login form or register
 * an account.
 */
function public_function_codes(): array
{
    return [
        'login',        // the login form itself
        'addCustomer',  // account registration
        'checkEmail',   // registration checks whether an address is already taken
        'addcontact',   // public contact form
        'checkArea',    // delivery area lookup used by the public pricing form
    ];
}

/*
 * Function codes a signed-in customer may use. Administrators may use them too.
 * Everything not listed here or above requires an administrator.
 */
function customer_function_codes(): array
{
    return [
        'addRequest',    // place a courier request
        'checkPassword', // verify own password before changing it
        'editQty',       // adjust a cart line
        'updateData',    // change own email or password; ownership is enforced
                         // inside updateDataTable(), which restricts a customer
                         // to their own row and refuses the employee table
    ];
}

/*
 * Expire a session that has been idle too long.
 *
 * An unattended browser left open on a shared machine keeps an administrative
 * session alive indefinitely in the original application, because nothing ever
 * expires it.
 */
function enforce_idle_timeout(): void
{
    if (!isset($_SESSION['last_activity'])) {
        return;
    }

    if (time() - $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        log_security_event('session_expired_idle', (string) ($_SESSION['admin'] ?? $_SESSION['customer'] ?? ''));
        $_SESSION = [];
        session_destroy();
        return;
    }

    $_SESSION['last_activity'] = time();
}

function current_role(): string
{
    return (string) ($_SESSION['role'] ?? '');
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

function is_authenticated(): bool
{
    return current_role() !== '';
}

/*
 * Deny the request and stop.
 *
 * 401 when nobody is signed in, 403 when someone is signed in but lacks the
 * role. The distinction is the correct one under HTTP and it gives the client
 * something meaningful to act on. The response body stays generic so it does
 * not confirm whether the function code exists.
 */
function deny(int $status, string $event, string $code): void
{
    log_security_event($event, current_role(), ['function_code' => $code]);
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['error' => $status === 401 ? 'Authentication required' : 'Not permitted']);
    exit;
}

/*
 * The single gate. Called once at the top of api.php, before any include has a
 * chance to act on the request.
 */
function require_authorisation(string $code): void
{
    enforce_idle_timeout();

    if (in_array($code, public_function_codes(), true)) {
        return;
    }

    if (!is_authenticated()) {
        deny(401, 'authz_denied_unauthenticated', $code);
    }

    /*
     * Beyond this point the caller holds a session, which is exactly the
     * condition a cross-site request forgery depends on. The browser attaches
     * the session cookie to a request no matter which page caused it, so the
     * session alone cannot show that the user intended the action. The token
     * can, because a page on another origin cannot read it.
     *
     * Public function codes are exempt: nobody holds a session at that point,
     * so there is no authority for an attacking page to borrow.
     */
    if (!verify_csrf_token()) {
        deny(403, 'csrf_token_invalid', $code);
    }

    if (in_array($code, customer_function_codes(), true)) {
        return; // any signed-in role may proceed
    }

    if (!is_admin()) {
        deny(403, 'authz_denied_insufficient_role', $code);
    }
}

/*
 * Ownership check for customer-scoped requests.
 *
 * Authorisation by role is not sufficient on its own. checkPassword accepts a
 * customer_id in the request body, so a signed-in customer could previously
 * submit somebody else's identifier and test passwords against that account.
 * That is an insecure direct object reference: the caller holds a valid session
 * but is acting on a record that is not theirs.
 */
function require_ownership(int $customerId): void
{
    if (is_admin()) {
        return;
    }

    if ((int) ($_SESSION['customer'] ?? 0) !== $customerId) {
        log_security_event('ownership_violation', (string) ($_SESSION['customer'] ?? ''), ['requested' => $customerId]);
        http_response_code(403);
        echo json_encode(['error' => 'Not permitted']);
        exit;
    }
}

/*
 * Guard for administrative pages, replacing Admin/checkAdmin.php.
 *
 * The original compared $_SESSION['admin'] against the literal string 'admin',
 * but the login routine stores an email address there, so the comparison was
 * false for every genuine administrator. It then issued a redirect without
 * calling exit, so PHP carried on and emitted the protected page anyway. The
 * page looked protected in a browser only because browsers follow redirects.
 * Anything that ignores them received the full response body.
 */
function require_admin_page(string $loginUrl = 'login.php'): void
{
    enforce_idle_timeout();

    if (!is_admin()) {
        log_security_event('admin_page_denied', current_role(), ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        header('Location: ' . $loginUrl);
        exit;  // the omission that made the original guard ineffective
    }
}

function require_customer_page(string $loginUrl = 'login.php'): void
{
    enforce_idle_timeout();

    if (!is_authenticated()) {
        header('Location: ' . $loginUrl);
        exit;
    }
}

}
