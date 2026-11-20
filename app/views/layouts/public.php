<?php
/**
 * Layout halaman publik.
 *
 * Atribut `lang` pada `<html>` mengikuti bahasa yang dipilih pengguna (Req 8.6),
 * dan `data-lite` memberi tahu `app.js` bahwa halaman ini dilayani dalam mode
 * ringan sehingga pembaruan otomatis dimatikan (Req 8.8).
 *
 * `$quickExit` memilih bentuk tombol keluar cepat: `labeled` untuk form lapor,
 * halaman kasus, dan halaman lacak (Req 8.17), `icon` untuk halaman publik lain
 * (Req 8.18), `none` bila halaman ini tidak boleh memuatnya.
 *
 * @var string $content  @var string|null $active  @var bool|null $noFooter  @var string|null $quickExit
 */
$liteLayout = lite();
$quickExit ??= 'icon';
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?php include APP_PATH . '/views/partials/head.php'; ?>
</head>
<body<?= $liteLayout ? ' class="is-lite" data-lite' : '' ?>>
<?php include APP_PATH . '/views/partials/icons.php'; ?>
<?php include APP_PATH . '/views/partials/public-header.php'; ?>
<?php if ($quickExit !== 'none') include APP_PATH . '/views/partials/quick-exit.php'; ?>
<?php include APP_PATH . '/views/partials/flash.php'; ?>
<?= $content ?>
<?php if (empty($noFooter)) include APP_PATH . '/views/partials/public-footer.php'; ?>
</body>
</html>
