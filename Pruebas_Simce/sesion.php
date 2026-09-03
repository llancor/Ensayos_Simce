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
    'csrfToken' => simceCsrfToken(),
    'https' => simceIsHttps(),
));

