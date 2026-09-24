<?php
/** Read the first XLSX worksheet without executing formulas or external links. */
function att_xlsx_rows(string $file):array {
 if(!class_exists('ZipArchive')||!function_exists('simplexml_load_string'))throw new InvalidArgumentException('XLSX requires PHP zip and SimpleXML. You can also upload CSV.');
 $zip=new ZipArchive();if($zip->open($file)!==true)throw new InvalidArgumentException('Upload a valid XLSX workbook.');
 try {
  $xml=function(string $path)use($zip){
   $stat=$zip->statName($path);if(!$stat||$stat['size']>10000000)throw new InvalidArgumentException('Workbook XML is missing or too large.');
   $text=$zip->getFromName($path);if($text===false||stripos($text,'<!DOCTYPE')!==false)throw new InvalidArgumentException('Unsupported workbook XML.');
   $old=libxml_use_internal_errors(true);
   try{$node=simplexml_load_string($text,'SimpleXMLElement',LIBXML_NONET);if($node===false)throw new InvalidArgumentException('Invalid workbook XML.');return $node;}
   finally{libxml_clear_errors();libxml_use_internal_errors($old);}
  };
  $book=$xml('xl/workbook.xml');$date1904=in_array((string)$book->workbookPr['date1904'],['1','true'],true);
  $sheetId=(string)$book->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
  $target='';foreach($xml('xl/_rels/workbook.xml.rels')->Relationship as $rel)if((string)$rel['Id']===$sheetId&&(string)$rel['TargetMode']!=='External')$target=(string)$rel['Target'];
  if(str_starts_with($target,'/xl/'))$path=ltrim($target,'/');else $path='xl/'.$target;
  if(!preg_match('~^xl/worksheets/[A-Za-z0-9_.-]+\\.xml$~D',$path))throw new InvalidArgumentException('The first worksheet could not be read.');
  $strings=[];if($zip->locateName('xl/sharedStrings.xml')!==false)foreach($xml('xl/sharedStrings.xml')->si as $si){$text='';foreach($si->xpath('.//*[local-name()="t"]') as $t)$text.=(string)$t;$strings[]=$text;}
  $rows=[];$headers=null;
  foreach($xml($path)->sheetData->row as $row){
   $cells=[];$numeric=[];
   foreach($row->c as $cell){
    if(isset($cell->f))throw new InvalidArgumentException('Use values only; formulas are not accepted in attendance uploads.');
    if(!preg_match('/^([A-Z]{1,3})[0-9]+$/D',(string)$cell['r'],$m))throw new InvalidArgumentException('Invalid worksheet cell.');
    $col=$m[1];$kind=(string)$cell['t'];$value=(string)$cell->v;
    if($kind==='s')$value=$strings[(int)$value]??'';
    elseif($kind==='inlineStr'){$value='';foreach($cell->xpath('.//*[local-name()="t"]') as $t)$value.=(string)$t;}
    elseif($kind==='e')throw new InvalidArgumentException('Resolve Excel cell errors before uploading.');
    $cells[$col]=trim($value);$numeric[$col]=($kind===''||$kind==='n')&&is_numeric($value);
   }
   if(!array_filter($cells,fn($v)=>$v!==''))continue;
   if($headers===null){$headers=array_map(fn($v)=>strtolower(trim($v)),$cells);if(count($headers)!==count(array_unique($headers))||array_diff(['employee_id','date','check_in','check_out'],$headers))throw new InvalidArgumentException('Required columns: employee_id,date,check_in,check_out.');continue;}
   if(count($rows)>=1000)throw new InvalidArgumentException('Maximum 1,000 rows per import.');
   $out=[];
   foreach($headers as $col=>$key){
    $value=$cells[$col]??'';
    if(($numeric[$col]??false)&&in_array($key,['date','check_in','check_out'],true)){
     $serial=(float)$value;if($serial<0||$serial>2958465)throw new InvalidArgumentException('Invalid Excel date/time.');
     $days=(int)floor($serial);$seconds=(int)round(($serial-$days)*86400);
     if($key!=='date'&&$serial<1){$value=gmdate('H:i:s',$seconds);}
     else{
      if(!$date1904&&$days===60)throw new InvalidArgumentException('Invalid Excel leap-day date.');
      $base=new DateTimeImmutable($date1904?'1904-01-01':($days<60?'1899-12-31':'1899-12-30'),new DateTimeZone('UTC'));
      $date=$base->modify('+'.$days.' days')->modify('+'.$seconds.' seconds');
      $value=$date->format($key==='date'?'Y-m-d':'Y-m-d H:i:s');
     }
    }
    $out[$key]=$value;
   }
   $rows[]=$out;
  }
  if(!$rows)throw new InvalidArgumentException('No attendance rows found.');return $rows;
 }finally{$zip->close();}
}
