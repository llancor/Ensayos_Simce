<?php
declare(strict_types=1);
require_once __DIR__.'/seguridad.php'; simceSecurityHeaders(); $db=simceDatabase();
$method=$_SERVER['REQUEST_METHOD']??'GET'; $action=(string)($_GET['action']??'courses');
if($method==='GET' && $action==='courses') { simceSyncCourses($db); $rows=$db->query('SELECT id,nombre FROM cursos WHERE activo=1 ORDER BY idgrado,nombre')->fetchAll(); simceJson(array('ok'=>true,'cursos'=>$rows)); }
if($method==='GET' && $action==='students') { $course=(int)($_GET['curso']??0); $s=$db->prepare('SELECT a.public_id,a.nombre FROM alumnos a JOIN cursos c ON c.nombre=a.curso WHERE c.id=:c AND a.activo=1 AND c.activo=1 ORDER BY a.nombre'); $s->execute(array(':c'=>$course)); simceJson(array('ok'=>true,'estudiantes'=>$s->fetchAll())); }
if($method==='GET' && $action==='session') {
    simceSyncStoredTests($db);
    simceStartStudentSession(); if(empty($_SESSION['alumno_id'])) simceJson(array('ok'=>true,'authenticated'=>false));
    $student=simceRequireStudent(); $s=$db->prepare('SELECT p.id,p.titulo,p.asignatura FROM asignaciones a JOIN pruebas p ON p.id=a.prueba_id WHERE a.curso_id=:c AND a.activa=1 AND p.activa=1 ORDER BY p.asignatura,p.titulo'); $s->execute(array(':c'=>$student['curso_id']));
    simceJson(array('ok'=>true,'authenticated'=>true,'estudiante'=>array('nombre'=>$student['nombre'],'curso'=>$student['curso']),'pruebas'=>$s->fetchAll()));
}
if($method!=='POST') simceJson(array('ok'=>false,'error'=>'Solicitud no válida.'),405);
$input=simceReadJson(65536); $post=(string)($input['action']??'login');
if($post==='logout'){ simceStartStudentSession(); $_SESSION=array(); session_destroy(); simceJson(array('ok'=>true)); }
if($post!=='login') simceJson(array('ok'=>false,'error'=>'Acción no válida.'),400);
$course=(int)($input['curso']??0); $public=trim((string)($input['estudiante']??'')); $pin=(string)($input['pin']??'');
$s=$db->prepare('SELECT a.id,a.nombre,a.curso,c.id AS curso_id,c.pin_hash,c.requiere_pin FROM alumnos a JOIN cursos c ON c.nombre=a.curso WHERE a.public_id=:p AND c.id=:c AND a.activo=1 AND c.activo=1'); $s->execute(array(':p'=>$public,':c'=>$course)); $student=$s->fetch();
if(!$student) simceJson(array('ok'=>false,'error'=>'No se encontró al estudiante en ese curso.'),401);
if((int)$student['requiere_pin']===1 && (empty($student['pin_hash']) || !password_verify($pin,(string)$student['pin_hash']))) { usleep(350000); simceJson(array('ok'=>false,'error'=>'El PIN del curso no es correcto o aún no fue configurado.'),401); }
simceStartStudentSession(); session_regenerate_id(true); $_SESSION['alumno_id']=(int)$student['id']; $_SESSION['curso_id']=(int)$student['curso_id']; $_SESSION['last_activity']=time();
simceJson(array('ok'=>true));
