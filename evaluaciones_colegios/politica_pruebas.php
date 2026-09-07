<?php
declare(strict_types=1);

function simceTestPolicy(PDO $db, int $course, int $test, int $school): ?array {
    $q=$db->prepare('SELECT p.*,a.configuracion FROM pruebas p JOIN asignaciones a ON a.prueba_id=p.id AND a.colegio_id=p.colegio_id JOIN cursos c ON c.id=a.curso_id AND c.colegio_id=a.colegio_id WHERE p.id=:p AND c.id=:c AND p.colegio_id=:school1 AND c.colegio_id=:school2 AND c.activo=1 AND p.activa=1 AND a.activa=1');
    $q->execute(array(':p'=>$test,':c'=>$course,':school1'=>$school,':school2'=>$school));
    $row=$q->fetch();
    if(!$row)return null;
    $path=realpath(__DIR__.'/'. $row['ruta']); $base=realpath(__DIR__);
    $sharedRoot=realpath(__DIR__.'/pruebas');$schoolRoot=realpath(__DIR__.'/pruebas_colegios/'.$school);
    $insideShared=$path&&$sharedRoot&&strpos($path,$sharedRoot.DIRECTORY_SEPARATOR)===0;
    $insideSchool=$path&&$schoolRoot&&strpos($path,$schoolRoot.DIRECTORY_SEPARATOR)===0;
    if(!$path || !$base || (!$insideShared && !$insideSchool) || !is_file($path))return null;
    $html=(string)file_get_contents($path);
    $defaults=array('feedback'=>true,'results'=>true,'attempts'=>0,'finish'=>'results');
    if(preg_match('/const SHOW_ANS\s*=\s*(true|false)/',$html,$m))$defaults['feedback']=$m[1]==='true';
    if(preg_match('/<script type="application\/json" id="simce-default-policy">(.*?)<\/script>/s',$html,$m))$defaults=array_replace($defaults,json_decode($m[1],true)?:array());
    $row['policy']=array_replace($defaults,json_decode((string)($row['configuracion']??''),true)?:array());
    return $row;
}

function simceAttemptCount(PDO $db,array $student,int $test): int {
    if(!empty($student['acceso_libre'])){
        $q=$db->prepare('SELECT COUNT(*) FROM resultados WHERE colegio_id=:school AND prueba_id=:p AND acceso_libre=1 AND curso_nombre=:c AND LOWER(participante_nombre)=LOWER(:n)');
        $q->execute(array(':school'=>(int)$student['colegio_id'],':p'=>$test,':c'=>$student['curso'],':n'=>$student['nombre']));
    }else{
        $q=$db->prepare('SELECT COUNT(*) FROM resultados WHERE colegio_id=:school AND prueba_id=:p AND alumno_id=:a');
        $q->execute(array(':school'=>(int)$student['colegio_id'],':p'=>$test,':a'=>$student['id']));
    }
    return (int)$q->fetchColumn();
}
