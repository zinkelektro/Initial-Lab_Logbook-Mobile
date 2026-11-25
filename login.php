<?php
session_start();
require_once __DIR__.'/config.php';
if (current_user()) { header('Location:index.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $st=db()->prepare("SELECT * FROM admin WHERE username=? AND aktif=1 LIMIT 1");
        $st->execute([trim($_POST['username'] ?? '')]);
        $u=$st->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']=$u['id'];
            $_SESSION['user_name']=$u['nama'];
            $_SESSION['user_role']=$u['role'];
            header('Location:index.php'); exit;
        }
        $error='Username atau password salah.';
    } catch(Throwable $e) { $error='Database belum siap. Jalankan sql/schema.sql atau sql/upgrade_multiuser.sql dan periksa config.php.'; }
}
?><!doctype html><html lang="id"><head>
<meta name="lab-user" content="<?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'guest' ?>">
<link rel="manifest" href="/mobile/manifest.webmanifest">
<meta name="theme-color" content="#17365d">
<script defer src="/mobile/mobile.js"></script><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login • <?=e(APP_NAME)?></title><link rel="stylesheet" href="assets/style.css"></head>
<body class="loginbody"><form class="loginbox" method="post"><h1>Logbook Lab</h1><p>Masuk ke sistem sesuai hak akses akun.</p><?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<label>Username<input name="username" required autofocus value="<?=e($_POST['username']??'admin')?>"></label>
<label>Password<input type="password" name="password" required></label><button class="btn primary full">Masuk</button>
<div ></div></form></body></html>