<?php
session_start();
require_once __DIR__.'/config.php';
require_role('viewer');
$pdo=db();
$types=[
 'sondir'=>['Sondir / CPT','SNI 2827:2008'],
 'sandcone'=>['Densitas Tanah di Tempat (Sand Cone)','SNI 2828:2011'],
 'cbr'=>['CBR Lapangan','SNI 1738:2011'],
 'dcp'=>['DCP / Penentuan CBR dengan DCP','Pedoman Cara Uji CBR dengan Dynamic Cone Penetrometer']
];
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function niceDate($d){return $d?date('d/m/Y',strtotime($d)):'-';}
function val($a,$k,$d='-'){return isset($a[$k])&&$a[$k]!==''?$a[$k]:$d;}
function labv($k,$fallback){return defined($k)?constant($k):$fallback;}
$id=(int)($_GET['id']??0);$format=$_GET['format']??'pdf';
if($id){$st=$pdo->prepare('SELECT * FROM pengujian_lapangan WHERE id=?');$st->execute([$id]);$row=$st->fetch();if(!$row){http_response_code(404);exit('Data pengujian tidak ditemukan.');}}
else {$row=null;}

function xlsx_download($sheets,$filename){
    $base = sys_get_temp_dir().'/xlsx_'.bin2hex(random_bytes(6));
    if (!mkdir($base.'/xl/worksheets',0777,true)) {
        http_response_code(500);
        exit('Gagal membuat folder sementara XLSX.');
    }
    mkdir($base.'/xl/_rels',0777,true);
    mkdir($base.'/_rels',0777,true);
    mkdir($base.'/docProps',0777,true);

    $col = function($n){
        $s='';
        while($n>0){
            $n--;
            $s=chr(65+($n%26)).$s;
            $n=intdiv($n,26);
        }
        return $s;
    };

    $sheetNames = array_keys($sheets);

    foreach($sheets as $si=>$sheetRows){
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml.='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml.='<sheetData>';

        foreach($sheetRows as $ri=>$r){
            $rowNo=$ri+1;
            $xml.='<row r="'.$rowNo.'">';

            foreach(array_values($r) as $ci=>$v){
                $ref=$col($ci+1).$rowNo;
                $value=htmlspecialchars(
                    (string)$v,
                    ENT_XML1|ENT_COMPAT,
                    'UTF-8'
                );

                $xml.='<c r="'.$ref.'" t="inlineStr">';
                $xml.='<is><t xml:space="preserve">'.$value.'</t></is>';
                $xml.='</c>';
            }

            $xml.='</row>';
        }

        $xml.='</sheetData>';
        $xml.='</worksheet>';

        file_put_contents(
            $base.'/xl/worksheets/sheet'.($si+1).'.xml',
            $xml
        );
    }

    $rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $rels.='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $rels.='<Relationship Id="rId1" ';
    $rels.='Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" ';
    $rels.='Target="xl/workbook.xml"/>';
    $rels.='</Relationships>';

    file_put_contents($base.'/_rels/.rels',$rels);

    $wb='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $wb.='<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ';
    $wb.='xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $wb.='<sheets>';

    foreach($sheetNames as $i=>$name){
        $safeName=htmlspecialchars(
            substr((string)$name,0,31),
            ENT_XML1|ENT_COMPAT,
            'UTF-8'
        );

        $wb.='<sheet name="'.$safeName.'" ';
        $wb.='sheetId="'.($i+1).'" ';
        $wb.='r:id="rId'.($i+1).'"/>';
    }

    $wb.='</sheets></workbook>';

    file_put_contents($base.'/xl/workbook.xml',$wb);

    $wbr='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $wbr.='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

    foreach($sheetNames as $i=>$name){
        $wbr.='<Relationship Id="rId'.($i+1).'" ';
        $wbr.='Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" ';
        $wbr.='Target="worksheets/sheet'.($i+1).'.xml"/>';
    }

    $wbr.='</Relationships>';

    file_put_contents($base.'/xl/_rels/workbook.xml.rels',$wbr);

    $ct='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $ct.='<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
    $ct.='<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
    $ct.='<Default Extension="xml" ContentType="application/xml"/>';
    $ct.='<Override PartName="/xl/workbook.xml" ';
    $ct.='ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';

    foreach($sheetNames as $i=>$name){
        $ct.='<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml" ';
        $ct.='ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }

    $ct.='</Types>';

    file_put_contents($base.'/[Content_Types].xml',$ct);

    $app='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $app.='<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" ';
    $app.='xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">';
    $app.='<Application>Logbook Laboratorium</Application>';
    $app.='</Properties>';

    file_put_contents($base.'/docProps/app.xml',$app);

    $out=$base.'.xlsx';

    $zip=new ZipArchive();
    $result=$zip->open(
        $out,
        ZipArchive::CREATE|ZipArchive::OVERWRITE
    );

    if($result!==true){
        http_response_code(500);
        exit('Gagal membuat file XLSX. Kode ZIP: '.$result);
    }

    $it=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $base,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach($it as $f){
        if(!$f->isFile()) continue;

        $local=substr(
            $f->getPathname(),
            strlen($base)+1
        );

        $zip->addFile($f->getPathname(),$local);
    }

    if(!$zip->close()){
        http_response_code(500);
        exit('Gagal menutup file XLSX.');
    }

    if(!is_file($out) || filesize($out)<100){
        http_response_code(500);
        exit('File XLSX tidak berhasil dibuat.');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header(
        'Content-Disposition: attachment; filename="'.
        preg_replace('/[^A-Za-z0-9_.-]/','_',$filename).
        '"'
    );
    header('Content-Length: '.filesize($out));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($out);

    @unlink($out);

    $it=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $base,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach($it as $f){
        if($f->isDir()) @rmdir($f->getPathname());
        else @unlink($f->getPathname());
    }

    @rmdir($base);

    exit;
}
function pdf_download($row,$types){
 $jenis=$row['jenis'];$data=json_decode($row['data_json']?:'{}',true)?:[];$hasil=json_decode($row['hasil_json']?:'{}',true)?:[];$title=$types[$jenis][0]??$jenis;$standard=$types[$jenis][1]??'';
 $logo=__DIR__.'/assets/logo-lab.png';$logoSrc=is_file($logo)?'file://'.str_replace(' ','%20',$logo):'';
 $meta=[['Nomor Uji',$row['nomor_uji']],['Jenis Pengujian',$title],['Standar / Acuan',$standard],['Tanggal Pengujian',niceDate($row['tanggal'])],['Nama Proyek',$row['proyek']],['Lokasi / STA',$row['lokasi']],['Titik Uji',$row['titik_uji']],['Elevasi / BM',$row['elevasi']],['Cuaca',$row['cuaca']],['Alat / No. Identifikasi',$row['alat']],['Operator / Teknisi',$row['operator']]];
 ob_start();?><!doctype html><html lang="id"><head><meta charset="utf-8"><style>@page{size:A4 portrait;margin:14mm 13mm 18mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;font-size:9pt;color:#111;line-height:1.3;margin:0}.kop{display:flex;border-bottom:3px solid #111;padding-bottom:7px}.logo{width:70px;text-align:center}.logo img{max-width:60px;max-height:55px}.ph{border:1px solid #777;padding:14px 3px;font-size:7px}.koptext{flex:1;text-align:center}.inst{font-weight:bold;font-size:10pt}.lab{font-weight:800;font-size:14pt}.addr{font-size:8pt}.title{text-align:center;font-weight:800;font-size:13pt;margin:10px 0 2px}.sub{text-align:center;margin-bottom:9px}.box{border:1px solid #222}.meta{border-collapse:collapse;width:100%}.meta td{border-bottom:1px solid #ccc;padding:4px 6px}.meta tr:last-child td{border:0}.meta td:first-child{width:30%;font-weight:bold;background:#f1f1f1}.sect{font-weight:bold;background:#e9e9e9;border:1px solid #222;padding:5px 6px;margin-top:9px}.tbl{width:100%;border-collapse:collapse;font-size:8pt}.tbl th,.tbl td{border:1px solid #222;padding:3.5px 4px;text-align:center}.tbl th{background:#e5e5e5}.left{text-align:left!important}.result,.note{border:1px solid #222;padding:7px;min-height:36px}.sign{display:flex;gap:20px;margin-top:22px;break-inside:avoid}.sig{width:50%;text-align:center}.space{height:58px}.line{border-top:1px solid #111;width:80%;margin:auto}.small{font-size:7.5pt}.foot{position:fixed;bottom:-10mm;left:0;right:0;text-align:center;font-size:7pt;border-top:1px solid #aaa;padding-top:3px;color:#555}thead{display:table-header-group}tr{page-break-inside:avoid}</style></head><body>
 <div class="kop"><div class="logo"><?php if($logoSrc):?><img src="<?=h($logoSrc)?>"><?php else:?><div class="ph">LOGO<br>LAB</div><?php endif;?></div><div class="koptext"><div class="inst"><?=h(labv('LAB_INSTITUTION','INSTANSI / UNIT KERJA'))?></div><div class="lab"><?=h(labv('LAB_NAME','LABORATORIUM PENGUJIAN MATERIAL'))?></div><div class="addr"><?=h(labv('LAB_ADDRESS','Alamat Laboratorium'))?></div><?php if(labv('LAB_CONTACT','')):?><div class="addr"><?=h(labv('LAB_CONTACT',''))?></div><?php endif;?></div></div>
 <div class="title">LAPORAN HASIL PENGUJIAN LAPANGAN</div><div class="sub"><?=h($title)?> · <?=h($standard)?></div><div class="box"><table class="meta"><?php foreach($meta as $m):?><tr><td><?=h($m[0])?></td><td><?=h($m[1]?:'-')?></td></tr><?php endforeach;?></table></div>
 <div class="sect">DATA PEMBACAAN DAN PERHITUNGAN</div>
 <?php if($jenis==='sondir'):?><table class="tbl"><thead><tr><th>No</th><th>Kedalaman (m)</th><th>Cw</th><th>Tw</th><th>qc</th><th>fs</th><th>Rf (%)</th><th>Tf</th></tr></thead><tbody><?php foreach(($hasil['rows']??$data['rows']??[]) as $i=>$r):?><tr><td><?=$i+1?></td><td><?=h(val($r,'depth'))?></td><td><?=h(val($r,'cw'))?></td><td><?=h(val($r,'tw'))?></td><td><?=h(val($r,'qc'))?></td><td><?=h(val($r,'fs'))?></td><td><?=h(val($r,'rf'))?></td><td><?=h(val($r,'tf'))?></td></tr><?php endforeach;?></tbody></table><p><b>Parameter alat:</b> Ac = <?=h(val($hasil,'area_cone','-'))?> cm²; As = <?=h(val($hasil,'area_sleeve','-'))?> cm².</p>
 <?php elseif($jenis==='sandcone'):?><table class="tbl"><tr><th>Parameter</th><th>Nilai</th><th>Satuan</th></tr><?php foreach([['Berat isi pasir γp',val($data,'gamma_p'),'g/cm³'],['Berat tanah basah',val($data,'w_tanah'),'g'],['Kadar air',val($data,'kadar_air'),'%'],['Berat pasir dalam lubang',val($data,'w_pasir'),'g'],['Volume lubang',val($hasil,'volume_lubang'),'cm³'],['Berat isi basah',val($hasil,'berat_isi_basah'),'g/cm³'],['Berat isi kering lapangan',val($hasil,'gd_lap'),'g/cm³'],['γd maksimum',val($data,'gdmax'),'g/cm³'],['Derajat kepadatan',val($hasil,'comp'),'%']] as $r):?><tr><td class="left"><?=h($r[0])?></td><td><?=h($r[1])?></td><td><?=h($r[2])?></td></tr><?php endforeach;?></table>
 <?php elseif($jenis==='cbr'):?><table class="tbl"><thead><tr><th>No</th><th>Penetrasi (mm)</th><th>Pembacaan</th><th>Beban terkoreksi</th></tr></thead><tbody><?php foreach(($hasil['rows']??$data['rows']??[]) as $i=>$r):?><tr><td><?=$i+1?></td><td><?=h(val($r,'penetration'))?></td><td><?=h(val($r,'dial'))?></td><td><?=h(val($r,'load'))?></td></tr><?php endforeach;?></tbody></table><table class="tbl" style="margin-top:7px"><tr><th>CBR 2,54 mm</th><th>CBR 5,08 mm</th><th>CBR digunakan</th></tr><tr><td><?=h(val($hasil,'cbr254'))?> %</td><td><?=h(val($hasil,'cbr508'))?> %</td><td><b><?=h(val($hasil,'cbruse'))?> %</b></td></tr></table>
 <?php elseif($jenis==='dcp'):?><table class="tbl"><thead><tr><th>No</th><th>Lapisan</th><th>Tumbukan</th><th>Penetrasi kumulatif (mm)</th><th>Increment (mm)</th><th>DCPI (mm/tumbukan)</th></tr></thead><tbody><?php foreach(($hasil['rows']??$data['rows']??[]) as $i=>$r):?><tr><td><?=$i+1?></td><td><?=h(val($r,'layer'))?></td><td><?=h(val($r,'blows'))?></td><td><?=h(val($r,'cum'))?></td><td><?=h(val($r,'increment'))?></td><td><?=h(val($r,'dcpi'))?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
 <div class="sect">HASIL AKHIR / KESIMPULAN</div><div class="result"><?=nl2br(h(val($hasil,'ringkasan','Data hasil pengujian tersimpan.')))?></div><div class="sect">CATATAN / PENYIMPANGAN</div><div class="note"><?=nl2br(h($row['catatan']?:'Tidak ada catatan.'))?></div>
 <div class="sign"><div class="sig">Diperiksa oleh,<div class="space"></div><div class="line"></div><b><?=h(labv('LAB_CHECKER','Nama Pemeriksa'))?></b><div class="small">Pemeriksa / Reviewer</div></div><div class="sig"><?=h(labv('LAB_CITY','Mataram'))?>, <?=niceDate($row['tanggal'])?><br><?=h(labv('LAB_HEAD_TITLE','Kepala Laboratorium'))?>,<div class="space"></div><div class="line"></div><b><?=h(labv('LAB_HEAD','Nama Kepala Laboratorium'))?></b><div class="small"><?=h(labv('LAB_HEAD_TITLE','Kepala Laboratorium'))?></div></div></div>
 <div class="foot">Dokumen hasil pengujian · Nomor Uji <?=h($row['nomor_uji'])?> · Dicetak <?=date('d/m/Y H:i')?> WITA</div></body></html><?php
 $html=ob_get_clean();$tmp=tempnam(sys_get_temp_dir(),'rep_').'.html';$pdf=$tmp.'.pdf';file_put_contents($tmp,$html);$chrome=trim((string)shell_exec('command -v chromium || command -v chromium-browser || true'));if(!$chrome){http_response_code(500);exit('Chromium belum terpasang. Jalankan: apt install -y chromium');}$cmd=escapeshellarg($chrome).' --headless --no-sandbox --disable-gpu --disable-dev-shm-usage --no-pdf-header-footer --print-to-pdf='.escapeshellarg($pdf).' '.escapeshellarg('file://'.$tmp).' 2>/dev/null';exec($cmd,$o,$code);if($code!==0||!is_file($pdf)){@unlink($tmp);http_response_code(500);exit('Gagal membuat PDF.');}header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="Laporan_'.$row['nomor_uji'].'.pdf"');header('Content-Length: '.filesize($pdf));readfile($pdf);@unlink($tmp);@unlink($pdf);exit;
}

if($id){$jenis=$row['jenis'];$data=json_decode($row['data_json']?:'{}',true)?:[];$hasil=json_decode($row['hasil_json']?:'{}',true)?:[];$s1=[['LAPORAN HASIL PENGUJIAN LAPANGAN'],['Nomor Uji',$row['nomor_uji']],['Jenis',$types[$jenis][0]??$jenis],['Standar',$types[$jenis][1]??''],['Tanggal',niceDate($row['tanggal'])],['Proyek',$row['proyek']],['Lokasi',$row['lokasi']],['Titik Uji',$row['titik_uji']],['Operator',$row['operator']],[],['PARAMETER','NILAI','SATUAN']];if($jenis==='sandcone'){$s1=array_merge($s1,[['Berat isi pasir',val($data,'gamma_p'),'g/cm³'],['Berat tanah basah',val($data,'w_tanah'),'g'],['Kadar air',val($data,'kadar_air'),'%'],['Berat pasir lubang',val($data,'w_pasir'),'g'],['Volume lubang',val($hasil,'volume_lubang'),'cm³'],['Berat isi kering',val($hasil,'gd_lap'),'g/cm³'],['Derajat kepadatan',val($hasil,'comp'),'%']]);}elseif($jenis==='cbr'){$s1=array_merge($s1,[['CBR 2,54 mm',val($hasil,'cbr254'),'%'],['CBR 5,08 mm',val($hasil,'cbr508'),'%'],['CBR digunakan',val($hasil,'cbruse'),'%']]);}elseif($jenis==='dcp'){$s1[]=['DCPI rata-rata',val($hasil,'dcpi_avg'),'mm/tumbukan'];}elseif($jenis==='sondir'){$s1[]=['qc maksimum',val($hasil,'qc_maks'),'satuan mengikuti pembacaan'];}$s1[]=[];$s1[]=['KESIMPULAN',val($hasil,'ringkasan')];$rowsheet=[['No','Kedalaman/Penetrasi','Pembacaan','Hasil 1','Hasil 2','Hasil 3']];foreach(($hasil['rows']??$data['rows']??[]) as $i=>$r){$rowsheet[]=[$i+1,val($r,'depth',val($r,'penetration',val($r,'layer'))),val($r,'cw',val($r,'dial',val($r,'blows'))),val($r,'tw',val($r,'qc',val($r,'cum'))),val($r,'fs',val($r,'dcpi')),val($r,'rf',val($r,'tf',val($r,'load')))];}xlsx_download(['Laporan'=>$s1,'Data Pembacaan'=>$rowsheet],'Laporan_'.$row['nomor_uji'].'.xlsx');}

$rows=$pdo->query('SELECT * FROM pengujian_lapangan ORDER BY tanggal DESC,id DESC')->fetchAll();$sheet=[['No Uji','Jenis Pengujian','Standar','Tanggal','Proyek','Lokasi','Titik Uji','Operator','Alat','Elevasi/BM','Cuaca','Kesimpulan']];foreach($rows as $r){$h=json_decode($r['hasil_json']?:'{}',true)?:[];$sheet[]=[$r['nomor_uji'],$types[$r['jenis']][0]??$r['jenis'],$types[$r['jenis']][1]??'',niceDate($r['tanggal']),$r['proyek'],$r['lokasi'],$r['titik_uji'],$r['operator'],$r['alat'],$r['elevasi'],$r['cuaca'],val($h,'ringkasan')];}xlsx_download(['Rekap Pengujian'=>$sheet],'pengujian_lapangan.xlsx');
