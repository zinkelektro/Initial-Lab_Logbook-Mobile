<?php
session_start();
require_once __DIR__.'/config.php';
require_role('petugas');
$pdo=db();

$types=[
 'sondir'=>['Sondir / CPT','SNI 2827:2008'],
 'sandcone'=>['Densitas Tanah di Tempat (Sand Cone)','SNI 2828:2011'],
 'cbr'=>['CBR Lapangan','SNI 1738:2011'],
 'dcp'=>['DCP / Penentuan CBR dengan DCP','Pedoman Cara Uji CBR dengan Dynamic Cone Penetrometer']
];
function num($v){ return is_numeric($v) ? (float)$v : null; }
function calc_test($jenis,$data){
 $hasil=[];
 if($jenis==='sondir'){
   $Ac=num($data['area_cone']??10) ?: 10; $As=num($data['area_sleeve']??150) ?: 150;
   $rows=[];
   foreach(($data['rows']??[]) as $r){
     $cw=num($r['cw']??null); $tw=num($r['tw']??null); $qc=num($r['qc']??null); $fs=num($r['fs']??null);
     $tf=null; $rf=null;
     if($qc===null && $cw!==null) $qc=$cw/$Ac;
     if($fs===null && $cw!==null && $tw!==null) $fs=($tw-$cw)/$As;
     if($qc!==null && $qc!=0 && $fs!==null) $rf=($fs/$qc)*100;
     if($cw!==null && $tw!==null) $tf=$tw-$cw;
     $r['qc']=$qc!==null?round($qc,4):''; $r['fs']=$fs!==null?round($fs,4):''; $r['rf']=$rf!==null?round($rf,2):''; $r['tf']=$tf!==null?round($tf,4):''; $rows[]=$r;
   }
   $maxqc=null; foreach($rows as $r) if(is_numeric($r['qc'])) $maxqc=$maxqc===null?(float)$r['qc']:max($maxqc,(float)$r['qc']);
   $hasil=['area_cone'=>$Ac,'area_sleeve'=>$As,'rows'=>$rows,'qc_maks'=>$maxqc,'ringkasan'=>$maxqc!==null?'qc maksimum = '.number_format($maxqc,2,',','.').' (satuan mengikuti pembacaan alat)':'Data sondir tersimpan.'];
 } elseif($jenis==='sandcone'){
   $gp=num($data['gamma_p']??null); $wt=num($data['w_tanah']??null); $w=num($data['kadar_air']??null); $wp=num($data['w_pasir']??null); $gdmax=num($data['gdmax']??null);
   $V=($gp && $wp!==null)?$wp/$gp:null; $gb=$V&&$V!=0&&$wt!==null?$wt/$V:null; $gd=$gb!==null&&$w!==null?$gb/(1+$w/100):null; $comp=$gd!==null&&$gdmax?$gd/$gdmax*100:null;
   $hasil=['volume_lubang'=>$V!==null?round($V,3):null,'berat_isi_basah'=>$gb!==null?round($gb,4):null,'gd_lap'=>$gd!==null?round($gd,4):null,'comp'=>$comp!==null?round($comp,2):null,'ringkasan'=>$comp!==null?'Derajat kepadatan = '.number_format($comp,2,',','.').' %':'Data tersimpan, hasil belum lengkap.'];
 } elseif($jenis==='cbr'){
   $unit=$data['load_unit']??'kgf'; $s254=$unit==='kN'?13.24:1350; $s508=$unit==='kN'?19.96:2035; $rows=[]; $l254=null;$l508=null;
   foreach(($data['rows']??[]) as $r){$p=num($r['penetration']??null);$dial=num($r['dial']??null);$load=num($r['load']??null);if($load===null)$load=$dial;$r['load']=$load!==null?round($load,3):'';$rows[]=$r;if($p!==null&&abs($p-2.54)<0.01)$l254=$load;if($p!==null&&abs($p-5.08)<0.01)$l508=$load;}
   $c1=$l254!==null?$l254/$s254*100:null;$c2=$l508!==null?$l508/$s508*100:null;$use=($c1!==null&&$c2!==null)?max($c1,$c2):($c1??$c2);
   $hasil=['load_unit'=>$unit,'rows'=>$rows,'cbr254'=>$c1!==null?round($c1,2):null,'cbr508'=>$c2!==null?round($c2,2):null,'cbruse'=>$use!==null?round($use,2):null,'ringkasan'=>$use!==null?'CBR digunakan = '.number_format($use,2,',','.').' %':'Data CBR tersimpan, hasil belum lengkap.'];
 } elseif($jenis==='dcp'){
   $rows=[];$cum=0;$last=0;$dcpi=[];
   foreach(($data['rows']??[]) as $r){$bl=num($r['blows']??null);$pen=num($r['penetration']??null);if($pen!==null){$cum=$pen;}else{$cum=$last;} $inc=$cum-$last;$d=($bl && $bl>0)?$inc/$bl:null;$r['cum']=$cum!==null?round($cum,2):'';$r['increment']=$inc!==null?round($inc,2):'';$r['dcpi']=$d!==null?round($d,2):'';$rows[]=$r;if($d!==null)$dcpi[]=$d;$last=$cum;}
   $avg=count($dcpi)?array_sum($dcpi)/count($dcpi):null;$hasil=['rows'=>$rows,'dcpi_avg'=>$avg!==null?round($avg,2):null,'ringkasan'=>$avg!==null?'DCPI rata-rata = '.number_format($avg,2,',','.').' mm/tumbukan':'Data DCP tersimpan, hasil belum lengkap.'];
 }
 return $hasil;
}
$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $jenis=$_POST['jenis']??''; if(!isset($types[$jenis]))throw new Exception('Jenis pengujian tidak valid.');
  $nomor=trim($_POST['nomor_uji']??'');$tanggal=$_POST['tanggal']??date('Y-m-d');$proyek=trim($_POST['proyek']??'');$lokasi=trim($_POST['lokasi']??'');$titik=trim($_POST['titik_uji']??'');$operator=trim($_POST['operator']??'');$alat=trim($_POST['alat']??'');$elevasi=trim($_POST['elevasi']??'');$cuaca=trim($_POST['cuaca']??'');$catatan=trim($_POST['catatan']??'');
  if(!$nomor||!$tanggal||!$operator)throw new Exception('Nomor uji, tanggal, dan operator wajib diisi.');
  $data=$_POST['data']??[];$hasil=calc_test($jenis,$data);
  $pdo->prepare("INSERT INTO pengujian_lapangan(jenis,nomor_uji,tanggal,proyek,lokasi,titik_uji,operator,alat,elevasi,cuaca,data_json,hasil_json,catatan,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
   ->execute([$jenis,$nomor,$tanggal,$proyek,$lokasi,$titik,$operator,$alat,$elevasi,$cuaca,json_encode($data,JSON_UNESCAPED_UNICODE),json_encode($hasil,JSON_UNESCAPED_UNICODE),$catatan,current_user()['id']]);
  $newId=(int)$pdo->lastInsertId(); $success='Data tersimpan dan hasil perhitungan otomatis sudah direkam. <a href="export_pengujian.php?id='.$newId.'&format=pdf" target="_blank">Buka PDF Resmi</a> · <a href="export_pengujian.php?id='.$newId.'&format=xlsx">Download XLSX</a>';
 }catch(Throwable $e){$error=$e->getMessage();}
}
$rows=$pdo->query("SELECT * FROM pengujian_lapangan ORDER BY id DESC LIMIT 20")->fetchAll();
?>
<!doctype html><html lang="id"><head>
<meta name="lab-user" content="<?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'guest' ?>">
<link rel="manifest" href="/mobile/manifest.webmanifest">
<meta name="theme-color" content="#17365d">
<script defer src="/mobile/mobile.js"></script><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pengujian Lapangan</title><link rel="stylesheet" href="assets/style.css"><style>.calcbox{margin-top:14px;padding:14px;border:1px solid #dbe4ec;border-radius:10px;background:#f8fafc}.calcbox b{font-size:16px}.formgrid{align-items:end}.muted{color:#64748b;font-size:12px}.actionrow{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.btn.small{padding:7px 10px;font-size:12px}.tablewrap input{min-width:82px}</style></head><body><div class="app-shell"><?php $sidebar_base="";include __DIR__.'/sidebar.php';?><main class="container">
<div class="pagehead"><div><div class="eyebrow">FIELD TESTING</div><h1>Pengujian Lapangan</h1><p>Isi data lapangan. Parameter perhitungan diisi otomatis berdasarkan metode pengujian yang dipilih.</p></div></div>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert success"><?=$success?></div><?php endif;?>
<section class="panel"><div class="formgrid"><label>Jenis Pengujian<select id="jenis" onchange="renderForm()"><?php foreach($types as $k=>$v):?><option value="<?=$k?>"><?=e($v[0])?> · <?=e($v[1])?></option><?php endforeach;?></select></label><div class="hint" id="standardHint"></div></div><hr style="border:0;border-top:1px solid #edf2f6;margin:18px 0">
<form method="post" id="testForm"><input type="hidden" name="jenis" id="jenisHidden"><div class="formgrid"><label>Nomor Uji<input name="nomor_uji" required placeholder="Contoh: SL-001"></label><label>Tanggal<input type="date" name="tanggal" value="<?=date('Y-m-d')?>" required></label><label>Nama Proyek<input name="proyek"></label><label>Lokasi / STA<input name="lokasi"></label><label>Titik Uji<input name="titik_uji"></label><label>Operator / Teknisi<input name="operator" required></label><label>Alat / No. Identifikasi<input name="alat"></label><label>Elevasi / BM<input name="elevasi"></label><label>Cuaca<input name="cuaca"></label></div><div id="dynamic" style="margin-top:18px"></div><label style="display:block;margin-top:14px;font-weight:700;font-size:11px;color:#4c6074">Catatan / Penyimpangan<textarea name="catatan" rows="3"></textarea></label><div class="actionrow"><button class="btn primary" type="submit">💾 Simpan & Hitung</button><button class="btn" type="reset" onclick="setTimeout(renderForm,0)">Reset Form</button></div></form></section>
<section class="panel"><div class="panelhead"><h2>Data Pengujian Tersimpan</h2><a class="btn" href="export_pengujian.php?format=xlsx">Export Semua XLSX</a></div><div class="tablewrap"><table><thead><tr><th>No Uji</th><th>Jenis</th><th>Tanggal</th><th>Lokasi</th><th>Operator</th><th>Hasil</th><th>File</th></tr></thead><tbody><?php foreach($rows as $r):$h=json_decode($r['hasil_json']?:'{}',true)?:[];?><tr><td><?=e($r['nomor_uji'])?></td><td><?=e($types[$r['jenis']][0]??$r['jenis'])?></td><td><?=e(date('d/m/Y',strtotime($r['tanggal'])))?></td><td><?=e(trim($r['lokasi'].' '.$r['titik_uji']))?></td><td><?=e($r['operator'])?></td><td><?=e($h['ringkasan']??'Tersimpan')?></td><td><a class="btn small" href="export_pengujian.php?id=<?=(int)$r['id']?>&format=pdf" target="_blank">PDF</a> <a class="btn small" href="export_pengujian.php?id=<?=(int)$r['id']?>&format=xlsx">XLSX</a> <button type="button" class="btn small" onclick="window.print()">Cetak</button></td></tr><?php endforeach;?></tbody></table></div></section>
</main></div>
<script>
const cfg={
sondir:{std:'SNI 2827:2008',html:`<h2>Sondir / CPT</h2><div class="formgrid"><label>Luas konus Ac (cm²)<input type="number" step="0.01" name="data[area_cone]" value="10"></label><label>Luas selimut geser As (cm²)<input type="number" step="0.01" name="data[area_sleeve]" value="150"></label></div><p class="muted">qc, fs, Rf dan Tf dihitung otomatis dari pembacaan Cw/Tw dan luas alat yang dimasukkan. Pastikan satuan pembacaan dan luas alat sesuai sertifikat/metode laboratorium.</p><div class="tablewrap"><table id="dataTable"><thead><tr><th>Kedalaman (m)</th><th>Cw / Konus</th><th>Tw / Konus+Geser</th><th>qc</th><th>fs</th><th>Rf (%)</th><th>Tf</th></tr></thead><tbody></tbody></table></div><button type="button" class="btn" onclick="addRow('sondir')">+ Tambah Pembacaan</button></div>`},
sandcone:{std:'SNI 2828:2011',html:`<h2>Sand Cone</h2><div class="formgrid"><label>Berat isi pasir γp (g/cm³)<input type="number" step="0.0001" name="data[gamma_p]" id="gp"></label><label>Berat tanah basah (g)<input type="number" step="0.01" name="data[w_tanah]" id="wt"></label><label>Kadar air w (%)<input type="number" step="0.01" name="data[kadar_air]" id="wa"></label><label>Berat pasir dalam lubang (g)<input type="number" step="0.01" name="data[w_pasir]" id="wp"></label><label>γd maksimum (g/cm³)<input type="number" step="0.0001" name="data[gdmax]" id="gm"></label><label>Kadar air optimum (%)<input type="number" step="0.01" name="data[wopt]"></label></div><div class="calcbox">Volume lubang: <b id="v_lubang">-</b> cm³ · γd lapangan: <b id="gd_lap">-</b> g/cm³ · Derajat kepadatan: <b id="comp">-</b> %</div><button type="button" class="btn" onclick="calcSand()">Hitung</button>`},
cbr:{std:'SNI 1738:2011',html:`<h2>CBR Lapangan</h2><div class="formgrid"><label>Satuan beban<select name="data[load_unit]" id="loadUnit"><option value="kgf">kgf</option><option value="kN">kN</option></select></label></div><p class="muted">Masukkan beban terkoreksi hasil pembacaan. Beban standar pada 2,54 mm dan 5,08 mm digunakan untuk menghitung CBR. Koreksi kurva penetrasi dan kalibrasi proving ring harus mengikuti prosedur alat/laboratorium.</p><div class="tablewrap"><table id="cbrTable"><thead><tr><th>Penetrasi (mm)</th><th>Pembacaan</th><th>Beban terkoreksi</th></tr></thead><tbody></tbody></table></div><button type="button" class="btn" onclick="addRow('cbr')">+ Tambah Pembacaan</button><div class="calcbox">CBR 2,54 mm: <b id="cbr254">-</b> % · CBR 5,08 mm: <b id="cbr508">-</b> % · CBR digunakan: <b id="cbruse">-</b> %</div><button type="button" class="btn" onclick="calcCBR()">Hitung CBR</button>`},
dcp:{std:'Pedoman Cara Uji CBR dengan Dynamic Cone Penetrometer',html:`<h2>DCP</h2><p class="muted">Sistem menghitung penetrasi kumulatif dan DCPI. Nilai CBR korelasi tidak dipaksakan karena persamaan korelasi harus dipilih sesuai pedoman/proyek yang berlaku.</p><div class="tablewrap"><table id="dcpTable"><thead><tr><th>Lapisan</th><th>Tumbukan</th><th>Penetrasi kumulatif (mm)</th><th>Penetrasi/increment (mm)</th><th>DCPI (mm/tumbukan)</th></tr></thead><tbody></tbody></table></div><button type="button" class="btn" onclick="addRow('dcp')">+ Tambah Pembacaan</button><div class="calcbox">DCPI rata-rata: <b id="dcpi">-</b> mm/tumbukan</div><button type="button" class="btn" onclick="calcDCP()">Hitung DCP</button>`}
};
function renderForm(){const t=document.getElementById('jenis').value;document.getElementById('jenisHidden').value=t;document.getElementById('standardHint').innerHTML='<b>Standar/acuan:</b> '+cfg[t].std;document.getElementById('dynamic').innerHTML=cfg[t].html;if(t==='sondir')addRow(t);if(t==='cbr')addRow(t);if(t==='dcp')addRow(t);}
function inp(field,type='number'){return `<input type="${type}" step="any" name="data[rows][${field.i}][${field.k}]">`}
function addRow(t){let tb=document.querySelector('#dataTable tbody, #cbrTable tbody, #dcpTable tbody');let i=tb.rows.length,tr=tb.insertRow();if(t==='sondir')tr.innerHTML=`<td>${inp({i,k:'depth'})}</td><td>${inp({i,k:'cw'})}</td><td>${inp({i,k:'tw'})}</td><td><input readonly></td><td><input readonly></td><td><input readonly></td><td><input readonly></td>`;if(t==='cbr')tr.innerHTML=`<td>${inp({i,k:'penetration'})}</td><td>${inp({i,k:'dial'})}</td><td>${inp({i,k:'load'})}</td>`;if(t==='dcp')tr.innerHTML=`<td><input name="data[rows][${i}][layer]"></td><td>${inp({i,k:'blows'})}</td><td>${inp({i,k:'penetration'})}</td><td><input readonly></td><td><input readonly></td>`;tb.querySelectorAll('input').forEach(x=>x.addEventListener('input',()=>{let t=document.getElementById('jenis').value;if(t==='sondir')calcSondir();if(t==='sandcone')calcSand();if(t==='cbr')calcCBR();if(t==='dcp')calcDCP();}));}
function valCell(tr,i){return parseFloat(tr.cells[i].querySelector('input').value)}
function setCell(tr,i,v){tr.cells[i].querySelector('input').value=(Number.isFinite(v)?v.toFixed(3):'');}
function calcSondir(){let ac=+document.querySelector('[name="data[area_cone]"]')?.value||10,as=+document.querySelector('[name="data[area_sleeve]"]')?.value||150;document.querySelectorAll('#dataTable tbody tr').forEach(tr=>{let cw=valCell(tr,1),tw=valCell(tr,2);let qc=Number.isFinite(cw)?cw/ac:NaN,fs=Number.isFinite(cw)&&Number.isFinite(tw)?(tw-cw)/as:NaN,rf=Number.isFinite(qc)&&qc?fs/qc*100:NaN,tf=Number.isFinite(cw)&&Number.isFinite(tw)?tw-cw:NaN;setCell(tr,3,qc);setCell(tr,4,fs);setCell(tr,5,rf);setCell(tr,6,tf);});}
function calcSand(){let gp=+document.getElementById('gp')?.value,wt=+document.getElementById('wt')?.value,wa=+document.getElementById('wa')?.value,wp=+document.getElementById('wp')?.value,gm=+document.getElementById('gm')?.value;if(gp>0&&wp>=0){let V=wp/gp;document.getElementById('v_lubang').textContent=V.toFixed(2);if(wt>=0&&wa>=0){let gb=wt/V,gd=gb/(1+wa/100);document.getElementById('gd_lap').textContent=gd.toFixed(4);document.getElementById('comp').textContent=gm>0?(gd/gm*100).toFixed(2):'-';}}}
function calcCBR(){let unit=document.getElementById('loadUnit')?.value||'kgf',s1=unit==='kN'?13.24:1350,s2=unit==='kN'?19.96:2035,l1=null,l2=null;document.querySelectorAll('#cbrTable tbody tr').forEach(tr=>{let p=valCell(tr,0),l=valCell(tr,2);if(Number.isFinite(p)&&Number.isFinite(l)){if(Math.abs(p-2.54)<.01)l1=l;if(Math.abs(p-5.08)<.01)l2=l;}});let c1=l1!==null?l1/s1*100:NaN,c2=l2!==null?l2/s2*100:NaN;document.getElementById('cbr254').textContent=Number.isFinite(c1)?c1.toFixed(2):'-';document.getElementById('cbr508').textContent=Number.isFinite(c2)?c2.toFixed(2):'-';document.getElementById('cbruse').textContent=Number.isFinite(c1)&&Number.isFinite(c2)?Math.max(c1,c2).toFixed(2):(Number.isFinite(c1)?c1.toFixed(2):(Number.isFinite(c2)?c2.toFixed(2):'-'));}
function calcDCP(){let last=0,ds=[];document.querySelectorAll('#dcpTable tbody tr').forEach(tr=>{let b=valCell(tr,1),cum=valCell(tr,2),inc=Number.isFinite(cum)?cum-last:NaN,d=Number.isFinite(inc)&&b>0?inc/b:NaN;setCell(tr,3,inc);setCell(tr,4,d);if(Number.isFinite(d))ds.push(d);if(Number.isFinite(cum))last=cum;});document.getElementById('dcpi').textContent=ds.length?(ds.reduce((a,b)=>a+b,0)/ds.length).toFixed(2):'-';}
renderForm();
</script></body></html>
