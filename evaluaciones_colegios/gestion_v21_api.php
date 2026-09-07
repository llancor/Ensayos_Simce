<?php
declare(strict_types=1);
require_once __DIR__.'/seguridad.php';
simceRequireRole(array('colegio_admin','docente'));
$db=simceDatabase();$cid=simceTenantId();if($cid<1)simceJson(array('ok'=>false,'error'=>'Sesion sin colegio.'),403);
$role=(string)($_SESSION['admin_rol']??'');

function simceLogSchoolAction(PDO $db,int $cid,string $action,string $detail):void{$q=$db->prepare('INSERT INTO auditoria_administracion(colegio_id,accion,detalle,administrador_id)VALUES(:c,:a,:d,:admin)');$q->execute(array(':c'=>$cid,':a'=>$action,':d'=>$detail,':admin'=>(int)$_SESSION['admin_id']));}
function simceCourseNameFromParts(int $grade,string $section):array{$name=($grade<=8?$grade:$grade-8).'° '.($grade<=8?'básico':'medio').' '.$section;return array($name,$grade<=8?'basica':'media');}

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    simceSyncCourses($db,$cid);simceSyncStoredTests($db,$cid);
    $courses=$db->prepare("SELECT id,nombre,idgrado,nivel,activo,modo_pruebas,(CASE WHEN pin_hash IS NULL OR pin_hash='' THEN 0 ELSE 1 END) AS pin_configurado,requiere_pin FROM cursos WHERE colegio_id=:c AND activo=1 ORDER BY idgrado,nombre");
    $courses->execute(array(':c'=>$cid));
    $allCourses=$db->prepare("SELECT id,nombre,idgrado,nivel,activo,modo_pruebas,(CASE WHEN pin_hash IS NULL OR pin_hash='' THEN 0 ELSE 1 END) AS pin_configurado,requiere_pin FROM cursos WHERE colegio_id=:c ORDER BY idgrado,nombre");
    $allCourses->execute(array(':c'=>$cid));
    $tests=$db->prepare('SELECT id,titulo,curso_codigo,asignatura FROM pruebas WHERE colegio_id=:c AND activa=1 ORDER BY curso_codigo,asignatura,titulo');
    $tests->execute(array(':c'=>$cid));
    $assignments=$db->prepare('SELECT curso_id,prueba_id,activa,configuracion FROM asignaciones WHERE colegio_id=:c');
    $assignments->execute(array(':c'=>$cid));
    $results=$db->prepare("SELECT r.id,COALESCE(a.nombre,r.participante_nombre,'Sin nombre') AS nombre,COALESCE(a.curso,r.curso_nombre,'Sin curso') AS curso,p.titulo,p.asignatura,r.intento,r.correctas,r.total,r.porcentaje,r.nota,r.finalizado_at,r.acceso_libre FROM resultados r LEFT JOIN alumnos a ON a.id=r.alumno_id AND a.colegio_id=r.colegio_id JOIN pruebas p ON p.id=r.prueba_id AND p.colegio_id=r.colegio_id WHERE r.colegio_id=:c ORDER BY r.finalizado_at DESC LIMIT 500");
    $results->execute(array(':c'=>$cid));
    $users=array();if($role==='colegio_admin'){$u=$db->prepare("SELECT id,usuario,rol,activo,created_at,last_login_at FROM administradores WHERE colegio_id=:c AND rol='docente' ORDER BY usuario");$u->execute(array(':c'=>$cid));$users=$u->fetchAll();}
    $school=$db->prepare('SELECT codigo,nombre FROM colegios WHERE id=:c');$school->execute(array(':c'=>$cid));
    simceJson(array('ok'=>true,'rol'=>$role,'colegio'=>$school->fetch(),'usa_lista_estudiantes'=>simceStudentListsEnabled($db,$cid),'cursos'=>$courses->fetchAll(),'todos_cursos'=>$allCourses->fetchAll(),'pruebas'=>$tests->fetchAll(),'asignaciones'=>$assignments->fetchAll(),'resultados'=>$results->fetchAll(),'usuarios'=>$users,'version'=>SIMCE_VERSION));
}

simceRequireCsrf();$d=simceReadJson(65536);$a=(string)($d['action']??'');

