<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceSecurityHeaders();
$db = simceDatabase();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    simceJson(array('ok' => true, 'marca' => simceBranding($db)));
}

simceRequireRole(array('superadmin', 'administrador'));
simceRequireCsrf();

$fields = array(
    'nombre_plataforma' => 80,
    'nombre_establecimiento' => 120,
    'subtitulo' => 120,
    'saludo' => 120,
    'seleccion_perfil' => 100,
);

foreach ($fields as $key => $max) {
    $value = trim((string) ($_POST[$key] ?? ''));
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($value === '' || $length > $max) {
        simceJson(array('ok' => false, 'error' => 'Revisa el campo ' . $key . '.'), 400);
    }

    $statement = $db->prepare('SELECT clave FROM configuracion WHERE clave=:k');
    $statement->execute(array(':k' => 'marca_' . $key));
    $query = $statement->fetch()
        ? $db->prepare('UPDATE configuracion SET valor=:v,updated_at=CURRENT_TIMESTAMP WHERE clave=:k')
        : $db->prepare('INSERT INTO configuracion(clave,valor) VALUES(:k,:v)');
    $query->execute(array(':k' => 'marca_' . $key, ':v' => $value));
}

function saveBrandFile(PDO $db, string $field, string $key, array $allowed, int $maxBytes): void
{
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return;
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > $maxBytes) {
        simceJson(array('ok' => false, 'error' => 'El archivo ' . $field . ' no pudo subirse o supera el máximo permitido.'), 400);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        simceJson(array('ok' => false, 'error' => 'Formato no permitido para ' . $field . '.'), 400);
    }

    $directory = __DIR__ . '/uploads/marca';
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        simceJson(array('ok' => false, 'error' => 'No se pudo crear la carpeta de personalización.'), 500);
    }

    $relative = 'uploads/marca/' . $key . '.' . $allowed[$mime];
    $destination = __DIR__ . '/' . $relative;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        simceJson(array('ok' => false, 'error' => 'No se pudo guardar ' . $field . '.'), 500);
    }

    foreach (glob($directory . '/' . $key . '.*') ?: array() as $oldFile) {
        if (realpath($oldFile) !== realpath($destination)) {
            @unlink($oldFile);
        }
    }

    $statement = $db->prepare('SELECT clave FROM configuracion WHERE clave=:k');
    $statement->execute(array(':k' => 'marca_' . $key));
    $query = $statement->fetch()
        ? $db->prepare('UPDATE configuracion SET valor=:v,updated_at=CURRENT_TIMESTAMP WHERE clave=:k')
        : $db->prepare('INSERT INTO configuracion(clave,valor) VALUES(:k,:v)');
    $query->execute(array(':k' => 'marca_' . $key, ':v' => $relative));
}

saveBrandFile(
    $db,
    'logo',
    'logo',
    array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'),
    2097152
);
saveBrandFile(
    $db,
    'favicon',
    'favicon',
    array('image/png' => 'png', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'),
    524288
);

simceJson(array('ok' => true, 'marca' => simceBranding($db)));
