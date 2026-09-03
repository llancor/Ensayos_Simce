<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/seguridad.php';
simceRequireRole(array('colegio_admin','docente'));
$schoolId=simceTenantId(); if($schoolId<1)simceJson(array('ok'=>false,'error'=>'Sesión sin colegio.'),403);

$baseDir = __DIR__ . '/pruebas_colegios/' . $schoolId;
$allowedCourses = array(
    '1basico', '2basico', '3basico', '4basico',
    '5basico', '6basico', '7basico', '8basico',
    '1medio', '2medio', '3medio', '4medio',
);
$allowedSubjects = array(
    'lenguaje', 'matematica', 'ciencia', 'historia', 'ingles',
    'tecnologia', 'artes', 'musica', 'educacion_fisica', 'otra',
);

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($method === 'GET') {
    $result = array();
    foreach ($allowedCourses as $course) {
        $result[$course] = $allowedSubjects;
    }
    simceJson($result);
}

if ($method !== 'POST') {
    simceJson(array('ok' => false, 'error' => 'Método no permitido.'), 405);
}

simceRequireCsrf();
$input = simceReadJson(16777216);
$course = isset($input['curso']) ? trim((string) $input['curso']) : '';
$subject = isset($input['asignatura']) ? trim((string) $input['asignatura']) : '';
$filename = isset($input['filename']) ? trim((string) $input['filename']) : '';
$html = isset($input['html']) ? (string) $input['html'] : '';

if (!in_array($course, $allowedCourses, true) || !in_array($subject, $allowedSubjects, true)) {
    simceJson(array('ok' => false, 'error' => 'Curso o asignatura no permitidos.'), 400);
}
if ($html === '') {
    simceJson(array('ok' => false, 'error' => 'No se recibió contenido HTML para guardar.'), 400);
}
if (strlen($html) > 15728640) {
    simceJson(array('ok' => false, 'error' => 'La prueba supera el máximo de 15 MB.'), 413);
}
if (stripos($html, '<!doctype html') === false || stripos($html, '<html') === false) {
    simceJson(array('ok' => false, 'error' => 'El contenido recibido no corresponde a una prueba HTML completa.'), 400);
}

$courseDir = $baseDir . '/' . $course;
$subjectDir = $courseDir . '/' . $subject;
if (!is_dir($subjectDir) && !mkdir($subjectDir, 0770, true) && !is_dir($subjectDir)) {
    simceJson(array('ok' => false, 'error' => 'No se pudo preparar la carpeta del curso. Revisa los permisos del servidor.'), 500);
}

$filename = preg_replace('/\.html?$/i', '', $filename);
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $filename);
$filename = preg_replace('/_{2,}/', '_', (string) $filename);
$filename = trim((string) $filename, '_-');
if ($filename === '') {
    $filename = 'prueba_' . date('Ymd_His');
}
$filename = substr($filename, 0, 120) . '.html';

$destination = $subjectDir . '/' . $filename;
$written = @file_put_contents($destination, $html, LOCK_EX);
if ($written === false) {
    simceJson(array('ok' => false, 'error' => 'No se pudo escribir la prueba. Verifica los permisos de la carpeta “pruebas”.'), 500);
}
@chmod($destination, 0640);

$database = simceDatabase();
$relativePath = 'pruebas_colegios/' . $schoolId . '/' . $course . '/' . $subject . '/' . $filename;
$title = trim((string)($input['titulo'] ?? pathinfo($filename, PATHINFO_FILENAME)));
if ($title === '') $title = pathinfo($filename, PATHINFO_FILENAME);
$find = $database->prepare('SELECT id FROM pruebas WHERE colegio_id=:school AND ruta=:ruta');
$find->execute(array(':school'=>$schoolId,':ruta'=>$relativePath)); $testId=$find->fetchColumn();
if ($testId) {
    $query=$database->prepare('UPDATE pruebas SET titulo=:titulo,curso_codigo=:curso,asignatura=:asig,creador_id=:creador,activa=1,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND colegio_id=:school');
    $query->execute(array(':titulo'=>substr($title,0,200),':curso'=>$course,':asig'=>$subject,':creador'=>(int)$_SESSION['admin_id'],':id'=>(int)$testId,':school'=>$schoolId));
} else {
    $query=$database->prepare('INSERT INTO pruebas(colegio_id,ruta,titulo,curso_codigo,asignatura,creador_id) VALUES(:school,:ruta,:titulo,:curso,:asig,:creador)');
    $query->execute(array(':school'=>$schoolId,':ruta'=>$relativePath,':titulo'=>substr($title,0,200),':curso'=>$course,':asig'=>$subject,':creador'=>(int)$_SESSION['admin_id'])); $testId=(int)$database->lastInsertId();
}
simceSyncCourses($database,$schoolId);
$courseQuery=$database->prepare('SELECT id,nombre FROM cursos WHERE colegio_id=:school AND activo=1');$courseQuery->execute(array(':school'=>$schoolId));
foreach ($courseQuery->fetchAll() as $row) {
    if (!simceCourseCodeMatches((string)$row['nombre'],$course)) continue;
    $check=$database->prepare('SELECT id FROM asignaciones WHERE prueba_id=:p AND curso_id=:c'); $check->execute(array(':p'=>$testId,':c'=>$row['id']));
    if(!$check->fetch()) { $assign=$database->prepare('INSERT INTO asignaciones(colegio_id,prueba_id,curso_id) VALUES(:school,:p,:c)'); $assign->execute(array(':school'=>$schoolId,':p'=>$testId,':c'=>$row['id'])); }
}

simceJson(array(
    'ok' => true,
    'path' => $relativePath,
    'pruebaId' => (int)$testId,
    'filename' => $filename,
));
