<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceRequireRole(array('superadmin','administrador'), false);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
readfile(__DIR__ . '/estudiantes.html');
