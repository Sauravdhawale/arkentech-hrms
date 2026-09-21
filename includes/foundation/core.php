<?php
function foundation_ready(PDO $pdo): bool {
 try{return (bool)$pdo->query("SELECT name FROM hr_migrations WHERE name='004-foundation'")->fetchColumn();}catch(Throwable $e){return false;}
}
function can(PDO $pdo,array $user,string $permission): bool {
 if($user['role']==='super_admin')return true;
 try{$q=$pdo->prepare('SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id AND r.active=1 JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id WHERE ur.user_id=? AND p.code=?');$q->execute([$user['id'],$permission]);return (bool)$q->fetchColumn();}catch(Throwable $e){return false;}
}
function need(PDO $pdo,array $user,string $permission):void {if(!can($pdo,$user,$permission)){http_response_code(403);exit('You do not have permission for this action.');}}
function faudit(PDO $pdo,array $user,string $action,int $id=0):void {$pdo->prepare('INSERT INTO hr_audit(actor_id,action,record_id) VALUES(?,?,?)')->execute([$user['id'],$action,$id]);}
function ftext(array $in,string $key,int $max=190,bool $required=false):string {$v=trim((string)($in[$key]??''));if(($required&&$v==='')||strlen($v)>$max)throw new InvalidArgumentException('Check the '.str_replace('_',' ',$key).' field.');return $v;}
function fdate(array $in,string $key):?string {$v=ftext($in,$key,10);if($v==='')return null;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Enter a valid '.str_replace('_',' ',$key).'.');return $v;}
function femail(array $in,string $key):?string {$v=strtolower(ftext($in,$key));if($v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address.');return $v?:null;}
function ftoken():void {echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">';}
function foptions(array $rows,$selected,string $label='name'):void {foreach($rows as $r)echo '<option value="'.(int)$r['id'].'" '.((string)$selected===(string)$r['id']?'selected':'').'>'.h($r[$label]).'</option>';}
function fimage(array $file):?array {
 if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
 if(($file['error']??1)!==UPLOAD_ERR_OK||$file['size']>2000000||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Upload a JPEG or PNG up to 2 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$size=@getimagesize($file['tmp_name']);
 if(!in_array($mime,['image/jpeg','image/png'],true)||!$size||$size[0]>6000||$size[1]>6000)throw new InvalidArgumentException('Use a valid JPEG or PNG up to 6000 × 6000 pixels.');
 return [$mime,file_get_contents($file['tmp_name'])];
}
function save_media(PDO $pdo,array $user,?int $owner,string $purpose,?array $image):void {
 if(!$image)return;$q=$pdo->prepare('DELETE FROM hr_media WHERE purpose=? AND user_id <=> ?');$q->execute([$purpose,$owner]);
 $pdo->prepare('INSERT INTO hr_media(user_id,purpose,mime,content,uploaded_by) VALUES(?,?,?,?,?)')->execute([$owner,$purpose,$image[0],$image[1],$user['id']]);
}
function split_employee_name(string $name):array {$parts=preg_split('/\s+/u',trim($name));$first=array_shift($parts)??'';$last=count($parts)?array_pop($parts):'';return [$first,implode(' ',$parts),$last];}
function unique_username(PDO $pdo,string $name):string {$parts=preg_split('/[^a-z0-9]+/',strtolower(iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name)),-1,PREG_SPLIT_NO_EMPTY);if(!$parts)throw new InvalidArgumentException('Name needs a usable username.');$base=$parts[0].(count($parts)>1?'.'.end($parts):'');$name=$base;$i=2;while(true){$q=$pdo->prepare('SELECT id FROM users WHERE username=?');$q->execute([$name]);if(!$q->fetchColumn())return $name;$name=$base.'.'.$i++;}}
function foundation_employee(PDO $pdo,int $id):?array {$q=$pdo->prepare('SELECT u.id,u.name,u.email,u.username,u.active,e.*,ur.role_id,d.name department_name,g.name designation_name,r.name role_name FROM users u JOIN employees e ON e.user_id=u.id LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN designations g ON g.id=e.designation_id WHERE u.id=? AND e.deleted_at IS NULL');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
