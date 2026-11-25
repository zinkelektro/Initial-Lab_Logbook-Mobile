<?php
session_start(); require_once __DIR__.'/config.php'; require_role('viewer'); $pdo=db();
$from=$_GET['from']??date('Y-m-01'); $to=$_GET['to']??date('Y-m-d'); $alat_id=(int)($_GET['alat_id']??0); $operator=trim($_GET['operator']??'');
$error=''; $success=''; $edit=null;

if($_SERVER['REQUEST_METHOD']==='POST' && can_manage_operations()){
  try{
    $action=$_POST['action']??'';
    if($action==='delete'){
      $id=(int)($_POST['id']??0);
      if($id<=0) throw new Exception('ID logbook tidak valid.');
      $st=$pdo->prepare("DELETE FROM logbook WHERE id=?");
      $st->execute([$id]);
      if($st->rowCount()===0) throw new Exception('Data logbook tidak ditemukan.');
      $success='Data logbook berhasil dihapus.';
    }elseif($action==='update'){
      $id=(int)($_POST['id']??0);
      $alat=(int)($_POST['alat_id']??0);
      $op=trim($_POST['nama_operator']??'');
      $mulai=trim($_POST['waktu_mulai']??'');
      $selesai=trim($_POST['waktu_selesai']??'');
      $kond=trim($_POST['kondisi']??'Baik');
      $nomor=trim($_POST['nomor_sampel']??'');
      $tujuan=trim($_POST['tujuan']??'');
      $cat=trim($_POST['catatan']??'');
      if(!$id||!$alat||!$op||!$mulai||!$selesai) throw new Exception('Lengkapi alat, operator, waktu mulai dan waktu selesai.');
      if(strtotime($selesai)<strtotime($mulai)) throw new Exception('Waktu selesai tidak boleh lebih awal dari waktu mulai.');
      if(!in_array($kond,['Baik','Baik dengan catatan','Perlu pemeriksaan','Rusak'],true)) throw new Exception('Kondisi alat tidak valid.');
      $st=$pdo->prepare("UPDATE logbook SET alat_id=?,nama_operator=?,waktu_mulai=?,waktu_selesai=?,kondisi=?,nomor_sampel=?,tujuan=?,catatan=? WHERE id=?");
      $st->execute([$alat,$op,str_replace('T',' ',$mulai),str_replace('T',' ',$selesai),$kond,$nomor,$tujuan,$cat,$id]);
      $success='Data logbook berhasil diperbarui.';
    }
  }catch(Throwable $e){$error=$e->getMessage();}
}elseif($_SERVER['REQUEST_METHOD']==='POST'){
  http_response_code(403); $error='Akun Anda hanya dapat melihat laporan.';
}
$where="DATE(l.waktu_mulai) BETWEEN ? AND ?"; $params=[$from,$to];
if($alat_id){$where.=" AND l.alat_id=?";$params[]=$alat_id;} if($operator){$where.=" AND l.nama_operator LIKE ?";$params[]="%$operator%";}
$st=$pdo->prepare("SELECT l.*,a.kode_alat,a.nama_alat,a.lokasi FROM logbook l JOIN alat a ON a.id=l.alat_id WHERE $where ORDER BY l.waktu_mulai DESC");$st->execute($params);$rows=$st->fetchAll();
$alat=$pdo->query("SELECT * FROM alat ORDER BY nama_alat")->fetchAll();
if(isset($_GET['edit']) && can_manage_operations()){
  $st=$pdo->prepare("SELECT * FROM logbook WHERE id=?");
  $st->execute([(int)$_GET['edit']]);
  $edit=$st->fetch()?:null;
}
$q=http_build_query(['from'=>$from,'to'=>$to,'alat_id'=>$alat_id,'operator'=>$operator]);
?><!doctype html><html lang="id"><head>
<meta name="lab-user" content="<?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'guest' ?>">
<link rel="manifest" href="/mobile/manifest.webmanifest">
<meta name="theme-color" content="#17365d">
<script defer src="/mobile/mobile.js"></script><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Laporan Logbook</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="app-shell"><?php $sidebar_base=""; include __DIR__."/sidebar.php"; ?><main class="container"><div class="pagehead"><div><h1>Laporan & Export</h1><p>Filter, periksa, edit atau hapus data logbook sebelum diekspor ke Excel/PDF.</p></div><div class="actions"><a class="btn" href="export.php?format=xlsx&<?=$q?>">↓ Excel (.xlsx)</a><a class="btn primary" href="export.php?format=pdf&<?=$q?>" target="_blank">↓ PDF siap cetak</a></div></div>
<section class="panel"><form class="filter" method="get"><label>Dari<input type="date" name="from" value="<?=e($from)?>"></label><label>Sampai<input type="date" name="to" value="<?=e($to)?>"></label><label>Alat<select name="alat_id"><option value="0">Semua alat</option><?php foreach($alat as $a):?><option value="<?=$a['id']?>" <?=$alat_id==$a['id']?'selected':''?>><?=e($a['nama_alat'])?></option><?php endforeach;?></select></label><label>Operator<input name="operator" value="<?=e($operator)?>" placeholder="Cari operator"></label><button class="btn primary">Terapkan</button></form></section>
<?php if($edit): ?>
<section class="panel">
  <div class="panelhead"><h2>Edit Data Logbook #<?=e((string)$edit['id'])?></h2><a href="laporan.php?<?=$q?>">Batal</a></div>
  <form method="post" class="formgrid">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?=e((string)$edit['id'])?>">
    <label>Alat<select name="alat_id" required><?php foreach($alat as $a): ?><option value="<?=$a['id']?>" <?=$edit['alat_id']==$a['id']?'selected':''?>><?=e($a['kode_alat'].' — '.$a['nama_alat'])?></option><?php endforeach; ?></select></label>
    <label>Nama Operator<input name="nama_operator" required value="<?=e($edit['nama_operator'])?>"></label>
    <label>Waktu Mulai<input type="datetime-local" name="waktu_mulai" required value="<?=e(date('Y-m-d\TH:i',strtotime($edit['waktu_mulai'])))?>"></label>
    <label>Waktu Selesai<input type="datetime-local" name="waktu_selesai" required value="<?=e(date('Y-m-d\TH:i',strtotime($edit['waktu_selesai'])))?>"></label>
    <label>Kondisi<select name="kondisi"><?php foreach(['Baik','Baik dengan catatan','Perlu pemeriksaan','Rusak'] as $x): ?><option <?=$edit['kondisi']===$x?'selected':''?>><?=$x?></option><?php endforeach; ?></select></label>
    <label>Nomor Sampel / Job<input name="nomor_sampel" value="<?=e($edit['nomor_sampel'])?>"></label>
    <label class="span2">Tujuan / Kegiatan<input name="tujuan" value="<?=e($edit['tujuan'])?>"></label>
    <label class="span2">Catatan<textarea name="catatan" rows="4"><?=e($edit['catatan'])?></textarea></label>
    <div class="actions span2"><button class="btn primary">Simpan Perubahan</button><a class="btn" href="laporan.php?<?=$q?>">Batal</a></div>
  </form>
