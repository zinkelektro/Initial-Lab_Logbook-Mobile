<?php
session_start(); require_once __DIR__.'/config.php'; require_role('petugas'); $pdo=db(); $error=''; $success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $alat=(int)($_POST['alat_id']??0); $op=trim($_POST['nama_operator']??''); $mulai=$_POST['waktu_mulai']??''; $selesai=$_POST['waktu_selesai']??'';
    $kond=$_POST['kondisi']??'Baik'; $tujuan=trim($_POST['tujuan']??''); $cat=trim($_POST['catatan']??''); $nomor=trim($_POST['nomor_sampel']??'');
    if(!$alat||!$op||!$mulai||!$selesai) throw new Exception('Lengkapi alat, operator, waktu mulai dan waktu selesai.');
    if(strtotime($selesai)<strtotime($mulai)) throw new Exception('Waktu selesai tidak boleh lebih awal dari waktu mulai.');
    $pdo->prepare("INSERT INTO logbook(alat_id,nama_operator,waktu_mulai,waktu_selesai,kondisi,tujuan,nomor_sampel,catatan) VALUES(?,?,?,?,?,?,?,?)")
      ->execute([$alat,$op,str_replace('T',' ',$mulai),str_replace('T',' ',$selesai),$kond,$tujuan,$nomor,$cat]);
    $success='Pencatatan penggunaan berhasil disimpan.';
  }catch(Throwable $e){$error=$e->getMessage();}
}
$alat=$pdo->query("SELECT * FROM alat WHERE aktif=1 ORDER BY nama_alat")->fetchAll();
$recent=$pdo->query("SELECT l.*,a.nama_alat FROM logbook l JOIN alat a ON a.id=l.alat_id ORDER BY l.id DESC LIMIT 12")->fetchAll();
$now=date('Y-m-d\TH:i');
?><!doctype html><html lang="id"><head>
<meta name="lab-user" content="<?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'guest' ?>">
<link rel="manifest" href="/mobile/manifest.webmanifest">
<meta name="theme-color" content="#17365d">
<script defer src="/mobile/mobile.js"></script><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logbook</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="app-shell"><?php $sidebar_base=""; include __DIR__."/sidebar.php"; ?><main class="container"><div class="pagehead"><div><h1>Catat Penggunaan Alat</h1><p>Isi setiap kali alat digunakan.</p></div></div>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
<section class="panel"><form method="post" class="formgrid">
<label>Jenis / Nama Alat<select name="alat_id" required><option value="">-- pilih alat --</option><?php foreach($alat as $a):?><option value="<?=$a['id']?>"><?=e($a['kode_alat'].' — '.$a['nama_alat'])?></option><?php endforeach;?></select></label>
<label>Nama Operator<input name="nama_operator" required placeholder="Nama lengkap"></label>
<label>Waktu Mulai<input type="datetime-local" name="waktu_mulai" value="<?=$now?>" required></label>
<label>Waktu Selesai<input type="datetime-local" name="waktu_selesai" value="<?=$now?>" required></label>
<label>Kondisi Alat<select name="kondisi"><option>Baik</option><option>Baik dengan catatan</option><option>Perlu pemeriksaan</option><option>Rusak</option></select></label>
<label>Nomor Sampel / Job<input name="nomor_sampel" placeholder="Opsional"></label>
<label class="span2">Tujuan / Kegiatan<input name="tujuan" placeholder="Contoh: Pengujian Marshall benda uji AC-WC"></label>
<label class="span2">Catatan<textarea name="catatan" rows="4" placeholder="Kondisi sebelum/sesudah, kejadian, hasil pemeriksaan awal, dll."></textarea></label>
<button class="btn primary span2">Simpan Logbook</button></form></section>
<section class="panel"><div class="panelhead"><h2>12 Pencatatan Terakhir</h2><a href="laporan.php">Buka laporan →</a></div><div class="tablewrap"><table><thead><tr><th>Alat</th><th>Operator</th><th>Mulai</th><th>Selesai</th><th>Kondisi</th><th>Tujuan</th></tr></thead><tbody><?php foreach($recent as $r):?><tr><td><?=e($r['nama_alat'])?></td><td><?=e($r['nama_operator'])?></td><td><?=e(date('d/m/Y H:i',strtotime($r['waktu_mulai'])))?></td><td><?=e(date('d/m/Y H:i',strtotime($r['waktu_selesai'])))?></td><td><span class="badge <?=e(strtolower($r['kondisi']))?>"><?=e($r['kondisi'])?></span></td><td><?=e($r['tujuan'])?></td></tr><?php endforeach;?></tbody></table></div></section>
</main></div></body></html>
