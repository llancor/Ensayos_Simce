<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceRequireAdmin(false);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
readfile(__DIR__ . '/estudiantes.html');

