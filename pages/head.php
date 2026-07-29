<head>
<?php 
    include 'server/api.php';  
    include 'pages/assets.php';  
?>
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
/*
 * Attach the CSRF token to every request the page makes.
 *
 * The first attempt used jQuery's ajaxSetup, which failed. assets.php loads
 * jQuery three times, and each load replaces the global object, discarding any
 * configuration attached to the previous one.
 *
 * Hooking XMLHttpRequest instead is immune to that. Every AJAX library on this
 * page, jQuery included, ultimately sends through XMLHttpRequest, so patching
 * it once covers all of them no matter what loads afterwards. fetch is wrapped
 * as well for anything that uses it.
 */
(function () {
  var meta  = document.querySelector('meta[name="csrf-token"]');
  var token = meta ? meta.getAttribute('content') : '';
  if (!token) { return; }

  var open = XMLHttpRequest.prototype.open;
  XMLHttpRequest.prototype.open = function () {
    var result = open.apply(this, arguments);
    try { this.setRequestHeader('X-CSRF-Token', token); } catch (e) {}
    return result;
  };

  if (window.fetch) {
    var original = window.fetch;
    window.fetch = function (input, init) {
      init = init || {};
      var headers = new Headers(init.headers || {});
      headers.set('X-CSRF-Token', token);
      init.headers = headers;
      return original(input, init);
    };
  }
})();
</script>
<?php

    $setting = getAllSettings();
    $res = mysqli_fetch_assoc($setting);

    $header = $res['header_image'];
    $header_src = "server/uploads/settings/".$header;

    $subheader = $res['sub_image'];
    $subheader_src = "server/uploads/settings/".$subheader;

    $about = $res['about_image'];
    $about_src = "server/uploads/settings/".$about;

    $background_image = $res['background_image'];
    $background_image_src = "server/uploads/settings/".$background_image;


    ?>
    <title>Royal Express</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,700,900|Display+Playfair:200,300,400,700"> 
    <link rel="stylesheet" href="fonts/icomoon/style.css">

    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/magnific-popup.css">
    <link rel="stylesheet" href="css/jquery-ui.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/owl.theme.default.min.css">

    <link rel="stylesheet" href="css/bootstrap-datepicker.css">

    <link rel="stylesheet" href="fonts/flaticon/font/flaticon.css">



    <link rel="stylesheet" href="css/aos.css">

    <link rel="stylesheet" href="css/style.css">
    
  </head>