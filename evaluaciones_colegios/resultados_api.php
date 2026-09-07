<?php
declare(strict_types=1);
require_once __DIR__.'/seguridad.php';
require_once __DIR__.'/politica_pruebas.php';
$student=simceRequireStudent();$db=simceDatabase();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')simceJson(array('ok'=>false,'error'=>'Metodo no permitido.'),405);
$d=simceReadJson(1048576);$testId=(int)($d['pruebaId']??0);$token=(string)($d['token']??'');$ticket=$_SESSION['prueba_tokens'][$token]??null;
if(!$ticket||(int)$ticket['id']!==$testId)simceJson(array('ok'=>false,'error'=>'Abre la prueba desde el portal para registrar el resultado.'),403);
$test=simceTestPolicy($db,(int)$student['curso_id'],$testId,(int)$student['colegio_id']);if(!$test)simceJson(array('ok'=>false,'error'=>'La prueba ya no esta asignada al curso.'),403);$policy=$test['policy'];
$q=$db->prepare('SELECT intento FROM resultados WHERE colegio_id=:school AND submission_token=:t');$q->execute(array(':school'=>$student['colegio_id'],':t'=>$token));$previous=$q->fetch();if($previous)simceJson(array('ok'=>true,'intento'=>(int)$previous['intento'],'intentos_usados'=>simceAttemptCount($db,$student,$testId),'policy'=>$policy));
$correct=max(0,(int)($d['correct']??0));$total=max(1,(int)($d['total']??0));if($correct>$total)simceJson(array('ok'=>false,'error'=>'Resultado invalido.'),400);
$score=(float)($d['puntaje']??$correct);$max=max(.01,(float)($d['puntajeMax']??$total));$pct=min(100,max(0,(float)($d['pct']??$correct/$total*100)));$nota=isset($d['notaNum'])?(float)$d['notaNum']:null;$details=json_encode($d['detalles']??array(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$db->beginTransaction();
try{
    $lock=$db->prepare('UPDATE cursos SET activo=activo WHERE colegio_id=:school AND id=:course');$lock->execute(array(':school'=>$student['colegio_id'],':course'=>$student['curso_id']));
    $test=simceTestPolicy($db,(int)$student['curso_id'],$testId,(int)$student['colegio_id']);if(!$test){$db->rollBack();simceJson(array('ok'=>false,'error'=>'La prueba ya no esta disponible.'),403);}
    $policy=$test['policy'];$used=simceAttemptCount($db,$student,$testId);if($policy['attempts']>0&&$used>=$policy['attempts']){$db->rollBack();simceJson(array('ok'=>false,'error'=>'Ya completaste los intentos permitidos.'),409);}
    $free=!empty($student['acceso_libre']);
    if($free){$n=$db->prepare('SELECT COALESCE(MAX(intento),0)+1 FROM resultados WHERE colegio_id=:school AND prueba_id=:p AND acceso_libre=1 AND curso_nombre=:c AND LOWER(participante_nombre)=LOWER(:nombre)');$n->execute(array(':school'=>$student['colegio_id'],':p'=>$testId,':c'=>$student['curso'],':nombre'=>$student['nombre']));}
    else{$n=$db->prepare('SELECT COALESCE(MAX(intento),0)+1 FROM resultados WHERE colegio_id=:school AND alumno_id=:a AND prueba_id=:p');$n->execute(array(':school'=>$student['colegio_id'],':a'=>$student['id'],':p'=>$testId));}
    $attempt=(int)$n->fetchColumn();
    $i=$db->prepare('INSERT INTO resultados(colegio_id,alumno_id,prueba_id,intento,correctas,total,puntaje,puntaje_max,porcentaje,nota,respuestas_json,iniciado_at,ip,participante_nombre,curso_nombre,acceso_libre,submission_token)VALUES(:school,:a,:p,:i,:c,:t,:score,:max,:pct,:nota,:r,:start,:ip,:nombre,:curso,:libre,:token)');
    $i->execute(array(':school'=>$student['colegio_id'],':a'=>$student['id'],':p'=>$testId,':i'=>$attempt,':c'=>$correct,':t'=>$total,':score'=>$score,':max'=>$max,':pct'=>$pct,':nota'=>$nota,':r'=>$details,':start'=>date('Y-m-d H:i:s',(int)$ticket['inicio']),':ip'=>simceClientIp(),':nombre'=>$student['nombre'],':curso'=>$student['curso'],':libre'=>$free?1:0,':token'=>$token));
    $db->commit();
}catch(Throwable$e){if($db->inTransaction())$db->rollBack();simceJson(array('ok'=>false,'error'=>'No se pudo guardar el resultado. Reintenta el guardado.'),500);}
simceJson(array('ok'=>true,'intento'=>$attempt,'intentos_usados'=>$used+1,'policy'=>$policy));
