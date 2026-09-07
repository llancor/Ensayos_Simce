<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceRequireRole(['colegio_admin', 'docente'], false);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
readfile(__DIR__ . '/pruebas_educativas.html');
