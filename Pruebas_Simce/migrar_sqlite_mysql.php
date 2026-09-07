<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$source=$argv[1]??''; if(!is_file($source)) exit(0);
require __DIR__.'/database.php'; $target=simceDatabase();
$sourceDb=new PDO('sqlite:'.$source,null,null,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC));
if((int)$target->query('SELECT COUNT(*) FROM administradores')->fetchColumn()>0 || (int)$target->query('SELECT COUNT(*) FROM alumnos')->fetchColumn()>0){fwrite(STDOUT,"MySQL ya contiene datos; no se sobrescribió.\n");exit(0);}
$target->beginTransaction();
try{
 foreach($sourceDb->query('SELECT * FROM administradores ORDER BY id')->fetchAll() as $r){$s=$target->prepare('INSERT INTO administradores(usuario,password_hash,rol,activo,created_at,last_login_at) VALUES(:u,:p,:r,:a,:c,:l)');$s->execute(array(':u'=>$r['usuario'],':p'=>$r['password_hash'],':r'=>$r['rol']??(((int)$r['id']===1)?'superadmin':'administrador'),':a'=>$r['activo'],':c'=>$r['created_at'],':l'=>$r['last_login_at']));}
 foreach($sourceDb->query('SELECT * FROM alumnos ORDER BY id')->fetchAll() as $r){$s=$target->prepare('INSERT INTO alumnos(public_id,rut,rut_normalizado,nombre,curso,idgrado,nivel,activo,created_at,updated_at) VALUES(:public,:rut,:rn,:n,:c,:g,:l,:a,:created,:updated)');$s->execute(array(':public'=>$r['public_id']??bin2hex(random_bytes(16)),':rut'=>$r['rut'],':rn'=>$r['rut_normalizado'],':n'=>$r['nombre'],':c'=>$r['curso'],':g'=>$r['idgrado'],':l'=>$r['nivel'],':a'=>$r['activo'],':created'=>$r['created_at'],':updated'=>$r['updated_at']));}
 $target->commit();simceSyncCourses($target);fwrite(STDOUT,"Migración completada.\n");
}catch(Throwable $e){if($target->inTransaction())$target->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
