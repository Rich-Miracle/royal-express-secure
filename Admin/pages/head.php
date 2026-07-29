<head>
    <?php include 'pages/assets.php'; ?>
    <?php include '../server/api.php'; ?>

<?php
/*
 * CSRF token, published for the front end.
 *
 * The meta tag is readable by this site's own JavaScript but not by a page on
 * another origin, which is what the same-origin policy guarantees. The
 * ajaxSetup hook then attaches it to every request jQuery sends, so no
 * individual call site had to be modified.
 */
?>
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
<script>
  if (window.jQuery) {
    $.ajaxSetup({
      headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') }
    });
  }
</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Royal Express</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">

    <link rel="stylesheet" href="assets/vendors/iconly/bold.css">

    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="shortcut icon" href="assets/images/favicon.jpg" type="image/x-icon">
    <style>
        body {
            background-color: #ffffff;
        }
    </style>
</head>