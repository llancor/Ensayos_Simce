<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceSecurityHeaders();
simceStartSession();

if (!simceIsAdmin()) {
    simceJson(array('ok' => true, 'authenticated' => false));
}

simceJson(array(
    'ok' => true,
    'authenticated' => true,
    'usuario' => (string) $_SESSION['admin_usuario'],
    'rol' => (string) $_SESSION['admin_rol'],
    'colegioId' => (int)($_SESSION['colegio_id'] ?? 0),
    'version' => SIMCE_VERSION,
    'csrfToken' => simceCsrfToken(),
    'https' => simceIsHttps(),
));
