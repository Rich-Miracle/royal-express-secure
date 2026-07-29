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