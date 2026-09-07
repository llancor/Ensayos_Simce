<?php
declare(strict_types=1);
require_once __DIR__.'/seguridad.php';
simceRequireRole(array('plataforma_superadmin'));
$db=simceDatabase();

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $rows=$db->query("SELECT c.id,c.codigo,c.nombre,c.activo,c.created_at,
        (SELECT COUNT(*) FROM administradores u WHERE u.colegio_id=c.id) usuarios,
        (SELECT COUNT(*) FROM alumnos a WHERE a.colegio_id=c.id) alumnos,
        (SELECT usuario FROM administradores u WHERE u.colegio_id=c.id AND u.rol='colegio_admin' ORDER BY u.id LIMIT 1) admin_usuario
        FROM colegios c ORDER BY c.nombre")->fetchAll();
    simceJson(array('ok'=>true,'colegios'=>$rows,'version'=>SIMCE_VERSION));
}

simceRequireCsrf();
$d=simceReadJson(65536);
$action=(string)($d['action']??'');

function simceValidSchoolPassword(string $password):bool{
    return strlen($password)>=12&&preg_match('/[A-Z]/',$password)&&preg_match('/[a-z]/',$password)&&preg_match('/[0-9]/',$password);
}

if($action==='create_school'){
    $code=strtolower(trim((string)($d['codigo']??'')));$name=trim((string)($d['nombre']??''));$user=trim((string)($d['usuario']??''));$pass=(string)($d['password']??'');
    if(!preg_match('/^[a-z0-9-]{3,40}$/',$code)||$name===''||strlen($name)>150||!preg_match('/^[A-Za-z0-9._-]{3,50}$/',$user)||!simceValidSchoolPassword($pass))simceJson(array('ok'=>false,'error'=>'Revisa código, nombre, usuario y contraseña segura.'),400);
    try{$db->beginTransaction();$s=$db->prepare('INSERT INTO colegios(codigo,nombre)VALUES(:c,:n)');$s->execute(array(':c'=>$code,':n'=>$name));$cid=(int)$db->lastInsertId();$u=$db->prepare("INSERT INTO administradores(colegio_id,usuario,password_hash,rol)VALUES(:c,:u,:p,'colegio_admin')");$u->execute(array(':c'=>$cid,':u'=>$user,':p'=>password_hash($pass,PASSWORD_DEFAULT)));$db->commit();}catch(Throwable$e){if($db->inTransaction())$db->rollBack();simceJson(array('ok'=>false,'error'=>'El código del colegio ya existe o no pudo crearse.'),409);}
    simceJson(array('ok'=>true));
}

if($action==='edit_school'){
    $id=(int)($d['id']??0);$code=strtolower(trim((string)($d['codigo']??'')));$name=trim((string)($d['nombre']??''));
    if($id<1||!preg_match('/^[a-z0-9-]{3,40}$/',$code)||$name===''||strlen($name)>150)simceJson(array('ok'=>false,'error'=>'Código o nombre no válido.'),400);
    try{$s=$db->prepare('UPDATE colegios SET codigo=:codigo,nombre=:nombre WHERE id=:id');$s->execute(array(':codigo'=>$code,':nombre'=>$name,':id'=>$id));}catch(Throwable$e){simceJson(array('ok'=>false,'error'=>'El código ya está en uso o no se pudo guardar.'),409);}
    if($s->rowCount()!==1)simceJson(array('ok'=>false,'error'=>'Colegio no encontrado o sin cambios.'),404);
    simceJson(array('ok'=>true));
}

if($action==='reset_school_password'){
    $id=(int)($d['id']??0);$pass=(string)($d['password']??'');
    if($id<1||!simceValidSchoolPassword($pass))simceJson(array('ok'=>false,'error'=>'La contraseña debe tener al menos 12 caracteres, mayúscula, minúscula y número.'),400);
    $s=$db->prepare("SELECT id FROM administradores WHERE colegio_id=:c AND rol='colegio_admin' ORDER BY id LIMIT 1");$s->execute(array(':c'=>$id));$adminId=(int)$s->fetchColumn();
    if($adminId<1)simceJson(array('ok'=>false,'error'=>'El colegio no tiene administrador principal.'),404);
    $u=$db->prepare('UPDATE administradores SET password_hash=:p,activo=1 WHERE id=:id');$u->execute(array(':p'=>password_hash($pass,PASSWORD_DEFAULT),':id'=>$adminId));
    simceJson(array('ok'=>true));
}

if($action==='toggle_school'){
    $id=(int)($d['id']??0);$active=!empty($d['activo'])?1:0;$s=$db->prepare('UPDATE colegios SET activo=:a WHERE id=:id');$s->execute(array(':a'=>$active,':id'=>$id));
    if($s->rowCount()!==1)simceJson(array('ok'=>false,'error'=>'Colegio no encontrado o ya estaba en ese estado.'),404);
    simceJson(array('ok'=>true));
}

if($action==='delete_school'){
    $id=(int)($d['id']??0);$confirmation=strtolower(trim((string)($d['confirmation']??'')));$school=$db->prepare('SELECT codigo,nombre FROM colegios WHERE id=:id');$school->execute(array(':id'=>$id));$row=$school->fetch();
    if(!$row)simceJson(array('ok'=>false,'error'=>'Colegio no encontrado.'),404);
    if($confirmation!==strtolower((string)$row['codigo']))simceJson(array('ok'=>false,'error'=>'El código de confirmación no coincide.'),400);
    try{
        $db->beginTransaction();
        foreach(array('resultados','asignaciones','pruebas','cursos','alumnos','importaciones_alumnos','auditoria_administracion','configuracion','administradores') as $table){$q=$db->prepare('DELETE FROM '.$table.' WHERE colegio_id=:c');$q->execute(array(':c'=>$id));}
        $q=$db->prepare('DELETE FROM colegios WHERE id=:id');$q->execute(array(':id'=>$id));$db->commit();
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();simceJson(array('ok'=>false,'error'=>'No se pudo eliminar el colegio.'),409);}
    simceJson(array('ok'=>true));
}

simceJson(array('ok'=>false,'error'=>'Acción no válida.'),400);
