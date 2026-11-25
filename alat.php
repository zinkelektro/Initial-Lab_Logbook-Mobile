<?php
session_start();require_once __DIR__.'/config.php';require_role('petugas');$pdo=db();$msg='';$error='';
function parse_date_value($v){
  $v=trim((string)$v);
  if($v==='') return null;
  if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) return $v;
  if(preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/',$v,$m)) return sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]);
  // Excel serial date (1900 date system), e.g. 45500.
  if(is_numeric($v) && (float)$v >= 1 && (float)$v <= 80000){
    $n=(int)floor((float)$v);
    if($n>=60) $n--; // Excel's historical 1900 leap-year bug.
    $dt=(new DateTime('1899-12-31'))->modify("+{$n} days");
    return $dt->format('Y-m-d');
  }
  return false;
}

/*
 * XLSX reader without SimpleXML/DOM.
 * This keeps Import Excel working even when php-xml is not installed.
 */
function xml_text($s){
  $s=preg_replace('/<t\b[^>]*>(.*?)<\/t>/si','$1',$s);
  $s=preg_replace('/<rPh\b[^>]*>.*?<\/rPh>/si','',$s);
  return html_entity_decode(strip_tags($s),ENT_QUOTES|ENT_XML1,'UTF-8');
}
function read_xlsx_rows($file){
  $z=new ZipArchive();
  if($z->open($file)!==true) throw new Exception('File Excel tidak dapat dibuka.');
  $xml=$z->getFromName('xl/worksheets/sheet1.xml');
  if($xml===false) throw new Exception('Sheet Excel pertama tidak ditemukan.');
  $shared=[];
  $sx=$z->getFromName('xl/sharedStrings.xml');
  if($sx!==false){
    if(preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si',$sx,$mm)){
      foreach($mm[1] as $item) $shared[]=xml_text($item);
    }
  }
  $rows=[];
  if(preg_match_all('/<row\b[^>]*>(.*?)<\/row>/si',$xml,$rm)){
    foreach($rm[1] as $rowxml){
      $vals=[];
      if(preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/si',$rowxml,$cm,PREG_SET_ORDER)){
        foreach($cm as $c){
          $attrs=$c[1]; $body=$c[2];
          $ref=''; $type='';
          if(preg_match('/\br=["\']([^"\']+)["\']/i',$attrs,$m)) $ref=$m[1];
          if(preg_match('/\bt=["\']([^"\']+)["\']/i',$attrs,$m)) $type=$m[1];
          if(!$ref || !preg_match('/^([A-Z]+)\d+$/i',$ref,$m)) continue;
          $col=0;
          foreach(str_split(strtoupper($m[1])) as $ch) $col=$col*26+(ord($ch)-64);
          while(count($vals)<$col-1) $vals[]='';
          $v='';
          if($type==='inlineStr'){
            if(preg_match('/<is\b[^>]*>(.*?)<\/is>/si',$body,$im)) $v=xml_text($im[1]);
          }else{
            if(preg_match('/<v\b[^>]*>(.*?)<\/v>/si',$body,$vm)) $v=xml_text($vm[1]);
            if($type==='s' && isset($shared[(int)$v])) $v=$shared[(int)$v];
            if($type==='str' && preg_match('/<v\b[^>]*>(.*?)<\/v>/si',$body,$vm)) $v=xml_text($vm[1]);
          }
          $vals[$col-1]=$v;
        }
      }
      $rows[]=$vals;
    }
  }
  $z->close();
  return $rows;
}
function read_csv_rows($file){$f=fopen($file,'r');if(!$f)throw new Exception('File tidak dapat dibaca.');$r=[];while(($x=fgetcsv($f,0,','))!==false)$r[]=$x;fclose($f);return $r;}
function hnorm($v){return strtoupper(trim(preg_replace('/\s+/',' ',str_replace("\xEF\xBB\xBF",'',(string)$v))));}

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{$action=$_POST['action']??'';if($action==='save'){$id=(int)($_POST['id']??0);$kode=trim($_POST['kode_alat']??'');$nama=trim($_POST['nama_alat']??'');$lok=trim($_POST['lokasi']??'');$status=trim($_POST['status_kalibrasi']??'Belum Kalibrasi');$tgl=parse_date_value($_POST['tanggal_kalibrasi']??'');$interval=max(1,min(120,(int)($_POST['interval_kalibrasi_bulan']??12)));$sert=trim($_POST['nomor_sertifikat']??'');$merk=trim($_POST['merk_tipe']??'');$tahun=$_POST['tahun_perolehan']!==''?(int)$_POST['tahun_perolehan']:null;$kond=trim($_POST['kondisi_alat']??'Baik');$cat=trim($_POST['catatan']??'');$aktif=isset($_POST['aktif'])?1:0;if($kode===''||$nama==='')throw new Exception('Kode dan nama alat wajib diisi.');if($tgl===false)throw new Exception('Tanggal kalibrasi tidak valid.');if(!in_array($status,['Terkalibrasi','Belum Kalibrasi','Kadaluarsa'],true)||!in_array($kond,['Baik','Baik dengan catatan','Perlu pemeriksaan','Rusak'],true))throw new Exception('Nilai status tidak valid.');if($id){$st=$pdo->prepare("UPDATE alat SET kode_alat=?,nama_alat=?,lokasi=?,status_kalibrasi=?,tanggal_kalibrasi=?,interval_kalibrasi_bulan=?,nomor_sertifikat=?,merk_tipe=?,tahun_perolehan=?,kondisi_alat=?,catatan=?,aktif=? WHERE id=?");$st->execute([$kode,$nama,$lok,$status,$tgl,$interval,$sert,$merk,$tahun,$kond,$cat,$aktif,$id]);$msg='Data alat diperbarui.';}else{$st=$pdo->prepare("INSERT INTO alat(kode_alat,nama_alat,lokasi,status_kalibrasi,tanggal_kalibrasi,interval_kalibrasi_bulan,nomor_sertifikat,merk_tipe,tahun_perolehan,kondisi_alat,catatan,aktif) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");$st->execute([$kode,$nama,$lok,$status,$tgl,$interval,$sert,$merk,$tahun,$kond,$cat,$aktif]);$msg='Data alat berhasil ditambahkan.';}}
 elseif($action==='import'){$f=$_FILES['file_excel']??null;if(!$f||empty($f['tmp_name']))throw new Exception('Pilih file Excel terlebih dahulu.');$ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));if(!in_array($ext,['xlsx','csv'],true))throw new Exception('Format harus .xlsx atau .csv.');$data=$ext==='xlsx'?read_xlsx_rows($f['tmp_name']):read_csv_rows($f['tmp_name']);if(!$data)throw new Exception('File tidak berisi data.');$head=array_map('hnorm',$data[0]);$aliases=['KODE'=>['KODE','KODE ALAT'],'NAMA ALAT'=>['NAMA ALAT','NAMA'],'LOKASI'=>['LOKASI'],'STATUS KALIBRASI'=>['STATUS KALIBRASI'],'TANGGAL KALIBRASI'=>['TANGGAL KALIBRASI'],'KONDISI ALAT'=>['KONDISI ALAT','KONDISI'],'INTERVAL KALIBRASI (BULAN)'=>['INTERVAL KALIBRASI (BULAN)','INTERVAL KALIBRASI','INTERVAL'],'NOMOR SERTIFIKAT'=>['NOMOR SERTIFIKAT','SERTIFIKAT'],'MERK / TIPE'=>['MERK / TIPE','MERK','TIPE'],'TAHUN PEROLEHAN'=>['TAHUN PEROLEHAN','TAHUN'],'CATATAN'=>['CATATAN']];$map=[];foreach($aliases as $key=>$opts){foreach($opts as $o){$i=array_search($o,$head,true);if($i!==false){$map[$key]=$i;break;}}}foreach(['KODE','NAMA ALAT','LOKASI','STATUS KALIBRASI','TANGGAL KALIBRASI','KONDISI ALAT'] as $req){if(!isset($map[$req]))throw new Exception('Kolom "'.$req.'" tidak ditemukan. Gunakan Template Excel.');}$new=0;$upd=0;$skip=0;$sql="INSERT INTO alat(kode_alat,nama_alat,lokasi,status_kalibrasi,tanggal_kalibrasi,interval_kalibrasi_bulan,nomor_sertifikat,merk_tipe,tahun_perolehan,kondisi_alat,catatan,aktif) VALUES(?,?,?,?,?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE nama_alat=VALUES(nama_alat),lokasi=VALUES(lokasi),status_kalibrasi=VALUES(status_kalibrasi),tanggal_kalibrasi=VALUES(tanggal_kalibrasi),interval_kalibrasi_bulan=VALUES(interval_kalibrasi_bulan),nomor_sertifikat=VALUES(nomor_sertifikat),merk_tipe=VALUES(merk_tipe),tahun_perolehan=VALUES(tahun_perolehan),kondisi_alat=VALUES(kondisi_alat),catatan=VALUES(catatan)";$st=$pdo->prepare($sql);foreach(array_slice($data,1) as $r){$g=function($k,$d='')use(&$r,&$map){return isset($map[$k])?trim((string)($r[$map[$k]]??$d)):$d;};$kode=$g('KODE');$nama=$g('NAMA ALAT');if($kode===''&&$nama==='')continue;if($kode===''||$nama===''){$skip++;continue;}$status=$g('STATUS KALIBRASI','Belum Kalibrasi');$kond=$g('KONDISI ALAT','Baik');$tgl=parse_date_value($g('TANGGAL KALIBRASI'));if($tgl===false||!in_array($status,['Terkalibrasi','Belum Kalibrasi','Kadaluarsa'],true)||!in_array($kond,['Baik','Baik dengan catatan','Perlu pemeriksaan','Rusak'],true)){$skip++;continue;}$interval=max(1,min(120,(int)$g('INTERVAL KALIBRASI (BULAN)','12')));$tahun=$g('TAHUN PEROLEHAN')!==''?(int)$g('TAHUN PEROLEHAN'):null;$st->execute([$kode,$nama,$g('LOKASI'),$status,$tgl,$interval,$g('NOMOR SERTIFIKAT'),$g('MERK / TIPE'),$tahun,$kond,$g('CATATAN')]);if($st->rowCount()===1)$new++;else$upd++;}$msg="Import selesai: {$new} data baru, {$upd} diperbarui, {$skip} dilewati.";}
