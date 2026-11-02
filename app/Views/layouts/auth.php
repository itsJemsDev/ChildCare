<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Barangay ChildCare') ?> · Barangay ChildCare</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpine.min.js') ?>"></script>
    <script defer src="<?= base_url('assets/js/form-submit.js') ?>"></script>
</head>
<body class="min-h-screen">
<?= $this->renderSection('content') ?>
</body>
</html>
