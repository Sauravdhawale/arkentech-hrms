<?php
require dirname(__DIR__).'/includes/attendance/xlsx.php';
function expect(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function workbook(string $cells,bool $date1904=false):string {
 $file=tempnam(sys_get_temp_dir(),'att-xlsx-');$zip=new ZipArchive();$zip->open($file,ZipArchive::OVERWRITE);
 $zip->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr date1904="'.($date1904?'1':'0').'"/><sheets><sheet name="Attendance" sheetId="1" r:id="rId1"/></sheets></workbook>');
 $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet2.xml"/></Relationships>');
 $zip->addFromString('xl/sharedStrings.xml','<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>00123</t></si></sst>');
 $head='';foreach(['A'=>'employee_id','B'=>'date','C'=>'check_in','D'=>'check_out'] as $col=>$label)$head.='<c r="'.$col.'1" t="inlineStr"><is><t>'.$label.'</t></is></c>';
 $zip->addFromString('xl/worksheets/sheet2.xml','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$head.'</row><row r="2">'.$cells.'</row></sheetData></worksheet>');$zip->close();return $file;
}
set_error_handler(function($n,$message,$file,$line){throw new ErrorException($message,0,$n,$file,$line);});
$file=workbook('<c r="A2" t="s"><v>0</v></c><c r="B2"><v>46029</v></c><c r="C2"><v>0.375</v></c><c r="D2"><v>0.75</v></c>');
try{$rows=att_xlsx_rows($file);expect($rows[0]===['employee_id'=>'00123','date'=>'2026-01-07','check_in'=>'09:00:00','check_out'=>'18:00:00'],'Excel serial date, clock time and leading-zero ID');}finally{unlink($file);}
$file=workbook('<c r="A2" t="inlineStr"><is><t>AT01</t></is></c><c r="B2"><v>0</v></c><c r="C2"><v>0.5</v></c>',true);
try{$rows=att_xlsx_rows($file);expect($rows[0]['date']==='1904-01-01'&&$rows[0]['check_out']==='','1904 dates and empty optional checkout');}finally{unlink($file);}
$file=workbook('<c r="A2"><f>1+1</f><v>2</v></c>');
try{$failed=false;try{att_xlsx_rows($file);}catch(InvalidArgumentException $e){$failed=true;}expect($failed,'Reject formulas');}finally{unlink($file);}
echo "PASS: XLSX first-sheet relationship, text IDs, Excel dates/times, empty checkout and formula rejection.\n";
