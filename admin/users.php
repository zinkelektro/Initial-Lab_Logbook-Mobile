<?php
session_start();
require_once __DIR__.'/../config.php';
require_role('admin');
$pdo=db();
$me=current_user();
$error=''; $success=''; $edit=null;

if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
$csrf=$_SESSION['csrf'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) throw new Exception('Permintaan tidak valid. Silakan ulangi.');
        $action=$_POST['action'] ?? '';
        if ($action==='save') {
            $id=(int)($_POST['id']??0);
            $username=trim($_POST['username']??'');
            $nama=trim($_POST['nama']??'');
            $role=$_POST['role']??'viewer';
            $password=(string)($_POST['password']??'');
            $aktif=isset($_POST['aktif']) ? 1 : 0;
            if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/',$username)) throw new Exception('Username 3–60 karakter: huruf, angka, titik, garis bawah, atau minus.');
            if ($nama==='') throw new Exception('Nama wajib diisi.');
            if (!in_array($role,['admin','petugas','viewer'],true)) throw new Exception('Role tidak valid.');
            if ($id===0) {
                if (strlen($password)<8) throw new Exception('Password pengguna baru minimal 8 karakter.');
                $st=$pdo->prepare("INSERT INTO admin(username,password,nama,role,aktif) VALUES(?,?,?,?,?)");
                $st->execute([$username,password_hash($password,PASSWORD_DEFAULT),$nama,$role,$aktif]);
                $success='Pengguna berhasil ditambahkan.';
            } else {
                $old=$pdo->prepare("SELECT * FROM admin WHERE id=?"); $old->execute([$id]); $old=$old->fetch();
                if (!$old) throw new Exception('Pengguna tidak ditemukan.');
                if ($id===(int)$me['id'] && !$aktif) throw new Exception('Akun yang sedang digunakan tidak boleh dinonaktifkan.');
                if ($old['role']==='admin' && $role!=='admin' && (int)$old['aktif']===1) {
                    $count=(int)$pdo->query("SELECT COUNT(*) FROM admin WHERE role='admin' AND aktif=1")->fetchColumn();
                    if ($count<=1) throw new Exception('Tidak boleh menghapus hak admin terakhir.');
                }
                if ($password!=='') {
                    if (strlen($password)<8) throw new Exception('Password baru minimal 8 karakter.');
                    $st=$pdo->prepare("UPDATE admin SET username=?,nama=?,role=?,aktif=?,password=? WHERE id=?");
                    $st->execute([$username,$nama,$role,$aktif,password_hash($password,PASSWORD_DEFAULT),$id]);
                } else {
                    $st=$pdo->prepare("UPDATE admin SET username=?,nama=?,role=?,aktif=? WHERE id=?");
                    $st->execute([$username,$nama,$role,$aktif,$id]);
                }
                $success='Pengguna berhasil diperbarui.';
            }
        } elseif ($action==='delete') {
            $id=(int)($_POST['id']??0);
            if ($id===(int)$me['id']) throw new Exception('Anda tidak dapat menghapus akun yang sedang digunakan.');
            $st=$pdo->prepare("SELECT role FROM admin WHERE id=?"); $st->execute([$id]); $target=$st->fetch();
            if (!$target) throw new Exception('Pengguna tidak ditemukan.');
            if ($target['role']==='admin') {
                $count=(int)$pdo->query("SELECT COUNT(*) FROM admin WHERE role='admin'")->fetchColumn();
                if ($count<=1) throw new Exception('Admin terakhir tidak boleh dihapus.');
            }
            $pdo->prepare("DELETE FROM admin WHERE id=?")->execute([$id]);
            $success='Pengguna berhasil dihapus.';
        }
    } catch (Throwable $e) {
        $error=$e instanceof PDOException && $e->getCode()==='23000' ? 'Username sudah digunakan.' : $e->getMessage();
    }
}
if (isset($_GET['edit'])) {
    $st=$pdo->prepare("SELECT id,username,nama,role,aktif FROM admin WHERE id=?"); $st->execute([(int)$_GET['edit']]); $edit=$st->fetch() ?: null;
}
$users=$pdo->query("SELECT id,username,nama,role,aktif,created_at FROM admin ORDER BY FIELD(role,'admin','petugas','viewer'),nama")->fetchAll();
?>
<!doctype html><html lang="id"><head>
<meta name="lab-user" content="<?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'guest' ?>">
<link rel="manifest" href="/mobile/manifest.webmanifest">
<meta name="theme-color" content="#17365d">
<script defer src="/mobile/mobile.js"></script><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manajemen Pengguna</title><link rel="stylesheet" href="../assets/style.css"></head><body>
<div class="app-shell"><?php $sidebar_base="../"; include __DIR__."/../sidebar.php"; ?><main class="container"><div class="pagehead"><div><h1>Manajemen Pengguna</h1><p>Tambah, edit, nonaktifkan, atau hapus akun pengguna.</p></div></div>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
<div class="grid2">
<section class="panel"><h2><?=$edit?'Edit Pengguna':'Tambah Pengguna'?></h2>
<form method="post" class="formgrid">
<input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$edit?(int)$edit['id']:0?>">
<label>Username<input name="username" required maxlength="60" value="<?=e($edit['username']??'')?>"></label>
<label>Nama Lengkap<input name="nama" required maxlength="120" value="<?=e($edit['nama']??'')?>"></label>
<label>Role<select name="role"><option value="admin" <?=($edit['role']??'')==='admin'?'selected':''?>>admin — akses penuh</option><option value="petugas" <?=($edit['role']??'')==='petugas'?'selected':''?>>petugas — alat, logbook, laporan</option><option value="viewer" <?=($edit['role']??'')==='viewer'?'selected':''?>>viewer — hanya melihat</option></select></label>
<label>Password<input type="password" name="password" minlength="8" placeholder="<?=$edit?'Kosongkan jika tidak diubah':'Minimal 8 karakter'?>"></label>
<label class="check"><input type="checkbox" name="aktif" value="1" <?=(!$edit || $edit['aktif'])?'checked':''?>> Akun aktif</label>
<button class="btn primary span2"><?=$edit?'Simpan Perubahan':'Tambah Pengguna'?></button>
<?php if($edit):?><a class="btn span2" href="users.php">Batal Edit</a><?php endif;?>
</form></section>
<section class="panel"><div class="panelhead"><h2><?=count($users)?> Akun</h2></div>
<div class="tablewrap"><table><thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($users as $u):?><tr><td><b><?=e($u['nama'])?></b></td><td><?=e($u['username'])?></td><td><span class="badge role-<?=e($u['role'])?>"><?=e($u['role'])?></span></td><td><?=$u['aktif']?'<span class="badge baik">Aktif</span>':'<span class="badge rusak">Nonaktif</span>'?></td><td><?=e(date('d/m/Y',strtotime($u['created_at'])))?></td>
<td class="actions"><a class="btn small" href="?edit=<?=$u['id']?>">Edit</a><?php if((int)$u['id']!==(int)$me['id']):?><form method="post" onsubmit="return confirm('Hapus akun ini? Data logbook tidak ikut terhapus.');" style="display:inline"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="btn small dangerbtn">Hapus</button></form><?php endif;?></td></tr><?php endforeach;?>
</tbody></table></div></section></div>
<section class="panel"><h2>Hak Akses</h2><div class="rolegrid"><div><b>admin</b><p>Akses penuh dan dapat mengelola pengguna.</p></div><div><b>petugas</b><p>Dapat mengelola alat, mencatat logbook, dan membuka laporan/export.</p></div><div><b>viewer</b><p>Hanya dapat melihat dashboard dan laporan/export.</p></div></div></section>
</main></div></body></html>
