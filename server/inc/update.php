<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/authz.php';
function updateDataTable($data)
{
    /*
     * This was the most dangerous function in the application.
     *
     * The table name, the column name, the value, the key column and the key
     * were all taken from the request body and interpolated into the statement.
     * Any caller who reached it could write any column of any table to any
     * value. Setting an administrator's password to a chosen string was a single
     * request. It also defeated the credential fix, because the customer profile
     * page changes passwords through here and wrote them as typed.
     *
     * Identifiers cannot be bound as parameters; only values can. So the table
     * and column names are checked against an allowlist rather than escaped.
     * The list holds the three combinations the front end actually uses. Anything
     * else is refused, which means a new use has to be added deliberately instead
     * of arriving by accident.
     */
    $table   = (string) ($data['table'] ?? '');
    $field   = (string) ($data['field'] ?? '');
    $idField = (string) ($data['id_fild'] ?? '');
    $id      = (string) ($data['id'] ?? '');
    $value   = (string) ($data['value'] ?? '');

    $permitted = [
        'customer' => [
            'keys'   => ['customer_id'],
            'fields' => ['email', 'password', 'name', 'phone', 'address'],
        ],
        'employee' => [
            'keys'   => ['email', 'emp_id'],
            'fields' => ['password', 'name', 'phone', 'address'],
        ],
    ];

    if (!isset($permitted[$table])
        || !in_array($field, $permitted[$table]['fields'], true)
        || !in_array($idField, $permitted[$table]['keys'], true)) {
        log_security_event('update_rejected_not_permitted', '', [
            'table' => $table, 'field' => $field, 'key' => $idField,
        ]);
        return false;
    }

    /*
     * Role alone is not enough. A signed-in customer may only alter their own
     * customer row, never another customer's and never an employee's. Without
     * this an authenticated customer could change any account's password.
     */
    if (!is_admin()) {
        if ($table !== 'customer' || (int) $id !== (int) ($_SESSION['customer'] ?? 0)) {
            log_security_event('update_rejected_ownership', (string) ($_SESSION['customer'] ?? ''), [
                'table' => $table, 'requested' => $id,
            ]);
            return false;
        }
    }

    // A password written through this path has to be hashed like any other.
    if ($field === 'password') {
        if (strlen($value) < 8) {
            log_security_event('password_change_rejected_too_short');
            return false;
        }
        $value = hash_password($value);
    }

    if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        log_security_event('update_rejected_invalid_email');
        return false;
    }

    // Identifiers are now fixed strings from the allowlist above, never input.
    $sql = "UPDATE `$table` SET `$field` = ? WHERE `$idField` = ?";
    return db_query($sql, 'ss', [$value, $id])->affected_rows >= 0;
}


function updateSubCatData($data)
{
    $con = db();

    $id_fild = $data['id_fild'];
    $id = $data['id'];
    $field = $data['field'];
    $value = $data['value'];
    $table = $data['table'];

    $getdatas = getAllSubCategory($id);
    $count = mysqli_num_rows($getdatas);

    if ($count > 0) {
        echo $count;
    }
    else {
        $sql = "UPDATE $table SET $field = '$value' where $id_fild = '$id'";
        return mysqli_query($con, $sql);
    }
}

function editImages($data, $img)
{
    $con = db();

    $id_fild = $data['id_fild'];
    $id = $data['id'];
    $field = $data['field'];
    $table = $data['table'];

    $sql = "UPDATE $table SET $field = '$img' where $id_fild = '$id'";
    return mysqli_query($con, $sql);
}

//qty reduce code

function productQtyReduce($pid, $qty)
{
    $con = db();

    $viewProducts = "SELECT * FROM products WHERE pid = '$pid'";
    $res = mysqli_query($con, $viewProducts);
    $row = mysqli_fetch_assoc($res);

    $value = $row['product_qty'] - $qty;

    $sql = "UPDATE products SET product_qty = '$value', date_updated = now() where pid = $pid";
    return mysqli_query($con, $sql);
}

function increaseQtyProduct($data)
{
    $con = db();

    $serve_id = $data['serve_id'];

    $viewProducts = "SELECT * FROM server_products WHERE serve_id = '$serve_id'";
    $res = mysqli_query($con, $viewProducts);
    $row = mysqli_fetch_assoc($res);

    $pid = $row['pid'];

    $exsactProducts = "SELECT * FROM products WHERE pid = '$pid'";
    $res2 = mysqli_query($con, $exsactProducts);
    $row2 = mysqli_fetch_assoc($res2);

    $value = $row['serve_qty'] + $row2['product_qty'];

    $sql = "UPDATE products SET product_qty = '$value', date_updated = now() where pid = $pid";
    return mysqli_query($con, $sql);
}

function changePageSettings($data)
{
    $con = db();
    $field = $data['field'];
    $value = $data['value'];

    $sql = "UPDATE settings SET $field = '$value'";
    return mysqli_query($con, $sql);
}

function editSettingImage($data, $img)
{
    $con = db();

    $field = $data['field'];

    $sql = "UPDATE settings SET $field = '$img'";
    return mysqli_query($con, $sql);
}

function editQtyinCart($data)
{
    $con = db();

    $cart_id = $data['cart_id'];
    $field = $data['field'];
    $value = $data['value'];

    $sql = "UPDATE cart SET $field = '$value', date_updated = now() where cart_id = $cart_id";
    return mysqli_query($con, $sql);	
}

?>