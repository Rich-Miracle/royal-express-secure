<?php
/*
 * API entry point.
 *
 * Two changes to the header of this file carry the access-control fix.
 *
 * First, configuration loads before the session starts. Session cookie flags
 * such as HttpOnly and SameSite can only be set before session_start(), so the
 * original ordering made them impossible to apply.
 *
 * Second, every request now passes require_authorisation() before any routing
 * happens. The function code is read once, checked once, and the request is
 * rejected here if the caller lacks the role. Nothing below this point can be
 * reached by an unauthorised caller, which is the property the original file
 * lacked entirely.
 */

require_once __DIR__ . '/inc/config.php';

if (session_id() == '') {
    session_start();
}

require_once __DIR__ . '/inc/authz.php';

/*
 * This file serves two purposes, which the authorisation gate has to account
 * for. It is the API endpoint, requested directly by the front-end JavaScript.
 * It is also included by pages/head.php on every page render, so that the
 * database functions are available to the page.
 *
 * Only the direct request is a request to authorise. An include is the page
 * loading its own library, and the page has already run its own guard. The two
 * cases are told apart by comparing this file against the script the web server
 * was actually asked to run.
 *
 * The dual role is worth noting as a design weakness in its own right: including
 * this file exposes all fifty-seven database functions to every page in the
 * application, whether the page needs them or not.
 */
$is_direct_request = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']);

if ($is_direct_request) {
    $function_code = isset($_GET['function_code']) ? (string) $_GET['function_code'] : '';

    if ($function_code === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'Missing function_code']));
    }

    require_authorisation($function_code);
} else {
    // Included as a library. Load the functions below, route nothing.
    $function_code = '';
}

include_once 'inc/get.php';
include_once 'inc/connection.php';
include_once 'inc/update.php';
include_once 'inc/delete.php';
include_once 'inc/add.php';

if ($function_code === 'getCustomerTbleData') {
    echo json_encode(getAllCustomer());
} else if ($function_code === 'updateData') {
    updateDataTable($_POST);
} else if ($function_code === 'insertImageUpload') {

    $img = $_FILES['file']['name'];
    $target_dir = "uploads/gallery/";
    $target_file = $target_dir . basename($img);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $extensions_arr = array("jpg", "jpeg", "png", "gif", "jfif", "svg", "webp");

    if (in_array($imageFileType, $extensions_arr)) {
        move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $img);
        insertImagetoGallery($img);
    }
} else if ($function_code === 'imageUploadProducts') {

    $img = $_FILES['file']['name'];
    $target_dir = "uploads/products/";
    $target_file = $target_dir . basename($img);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $extensions_arr = array("jpg", "jpeg", "png", "gif", "jfif", "svg", "webp");

    if (in_array($imageFileType, $extensions_arr)) {
        move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $img);
        editImages($_POST, $img);
    }
} else if ($function_code === 'addProducts') {

    $img = $_FILES['file']['name'];
    $target_dir = "uploads/products/";
    $target_file = $target_dir . basename($img);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $extensions_arr = array("jpg", "jpeg", "png", "gif", "jfif", "svg", "webp");
} else if ($function_code === 'deleteData') {
    deleteDataTables($_POST);
} else if ($function_code === 'permanantDeleteData') {
    permanantDeleteDataTable($_POST);
} else if ($function_code === 'changesettings') {
    changePageSettings($_POST);
} else if ($function_code === 'SettingImage') {

    $img = $_FILES['file']['name'];
    $target_dir = "uploads/settings/";
    $target_file = $target_dir . basename($img);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $extensions_arr = array("jpg", "jpeg", "png", "gif", "jfif", "svg", "webp");

    if (in_array($imageFileType, $extensions_arr)) {
        move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $img);
        editSettingImage($_POST, $img);
    }
} else if ($function_code === 'login') {
    echo getLoginAdmin($_POST);
} else if ($function_code === 'checkPasswordByEmail') {
    checkPasswordByName($_POST);
} else if ($function_code === 'editQty') {
    editQtyinCart($_POST);
} else if ($function_code === 'addcontact') {
    addMessage($_POST);
} else if ($function_code === 'addCustomer') {
    createCustomer($_POST);
} else if ($function_code === 'checkEmail') {
    checkUserEmail($_POST);
} else if ($function_code === 'checkPassword') {
    // Role alone is not enough. Confirm the caller owns the record.
    require_ownership((int) ($_POST['customer_id'] ?? 0));
    checkuserPassword($_POST);
} else if ($function_code === 'addEmployee') {
    addEmployee($_POST);
} else if ($function_code === 'addBranch') {
    addBranch($_POST);
} else if ($function_code === 'addPrice') {
    addPrice($_POST);
} else if ($function_code === 'checkArea') {
    checkArea($_POST);
} else if ($function_code === 'addArea') {
    addArea($_POST);
} else if ($function_code === 'addRequest') {
    addRequest($_POST);
}
