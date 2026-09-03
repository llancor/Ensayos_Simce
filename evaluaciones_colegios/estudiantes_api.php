<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceRequireRole(array('colegio_admin'));
$database = simceDatabase();
$schoolId=simceTenantId(); if($schoolId<1)simceJson(array('ok'=>false,'error'=>'No hay un colegio asociado a la sesión.'),403);

function simceNormalizeRut(string $rut): string
{
    return strtoupper((string) preg_replace('/[^0-9Kk]/', '', $rut));
}

function simceNormalizeCourseText(string $course): string
{
    $course = trim((string) preg_replace('/\s+/u', ' ', $course));
    return $course;
}

function simceCourseLevel(string $course, int $gradeId): array
{
    $normalized = strtolower(strtr($course, array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u')));
    if (!preg_match('/^\s*([1-8])\s*[°º]?\s*(basico|medio)\b/u', $normalized, $matches)) {
        return array('', 'El curso debe escribirse como “1° básico A” o “1° medio A”.');
    }

    $courseGrade = (int) $matches[1];
    $type = $matches[2];
    if ($type === 'basico') {
        if ($courseGrade > 8 || $gradeId !== $courseGrade) {
            return array('', 'El idgrado no coincide con el curso de enseñanza básica.');
        }
        return array('basica', '');
    }

    if ($courseGrade > 4 || $gradeId !== $courseGrade + 8) {
        return array('', 'Para enseñanza media usa idgrado 9, 10, 11 o 12 según el curso.');
    }
    return array('media', '');
}

function simceValidateStudent(array $student): array
{
    $name = isset($student['Nombre']) ? trim((string) $student['Nombre']) : '';
    $rut = isset($student['Rut']) ? trim((string) $student['Rut']) : '';
    $course = isset($student['Curso']) ? simceNormalizeCourseText((string) $student['Curso']) : '';
    $gradeIdRaw = isset($student['idgrado']) ? $student['idgrado'] : null;
    $gradeId = filter_var($gradeIdRaw, FILTER_VALIDATE_INT);
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);

    if ($name === '' || $nameLength > 150) {
        return array(null, 'Nombre vacío o demasiado largo.');
    }
    $normalizedRut = simceNormalizeRut($rut);
    if (!preg_match('/^[0-9K]{6,12}$/', $normalizedRut)) {
        return array(null, 'RUT o identificador inválido.');
    }
    if ($course === '' || strlen($course) > 80) {
        return array(null, 'Curso vacío o demasiado largo.');
    }
    if ($gradeId === false || $gradeId < 1 || $gradeId > 12) {
        return array(null, 'idgrado debe estar entre 1 y 12.');
    }

    list($level, $levelError) = simceCourseLevel($course, (int) $gradeId);
    if ($levelError !== '') {
        return array(null, $levelError);
    }

    return array(array(
        'nombre' => $name,
        'rut' => $rut,
        'rut_normalizado' => $normalizedRut,
        'curso' => $course,
        'idgrado' => (int) $gradeId,
        'nivel' => $level,
    ), '');
}

function simceMaskedRut(string $rut): string
{
    $normalized = simceNormalizeRut($rut);
    $visible = substr($normalized, -4);
    return str_repeat('•', max(4, strlen($normalized) - 4)) . $visible;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? (string) $_GET['action'] : 'list';
    if ($action === 'summary') {
        $stats=$database->prepare("SELECT COUNT(*) total,SUM(nivel='basica') basica,SUM(nivel='media') media FROM alumnos WHERE colegio_id=:school AND activo=1");$stats->execute(array(':school'=>$schoolId));$sr=$stats->fetch();$total=(int)$sr['total'];$basic=(int)$sr['basica'];$middle=(int)$sr['media'];
        $courses=$database->prepare('SELECT curso,idgrado,nivel,COUNT(*) total FROM alumnos WHERE colegio_id=:school AND activo=1 GROUP BY curso,idgrado,nivel ORDER BY idgrado,curso');$courses->execute(array(':school'=>$schoolId));$courseRows=$courses->fetchAll();
        simceJson(array('ok' => true, 'total' => $total, 'basica' => $basic, 'media' => $middle, 'cursos' => $courseRows));
    }

    if ($action !== 'list') {
        simceJson(array('ok' => false, 'error' => 'Acción no válida.'), 400);
    }

    $course = isset($_GET['curso']) ? trim((string) $_GET['curso']) : '';
    $search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    $limit = max(10, min(200, $limit));
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    $where = array('colegio_id = :school','activo = 1');
    $params = array(':school'=>$schoolId);
    if ($course !== '') {
        $where[] = 'curso = :curso';
        $params[':curso'] = $course;
    }
    if ($search !== '') {
        $where[] = '(nombre LIKE :search OR rut_normalizado LIKE :rut_search)';
        $params[':search'] = '%' . $search . '%';
        $params[':rut_search'] = '%' . simceNormalizeRut($search) . '%';
    }
    $whereSql = implode(' AND ', $where);

    $countStatement = $database->prepare('SELECT COUNT(*) FROM alumnos WHERE ' . $whereSql);
    $countStatement->execute($params);
    $total = (int) $countStatement->fetchColumn();

    $query = 'SELECT id, nombre, rut, curso, idgrado, nivel FROM alumnos WHERE ' . $whereSql . ' ORDER BY idgrado, curso, nombre LIMIT :limit OFFSET :offset';
    $statement = $database->prepare($query);
    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $students = array();
    foreach ($statement->fetchAll() as $row) {
        $students[] = array(
            'id' => (int) $row['id'],
            'nombre' => (string) $row['nombre'],
            'rut' => (string) $row['rut'],
            'curso' => (string) $row['curso'],
            'idgrado' => (int) $row['idgrado'],
            'nivel' => (string) $row['nivel'],
        );
    }
    simceJson(array('ok' => true, 'total' => $total, 'page' => $page, 'limit' => $limit, 'students' => $students));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    simceJson(array('ok' => false, 'error' => 'Método no permitido.'), 405);
}

simceRequireCsrf();
$input = simceReadJson();
$postAction = isset($input['action']) ? (string) $input['action'] : '';

if ($postAction === 'clear') {
    $confirmation = isset($input['confirmation']) ? (string) $input['confirmation'] : '';
    $expectedTotal = isset($input['expectedTotal']) ? filter_var($input['expectedTotal'], FILTER_VALIDATE_INT) : false;
    if ($confirmation !== 'VACIAR') {
        simceJson(array('ok' => false, 'error' => 'La confirmación para vaciar la nómina no es válida.'), 400);
    }
    if ($expectedTotal === false || $expectedTotal < 0) {
        simceJson(array('ok' => false, 'error' => 'No se pudo verificar el total actual de estudiantes.'), 400);
    }

    try {
        $database->beginTransaction();
        $count=$database->prepare('SELECT COUNT(*) FROM alumnos WHERE colegio_id=:school AND activo=1');$count->execute(array(':school'=>$schoolId));$currentTotal=(int)$count->fetchColumn();
        if ($currentTotal !== $expectedTotal) {
            $database->rollBack();
            simceJson(array(
                'ok' => false,
                'error' => 'La nómina cambió desde la última actualización de pantalla. Recarga e inténtalo nuevamente.',
            ), 409);
        }

        $delete = $database->prepare('DELETE FROM alumnos WHERE colegio_id=:school');
        $delete->execute(array(':school'=>$schoolId));
        $deleted = $delete->rowCount();

        $audit = $database->prepare('INSERT INTO auditoria_administracion (colegio_id,accion, detalle, administrador_id) VALUES (:school,:accion, :detalle, :administrador_id)');
        $audit->execute(array(
            ':accion' => 'vaciar_nomina',
            ':detalle' => 'Estudiantes eliminados: ' . $deleted,
            ':administrador_id' => (int) $_SESSION['admin_id'],
            ':school'=>$schoolId,
        ));
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        simceJson(array('ok' => false, 'error' => 'No se pudo vaciar la nómina. Revisa los permisos de la base de datos.'), 500);
    }

    simceJson(array('ok' => true, 'deleted' => (int) $deleted));
}

if ($postAction !== 'import') {
    simceJson(array('ok' => false, 'error' => 'Acción no válida.'), 400);
}

$students = isset($input['students']) && is_array($input['students']) ? $input['students'] : array();
if (count($students) === 0) {
    simceJson(array('ok' => false, 'error' => 'El archivo no contiene estudiantes.'), 400);
}
if (count($students) > 5000) {
    simceJson(array('ok' => false, 'error' => 'La importación admite hasta 5.000 estudiantes por archivo.'), 413);
}

$filename = isset($input['filename']) ? basename(str_replace('\\', '/', (string) $input['filename'])) : 'importacion.xlsx';
$filename = substr($filename, 0, 200);
$inserted = 0;
$updated = 0;
$rejected = 0;
$errors = array();
$batchRuts = array();

$select = $database->prepare('SELECT id FROM alumnos WHERE colegio_id=:school AND rut_normalizado=:rut');
$insert = $database->prepare('INSERT INTO alumnos (colegio_id,public_id,rut,rut_normalizado,nombre,curso,idgrado,nivel) VALUES (:school,:public_id,:rut,:rut_normalizado,:nombre,:curso,:idgrado,:nivel)');
$update = $database->prepare('UPDATE alumnos SET rut=:rut,nombre=:nombre,curso=:curso,idgrado=:idgrado,nivel=:nivel,activo=1,updated_at=CURRENT_TIMESTAMP WHERE colegio_id=:school AND rut_normalizado=:rut_normalizado');

try {
    $database->beginTransaction();
    foreach ($students as $index => $student) {
        if (!is_array($student)) {
            $rejected++;
            $errors[] = array('fila' => $index + 2, 'error' => 'Fila no válida.');
            continue;
        }
        list($validStudent, $validationError) = simceValidateStudent($student);
        if (!$validStudent) {
            $rejected++;
            if (count($errors) < 30) {
                $errors[] = array('fila' => $index + 2, 'error' => $validationError);
            }
            continue;
        }
        if (isset($batchRuts[$validStudent['rut_normalizado']])) {
            $rejected++;
            if (count($errors) < 30) {
                $errors[] = array('fila' => $index + 2, 'error' => 'Identificador repetido dentro del archivo.');
            }
            continue;
        }
        $batchRuts[$validStudent['rut_normalizado']] = true;

        $select->execute(array(':school'=>$schoolId,':rut' => $validStudent['rut_normalizado']));
        $exists = $select->fetchColumn() !== false;
        if ($exists) {
            $update->execute(array(
                ':rut' => $validStudent['rut'],
                ':nombre' => $validStudent['nombre'],
                ':curso' => $validStudent['curso'],
                ':idgrado' => $validStudent['idgrado'],
                ':nivel' => $validStudent['nivel'],
                ':rut_normalizado' => $validStudent['rut_normalizado'],
                ':school'=>$schoolId,
            ));
            $updated++;
        } else {
            $insert->execute(array(
                ':public_id' => bin2hex(random_bytes(16)),
                ':school'=>$schoolId,
                ':rut' => $validStudent['rut'],
                ':rut_normalizado' => $validStudent['rut_normalizado'],
                ':nombre' => $validStudent['nombre'],
                ':curso' => $validStudent['curso'],
                ':idgrado' => $validStudent['idgrado'],
                ':nivel' => $validStudent['nivel'],
            ));
            $inserted++;
        }
    }

    $log = $database->prepare('INSERT INTO importaciones_alumnos (colegio_id,archivo,total,insertados,actualizados,rechazados,administrador_id) VALUES (:school,:archivo,:total,:insertados,:actualizados,:rechazados,:administrador_id)');
    $log->execute(array(
        ':archivo' => $filename,
        ':total' => count($students),
        ':insertados' => $inserted,
        ':actualizados' => $updated,
        ':rechazados' => $rejected,
        ':administrador_id' => (int) $_SESSION['admin_id'],
        ':school'=>$schoolId,
    ));
    $database->commit();
    simceSyncCourses($database);
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    simceJson(array('ok' => false, 'error' => 'La importación no pudo guardarse. Revisa los permisos de la base de datos.'), 500);
}

simceJson(array(
    'ok' => true,
    'total' => count($students),
    'inserted' => $inserted,
    'updated' => $updated,
    'rejected' => $rejected,
    'errors' => $errors,
));
