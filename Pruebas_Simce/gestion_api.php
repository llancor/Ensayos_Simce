<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceRequireAdmin();
$db = simceDatabase();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'summary');

function simceManagedUser(PDO $db, int $id): array
{
    $query = $db->prepare('SELECT id,usuario,rol,activo FROM administradores WHERE id=:id');
    $query->execute(array(':id' => $id));
    $user = $query->fetch();
    if (!$user) simceJson(array('ok' => false, 'error' => 'Usuario no encontrado.'), 404);

    $actorRole = (string) ($_SESSION['admin_rol'] ?? '');
    if ($actorRole === 'superadmin') return $user;
    if ($actorRole === 'administrador' && $user['rol'] === 'docente') return $user;
    simceJson(array('ok' => false, 'error' => 'No puedes administrar este perfil.'), 403);
}

function simceLogUserAction(PDO $db, string $action, string $detail): void
{
    $query = $db->prepare('INSERT INTO auditoria_administracion(accion,detalle,administrador_id) VALUES(:accion,:detalle,:admin)');
    $query->execute(array(':accion' => $action, ':detalle' => $detail, ':admin' => (int) $_SESSION['admin_id']));
}

if ($method === 'GET' && $action === 'summary') {
    simceSyncCourses($db);
    simceSyncStoredTests($db);
    $courses = $db->query("SELECT id,nombre,idgrado,nivel,activo,modo_pruebas,(CASE WHEN pin_hash IS NULL OR pin_hash='' THEN 0 ELSE 1 END) AS pin_configurado,requiere_pin FROM cursos WHERE activo=1 ORDER BY idgrado,nombre")->fetchAll();
    $results = $db->query("SELECT r.id,COALESCE(a.nombre,r.participante_nombre,'Sin nombre') AS nombre,COALESCE(a.curso,r.curso_nombre,'Sin curso') AS curso,p.titulo,p.asignatura,r.intento,r.correctas,r.total,r.porcentaje,r.nota,r.finalizado_at,r.acceso_libre FROM resultados r LEFT JOIN alumnos a ON a.id=r.alumno_id JOIN pruebas p ON p.id=r.prueba_id ORDER BY r.finalizado_at DESC LIMIT 500")->fetchAll();
    $users = array();
    $role = (string) ($_SESSION['admin_rol'] ?? '');
    if ($role === 'superadmin') {
        $users = $db->query('SELECT id,usuario,rol,activo,created_at,last_login_at FROM administradores ORDER BY usuario')->fetchAll();
    } elseif ($role === 'administrador') {
        $users = $db->query("SELECT id,usuario,rol,activo,created_at,last_login_at FROM administradores WHERE rol='docente' ORDER BY usuario")->fetchAll();
    }
    foreach ($users as &$user) $user['es_actual'] = (int) $user['id'] === (int) $_SESSION['admin_id'];
    unset($user);
    simceJson(array('ok' => true, 'rol' => $role, 'usa_lista_estudiantes' => simceStudentListsEnabled($db), 'cursos' => $courses, 'todos_cursos' => $db->query('SELECT id,nombre,idgrado,nivel,activo,modo_pruebas FROM cursos ORDER BY idgrado,nombre')->fetchAll(), 'pruebas'=>$db->query('SELECT id,titulo,curso_codigo,asignatura FROM pruebas WHERE activa=1 ORDER BY curso_codigo,asignatura,titulo')->fetchAll(), 'asignaciones'=>$db->query('SELECT curso_id,prueba_id,activa,configuracion FROM asignaciones')->fetchAll(), 'resultados' => $results, 'usuarios' => $users));
}

if ($method !== 'POST') simceJson(array('ok' => false, 'error' => 'Solicitud no válida.'), 405);
simceRequireCsrf();
$data = simceReadJson(65536);
$action = (string) ($data['action'] ?? '');

