<?php
require_once __DIR__ . '/connection.php';

function deleteDataTables($data){
    $con = db();

    $id_fild =  $data['id_fild'];
    $id =  $data['id'];
    $table = $data['table'];

    $sql = "UPDATE $table SET is_deleted = '1' where $id_fild='$id'";
    return mysqli_query($con, $sql);	
}

function permanantDeleteDataTable($data){
    $con = db();

    $id_fild =  $data['id_fild'];
    $id =  $data['id'];
    $table = $data['table'];

    $sql = "DELETE FROM $table WHERE $id_fild = $id";
    return mysqli_query($con, $sql);	
}


function deleteAllCartItems($customer_id){

	$con = db();

	$sql2 = "DELETE FROM cart where customer_id = $customer_id";
    return mysqli_query($con, $sql2);
}


?>