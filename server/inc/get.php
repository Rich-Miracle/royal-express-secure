<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/security.php';


function getAllBranch()
{
    $con = db();

    $viewcat = "SELECT * FROM branch WHERE is_deleted = 0";
    return mysqli_query($con, $viewcat);
}
function getAllArea()
{
    $con = db();

    $viewcat = "SELECT * FROM area WHERE is_deleted = 0";
    return mysqli_query($con, $viewcat);
}
function getAllAreabyID($area_id)
{
    $con = db();

    $viewcat = "SELECT * FROM area WHERE is_deleted = 0 AND area_id = '$area_id'";
    return mysqli_query($con, $viewcat);
}
function getAllPrice()
{
    $con = db();

    $viewcat = "SELECT * FROM price_table WHERE is_deleted = 0";
    return mysqli_query($con, $viewcat);
}

function checkPrice($start_area, $end_area)
{
    $con = db();

    $viewcat = "SELECT * FROM price_table WHERE is_deleted = 0 AND start_area = '$start_area' AND end_area = '$end_area'";
    return mysqli_num_rows(mysqli_query($con, $viewcat));
}

function getBille($customer_id)
{
    $con = db();

    $q1 = "SELECT * FROM request join customer on customer.customer_id = request.customer_id WHERE request.customer_id = '$customer_id' ";
    return mysqli_query($con, $q1);
}

//product

function getAllemployee()
{
    $con = db();

    $q1 = "SELECT * FROM employee WHERE is_deleted = 0 AND email != 'admin'";
    return mysqli_query($con, $q1);
}

function getemployeeByID($emp_id)
{
    $con = db();

    $q1 = "SELECT * FROM employee WHERE is_deleted = 0 AND emp_id = '$emp_id'";
    return mysqli_query($con, $q1);
}

function getemployeeByEmail($email)
{
    $con = db();

    $q1 = "SELECT * FROM employee WHERE is_deleted = 0 AND email = '$email'";
    return mysqli_query($con, $q1);
}

function getBranchByID($branch_id)
{
    $con = db();

    $q1 = "SELECT * FROM branch WHERE is_deleted = 0 AND branch_id = '$branch_id'";
    return mysqli_query($con, $q1);
}

function getAllTrackingByCUS($customer_id)
{
    $con = db();

    $viewcat = "SELECT * FROM request WHERE is_deleted = 0 AND customer_id = '$customer_id' ORDER BY date_updated DESC";
    return mysqli_query($con, $viewcat);
}

function getAllTracking()
{
    $con = db();

    $viewcat = "SELECT * FROM request join customer on customer.customer_id = request.customer_id WHERE request.is_deleted = 0 ORDER BY date_updated DESC";
    return mysqli_query($con, $viewcat);
}

function checkemployeetByEmail($email)
{
    $con = db();

    $employee = "SELECT * FROM employee WHERE email = '$email' AND is_deleted = 0";
    $result = mysqli_query($con, $employee);

    $customer = "SELECT * FROM customer WHERE email = '$email' AND is_deleted = 0";
    $cus_res = mysqli_query($con, $customer);

    if (mysqli_num_rows($result) > 0) {
        return mysqli_num_rows($result);
    } else if (mysqli_num_rows($cus_res) > 0) {
        return mysqli_num_rows($cus_res);
    } else {
        return 0;
    }
}

function getAllgalleryImages()
{
    $con = db();

    $q1 = "SELECT * FROM gallery";
    return mysqli_query($con, $q1);
}

//customer


function checkuserPassword($data)
{
    $customer_id = (int) ($data['customer_id'] ?? 0);
    $password    = (string) ($data['password'] ?? '');

    if ($customer_id <= 0 || $password === '') {
        echo '0';
        return;
    }

    $row = db_select_one(
        "SELECT customer_id, password FROM customer WHERE customer_id = ? AND is_deleted = 0",
        'i',
        [$customer_id]
    );

    $ok = $row !== null
        && verify_and_upgrade_password('customer', $customer_id, $password, $row['password']);

    if (!$ok) {
        log_security_event('password_check_failed', (string) $customer_id);
    }

    // Caller expects a row count. Preserved so the existing JavaScript still works.
    echo $ok ? '1' : '0';
}

