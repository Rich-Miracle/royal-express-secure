<?php
include 'checkAdmin.php';


if (session_id() == '') {
    session_start();
}

/*
 * The inline guard that stood here duplicated checkAdmin.php and, like it,
 * redirected without halting execution, so the page body was transmitted
 * anyway. checkAdmin.php is included above and now calls exit on denial, so
 * this check is both redundant and unsafe.
 */
