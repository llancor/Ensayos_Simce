<?php
declare(strict_types=1);
require_once __DIR__.'/seguridad.php'; $student=simceRequireStudent(); $db=simceDatabase(); $id=(int)($_GET['id']??0);
$s=$db->prepare('SELECT p.* FROM pruebas p JOIN asignaciones a ON a.prueba_id=p.id WHERE p.id=:p AND a.curso_id=:c AND p.activa=1 AND a.activa=1'); $s->execute(array(':p'=>$id,':c'=>$student['curso_id'])); $test=$s->fetch();
if(!$test){http_response_code(404);exit('Prueba no disponible.');} $path=realpath(__DIR__.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,(string)$test['ruta'])); $root=realpath(__DIR__.DIRECTORY_SEPARATOR.'pruebas');
if(!$path||!$root||strpos($path,$root.DIRECTORY_SEPARATOR)!==0||!is_file($path)){http_response_code(404);exit('Archivo de prueba no encontrado.');}
$_SESSION['prueba_inicio']=time(); $html=(string)file_get_contents($path); $name=json_encode((string)$student['nombre'],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$bridge="\nfetch('resultados_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({pruebaId:$id},resultData))}).then(function(r){return r.json()}).then(function(x){if(!x.ok)console.warn(x.error)});";
$assignmentPattern='/^([ \t]*resultData\s*=\s*\{[^\r\n]*analyticsPDFHTML[^\r\n]*\};)[ \t]*$/m';
$html=preg_replace_callback($assignmentPattern,static function(array $match) use($bridge): string{return $match[1].$bridge;},$html,1);
$prefill="<script>document.addEventListener('DOMContentLoaded',function(){var e=document.getElementById('student-name');if(e){e.value=$name;e.readOnly=true;}});</script>";
$html=preg_replace('/<\/body>\s*<\/html>\s*$/i',$prefill.'</body></html>',$html,1);
header('Content-Type:text/html; charset=utf-8'); header('Cache-Control:no-store'); echo $html;