function checkArea($data)
{
    /*
     * Reachable without a session, because the public pricing form has to work
     * before anyone logs in. That makes it one of the few entry points an
     * unauthenticated attacker can reach, so the parameterisation here matters
     * more than it does on an administrator-only function.
     *
     * The area identifiers are integers in the schema, so they are cast and
     * bound as integers. A value that is not numeric becomes 0 and matches
     * nothing, which is the correct outcome for a malformed request.
     */
    $start_area = (int) ($data['send_location'] ?? 0);
    $end_area   = (int) ($data['end_location'] ?? 0);

    $row = db_select_one(
        "SELECT price FROM price_table WHERE is_deleted = 0 AND start_area = ? AND end_area = ?",
        'ii',
        [$start_area, $end_area]
    );

    // The original echoed $row['price'] without checking the row existed, so an
    // undefined route and a database failure produced the same empty response.
    echo $row === null ? '' : $row['price'];
}

function checkAreaByName($area_name)
{
    return db_select(
        "SELECT area_id FROM area WHERE area_name = ? AND is_deleted = 0",
        's',
        [(string) $area_name]
    )->num_rows;
}

function checkUserEmail($data)
{
    $email       = trim((string) ($data['email'] ?? ''));
    $customer_id = (int) ($data['customer_id'] ?? 0);

    $count = db_select(
        "SELECT customer_id FROM customer WHERE is_deleted = 0 AND email = ? AND customer_id = ?",
        'si',
        [$email, $customer_id]
    )->num_rows;

    echo $count;
}

function getAllcustomerById($customer_id)
{
    return db_select(
        "SELECT * FROM customer WHERE is_deleted = 0 AND customer_id = ?",
        'i',
        [(int) $customer_id]
    );
}

function getAllcustomers()
{
    $con = db();

    $q1 = "SELECT * FROM customer WHERE is_deleted = 0 AND email != 'admin'";
    return mysqli_query($con, $q1);
}