if($a==='create_course'){
    $grade=filter_var($d['grado']??null,FILTER_VALIDATE_INT);$section=strtoupper(trim((string)($d['seccion']??'')));$mode=(string)($d['modo']??'asignadas');$pin=trim((string)($d['pin']??''));
    if(!$grade||$grade<1||$grade>12||!preg_match('/^[A-Z0-9]{1,8}$/',$section)||!in_array($mode,array('todas','asignadas'),true)||($pin!==''&&(strlen($pin)<4||strlen($pin)>30)))simceJson(array('ok'=>false,'error'=>'Revisa nivel, seccion y PIN opcional.'),400);
    [$name,$level]=simceCourseNameFromParts((int)$grade,$section);
    try{$q=$db->prepare('INSERT INTO cursos(colegio_id,nombre,idgrado,nivel,modo_pruebas,requiere_pin,pin_hash)VALUES(:c,:n,:g,:l,:m,:r,:p)');$q->execute(array(':c'=>$cid,':n'=>$name,':g'=>$grade,':l'=>$level,':m'=>$mode,':r'=>$pin!==''?1:0,':p'=>$pin!==''?password_hash($pin,PASSWORD_DEFAULT):null));}catch(Throwable$e){simceJson(array('ok'=>false,'error'=>'El curso ya existe o no pudo crearse.'),409);}
    $id=(int)$db->lastInsertId();simceSyncStoredTests($db,$cid);simceLogSchoolAction($db,$cid,'crear_curso',$name);simceJson(array('ok'=>true,'id'=>$id));
}

if(in_array($a,array('update_course','toggle_course','delete_course'),true)){
    $id=(int)($d['curso']??0);$q=$db->prepare('SELECT * FROM cursos WHERE id=:id AND colegio_id=:c');$q->execute(array(':id'=>$id,':c'=>$cid));$course=$q->fetch();if(!$course)simceJson(array('ok'=>false,'error'=>'Curso no encontrado.'),404);
    $q=$db->prepare('SELECT COUNT(*) FROM alumnos WHERE colegio_id=:c AND curso=:n');$q->execute(array(':c'=>$cid,':n'=>$course['nombre']));$students=(int)$q->fetchColumn();
    $q=$db->prepare('SELECT COUNT(*) FROM resultados WHERE colegio_id=:c1 AND alumno_id IN (SELECT id FROM alumnos WHERE colegio_id=:c2 AND curso=:n)');$q->execute(array(':c1'=>$cid,':c2'=>$cid,':n'=>$course['nombre']));$results=(int)$q->fetchColumn();
    if($a==='delete_course'&&($students||$results))simceJson(array('ok'=>false,'error'=>'Este curso tiene alumnos o resultados. Puedes deshabilitarlo para conservar su historial.'),409);
    if($a==='toggle_course'&&!is_bool($d['activo']??null))simceJson(array('ok'=>false,'error'=>'Estado invalido.'),400);
    if($a==='update_course'){
        $grade=filter_var($d['grado']??null,FILTER_VALIDATE_INT);$section=strtoupper(trim((string)($d['seccion']??'')));
        if(!$grade||$grade<1||$grade>12||!preg_match('/^[A-Z0-9]{1,8}$/',$section))simceJson(array('ok'=>false,'error'=>'Revisa el nivel y la seccion.'),400);
        if((int)$grade!==(int)$course['idgrado']&&($students||$results))simceJson(array('ok'=>false,'error'=>'No se puede cambiar el nivel de un curso con alumnos o resultados. Puedes editar su seccion.'),409);
        [$name,$level]=simceCourseNameFromParts((int)$grade,$section);$q=$db->prepare('SELECT id FROM cursos WHERE colegio_id=:c AND nombre=:n AND id<>:id');$q->execute(array(':c'=>$cid,':n'=>$name,':id'=>$id));if($q->fetch())simceJson(array('ok'=>false,'error'=>'Ya existe un curso con ese nivel y seccion.'),409);
    }
    $db->beginTransaction();
    try{
        if($a==='delete_course'){$q=$db->prepare('DELETE FROM asignaciones WHERE colegio_id=:c AND curso_id=:id');$q->execute(array(':c'=>$cid,':id'=>$id));$q=$db->prepare('DELETE FROM cursos WHERE colegio_id=:c AND id=:id');$q->execute(array(':c'=>$cid,':id'=>$id));}
        elseif($a==='toggle_course'){$q=$db->prepare('UPDATE cursos SET activo=:a,updated_at=CURRENT_TIMESTAMP WHERE colegio_id=:c AND id=:id');$q->execute(array(':a'=>$d['activo']?1:0,':c'=>$cid,':id'=>$id));}
        else{$q=$db->prepare('UPDATE cursos SET nombre=:n,idgrado=:g,nivel=:l,updated_at=CURRENT_TIMESTAMP WHERE colegio_id=:c AND id=:id');$q->execute(array(':n'=>$name,':g'=>$grade,':l'=>$level,':c'=>$cid,':id'=>$id));$q=$db->prepare('UPDATE alumnos SET curso=:n WHERE colegio_id=:c AND curso=:old');$q->execute(array(':n'=>$name,':c'=>$cid,':old'=>$course['nombre']));if((int)$grade!==(int)$course['idgrado']){$q=$db->prepare('DELETE FROM asignaciones WHERE colegio_id=:c AND curso_id=:id');$q->execute(array(':c'=>$cid,':id'=>$id));}}
        simceLogSchoolAction($db,$cid,$a,'Curso '.$id.': '.$course['nombre']);$db->commit();
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();simceJson(array('ok'=>false,'error'=>'No se pudo actualizar el curso. Recarga e intenta nuevamente.'),409);}
    simceJson(array('ok'=>true));
}

