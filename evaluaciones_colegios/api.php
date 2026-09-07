<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceRequireRole(array('colegio_admin', 'docente'));

$database = simceDatabase();
$schoolId = simceTenantId();
if ($schoolId < 1) simceJson(array('ok' => false, 'error' => 'Sesión sin colegio.'), 403);
simceSyncStoredTests($database, $schoolId);

$statement = $database->prepare('SELECT id,titulo,curso_codigo,asignatura,ruta FROM pruebas WHERE colegio_id=:school AND activa=1 ORDER BY curso_codigo,asignatura,titulo');
$statement->execute(array(':school' => $schoolId));
$results = array();

foreach ($statement->fetchAll() as $row) {
    $course = (string) $row['curso_codigo'];
    $subject = (string) $row['asignatura'];
    if (!isset($results[$course])) $results[$course] = array();
    if (!isset($results[$course][$subject])) $results[$course][$subject] = array();
    $results[$course][$subject][] = array(
        'id' => (int) $row['id'],
        'titulo' => (string) $row['titulo'],
        'desc' => 'Prueba de ' . ucfirst($subject),
        'archivo' => pathinfo((string) $row['ruta'], PATHINFO_FILENAME),
        'url' => (string) $row['ruta'],
    );
}

simceJson($results);
