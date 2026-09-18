<?php
function import_xlsx(string $file): array {
 if(!class_exists('ZipArchive')||!function_exists('simplexml_load_string'))throw new InvalidArgumentException('Enable PHP zip and SimpleXML extensions to import XLSX.');
 $zip=new ZipArchive();if($zip->open($file)!==true)throw new InvalidArgumentException('Upload a valid XLSX workbook.');
 $xml=function($path)use($zip){$stat=$zip->statName($path);if(!$stat||$stat['size']>10000000)throw new InvalidArgumentException('Workbook XML is missing or too large.');$str=$zip->getFromName($path);if(stripos($str,'<!DOCTYPE')!==false)throw new InvalidArgumentException('Unsupported workbook XML.');$obj=simplexml_load_string($str,'SimpleXMLElement',LIBXML_NONET);if(!$obj)throw new InvalidArgumentException('Invalid workbook XML.');return $obj;};
 $strings=[];if($zip->locateName('xl/sharedStrings.xml')!==false)foreach($xml('xl/sharedStrings.xml')->si as $si){$text='';foreach($si->xpath('.//*[local-name()="t"]') as $t)$text.=(string)$t;$strings[]=$text;}
 $sheet=$xml('xl/worksheets/sheet1.xml');$rows=[];
 foreach($sheet->sheetData->row as $r){$row=[];foreach($r->c as $c){preg_match('/^[A-Z]+/',(string)$c['r'],$m);$col=$m[0]??'';$v=(string)$c->v;if((string)$c['t']==='s')$v=$strings[(int)$v]??'';elseif((string)$c['t']==='inlineStr')$v=(string)$c->is->t;$row[$col]=trim($v);} $rows[]=$row;}
 $zip->close();if(count($rows)>2001)throw new InvalidArgumentException('Import at most 2,000 employees at a time.');
 $header=array_shift($rows);$columns=array_flip($header);if(!isset($columns['Name']))throw new InvalidArgumentException('Sheet1 must have a Name column in its first row.');
 $result=[];$seen=[];
 foreach($rows as $row){$get=fn($k)=>trim($row[$columns[$k]??'']??'');$name=preg_replace('/\s+/u',' ',$get('Name'));if(!$name)continue;$key=strtolower($name);if(isset($seen[$key]))continue;$seen[$key]=true;
  $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);$parts=preg_split('/[^a-z0-9]+/',strtolower($ascii),-1,PREG_SPLIT_NO_EMPTY);if(!$parts)throw new InvalidArgumentException('A name cannot be converted to a username.');$username=$parts[0].(count($parts)>1?'.'.end($parts):'');
  $dob=$get('DOB');if(is_numeric($dob))$dob=(new DateTimeImmutable('1899-12-30'))->modify('+'.(int)$dob.' days')->format('Y-m-d');elseif($dob){$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$dob);if(!$dt||$dt->format('Y-m-d')!==$dob)$dob='';}
  $result[]=['name'=>$name,'username'=>$username,'designation'=>$get('Designation'),'birth_date'=>$dob,'phone'=>$get('Phone Number'),'blood_group'=>$get('Blood Group'),'emergency_contact'=>$get('Emergency No'),'source_status'=>$get('Status')];
 }
 return $result;
}
function install_login_columns(PDO $pdo): void {
 $cols=$pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
 if(!in_array('username',$cols,true))$pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(190) NULL UNIQUE');
 if(!in_array('must_change_password',$cols,true))$pdo->exec('ALTER TABLE users ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE');
 $pdo->exec('ALTER TABLE users MODIFY email VARCHAR(190) NULL');
}
