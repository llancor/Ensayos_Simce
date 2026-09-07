<?php
declare(strict_types=1);
require_once __DIR__.'/seguridad.php';
require_once __DIR__.'/politica_pruebas.php';
$student=simceRequireStudent();$db=simceDatabase();$id=(int)($_GET['id']??0);
$test=simceTestPolicy($db,(int)$student['curso_id'],$id);
function simceUnavailable(string $message): void {http_response_code(403);header('Content-Type:text/html; charset=utf-8');echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Prueba no disponible</title><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p><a href="portal_estudiante.php">Volver a mis pruebas</a></html>';exit;}
if(!$test)simceUnavailable('Esta prueba no está disponible para tu curso.');
$used=simceAttemptCount($db,$student,$id);$policy=$test['policy'];
if($policy['attempts']>0 && $used>=$policy['attempts'])simceUnavailable('Ya completaste los intentos permitidos para esta prueba.');
$html=(string)file_get_contents(__DIR__.'/'.$test['ruta']);
$token=bin2hex(random_bytes(24));
$_SESSION['prueba_tokens'][$token]=array('id'=>$id,'inicio'=>time());
if(count($_SESSION['prueba_tokens'])>50)array_shift($_SESSION['prueba_tokens']);
$context=array('testId'=>$id,'token'=>$token,'used'=>$used,'name'=>$student['nombre']);
$flags=JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT;
$runtime='<script>'.file_get_contents(__DIR__.'/politica-runtime.js').'simceInstallPolicy('.json_encode($policy,$flags).','.json_encode($context,$flags).');</script>';
// The assignment configuration precedes any defaults embedded by the creator.
$html=preg_replace_callback('/<head[^>]*>/i',static function(array $match)use($runtime):string{return $match[0].$runtime;},$html,1);
$html=preg_replace('/const SHOW_ANS\s*=\s*(true|false)/','const SHOW_ANS = '.($policy['feedback']?'true':'false'),$html,1);
$html=preg_replace('/(?<!async )function submitTest\s*\(/','async function submitTest(',$html,1);
if(strpos($html,'window.simceBegin();')===false)$html=preg_replace('/submitted\s*=\s*true\s*;/','window.simceBegin(); submitted = true;',$html,1);
// Imported quiz-engine format uses a different submit function and result container.
if(strpos($html,'submit:function(timeout){')!==false){
    $html=str_replace('submit:function(timeout){','submit:async function(timeout){',$html);
    $html=str_replace('if(done)return;done=true;','if(done)return;done=true;window.simceBegin();',$html);
    $adapter=<<<'JS'
  var resultData={correct:correct,total:total,puntaje:pts,puntajeMax:C.puntajeMaximo,pct:pct,notaNum:nota,detalles:QS.map(function(q,i){var id=q.id||('q'+i);return {num:i+1,pregunta:q.question||q.text||q.title||'',alumno:ans[id],correcta:COR[id],textAlumno:ans[id]||'',textCorrecta:COR[id]||'',ok:ans[id]!=null&&String(ans[id])===String(COR[id]),habilidad:q.indicador||''};})};
  if (!(await window.simceFinish(resultData))) return;
JS;
    $html=str_replace('  var resultEl=document.getElementById("result");',$adapter."\n  var resultEl=document.getElementById(\"result\");",$html);
}
if(strpos($html,'await window.simceFinish(resultData)')===false){
    $html=preg_replace('/^([ \t]*resultData\s*=\s*\{[^\r\n]*\};)[ \t]*$/m','$1'."\n    if (!(await window.simceFinish(resultData))) return;",$html,1,$count);
    if($count!==1)simceUnavailable('Esta prueba necesita actualizarse en el creador antes de rendirse.');
}
$html=str_replace('w.document.close();',"w.document.close(); if(document.documentElement.classList.contains('simce-no-retry')) w.document.querySelectorAll('.btn-retry-report').forEach(function(b){b.remove();});",$html);
header('Content-Type:text/html; charset=utf-8');header('Cache-Control:no-store');echo $html;