if($a==='save_course_tests'){
    $id=(int)($d['curso']??0);$mode=(string)($d['modo']??'');$q=$db->prepare('SELECT * FROM cursos WHERE colegio_id=:c AND id=:id AND activo=1');$q->execute(array(':c'=>$cid,':id'=>$id));$course=$q->fetch();
    if(!$course||!in_array($mode,array('todas','asignadas'),true)||!is_array($d['pruebas']??null))simceJson(array('ok'=>false,'error'=>'Curso o configuracion no validos.'),400);
    $tests=$db->prepare('SELECT id,curso_codigo FROM pruebas WHERE colegio_id=:c AND activa=1');$tests->execute(array(':c'=>$cid));$valid=array();foreach($tests->fetchAll()as$test)if(simceCourseCodeMatches($course['nombre'],$test['curso_codigo']))$valid[(int)$test['id']]=true;
    $rows=array();foreach($d['pruebas']as$row){$testId=(int)($row['id']??0);$policy=$row['configuracion']??null;if(!isset($valid[$testId])||isset($rows[$testId]))simceJson(array('ok'=>false,'error'=>'Prueba duplicada o ajena al nivel del curso.'),400);if($policy!==null){if(!is_array($policy)||!is_bool($policy['feedback']??null)||!is_bool($policy['results']??null)||!is_int($policy['attempts']??null)||$policy['attempts']<0||$policy['attempts']>100||!in_array($policy['finish']??'',array('results','return'),true))simceJson(array('ok'=>false,'error'=>'Opciones de prueba invalidas.'),400);$policy=array_intersect_key($policy,array_flip(array('feedback','results','attempts','finish')));}$rows[$testId]=array('active'=>!empty($row['activa'])?1:0,'policy'=>$policy===null?null:json_encode($policy));}
    if(count($rows)!==count($valid))simceJson(array('ok'=>false,'error'=>'Cambio el catalogo de pruebas. Recarga la pagina antes de guardar.'),409);
    $db->beginTransaction();
    try{$q=$db->prepare('UPDATE cursos SET modo_pruebas=:m,updated_at=CURRENT_TIMESTAMP WHERE colegio_id=:c AND id=:id');$q->execute(array(':m'=>$mode,':c'=>$cid,':id'=>$id));foreach($rows as$testId=>$row){$q=$db->prepare('SELECT id FROM asignaciones WHERE colegio_id=:c AND curso_id=:u AND prueba_id=:p');$q->execute(array(':c'=>$cid,':u'=>$id,':p'=>$testId));$sql=$q->fetch()?'UPDATE asignaciones SET activa=:a,configuracion=:f WHERE colegio_id=:c AND curso_id=:u AND prueba_id=:p':'INSERT INTO asignaciones(colegio_id,curso_id,prueba_id,activa,configuracion)VALUES(:c,:u,:p,:a,:f)';$q=$db->prepare($sql);$q->execute(array(':c'=>$cid,':u'=>$id,':p'=>$testId,':a'=>$mode==='todas'?1:$row['active'],':f'=>$row['policy']));}simceLogSchoolAction($db,$cid,'configurar_pruebas_curso','Curso '.$id.': '.$mode);$db->commit();}catch(Throwable$e){if($db->inTransaction())$db->rollBack();simceJson(array('ok'=>false,'error'=>'No se pudo guardar la configuracion.'),500);}
    simceJson(array('ok'=>true));
}

if($a==='course_pin'||$a==='clear_course_pin'){
    $id=(int)($d['curso']??0);if($a==='course_pin'){$pin=trim((string)($d['pin']??''));if(strlen($pin)<4||strlen($pin)>30)simceJson(array('ok'=>false,'error'=>'PIN de 4 a 30 caracteres.'),400);$s=$db->prepare('UPDATE cursos SET pin_hash=:p,requiere_pin=1,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND colegio_id=:c');$s->execute(array(':p'=>password_hash($pin,PASSWORD_DEFAULT),':id'=>$id,':c'=>$cid));}else{$s=$db->prepare('UPDATE cursos SET pin_hash=NULL,requiere_pin=0,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND colegio_id=:c');$s->execute(array(':id'=>$id,':c'=>$cid));}if($s->rowCount()!==1)simceJson(array('ok'=>false,'error'=>'Curso no encontrado.'),404);simceLogSchoolAction($db,$cid,$a,'Curso '.$id);simceJson(array('ok'=>true));
}

