<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/politica_pruebas.php';
simceSecurityHeaders();
$db = simceDatabase();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'courses');

if ($method === 'GET' && $action === 'courses') {
    simceSyncCourses($db);
    $rows = $db->query('SELECT id,nombre FROM cursos WHERE activo=1 ORDER BY idgrado,nombre')->fetchAll();
    simceJson(array('ok'=>true,'usa_lista_estudiantes'=>simceStudentListsEnabled($db),'cursos'=>$rows));
}

if ($method === 'GET' && $action === 'students') {
    if (!simceStudentListsEnabled($db)) simceJson(array('ok'=>false,'error'=>'El ingreso mediante lista está desactivado.'),409);
    $course = (int) ($_GET['curso'] ?? 0);
    $statement = $db->prepare('SELECT a.public_id,a.nombre FROM alumnos a JOIN cursos c ON c.nombre=a.curso WHERE c.id=:c AND a.activo=1 AND c.activo=1 ORDER BY a.nombre');
    $statement->execute(array(':c'=>$course));
    simceJson(array('ok'=>true,'estudiantes'=>$statement->fetchAll()));
}

if ($method === 'GET' && $action === 'session') {
    simceSyncStoredTests($db);
    simceStartStudentSession();
    if (empty($_SESSION['alumno_id'])) simceJson(array('ok'=>true,'authenticated'=>false));
    $student = simceRequireStudent();
    $statement = $db->prepare('SELECT p.id,p.titulo,p.asignatura FROM asignaciones a JOIN pruebas p ON p.id=a.prueba_id WHERE a.curso_id=:c AND a.activa=1 AND p.activa=1 ORDER BY p.asignatura,p.titulo');
    $statement->execute(array(':c'=>$student['curso_id']));
    $tests=$statement->fetchAll();
    foreach($tests as &$test){
        $configured=simceTestPolicy($db,(int)$student['curso_id'],(int)$test['id']);
        $test['intentos_usados']=simceAttemptCount($db,$student,(int)$test['id']);
        $test['intentos_maximos']=$configured?$configured['policy']['attempts']:0;
        $test['agotada']=!$configured || ($test['intentos_maximos']>0 && $test['intentos_usados']>=$test['intentos_maximos']);
    }
    unset($test);
    simceJson(array('ok'=>true,'authenticated'=>true,'acceso_libre'=>!empty($student['acceso_libre']),'estudiante'=>array('nombre'=>$student['nombre'],'curso'=>$student['curso']),'pruebas'=>$tests));
}

if ($method !== 'POST') simceJson(array('ok'=>false,'error'=>'Solicitud no válida.'),405);
$input = simceReadJson(65536);
$post = (string) ($input['action'] ?? 'login');
if ($post === 'logout') {
    simceStartStudentSession(); $_SESSION=array(); session_destroy(); simceJson(array('ok'=>true));
}
if ($post !== 'login') simceJson(array('ok'=>false,'error'=>'Acción no válida.'),400);

$courseId = (int) ($input['curso'] ?? 0);
$pin = (string) ($input['pin'] ?? '');
$courseQuery = $db->prepare('SELECT id,nombre,pin_hash,requiere_pin FROM cursos WHERE id=:id AND activo=1');
$courseQuery->execute(array(':id'=>$courseId));
$course = $courseQuery->fetch();
if (!$course) simceJson(array('ok'=>false,'error'=>'Selecciona un curso válido.'),400);
if ((int)$course['requiere_pin'] === 1 && (empty($course['pin_hash']) || !password_verify($pin,(string)$course['pin_hash']))) {
    usleep(350000); simceJson(array('ok'=>false,'error'=>'El PIN del curso no es correcto o aún no fue configurado.'),401);
}

$usesList = simceStudentListsEnabled($db);
if ($usesList) {
    $public = trim((string) ($input['estudiante'] ?? ''));
    $statement = $db->prepare('SELECT a.id,a.nombre,a.curso,c.id AS curso_id FROM alumnos a JOIN cursos c ON c.nombre=a.curso WHERE a.public_id=:p AND c.id=:c AND a.activo=1 AND c.activo=1');
    $statement->execute(array(':p'=>$public,':c'=>$courseId));
    $student = $statement->fetch();
    if (!$student) simceJson(array('ok'=>false,'error'=>'No se encontró al estudiante en ese curso.'),401);
    $studentId = (int) $student['id'];
    $studentName = '';
} else {
    $studentName = preg_replace('/\s+/u',' ',trim((string)($input['nombre']??''))) ?? '';
    $nameLength = function_exists('mb_strlen') ? mb_strlen($studentName,'UTF-8') : strlen($studentName);
    if ($nameLength < 2 || $nameLength > 150 || preg_match('/[\x00-\x1F\x7F]/u',$studentName)) {
        simceJson(array('ok'=>false,'error'=>'Escribe un nombre válido de entre 2 y 150 caracteres.'),400);
    }
    $studentId = -random_int(1,2147483647);
}

simceStartStudentSession();
session_regenerate_id(true);
$_SESSION['alumno_id']=$studentId;
$_SESSION['curso_id']=$courseId;
$_SESSION['last_activity']=time();
if ($usesList) unset($_SESSION['alumno_nombre_libre']); else $_SESSION['alumno_nombre_libre']=$studentName;
simceJson(array('ok'=>true));