</section>
<?php endif; ?>
<section class="panel"><div class="panelhead"><h2><?=count($rows)?> data ditemukan</h2></div><div class="tablewrap"><table><thead><tr><th>No</th><th>Waktu</th><th>Alat</th><th>Operator</th><th>Durasi</th><th>Kondisi</th><th>Job/Tujuan</th><th>Catatan</th><?php if(can_manage_operations()): ?><th>Aksi</th><?php endif; ?></tr></thead><tbody>
<?php foreach($rows as $i=>$r):$dur=max(0,strtotime($r['waktu_selesai'])-strtotime($r['waktu_mulai']));$h=floor($dur/3600);$m=floor(($dur%3600)/60);?><tr><td><?=$i+1?></td><td><?=e(date('d/m/Y H:i',strtotime($r['waktu_mulai'])))?><br>— <?=e(date('H:i',strtotime($r['waktu_selesai'])))?></td><td><?=e($r['nama_alat'])?></td><td><?=e($r['nama_operator'])?></td><td><?=$h?>j <?=$m?>m</td><td><?=e($r['kondisi'])?></td><td><?=e($r['nomor_sampel'])?><br><?=e($r['tujuan'])?></td><td><?=e($r['catatan'])?></td><?php if(can_manage_operations()): ?><td class="actions">
<a class="btn small" href="laporan.php?<?=$q?>&edit=<?=$r['id']?>">Edit</a>
<form method="post" onsubmit="return confirm('Hapus data logbook ini? Data yang sudah dihapus tidak dapat dikembalikan.');">
<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>">
<button class="btn small dangerbtn">Hapus</button></form>
</td><?php endif; ?></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="<?=can_manage_operations()?9:8?>" class="empty">Tidak ada data pada filter tersebut.</td></tr><?php endif;?></tbody></table></div></section></main></div></body></html>