if($a==='student_list_mode'){
    simceRequireRole(array('colegio_admin'));
    $enabled=!empty($d['activo'])?'1':'0';
    $statement=$db->prepare("SELECT clave FROM configuracion WHERE colegio_id=:c AND clave='acceso_lista_estudiantes'");
    $statement->execute(array(':c'=>$cid));
    $query=$statement->fetch()
        ? $db->prepare("UPDATE configuracion SET valor=:valor,updated_at=CURRENT_TIMESTAMP WHERE colegio_id=:c AND clave='acceso_lista_estudiantes'")
        : $db->prepare("INSERT INTO configuracion(colegio_id,clave,valor)VALUES(:c,'acceso_lista_estudiantes',:valor)");
    $query->execute(array(':c'=>$cid,':valor'=>$enabled));
    simceLogSchoolAction($db,$cid,$enabled==='1'?'activar_listas_estudiantes':'desactivar_listas_estudiantes',$enabled==='1'?'Ingreso mediante listas activado.':'Ingreso libre mediante nombre activado.');
    simceJson(array('ok'=>true,'usa_lista_estudiantes'=>$enabled==='1'));
}

if($a==='create_teacher'){simceRequireRole(array('colegio_admin'));$u=trim((string)($d['usuario']??''));$p=(string)($d['password']??'');if(!preg_match('/^[A-Za-z0-9._-]{3,50}$/',$u)||strlen($p)<12||!preg_match('/[A-Z]/',$p)||!preg_match('/[a-z]/',$p)||!preg_match('/[0-9]/',$p))simceJson(array('ok'=>false,'error'=>'Usuario o contrasena no validos.'),400);try{$s=$db->prepare("INSERT INTO administradores(colegio_id,usuario,password_hash,rol)VALUES(:c,:u,:p,'docente')");$s->execute(array(':c'=>$cid,':u'=>$u,':p'=>password_hash($p,PASSWORD_DEFAULT)));}catch(Throwable$e){simceJson(array('ok'=>false,'error'=>'El docente ya existe.'),409);}simceJson(array('ok'=>true));}
if($a==='reset_teacher'){simceRequireRole(array('colegio_admin'));$id=(int)($d['id']??0);$p=(string)($d['password']??'');if(strlen($p)<12||!preg_match('/[A-Z]/',$p)||!preg_match('/[a-z]/',$p)||!preg_match('/[0-9]/',$p))simceJson(array('ok'=>false,'error'=>'La contrasena debe tener al menos 12 caracteres, mayuscula, minuscula y numero.'),400);$s=$db->prepare("UPDATE administradores SET password_hash=:p WHERE id=:id AND colegio_id=:c AND rol='docente'");$s->execute(array(':p'=>password_hash($p,PASSWORD_DEFAULT),':id'=>$id,':c'=>$cid));if($s->rowCount()!==1)simceJson(array('ok'=>false,'error'=>'Usuario no encontrado.'),404);simceJson(array('ok'=>true));}
if($a==='set_teacher_status'){simceRequireRole(array('colegio_admin'));$id=(int)($d['id']??0);$active=!empty($d['activo'])?1:0;$s=$db->prepare("UPDATE administradores SET activo=:activo WHERE id=:id AND colegio_id=:c AND rol='docente'");$s->execute(array(':activo'=>$active,':id'=>$id,':c'=>$cid));if($s->rowCount()!==1)simceJson(array('ok'=>false,'error'=>'Usuario no encontrado o ya estaba en ese estado.'),404);simceLogSchoolAction($db,$cid,$active?'activar_docente':'desactivar_docente','Usuario docente ID '.$id);simceJson(array('ok'=>true));}
if($a==='delete_teacher'){simceRequireRole(array('colegio_admin'));$id=(int)($d['id']??0);$find=$db->prepare("SELECT usuario FROM administradores WHERE id=:id AND colegio_id=:c AND rol='docente'");$find->execute(array(':id'=>$id,':c'=>$cid));$teacher=$find->fetch();if(!$teacher)simceJson(array('ok'=>false,'error'=>'Usuario no encontrado.'),404);try{$db->beginTransaction();$delete=$db->prepare("DELETE FROM administradores WHERE id=:id AND colegio_id=:c AND rol='docente'");$delete->execute(array(':id'=>$id,':c'=>$cid));simceLogSchoolAction($db,$cid,'eliminar_docente','Usuario docente eliminado: '.$teacher['usuario']);$db->commit();}catch(Throwable$e){if($db->inTransaction())$db->rollBack();simceJson(array('ok'=>false,'error'=>'No se pudo eliminar el usuario.'),409);}simceJson(array('ok'=>true));}
simceJson(array('ok'=>false,'error'=>'Accion no valida.'),400);
