<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/seguridad.php';
simceRequireRole(array('superadmin', 'administrador', 'docente'));

$database = simceDatabase();
simceSyncStoredTests($database);

$allowedCourses = array('1basico','2basico','3basico','4basico','5basico','6basico','7basico','8basico','1medio','2medio','3medio','4medio');
$allowedSubjects = array('lenguaje','matematica','ciencia','ciencias','historia','ingles','tecnologia','artes','musica','educacion_fisica','otra');
$baseDir = __DIR__ . '/pruebas';

function simceBibliotecaSlug(string $value, string $fallback): string
{
    $value = preg_replace('/\.html?$/i', '', $value);
    $value = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $value);
    $value = preg_replace('/_{2,}/', '_', (string) $value);
    $value = trim((string) $value, '_-');
    if ($value === '') $value = $fallback;
    return substr($value, 0, 120) . '.html';
}

function simceBibliotecaPath(string $relative): array
{
    $relative = str_replace('\\', '/', $relative);
    $path = realpath(__DIR__ . '/' . $relative);
    $root = realpath(__DIR__ . '/pruebas');
    if (!$path || !$root || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
        simceJson(array('ok' => false, 'error' => 'Archivo de prueba no encontrado.'), 404);
    }
    return array($path, basename($path));
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($method === 'GET' && $action === 'export') {
    $id = (int) ($_GET['id'] ?? 0);
    $statement = $database->prepare('SELECT titulo,ruta FROM pruebas WHERE id=:id AND activa=1');
    $statement->execute(array(':id' => $id));
    $test = $statement->fetch();
    if (!$test) simceJson(array('ok' => false, 'error' => 'Prueba no encontrada.'), 404);
    [$path, $filename] = simceBibliotecaPath((string) $test['ruta']);
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

if ($method !== 'POST') {
    simceJson(array('ok' => false, 'error' => 'Método no permitido.'), 405);
}

simceRequireCsrf();
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$input = array();
if (strpos($contentType, 'application/json') !== false) {
    $input = simceReadJson();
    $action = (string) ($input['action'] ?? $action);
} else {
    $input = $_POST;
}

if ($action === 'delete') {
    simceRequireRole(array('superadmin', 'administrador'));
    $id = (int) ($input['id'] ?? 0);
    if ($id < 1) simceJson(array('ok' => false, 'error' => 'Prueba no válida.'), 400);
    $statement = $database->prepare('SELECT id,titulo FROM pruebas WHERE id=:id AND activa=1');
    $statement->execute(array(':id' => $id));
    $test = $statement->fetch();
    if (!$test) simceJson(array('ok' => false, 'error' => 'Prueba no encontrada o ya fue borrada.'), 404);
    $database->beginTransaction();
    try {
        $database->prepare('UPDATE pruebas SET activa=0,updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(array(':id' => $id));
        $database->prepare('UPDATE asignaciones SET activa=0 WHERE prueba_id=:id')->execute(array(':id' => $id));
        $database->prepare('INSERT INTO auditoria_administracion(accion,detalle,administrador_id) VALUES(:a,:d,:u)')->execute(array(':a' => 'borrar_prueba_biblioteca', ':d' => 'Prueba desactivada: ' . $test['titulo'], ':u' => (int) $_SESSION['admin_id']));
        $database->commit();
    } catch (Throwable $e) {
        if ($database->inTransaction()) $database->rollBack();
        simceJson(array('ok' => false, 'error' => 'No se pudo borrar la prueba.'), 500);
    }
    simceJson(array('ok' => true));
}

if ($action === 'bulk_delete') {
    simceRequireRole(array('superadmin', 'administrador'));
    $ids = isset($input['ids']) && is_array($input['ids']) ? array_values(array_unique(array_map('intval', $input['ids']))) : array();
    $ids = array_values(array_filter($ids, static fn($id) => $id > 0));
    if (!$ids) simceJson(array('ok' => false, 'error' => 'Selecciona al menos una prueba.'), 400);
    if (count($ids) > 200) simceJson(array('ok' => false, 'error' => 'Selecciona hasta 200 pruebas por operación.'), 400);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $database->beginTransaction();
    try {
        $statement = $database->prepare("SELECT id,titulo FROM pruebas WHERE activa=1 AND id IN ($placeholders)");
        $statement->execute($ids);
        $tests = $statement->fetchAll();
        if (!$tests) {
            $database->rollBack();
            simceJson(array('ok' => false, 'error' => 'No hay pruebas activas seleccionadas.'), 404);
        }
        $activeIds = array_map(static fn($row) => (int) $row['id'], $tests);
        $activePlaceholders = implode(',', array_fill(0, count($activeIds), '?'));
        $database->prepare("UPDATE pruebas SET activa=0,updated_at=CURRENT_TIMESTAMP WHERE id IN ($activePlaceholders)")->execute($activeIds);
        $database->prepare("UPDATE asignaciones SET activa=0 WHERE prueba_id IN ($activePlaceholders)")->execute($activeIds);
        $database->prepare('INSERT INTO auditoria_administracion(accion,detalle,administrador_id) VALUES(:a,:d,:u)')->execute(array(':a' => 'borrar_pruebas_biblioteca_masivo', ':d' => count($activeIds) . ' pruebas desactivadas desde biblioteca.', ':u' => (int) $_SESSION['admin_id']));
        $database->commit();
    } catch (Throwable $e) {
        if ($database->inTransaction()) $database->rollBack();
        simceJson(array('ok' => false, 'error' => 'No se pudieron borrar las pruebas seleccionadas.'), 500);
    }
    simceJson(array('ok' => true, 'deleted' => count($activeIds)));
}

if ($action === 'import') {
    $course = trim((string) ($input['curso'] ?? ''));
    $subject = trim((string) ($input['asignatura'] ?? ''));
    $title = trim((string) ($input['titulo'] ?? ''));
    if (!in_array($course, $allowedCourses, true) || !in_array($subject, $allowedSubjects, true)) {
        simceJson(array('ok' => false, 'error' => 'Curso o asignatura no permitidos.'), 400);
    }
    if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo']) || (int) $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        simceJson(array('ok' => false, 'error' => 'Selecciona un archivo HTML válido.'), 400);
    }
    if ((int) $_FILES['archivo']['size'] > 15728640) {
        simceJson(array('ok' => false, 'error' => 'La prueba supera el máximo de 15 MB.'), 413);
    }
    $originalName = (string) ($_FILES['archivo']['name'] ?? 'prueba.html');
    if (!preg_match('/\.html?$/i', $originalName)) {
        simceJson(array('ok' => false, 'error' => 'Solo se pueden importar archivos HTML.'), 400);
    }
    $tmp = (string) ($_FILES['archivo']['tmp_name'] ?? '');
    $html = $tmp !== '' ? file_get_contents($tmp) : false;
    if ($html === false || $html === '') simceJson(array('ok' => false, 'error' => 'No se pudo leer el archivo importado.'), 400);
    if (stripos($html, '<html') === false) simceJson(array('ok' => false, 'error' => 'El archivo no parece ser una prueba HTML completa.'), 400);
    if ($title === '') $title = pathinfo($originalName, PATHINFO_FILENAME);

    $subjectDir = $baseDir . '/' . $course . '/' . $subject;
    if (!is_dir($subjectDir) && !mkdir($subjectDir, 0770, true) && !is_dir($subjectDir)) {
        simceJson(array('ok' => false, 'error' => 'No se pudo preparar la carpeta de destino.'), 500);
    }
    $filename = simceBibliotecaSlug($originalName, 'prueba_' . date('Ymd_His'));
    $destination = $subjectDir . '/' . $filename;
    if (file_exists($destination)) {
        $filename = preg_replace('/\.html$/i', '', $filename) . '_' . date('His') . '.html';
        $destination = $subjectDir . '/' . $filename;
    }
    if (file_put_contents($destination, $html, LOCK_EX) === false) simceJson(array('ok' => false, 'error' => 'No se pudo guardar la prueba importada.'), 500);
    @chmod($destination, 0640);

    $relativePath = 'pruebas/' . $course . '/' . $subject . '/' . $filename;
    $statement = $database->prepare('INSERT INTO pruebas(ruta,titulo,curso_codigo,asignatura,creador_id,activa) VALUES(:ruta,:titulo,:curso,:asig,:creador,1)');
    $statement->execute(array(':ruta' => $relativePath, ':titulo' => substr($title, 0, 200), ':curso' => $course, ':asig' => $subject, ':creador' => (int) $_SESSION['admin_id']));
    $testId = (int) $database->lastInsertId();
    simceSyncCourses($database);
    foreach ($database->query("SELECT id,nombre FROM cursos WHERE activo=1 AND modo_pruebas='todas'")->fetchAll() as $row) {
        if (!simceCourseCodeMatches((string) $row['nombre'], $course)) continue;
        $check = $database->prepare('SELECT id FROM asignaciones WHERE prueba_id=:p AND curso_id=:c');
        $check->execute(array(':p' => $testId, ':c' => $row['id']));
        if (!$check->fetch()) $database->prepare('INSERT INTO asignaciones(prueba_id,curso_id) VALUES(:p,:c)')->execute(array(':p' => $testId, ':c' => $row['id']));
    }
    $database->prepare('INSERT INTO auditoria_administracion(accion,detalle,administrador_id) VALUES(:a,:d,:u)')->execute(array(':a' => 'importar_prueba_biblioteca', ':d' => 'Prueba importada: ' . $title, ':u' => (int) $_SESSION['admin_id']));
    simceJson(array('ok' => true, 'pruebaId' => $testId, 'path' => $relativePath, 'filename' => $filename));
}

simceJson(array('ok' => false, 'error' => 'Acción no reconocida.'), 400);
