<?php
/** Small, dependency-free paginated text PDF. The HTML print view retains logos and Unicode. */
function pay_pdf(array $lines):string {
 $wrapped=[];foreach($lines as $line){$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$line);$ascii=preg_replace('/[^\\x20-\\x7E]/',' ',(string)$ascii);foreach(explode("\n",wordwrap($ascii,88,"\n",true)) as $part)$wrapped[]=$part;}
 $chunks=array_chunk($wrapped,48);if(!$chunks)$chunks=[[]];$objects=[1=>'',2=>'<< /Type /Pages /Kids [] /Count 0 >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];$kids=[];
 foreach($chunks as $chunk){$page=count($objects)+1;$content=$page+1;$kids[]=$page.' 0 R';$stream="BT /F1 10 Tf 45 790 Td 15 TL\n";
  foreach($chunk as $line){$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$line);$ascii=preg_replace('/[^\x20-\x7E]/',' ',(string)$ascii);$escaped=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$ascii);$stream.='('.$escaped.") Tj T*\n";}
  $stream.="ET";$objects[$page]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$content.' 0 R >>';$objects[$content]="<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream";
 }
 $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
 $out="%PDF-1.4\n";$offset=[0];foreach($objects as $id=>$value){$offset[$id]=strlen($out);$out.=$id." 0 obj\n".$value."\nendobj\n";}$xref=strlen($out);$out.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($id=1;$id<=count($objects);$id++)$out.=sprintf("%010d 00000 n \n",$offset[$id]);$out.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";return $out;
}
