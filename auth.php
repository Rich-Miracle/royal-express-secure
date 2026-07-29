<?php
/*
 * Customer page guard.
 *
 * The original had the same missing-exit fault as checkAdmin.php, and its
 * redirect target was wrong as well: it pointed at "admin/login.php" while the
 * directory on disk is "Admin". On a case-sensitive filesystem that is a dead
 * link, so a denied user was redirected to a 404 while still receiving the
 * protected page.
 */

require_once __DIR__ . '/server/inc/config.php';

if (session_id() == '') {
    session_start();
}

require_once __DIR__ . '/server/inc/authz.php';

require_customer_page('Admin/login.php');

$getall      = getAllcustomerById($_SESSION['customer']);
$cus         = mysqli_fetch_assoc($getall);
$customer_id = $cus['customer_id'];