if (in_array($action, array('update_course','toggle_course','delete_course'), true)) {
    simceRequireRole(array('superadmin','administrador','docente'));
    $id=(int)($data['curso']??0);
    $q=$db->prepare('SELECT * FROM cursos WHERE id=:id');$q->execute(array(':id'=>$id));$course=$q->fetch();
    if(!$course)simceJson(array('ok'=>false,'error'=>'Curso no encontrado.'),404);
    $q=$db->prepare('SELECT COUNT(*) FROM alumnos WHERE curso=:n');$q->execute(array(':n'=>$course['nombre']));$students=(int)$q->fetchColumn();
    $q=$db->prepare('SELECT COUNT(*) FROM resultados WHERE curso_nombre=:n OR alumno_id IN (SELECT id FROM alumnos WHERE curso=:a)');$q->execute(array(':n'=>$course['nombre'],':a'=>$course['nombre']));$results=(int)$q->fetchColumn();
    if($action==='delete_course' && ($students || $results))simceJson(array('ok'=>false,'error'=>'Este curso tiene alumnos o resultados. Puedes deshabilitarlo para conservar su historial.'),409);
    if($action==='toggle_course' && !is_bool($data['activo']??null))simceJson(array('ok'=>false,'error'=>'Estado inválido.'),400);
    if($action==='update_course') {
        $grade=filter_var($data['grado']??null,FILTER_VALIDATE_INT);
        $section=strtoupper(trim((string)($data['seccion']??'')));
        if(!$grade || $grade<1 || $grade>12 || !preg_match('/^[A-Z0-9]{1,8}$/',$section))simceJson(array('ok'=>false,'error'=>'Revisa el nivel y la sección (1 a 8 letras o números).'),400);
        if($grade!==(int)$course['idgrado'] && ($students || $results))simceJson(array('ok'=>false,'error'=>'No se puede cambiar el nivel de un curso con alumnos o resultados. Puedes editar su sección.'),409);
        $name=($grade<=8?$grade:$grade-8).'° '.($grade<=8?'básico':'medio').' '.$section;
        $q=$db->prepare('SELECT id FROM cursos WHERE nombre=:n AND id<>:id');$q->execute(array(':n'=>$name,':id'=>$id));
        if($q->fetch())simceJson(array('ok'=>false,'error'=>'Ya existe un curso con ese nivel y sección.'),409);
    }
    $db->beginTransaction();
    try {
        if($action==='delete_course') {
            $q=$db->prepare('DELETE FROM asignaciones WHERE curso_id=:id');$q->execute(array(':id'=>$id));
            $q=$db->prepare('DELETE FROM cursos WHERE id=:id');$q->execute(array(':id'=>$id));
        } elseif($action==='toggle_course') {
            $q=$db->prepare('UPDATE cursos SET activo=:a,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$q->execute(array(':a'=>$data['activo']?1:0,':id'=>$id));
        } else {
            $q=$db->prepare('UPDATE cursos SET nombre=:n,idgrado=:g,nivel=:l,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$q->execute(array(':n'=>$name,':g'=>$grade,':l'=>$grade<=8?'basica':'media',':id'=>$id));
            $q=$db->prepare('UPDATE alumnos SET curso=:n WHERE curso=:old');$q->execute(array(':n'=>$name,':old'=>$course['nombre']));
            $q=$db->prepare('UPDATE resultados SET curso_nombre=:n WHERE curso_nombre=:old');$q->execute(array(':n'=>$name,':old'=>$course['nombre']));
            if($grade!==(int)$course['idgrado']){$q=$db->prepare('DELETE FROM asignaciones WHERE curso_id=:id');$q->execute(array(':id'=>$id));}
        }
        simceLogUserAction($db,$action,'Curso '.$id.': '.$course['nombre']);
        $db->commit();
    } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();simceJson(array('ok'=>false,'error'=>'No se pudo actualizar el curso. Recarga e intenta nuevamente.'),409);}
    simceJson(array('ok'=>true));
}

if ($action === 'create_course') {
    simceRequireRole(array('superadmin','administrador','docente'));
    $grade=filter_var($data['grado']??null,FILTER_VALIDATE_INT);
    $section=strtoupper(trim((string)($data['seccion']??'')));
    $mode=(string)($data['modo']??'asignadas');
    $pin=trim((string)($data['pin']??''));
    if(!$grade || $grade<1 || $grade>12 || !preg_match('/^[A-Z0-9]{1,8}$/',$section) || !in_array($mode,array('todas','asignadas'),true) || ($pin!=='' && (strlen($pin)<4 || strlen($pin)>30))) simceJson(array('ok'=>false,'error'=>'Revisa nivel, sección y PIN (opcional, entre 4 y 30 caracteres).'),400);
    $name=($grade<=8?$grade:($grade-8)).'° '.($grade<=8?'básico':'medio').' '.$section;
    try {
        $q=$db->prepare('INSERT INTO cursos(nombre,idgrado,nivel,modo_pruebas,requiere_pin,pin_hash) VALUES(:n,:g,:l,:m,:r,:p)');
        $q->execute(array(':n'=>$name,':g'=>$grade,':l'=>$grade<=8?'basica':'media',':m'=>$mode,':r'=>$pin!==''?1:0,':p'=>$pin!==''?password_hash($pin,PASSWORD_DEFAULT):null));
    } catch(PDOException $e) { simceJson(array('ok'=>false,'error'=>'El curso ya existe o no pudo crearse.'),409); }
    $id=(int)$db->lastInsertId();
    simceSyncStoredTests($db);
    simceLogUserAction($db,'crear_curso',$name);
    simceJson(array('ok'=>true,'id'=>$id));
}

if ($action === 'save_course_tests') {
    simceRequireRole(array('superadmin','administrador','docente'));
    $id=(int)($data['curso']??0); $mode=(string)($data['modo']??'');
    $q=$db->prepare('SELECT * FROM cursos WHERE id=:id AND activo=1');$q->execute(array(':id'=>$id));$course=$q->fetch();
    if(!$course || !in_array($mode,array('todas','asignadas'),true) || !is_array($data['pruebas']??null))simceJson(array('ok'=>false,'error'=>'Curso o configuración no válidos.'),400);
    $tests=$db->query('SELECT id,curso_codigo FROM pruebas WHERE activa=1')->fetchAll();
    $valid=array();foreach($tests as $test)if(simceCourseCodeMatches($course['nombre'],$test['curso_codigo']))$valid[(int)$test['id']]=true;
    $rows=array();
    foreach($data['pruebas'] as $row) {
        $testId=(int)($row['id']??0);$policy=$row['configuracion']??null;
        if(!isset($valid[$testId]) || isset($rows[$testId]))simceJson(array('ok'=>false,'error'=>'Prueba duplicada o ajena al nivel del curso.'),400);
        if($policy!==null) {
            if(!is_array($policy) || !is_bool($policy['feedback']??null) || !is_bool($policy['results']??null) || !is_int($policy['attempts']??null) || $policy['attempts']<0 || $policy['attempts']>100 || !in_array($policy['finish']??'',array('results','return'),true))simceJson(array('ok'=>false,'error'=>'Opciones de prueba inválidas.'),400);
            $policy=array_intersect_key($policy,array_flip(array('feedback','results','attempts','finish')));
        }
        $rows[$testId]=array('active'=>!empty($row['activa'])?1:0,'policy'=>$policy===null?null:json_encode($policy));
    }
    if(count($rows)!==count($valid))simceJson(array('ok'=>false,'error'=>'Cambió el catálogo de pruebas. Recarga la página antes de guardar.'),409);
    $db->beginTransaction();
    try {
        $q=$db->prepare('UPDATE cursos SET modo_pruebas=:m,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$q->execute(array(':m'=>$mode,':id'=>$id));
        foreach($rows as $testId=>$row) {
            $q=$db->prepare('SELECT id FROM asignaciones WHERE curso_id=:c AND prueba_id=:p');$q->execute(array(':c'=>$id,':p'=>$testId));
            $sql=$q->fetch()?'UPDATE asignaciones SET activa=:a,configuracion=:f WHERE curso_id=:c AND prueba_id=:p':'INSERT INTO asignaciones(curso_id,prueba_id,activa,configuracion) VALUES(:c,:p,:a,:f)';
            $q=$db->prepare($sql);$q->execute(array(':c'=>$id,':p'=>$testId,':a'=>$mode==='todas'?1:$row['active'],':f'=>$row['policy']));
        }
        simceLogUserAction($db,'configurar_pruebas_curso','Curso '.$id.': '.$mode);
        $db->commit();
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack();simceJson(array('ok'=>false,'error'=>'No se pudo guardar la configuración.'),500); }
    simceJson(array('ok'=>true));
}

if ($action === 'course_pin') {
    simceRequireRole(array('superadmin', 'administrador', 'docente'));
    $id = (int) ($data['curso'] ?? 0);
    $pin = trim((string) ($data['pin'] ?? ''));
    if (strlen($pin) < 4 || strlen($pin) > 30) simceJson(array('ok' => false, 'error' => 'El PIN debe tener entre 4 y 30 caracteres.'), 400);
    $query = $db->prepare('UPDATE cursos SET pin_hash=:hash,requiere_pin=1,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
    $query->execute(array(':hash' => password_hash($pin, PASSWORD_DEFAULT), ':id' => $id));
    if ($query->rowCount() !== 1) simceJson(array('ok' => false, 'error' => 'Curso no encontrado.'), 404);
    simceLogUserAction($db, 'actualizar_pin_curso', 'PIN actualizado para el curso ' . $id);
    simceJson(array('ok' => true));
}

if ($action === 'clear_course_pin') {
    simceRequireRole(array('superadmin', 'administrador', 'docente'));
    $id = (int) ($data['curso'] ?? 0);
    $query = $db->prepare('UPDATE cursos SET pin_hash=NULL,requiere_pin=0,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
    $query->execute(array(':id' => $id));
    if ($query->rowCount() !== 1) simceJson(array('ok' => false, 'error' => 'Curso no encontrado o ya estaba sin PIN.'), 404);
    simceLogUserAction($db, 'quitar_pin_curso', 'PIN desactivado para el curso ' . $id);
    simceJson(array('ok' => true));
}

if ($action === 'student_list_mode') {
    simceRequireRole(array('superadmin', 'administrador', 'docente'));
    $enabled = !empty($data['activo']) ? '1' : '0';
    $statement = $db->prepare("SELECT clave FROM configuracion WHERE clave='acceso_lista_estudiantes'");
    $statement->execute();
    $query = $statement->fetch()
        ? $db->prepare("UPDATE configuracion SET valor=:valor,updated_at=CURRENT_TIMESTAMP WHERE clave='acceso_lista_estudiantes'")
        : $db->prepare("INSERT INTO configuracion(clave,valor) VALUES('acceso_lista_estudiantes',:valor)");
    $query->execute(array(':valor'=>$enabled));
    simceLogUserAction($db, $enabled==='1'?'activar_listas_estudiantes':'desactivar_listas_estudiantes', $enabled==='1'?'Ingreso mediante listas activado.':'Ingreso libre mediante nombre activado.');
    simceJson(array('ok'=>true,'usa_lista_estudiantes'=>$enabled==='1'));
}

if ($action === 'create_user') {
    simceRequireRole(array('superadmin', 'administrador'));
    $username = trim((string) ($data['usuario'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $actorRole = (string) $_SESSION['admin_rol'];
    $role = $actorRole === 'superadmin' ? (string) ($data['rol'] ?? 'docente') : 'docente';
    $validPassword = strlen($password) >= 12 && preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/[0-9]/', $password);
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) || !$validPassword || !in_array($role, array('docente', 'administrador', 'superadmin'), true)) {
        simceJson(array('ok' => false, 'error' => 'Revisa usuario, perfil y contraseña (12 caracteres, mayúscula, minúscula y número).'), 400);
    }
    try {
        $query = $db->prepare('INSERT INTO administradores(usuario,password_hash,rol) VALUES(:usuario,:password,:rol)');
        $query->execute(array(':usuario' => $username, ':password' => password_hash($password, PASSWORD_DEFAULT), ':rol' => $role));
        simceLogUserAction($db, 'crear_usuario', 'Usuario creado: ' . $username . ' (' . $role . ')');
    } catch (Throwable $error) {
        simceJson(array('ok' => false, 'error' => 'El usuario ya existe o no se pudo crear.'), 409);
    }
    simceJson(array('ok' => true));
}

if ($action === 'reset_password') {
    simceRequireRole(array('superadmin', 'administrador'));
    $id = (int) ($data['id'] ?? 0);
    $password = (string) ($data['password'] ?? '');
    $validPassword = strlen($password) >= 12 && preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/[0-9]/', $password);
    if (!$validPassword) simceJson(array('ok' => false, 'error' => 'La contraseña debe tener 12 caracteres, mayúscula, minúscula y número.'), 400);
    $target = simceManagedUser($db, $id);
    if ($id === (int) $_SESSION['admin_id']) simceJson(array('ok' => false, 'error' => 'Cambia tu propia contraseña desde una opción de perfil.'), 400);
    $query = $db->prepare('UPDATE administradores SET password_hash=:password WHERE id=:id');
    $query->execute(array(':password' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id));
    simceLogUserAction($db, 'restablecer_password', 'Contraseña restablecida: ' . $target['usuario']);
    simceJson(array('ok' => true));
}

if ($action === 'set_user_status') {
    simceRequireRole(array('superadmin', 'administrador'));
    $id = (int) ($data['id'] ?? 0);
    $active = !empty($data['activo']) ? 1 : 0;
    $target = simceManagedUser($db, $id);
    if ($id === (int) $_SESSION['admin_id']) simceJson(array('ok' => false, 'error' => 'No puedes desactivar tu propia cuenta.'), 400);
    $query = $db->prepare('UPDATE administradores SET activo=:activo WHERE id=:id');
    $query->execute(array(':activo' => $active, ':id' => $id));
    if ($query->rowCount() !== 1) simceJson(array('ok' => false, 'error' => 'El usuario ya tenía ese estado.'), 409);
    simceLogUserAction($db, $active ? 'activar_usuario' : 'desactivar_usuario', 'Usuario: ' . $target['usuario']);
    simceJson(array('ok' => true));
}

if ($action === 'delete_user') {
    simceRequireRole(array('superadmin', 'administrador'));
    $id = (int) ($data['id'] ?? 0);
    $target = simceManagedUser($db, $id);
    if ($id === (int) $_SESSION['admin_id']) simceJson(array('ok' => false, 'error' => 'No puedes eliminar tu propia cuenta.'), 400);
    try {
        $db->beginTransaction();
        $query = $db->prepare('DELETE FROM administradores WHERE id=:id');
        $query->execute(array(':id' => $id));
        if ($query->rowCount() !== 1) throw new RuntimeException('Usuario no encontrado.');
        simceLogUserAction($db, 'eliminar_usuario', 'Usuario eliminado: ' . $target['usuario'] . ' (' . $target['rol'] . ')');
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        simceJson(array('ok' => false, 'error' => 'No se pudo eliminar el usuario.'), 409);
    }
    simceJson(array('ok' => true));
}

simceJson(array('ok' => false, 'error' => 'Acción no válida.'), 400);