elseif($action==='delete'){if(!is_admin())throw new Exception('Hanya admin yang dapat menghapus alat.');$id=(int)$_POST['id'];$st=$pdo->prepare("SELECT COUNT(*) FROM logbook WHERE alat_id=?");$st->execute([$id]);if($st->fetchColumn()>0)throw new Exception('Alat memiliki riwayat logbook. Nonaktifkan alat agar riwayat tetap aman.');$pdo->prepare("DELETE FROM alat WHERE id=?")->execute([$id]);$msg='Data alat dihapus.';}
 }catch(Throwable $e){$error=$e instanceof PDOException&&$e->getCode()==='23000'?'Kode alat sudah digunakan.':$e->getMessage();}}
$edit=null;if(isset($_GET['edit'])){$st=$pdo->prepare('SELECT * FROM alat WHERE id=?');$st->execute([(int)$_GET['edit']]);$edit=$st->fetch()?:null;}
$q=trim($_GET['q']??'');$filter=$_GET['filter']??'all';$where=[];$p=[];if($q){$where[]='(kode_alat LIKE ? OR nama_alat LIKE ? OR lokasi LIKE ?)';$p=["%$q%","%$q%","%$q%"];}$sql="SELECT *,DATE_ADD(tanggal_kalibrasi,INTERVAL interval_kalibrasi_bulan MONTH) jatuh_tempo FROM alat";if($filter==='kalibrasi')$where[]="tanggal_kalibrasi IS NOT NULL AND DATE_ADD(tanggal_kalibrasi,INTERVAL interval_kalibrasi_bulan MONTH)<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)";if($filter==='rusak')$where[]="kondisi_alat IN ('Rusak','Perlu pemeriksaan')";if($filter==='nonaktif')$where[]='aktif=0';if($where)$sql.=' WHERE '.implode(' AND ',$where);$sql.=' ORDER BY kode_alat';$st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();
function cbadge($r){if(!$r['tanggal_kalibrasi'])return ['belum','Belum Kalibrasi'];if($r['jatuh_tempo']<date('Y-m-d'))return ['kadaluarsa','Kadaluarsa'];if($r['jatuh_tempo']<=date('Y-m-d',strtotime('+30 days')))return ['belum','Segera'];return ['terkalibrasi','Terkalibrasi'];}
?><!doctype html><html lang="id"><head>
<meta name="lab-user" content="<?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'guest' ?>">
<link rel="manifest" href="/mobile/manifest.webmanifest">
<meta name="theme-color" content="#17365d">
<script defer src="/mobile/mobile.js"></script><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Data Alat | LAB Manager</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="app-shell"><?php include __DIR__.'/sidebar.php';?><main class="container"><div class="pagehead"><div><div class="eyebrow">Asset & Calibration</div><h1>Inventory Alat Laboratorium</h1><p>Kelola inventaris, status kalibrasi, kondisi, lokasi, sertifikat, dan siklus pemeliharaan.</p></div><div class="actions"><a class="btn" href="export_alat.php">↓ Export Excel</a><a class="btn" href="export_alat.php?template=1">＋ Template Excel</a></div></div><?php if($msg):?><div class="alert success">✓ <?=e($msg)?></div><?php endif;?><?php if($error):?><div class="alert danger">! <?=e($error)?></div><?php endif;?>
<section class="panel"><div class="panelhead"><h2><?= $edit?'Edit Data Alat':'Input Data Alat'?></h2><?php if($edit):?><a href="alat.php">＋ Input Baru</a><?php endif;?></div><form method="post" class="formgrid"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$edit?(int)$edit['id']:0?>"><label>KODE<input name="kode_alat" required value="<?=e($edit['kode_alat']??'')?>" placeholder="OVN-01"></label><label>NAMA ALAT<input name="nama_alat" required value="<?=e($edit['nama_alat']??'')?>" placeholder="Oven"></label><label>LOKASI<input name="lokasi" value="<?=e($edit['lokasi']??'')?>" placeholder="Ruang Pengujian Material"></label><label>MERK / TIPE<input name="merk_tipe" value="<?=e($edit['merk_tipe']??'')?>"></label><label>STATUS KALIBRASI<select name="status_kalibrasi"><option <?=($edit['status_kalibrasi']??'')==='Terkalibrasi'?'selected':''?>>Terkalibrasi</option><option <?=($edit['status_kalibrasi']??'Belum Kalibrasi')==='Belum Kalibrasi'?'selected':''?>>Belum Kalibrasi</option><option <?=($edit['status_kalibrasi']??'')==='Kadaluarsa'?'selected':''?>>Kadaluarsa</option></select></label><label>TANGGAL KALIBRASI<input type="date" name="tanggal_kalibrasi" value="<?=e($edit['tanggal_kalibrasi']??'')?>"></label><label>INTERVAL KALIBRASI (BULAN)<input type="number" min="1" max="120" name="interval_kalibrasi_bulan" value="<?=e($edit['interval_kalibrasi_bulan']??12)?>"></label><label>NOMOR SERTIFIKAT<input name="nomor_sertifikat" value="<?=e($edit['nomor_sertifikat']??'')?>"></label><label>KONDISI ALAT<select name="kondisi_alat"><?php foreach(['Baik','Baik dengan catatan','Perlu pemeriksaan','Rusak'] as $x):?><option <?=$x===($edit['kondisi_alat']??'Baik')?'selected':''?>><?=$x?></option><?php endforeach;?></select></label><label>TAHUN PEROLEHAN<input type="number" min="1900" max="2100" name="tahun_perolehan" value="<?=e($edit['tahun_perolehan']??'')?>"></label><label class="span2">CATATAN<textarea name="catatan" rows="3" placeholder="Catatan teknis, pemeliharaan, atau keterangan alat..."><?=e($edit['catatan']??'')?></textarea></label><label class="check"><input type="checkbox" name="aktif" value="1" <?=(!$edit||$edit['aktif'])?'checked':''?>> Alat aktif digunakan</label><div class="actions span2"><button class="btn primary"><?= $edit?'Simpan Perubahan':'Simpan Data Alat'?></button><?php if($edit):?><a class="btn" href="alat.php">Batal</a><?php endif;?></div></form></section>
<section class="panel"><div class="panelhead"><h2>Inventaris <span class="muted">(<?=count($rows)?>)</span></h2><form class="actions" method="get"><input name="q" value="<?=e($q)?>" placeholder="Cari kode, nama, lokasi..." style="padding:8px 10px;border:1px solid #ccd8e2;border-radius:8px"><select name="filter" style="padding:8px;border:1px solid #ccd8e2;border-radius:8px"><option value="all" <?=$filter==='all'?'selected':''?>>Semua</option><option value="kalibrasi" <?=$filter==='kalibrasi'?'selected':''?>>Kalibrasi ≤ 30 hari</option><option value="rusak" <?=$filter==='rusak'?'selected':''?>>Perlu perhatian</option><option value="nonaktif" <?=$filter==='nonaktif'?'selected':''?>>Nonaktif</option></select><button class="btn">Filter</button></form></div><div class="tablewrap"><table><thead><tr><th>KODE</th><th>NAMA ALAT</th><th>LOKASI</th><th>KALIBRASI</th><th>JATUH TEMPO</th><th>KONDISI</th><th>AKTIF</th><th>AKSI</th></tr></thead><tbody><?php foreach($rows as $r):$cb=cbadge($r);?><tr><td><b><?=e($r['kode_alat'])?></b></td><td><?=e($r['nama_alat'])?><br><span class="calendar-note"><?=e($r['merk_tipe'])?></span></td><td><?=e($r['lokasi'])?></td><td><span class="badge <?=$cb[0]?>"><?=e($cb[1])?></span></td><td><?=e($r['jatuh_tempo']?date('d/m/Y',strtotime($r['jatuh_tempo'])):'-')?></td><td><span class="badge <?=strtolower($r['kondisi_alat'])==='baik'?'baik':(strtolower($r['kondisi_alat'])==='rusak'?'rusak':'catatan')?>"><?=e($r['kondisi_alat'])?></span></td><td><?=$r['aktif']?'<span class="badge aktif">Aktif</span>':'<span class="badge rusak">Nonaktif</span>'?></td><td class="actions"><a class="btn small" href="?edit=<?=$r['id']?>">Edit</a><?php if(is_admin()):?><form method="post" onsubmit="return confirm('Hapus alat ini?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn small dangerbtn">Hapus</button></form><?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="8" class="empty">Tidak ada data sesuai filter.</td></tr><?php endif;?></tbody></table></div></section>
<section class="panel"><h2>Import / Export Excel</h2><p class="hint">Format import: <b>KODE, NAMA ALAT, LOKASI, STATUS KALIBRASI, TANGGAL KALIBRASI, KONDISI ALAT</b>. Sistem juga menerima kolom tambahan <b>INTERVAL KALIBRASI (BULAN), NOMOR SERTIFIKAT, MERK / TIPE, TAHUN PEROLEHAN, CATATAN</b>. Kode yang sama akan diperbarui.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="import"><input type="file" name="file_excel" accept=".xlsx,.csv" required><button class="btn primary" style="margin-top:10px">↑ Import Data</button></form></section>
</main></div></body></html>
