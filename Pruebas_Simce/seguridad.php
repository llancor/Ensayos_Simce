<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function simceIsHttps(): bool
{
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    $forwardedProto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) : '';
    return $forwardedProto === 'https';
}

function simceSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'");
    if (simceIsHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function simceStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_name('SIMCESESSID');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => simceIsHttps(),
        'httponly' => true,
        'samesite' => 'Strict',
    ));
    session_start();
}

function simceCsrfToken(): string
{
    simceStartSession();
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function simceIsAdmin(): bool
{
    simceStartSession();
    if (!isset($_SESSION['admin_id'], $_SESSION['admin_usuario'], $_SESSION['admin_password_stamp'])) {
        return false;
    }
    $now = time();
    if (isset($_SESSION['last_activity']) && $now - (int) $_SESSION['last_activity'] > 7200) {
        simceDestroySession();
        return false;
    }

    $database = simceDatabase();
    $statement = $database->prepare('SELECT usuario, password_hash, rol FROM administradores WHERE id = :id AND activo = 1 LIMIT 1');
    $statement->execute(array(':id' => (int) $_SESSION['admin_id']));
    $admin = $statement->fetch();
    $expectedStamp = $admin ? hash('sha256', (string) $admin['password_hash']) : '';
    if (!$admin || !hash_equals($expectedStamp, (string) $_SESSION['admin_password_stamp'])) {
        simceDestroySession();
        return false;
    }

    $_SESSION['admin_usuario'] = (string) $admin['usuario'];
    $_SESSION['admin_rol'] = (string) $admin['rol'];
    $_SESSION['last_activity'] = $now;
    return true;
}

function simceRequireRole(array $roles, bool $jsonResponse = true): void
{
    simceRequireAdmin($jsonResponse);
    if (in_array((string)($_SESSION['admin_rol'] ?? ''), $roles, true)) return;
    if ($jsonResponse) simceJson(array('ok'=>false,'error'=>'Tu perfil no tiene permiso para esta operación.'), 403);
    http_response_code(403); echo 'Acceso no autorizado.'; exit;
}

function simceStartStudentSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1'); ini_set('session.gc_maxlifetime','10800');
    session_name('SIMCEALUMNO');
    session_set_cookie_params(array('lifetime'=>0,'path'=>'/','domain'=>'','secure'=>simceIsHttps(),'httponly'=>true,'samesite'=>'Strict'));
    session_start();
}

function simceRequireStudent(): array
{
    simceSecurityHeaders(); simceStartStudentSession();
    $id=(int)($_SESSION['alumno_id']??0); $courseId=(int)($_SESSION['curso_id']??0);
    if ($id<1 || $courseId<1 || time()-(int)($_SESSION['last_activity']??0)>10800) simceJson(array('ok'=>false,'error'=>'La sesión del estudiante venció.'),401);
    $s=simceDatabase()->prepare('SELECT a.id,a.nombre,a.curso,a.idgrado,c.id AS curso_id FROM alumnos a JOIN cursos c ON c.nombre=a.curso WHERE a.id=:id AND c.id=:curso AND a.activo=1 AND c.activo=1');
    $s->execute(array(':id'=>$id,':curso'=>$courseId)); $student=$s->fetch();
    if(!$student) simceJson(array('ok'=>false,'error'=>'El estudiante ya no está activo.'),401);
    $_SESSION['last_activity']=time(); return $student;
}

function simceRequireAdmin(bool $jsonResponse = true): void
{
    simceSecurityHeaders();
    if (simceIsAdmin()) {
        return;
    }

    if ($jsonResponse) {
        simceJson(array('ok' => false, 'error' => 'Sesión no válida. Ingresa nuevamente.'), 401);
    }
    header('Location: index.html');
    exit;
}

function simceRequireCsrf(): void
{
    simceStartSession();
    $received = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    $expected = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
    if ($received === '' || $expected === '' || !hash_equals($expected, $received)) {
        simceJson(array('ok' => false, 'error' => 'La sesión de seguridad venció. Recarga la página.'), 403);
    }
}

function simceDestroySession(): void
{
    simceStartSession();
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}
