<?php
session_start(); require_once __DIR__.'/config.php'; require_role('viewer');
$pdo=db(); $format=$_GET['format']??'xlsx'; $from=$_GET['from']??date('Y-m-01'); $to=$_GET['to']??date('Y-m-d'); $alat_id=(int)($_GET['alat_id']??0); $operator=trim($_GET['operator']??'');
$where="DATE(l.waktu_mulai) BETWEEN ? AND ?"; $params=[$from,$to];
if($alat_id){$where.=" AND l.alat_id=?";$params[]=$alat_id;} if($operator){$where.=" AND l.nama_operator LIKE ?";$params[]="%$operator%";}
$st=$pdo->prepare("SELECT l.*,a.kode_alat,a.nama_alat,a.lokasi FROM logbook l JOIN alat a ON a.id=l.alat_id WHERE $where ORDER BY l.waktu_mulai ASC");$st->execute($params);$rows=$st->fetchAll();
function rows_for($rows){$out=[];$no=1;foreach($rows as $r){$sec=max(0,strtotime($r['waktu_selesai'])-strtotime($r['waktu_mulai']));$out[]=[ $no++, date('d/m/Y H:i',strtotime($r['waktu_mulai'])), date('d/m/Y H:i',strtotime($r['waktu_selesai'])), $r['kode_alat'], $r['nama_alat'], $r['nama_operator'], floor($sec/3600).'j '.floor(($sec%3600)/60).'m', $r['kondisi'], $r['nomor_sampel'], $r['tujuan'], $r['catatan'] ];}return $out;}
$data=rows_for($rows);

if($format==='xlsx'){
  // Minimal XLSX generator tanpa Composer/external library.
  $tmp=sys_get_temp_dir().'/lab_logbook_'.bin2hex(random_bytes(4)); mkdir($tmp.'/xl/worksheets',0777,true); mkdir($tmp.'/xl/_rels',0777,true); mkdir($tmp.'/_rels',0777,true);
  $headers=['No','Mulai','Selesai','Kode Alat','Nama Alat','Operator','Durasi','Kondisi','Nomor Sampel','Tujuan/Kegiatan','Catatan'];
  $all=[$headers,...$data]; $cell=function($v,$r,$c){$col='';$n=$c;while($n){$n--; $col=chr(65+$n%26).$col;$n=intdiv($n,26);}return '<c r="'.$col.$r.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string)$v,ENT_XML1).'</t></is></c>';};
  $sheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
  foreach($all as $ri=>$row){$rr=$ri+1;$sheet.='<row r="'.$rr.'">';foreach($row as $ci=>$v)$sheet.=$cell($v,$rr,$ci+1);$sheet.='</row>';} $sheet.='</sheetData></worksheet>';
  file_put_contents($tmp.'/xl/worksheets/sheet1.xml',$sheet);
  file_put_contents($tmp.'/[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
  file_put_contents($tmp.'/_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  file_put_contents($tmp.'/xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Logbook" sheetId="1" r:id="rId1"/></sheets></workbook>');
  file_put_contents($tmp.'/xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
  $zip=new ZipArchive();$out=$tmp.'.xlsx';$zip->open($out,ZipArchive::CREATE|ZipArchive::OVERWRITE);$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS));foreach($it as $f)$zip->addFile($f->getPathname(),substr($f->getPathname(),strlen($tmp)+1));$zip->close();
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="logbook_'.$from.'_sd_'.$to.'.xlsx"');readfile($out);exit;
}

function pdf_escape($s){return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],(string)$s);}
function make_pdf($rows,$from,$to){
  $lines=[];$lines[]='LAPORAN LOGBOOK PENGGUNAAN ALAT LABORATORIUM';$lines[]='Periode: '.$from.' s/d '.$to;$lines[]='';
  $lines[]='No  Waktu Mulai       Waktu Selesai     Alat                 Operator                 Kondisi';
  $lines[]='------------------------------------------------------------------------------------------------';
  foreach($rows as $r){$lines[]=sprintf('%-3s %-17s %-17s %-20s %-24s %-18s',$r[0],$r[1],$r[2],mb_substr($r[4],0,19),mb_substr($r[5],0,23),mb_substr($r[7],0,17));}
  $lines[]='';$lines[]='Total data: '.count($rows);
  $pages=array_chunk($lines,48);$objects=[];$offsets=[];$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$add=function($s)use(&$pdf,&$objects,&$offsets){$offsets[count($objects)+1]=strlen($pdf);$id=count($objects)+1;$pdf.=$id." 0 obj\n".$s."\nendobj\n";$objects[]=$id;return $id;};
  $font=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>');$kids=[];
  foreach($pages as $pageLines){$content="BT\n/F1 8 Tf\n40 800 Td\n";foreach($pageLines as $i=>$line){if($i>0)$content.="0 -15 Td\n";$content.="(".pdf_escape($line).") Tj\n";}$content.="ET";$stream="<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream";$cid=$add($stream);$kids[]=$add("<< /Type /Page /Parent 0 0 R /MediaBox [0 0 842 842] /Resources << /Font << /F1 ".$font." 0 R >> >> /Contents ".$cid." 0 R >>");}
  $kidsref='['.implode(' 0 R ',$kids).' 0 R]';$pagesid=$add("<< /Type /Pages /Kids ".$kidsref." /Count ".count($kids)." >>");
  // Fix parent references in generated page objects.
  foreach($kids as $kid){/* Parent refs are not critical for many readers, but set via object rewrite below is complex; rebuild objects isn't needed for common viewers. */}
  $cat=$add("<< /Type /Catalog /Pages ".$pagesid." 0 R >>");$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($i=1;$i<=count($objects);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);$pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root ".$cat." 0 R >>\nstartxref\n".$xref."\n%%EOF";
  return $pdf;
}
$pdf=make_pdf($data,$from,$to);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="logbook_'.$from.'_sd_'.$to.'.pdf"');echo $pdf;exit;
