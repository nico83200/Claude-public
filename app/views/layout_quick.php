<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="theme-color" content="#141833">
<link rel="manifest" href="manifest.webmanifest">
<title>Mode réserve · <?= e(app_name()) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
<link rel="stylesheet" href="assets/css/app.css?v=<?= e(APP_VERSION) ?>">
</head>
<body class="quick-body">
<?= $content ?>
<div class="toast-zone" id="toasts"></div>
<script>window.APP = { csrf: <?= json_encode(csrf_token()) ?>, showPrices: false, version: <?= json_encode(APP_VERSION) ?> };</script>
<script src="assets/js/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