function getLoginAdmin($data)
{
    $email    = trim((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($email === '' || $password === '') {
        log_security_event('login_rejected_empty_field');
        echo '';
        return;
    }

    /*
     * The password can no longer take part in the lookup. A bcrypt digest is
     * salted, so the stored value never equals the submitted value and an
     * equality test in SQL would match nothing. The row is therefore selected
     * on the identifier alone and the secret is checked afterwards in PHP.
     *
     * is_deleted = 0 is now part of the condition. The original query omitted
     * it, so an employee who had been removed through the admin interface could
     * still authenticate.
     */
    $account = db_select_one(
        "SELECT emp_id, email, password FROM employee WHERE email = ? AND is_deleted = 0",
        's',
        [$email]
    );
    $role  = 'admin';
    $table = 'employee';
    $idCol = 'emp_id';

    if ($account === null) {
        $account = db_select_one(
            "SELECT customer_id, email, password FROM customer WHERE email = ? AND is_deleted = 0",
            's',
            [$email]
        );
        $role  = 'customer';
        $table = 'customer';
        $idCol = 'customer_id';
    }

    if ($account === null
        || !verify_and_upgrade_password($table, $account[$idCol], $password, $account['password'])) {
        /*
         * One branch for "no such account" and "wrong password" on purpose.
         * Reporting them separately lets an attacker enumerate valid addresses
         * without ever guessing a password.
         */
        log_security_event('login_failed', $email);
        echo '';
        return;
    }

    start_authenticated_session($role, $account);
    log_security_event('login_success', $email, ['role' => $role]);

    echo $role;
}

function checkemployee($email)
{
    $con = db();

    $q1 = "SELECT * FROM employee WHERE email='$email' AND is_deleted='0'";
    return mysqli_query($con, $q1);
}

function checkCustomerByEmail($email)
{
    $con = db();

    $q1 = "SELECT * FROM customer WHERE email='$email' AND is_deleted='0'";
    return mysqli_query($con, $q1);
}


function checkCustomerByID($customer_id)
{
    $con = db();

    $q1 = "SELECT * FROM customer WHERE customer_id='$customer_id' AND is_deleted = '0'";
    return mysqli_query($con, $q1);
}

function getAllCustomer()
{
    $con = db();

    $q1 = "SELECT * FROM customer WHERE is_deleted = '0' AND email != 'admin'";
    $table = mysqli_query($con, $q1);
    $columns = mysqli_fetch_all($table, MYSQLI_ASSOC);

    return $columns;
}


//contact

function getAllMessages()
{
    $con = db();

    $messages = "SELECT * FROM contact";
    return mysqli_query($con, $messages);
}

//count

function dataCount($table)
{
    $con = db();

    $counts = "SELECT * FROM $table WHERE is_deleted = 0";
    $res =  mysqli_query($con, $counts);
    $count =  mysqli_num_rows($res);
    echo $count;
}

function dataCountWhere($table, $where)
{
    $con = db();

    $counts = "SELECT * FROM $table WHERE $where AND is_deleted = 0";
    $res =  mysqli_query($con, $counts);
    $count =  mysqli_num_rows($res);
    echo $count;
}

function dataforCount($table)
{
    $con = db();

    $counts = "SELECT sum(total) as sum FROM $table WHERE is_deleted = 0";
    return mysqli_query($con, $counts);
}

function dataforCountToday($table)
{
    $con = db();

    $counts = "SELECT sum(total) as sum FROM $table WHERE month(now()) = month(date_updated) AND is_deleted = 0s";
    return mysqli_query($con, $counts);
}


//settings

function getAllSettings()
{
    $con = db();

    $settings = "SELECT * FROM settings";
    return mysqli_query($con, $settings);
}

function checkPasswordByName($data)
{
    $email    = trim((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($email === '' || $password === '') {
        echo '0';
        return;
    }

    $row = db_select_one(
        "SELECT emp_id, password FROM employee WHERE email = ? AND is_deleted = 0",
        's',
        [$email]
    );

    $ok = $row !== null
        && verify_and_upgrade_password('employee', $row['emp_id'], $password, $row['password']);

    if (!$ok) {
        log_security_event('password_check_failed', $email);
    }

    echo $ok ? '1' : '0';
}

function getAllCart($customer_id)
{
    $con = db();

    $q1 = "SELECT * FROM cart join products on products.pid = cart.pid join customer on customer.customer_id = cart.customer_id WHERE cart.customer_id = '$customer_id'";
    return mysqli_query($con, $q1);
}


function getAllOrdersByCustomer($customer_id)
{
    $con = db();

    $viewcat = "SELECT * FROM product_orders WHERE customer_id = '$customer_id' AND is_deleted = '0' ORDER BY date_updated DESC";
    return mysqli_query($con, $viewcat);
}

function getAllOrderItemsBYOrder($order_id)
{
    $con = db();

    $viewcat = "SELECT * FROM order_items join products on order_items.pid = products.pid WHERE order_items.order_id = '$order_id'";
    return mysqli_query($con, $viewcat);
}

function getAllOrders()
{
    $con = db();

    $viewcat = "SELECT * FROM product_orders join customer on customer.customer_id = product_orders.customer_id  WHERE product_orders.is_deleted = '0' ORDER BY date_updated DESC";
    return mysqli_query($con, $viewcat);
}

function getAllOrdersPending()
{
    $con = db();

    $viewcat = "SELECT * FROM product_orders join customer on customer.customer_id = product_orders.customer_id  WHERE product_orders.is_deleted = '0' AND product_orders.order_status = '1' ORDER BY date_updated DESC";
    return mysqli_query($con, $viewcat);
}

function getAllOrderItems($order_id)
{
    $con = db();

    $viewcat = "SELECT * FROM order_items join products on order_items.pid = products.pid WHERE order_items.order_id = '$order_id'";
    return mysqli_query($con, $viewcat);
    
}

function demoRegression($data){
    $con = db();
    $email = $data['email'];
    $password = $data['password'];
    $sql = "SELECT * FROM employee WHERE email = '$email' AND password = '$password'";
    return mysqli_query($con, $sql);
}
