<?php
/** A styled A4 download for shared hosting. No shell, remote fetch or PDF service. */
final class PayStatementPdf {
 private array $pages=[];
 private string $stream='';
 private float $y=42;
 private ?array $image=null;
 public function __construct(private string $number){}
 private function ascii(string $text):string {
  $text=str_replace(['₹','—','–',"\r","\n"],['INR','-','-',' ',' '],$text);
  return (string)iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);
 }
 private function esc(string $text):string{return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$text);}
 private function wrap(string $text,int $width):array {
  $lines=[];foreach(explode("\n",str_replace("\r",'', $text)) as $line)foreach(explode("\n",wordwrap($this->ascii($line),max(8,$width),"\n",true)) as $part)$lines[]=$part;
  return $lines?:[''];
 }
 private function draw(string $text,float $x,float $y,int $size=10,bool $bold=false,string $color='0.13 0.15 0.19'):void {
  $font=$bold?'F2':'F1';$this->stream.="$color rg BT /$font $size Tf 0 Tc 0 Tw 1 0 0 1 $x ".(842-$y)." Tm (".$this->esc($text).") Tj ET\n";
 }
 private function rect(float $x,float $y,float $w,float $h,string $color):void{$this->stream.="$color rg $x ".(842-$y-$h)." $w $h re f\n";}
 private function line(float $y):void{$this->stream.='0.88 0.89 0.91 RG 0.5 w 42 '.(842-$y).' m 553 '.(842-$y)." l S\n";}
 private function page():void {
  $this->draw($this->ascii($this->number).'  |  Private & confidential',42,809,8,false,'0.4 0.44 0.5');
  $this->draw('Page '.(count($this->pages)+1),510,809,8,false,'0.4 0.44 0.5');
  $this->pages[]=$this->stream;$this->stream='';$this->y=42;
 }
 private function ensure(float $height):void{if($this->y+$height>780){$this->page();$this->draw('PAYSLIP - continued',42,$this->y,10,true);$this->y+=25;}}
 public function paragraph(string $text,int $size=9,bool $bold=false):void {
  foreach($this->wrap($text,(int)(510/($size*.65))) as $line){$this->ensure($size+6);$this->draw($line,42,$this->y+$size,$size,$bold);$this->y+=$size+5;}
 }
 public function heading(string $label):void{$this->ensure(45);$this->y+=6;$this->draw($this->ascii($label),42,$this->y+12,12,true);$this->y+=19;}
 public function row(string $label,string $value,bool $total=false):void {
  $lines=$this->wrap($label,53);$amount=$this->wrap($value,29);$height=max(count($lines),count($amount))*13+8;$this->ensure($height);
  if($total)$this->rect(42,$this->y,511,$height,'0.98 0.94 0.94');
  foreach($lines as $i=>$text)$this->draw($text,50,$this->y+15+$i*13,9,$total);
  foreach($amount as $i=>$text)$this->draw($text,380,$this->y+15+$i*13,9,$total);
  $this->y+=$height;$this->line($this->y);
 }
 public function info(array $info):void {
  $pairs=array_chunk($info,2,true);
  foreach($pairs as $pair){$height=0;$cells=[];foreach($pair as $label=>$value){$lines=$this->wrap((string)$value,34);$height=max($height,20+count($lines)*13);$cells[]=[$label,$lines];}$this->ensure($height+5);
   $this->rect(42,$this->y,511,$height+5,'0.965 0.97 0.98');foreach($cells as $i=>[$label,$lines]){$x=54+$i*255;$this->draw($this->ascii($label),$x,$this->y+13,8,false,'0.4 0.44 0.5');foreach($lines as $j=>$line)$this->draw($line,$x,$this->y+28+$j*13,10,true);}$this->y+=$height+5;
  }$this->y+=10;
 }
 public function logo(string $uri):void {
  if(!preg_match('~^data:image/(png|jpeg|gif|webp);base64,([A-Za-z0-9+/=]+)$~D',$uri,$m))return;
  $bytes=base64_decode($m[2],true);if($bytes===false||strlen($bytes)>2000000)return;
  $size=@getimagesizefromstring($bytes);if(!$size||$size[0]*$size[1]>16000000)return;
  if($m[1]!=='jpeg'){
   if(!function_exists('imagecreatefromstring'))return;
   $source=@imagecreatefromstring($bytes);if(!$source)return;
   $rgb=imagecreatetruecolor($size[0],$size[1]);imagefill($rgb,0,0,imagecolorallocate($rgb,255,255,255));imagecopy($rgb,$source,0,0,0,0,$size[0],$size[1]);ob_start();imagejpeg($rgb,null,90);$bytes=ob_get_clean();imagedestroy($source);imagedestroy($rgb);
  }
  $this->image=['bytes'=>$bytes,'w'=>$size[0],'h'=>$size[1]];$scale=min(130/$size[0],44/$size[1]);$w=$size[0]*$scale;$h=$size[1]*$scale;
  $this->stream.="q $w 0 0 $h 42 ".(842-$this->y-$h)." cm /Logo Do Q\n";$this->y+=$h+12;
 }
 public function header(array $d):void {
  $this->rect(42,25,511,4,'0.694 0.149 0.145');$top=$this->y;$this->logo($d['logo']);$logoEnd=$this->y;
  $x=$this->image?200:42;$y=$top;
  foreach($this->wrap($d['company'],$this->image?33:48) as $line){$this->draw($line,$x,$y+16,16,true);$y+=20;}
  if($d['address'])foreach($this->wrap($d['address'],$this->image?58:80) as $line){$this->draw($line,$x,$y+10,8,false,'0.4 0.44 0.5');$y+=13;}
  $this->y=max($logoEnd,$y)+8;$this->paragraph('MONTHLY PAYSLIP | '.$d['month'],13,true);$this->paragraph($d['number'],9);
  if($d['draft'])$this->paragraph('DRAFT - NOT FINAL',12,true);
  $this->y+=10;
 }
 public function net(array $d):void {
  $lines=$this->wrap($d['words'],75);$height=64+count($lines)*13;$this->ensure($height+15);$this->y+=10;
  $this->rect(42,$this->y,511,$height,'0.98 0.94 0.94');$this->rect(42,$this->y,4,$height,'0.694 0.149 0.145');
  $this->draw('NET PAY',58,$this->y+20,9,true,'0.55 0.11 0.11');$this->draw($this->ascii($d['net']),58,$this->y+44,22,true,'0.55 0.11 0.11');
  foreach($lines as $i=>$line)$this->draw($line,58,$this->y+64+$i*13,9);$this->y+=$height+12;
 }
 public function attendance(array $values):void {
  $this->heading('Attendance summary');
  foreach(array_chunk($values,3,true) as $row){$this->ensure(54);$i=0;foreach($row as $label=>$value){$x=42+$i*173;$this->rect($x,$this->y,165,47,'0.965 0.97 0.98');$this->draw($this->ascii($label),$x+9,$this->y+15,8,false,'0.4 0.44 0.5');$this->draw($this->ascii($value),$x+9,$this->y+34,13,true);$i++;}$this->y+=54;}
 }
 public function finish():string {
  $this->page();$objects=[1=>'',2=>'',3=>'',4=>''];
  $fonts=json_decode(file_get_contents(dirname(__DIR__,2).'/assets/fonts/payslip-fonts.json'),true,512,JSON_THROW_ON_ERROR);
  foreach(['regular'=>3,'bold'=>4] as $key=>$id){
   $font=$fonts[$key];$descriptor=count($objects)+1;$file=$descriptor+1;$bytes=base64_decode($font['data'],true);$name='HRMSAA+'.$font['name'];
   $objects[$id]='<< /Type /Font /Subtype /TrueType /BaseFont /'.$name.' /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 255 /Widths ['.implode(' ',$font['widths']).'] /FontDescriptor '.$descriptor.' 0 R >>';
   $objects[$descriptor]='<< /Type /FontDescriptor /FontName /'.$name.' /Flags 32 /FontBBox ['.implode(' ',$font['bbox']).'] /ItalicAngle 0 /Ascent '.$font['ascent'].' /Descent '.$font['descent'].' /CapHeight '.$font['capHeight'].' /StemV '.($key==='bold'?120:80).' /FontFile2 '.$file.' 0 R >>';
   $objects[$file]='<< /Filter /FlateDecode /Length '.strlen($bytes).' /Length1 '.$font['length']." >>\nstream\n".$bytes."\nendstream";
  }
  $imageRef='';if($this->image){$im=$this->image;$imageId=count($objects)+1;$objects[$imageId]='<< /Type /XObject /Subtype /Image /Width '.$im['w'].' /Height '.$im['h'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($im['bytes'])." >>\nstream\n".$im['bytes']."\nendstream";$imageRef='/XObject << /Logo '.$imageId.' 0 R >>';}
  $kids=[];foreach($this->pages as $stream){$page=count($objects)+1;$content=$page+1;$kids[]="$page 0 R";$objects[$page]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> '.$imageRef.' >> /Contents '.$content.' 0 R >>';$objects[$content]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";}
  $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
  $out="%PDF-1.4\n";$offset=[0];foreach($objects as $id=>$value){$offset[$id]=strlen($out);$out.="$id 0 obj\n$value\nendobj\n";}$xref=strlen($out);$out.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($id=1;$id<=count($objects);$id++)$out.=sprintf("%010d 00000 n \n",$offset[$id]);
  return $out.'trailer'."\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
 }
}
function pay_statement_pdf(array $d):string {
 $pdf=new PayStatementPdf($d['number']);$pdf->header($d);$pdf->info($d['info']);
 foreach(['Earning'=>['Earnings','Gross earnings',$d['gross']],'Deduction'=>['Deductions','Total deductions',$d['deductions']]] as $category=>$labels){$pdf->heading($labels[0]);foreach($d['groups'][$category] as [$name,$amount])$pdf->row($name,$amount);if(!$d['groups'][$category])$pdf->paragraph('No '.strtolower($labels[0]));$pdf->row($labels[1],$labels[2],true);}
 $pdf->net($d);
 if($d['groups']['Employer']){$pdf->heading('Employer contributions');$pdf->paragraph('Shown separately; not deducted from net pay.');foreach($d['groups']['Employer'] as [$name,$amount])$pdf->row($name,$amount);}
 if($d['attendance']){$pdf->attendance($d['attendance']);$pdf->paragraph($d['attendance_note'],8);}
 if($d['signatory'])$pdf->paragraph('Authorized signatory: '.$d['signatory'],9,true);
 if($d['footer'])$pdf->paragraph($d['footer'],8);
 $pdf->paragraph('This payslip records the published payroll amounts; it is not a payment receipt.',8);
 return $pdf->finish();
}
