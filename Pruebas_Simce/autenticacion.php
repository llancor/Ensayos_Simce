<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    simceJson(array('ok' => false, 'error' => 'Método no permitido.'), 405);
}

$input = simceReadJson(65536);
$action = isset($input['action']) ? (string) $input['action'] : 'login';

if ($action === 'logout') {
    simceRequireAdmin();
    simceRequireCsrf();
    simceDestroySession();
    simceJson(array('ok' => true));
}

if ($action !== 'login') {
    simceJson(array('ok' => false, 'error' => 'Acción no válida.'), 400);
}

$database = simceDatabase();
$adminCount = (int) $database->query('SELECT COUNT(*) FROM administradores WHERE activo = 1')->fetchColumn();
if ($adminCount === 0) {
    simceJson(array(
        'ok' => false,
        'needsSetup' => true,
        'error' => 'Primero debes crear la cuenta administradora del sistema.',
    ), 503);
}

$ip = simceClientIp();
$now = time();
$attemptStatement = $database->prepare('SELECT intentos, bloqueado_hasta FROM intentos_login WHERE ip = :ip');
$attemptStatement->execute(array(':ip' => $ip));
$attempt = $attemptStatement->fetch();
if ($attempt && (int) $attempt['bloqueado_hasta'] > $now) {
    $remaining = max(1, (int) ceil(((int) $attempt['bloqueado_hasta'] - $now) / 60));
    simceJson(array('ok' => false, 'error' => 'Demasiados intentos. Prueba nuevamente en ' . $remaining . ' minuto(s).'), 429);
}

$username = isset($input['usuario']) ? trim((string) $input['usuario']) : '';
$password = isset($input['password']) ? (string) $input['password'] : '';
$statement = $database->prepare('SELECT id, usuario, password_hash FROM administradores WHERE usuario = :usuario AND activo = 1 LIMIT 1');
$statement->execute(array(':usuario' => $username));
$admin = $statement->fetch();

if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
    $failed = $attempt ? (int) $attempt['intentos'] + 1 : 1;
    $blockedUntil = $failed >= 5 ? $now + 900 : 0;
    if ($attempt) {
        $update = $database->prepare('UPDATE intentos_login SET intentos = :intentos, bloqueado_hasta = :bloqueado, actualizado_en = :actualizado WHERE ip = :ip');
        $update->execute(array(':intentos' => $failed, ':bloqueado' => $blockedUntil, ':actualizado' => $now, ':ip' => $ip));
    } else {
        $insert = $database->prepare('INSERT INTO intentos_login (ip, intentos, bloqueado_hasta, actualizado_en) VALUES (:ip, :intentos, :bloqueado, :actualizado)');
        $insert->execute(array(':ip' => $ip, ':intentos' => $failed, ':bloqueado' => $blockedUntil, ':actualizado' => $now));
    }
    usleep(350000);
    simceJson(array('ok' => false, 'error' => 'Usuario o contraseña incorrectos.'), 401);
}

$database->prepare('DELETE FROM intentos_login WHERE ip = :ip')->execute(array(':ip' => $ip));
$database->prepare('UPDATE administradores SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id')->execute(array(':id' => (int) $admin['id']));

simceStartSession();
session_regenerate_id(true);
$_SESSION['admin_id'] = (int) $admin['id'];
$_SESSION['admin_usuario'] = (string) $admin['usuario'];
$_SESSION['admin_password_stamp'] = hash('sha256', (string) $admin['password_hash']);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['last_activity'] = time();

simceJson(array(
    'ok' => true,
    'redirect' => 'pruebas_educativas.php',
    'csrfToken' => $_SESSION['csrf_token'],
));
