<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/seguridad.php';
simceRequireAdmin();

$baseDir = __DIR__ . '/pruebas';
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

simceJson(array(
    'ok' => true,
    'path' => 'pruebas/' . $course . '/' . $subject . '/' . $filename,
    'filename' => $filename,
));

