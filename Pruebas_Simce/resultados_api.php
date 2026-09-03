<?php
declare(strict_types=1);
require_once __DIR__.'/seguridad.php'; $student=simceRequireStudent(); $db=simceDatabase();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST') simceJson(array('ok'=>false,'error'=>'Método no permitido.'),405);
$d=simceReadJson(1048576); $testId=(int)($d['pruebaId']??0);
$q=$db->prepare('SELECT p.id FROM pruebas p JOIN asignaciones a ON a.prueba_id=p.id WHERE p.id=:p AND a.curso_id=:c AND p.activa=1 AND a.activa=1'); $q->execute(array(':p'=>$testId,':c'=>$student['curso_id'])); if(!$q->fetch())simceJson(array('ok'=>false,'error'=>'La prueba no está asignada al curso.'),403);
$correct=max(0,(int)($d['correct']??0)); $total=max(1,(int)($d['total']??0)); if($correct>$total)simceJson(array('ok'=>false,'error'=>'Resultado inválido.'),400);
$score=(float)($d['puntaje']??$correct); $max=max(0.01,(float)($d['puntajeMax']??$total)); $pct=min(100,max(0,(float)($d['pct']??($correct/$total*100)))); $nota=isset($d['notaNum'])?(float)$d['notaNum']:null; $details=json_encode($d['detalles']??array(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$n=$db->prepare('SELECT COALESCE(MAX(intento),0)+1 FROM resultados WHERE alumno_id=:a AND prueba_id=:p'); $n->execute(array(':a'=>$student['id'],':p'=>$testId)); $attempt=(int)$n->fetchColumn();
$i=$db->prepare('INSERT INTO resultados(alumno_id,prueba_id,intento,correctas,total,puntaje,puntaje_max,porcentaje,nota,respuestas_json,iniciado_at,ip) VALUES(:a,:p,:i,:c,:t,:s,:m,:pct,:n,:r,:start,:ip)');
$i->execute(array(':a'=>$student['id'],':p'=>$testId,':i'=>$attempt,':c'=>$correct,':t'=>$total,':s'=>$score,':m'=>$max,':pct'=>$pct,':n'=>$nota,':r'=>$details,':start'=>date('Y-m-d H:i:s',(int)($_SESSION['prueba_inicio']??time())),':ip'=>simceClientIp())); simceJson(array('ok'=>true,'intento'=>$attempt));
