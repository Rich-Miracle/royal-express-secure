<?php
require_once __DIR__ . '/connection.php';
function updateDataTable($data)
{
    $con = db();

    $id_fild = $data['id_fild'];
    $id = $data['id'];
    $field = $data['field'];
    $value = $data['value'];
    $table = $data['table'];

    $sql = "UPDATE $table SET $field = '$value' where $id_fild = '$id'";
    return mysqli_query($con, $sql);
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