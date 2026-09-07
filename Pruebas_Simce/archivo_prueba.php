<?php
declare(strict_types=1);
require_once __DIR__.'/seguridad.php';
simceSecurityHeaders();
$relative=str_replace('\\','/',(string)($_GET['ruta']??''));
$root=realpath(__DIR__.'/pruebas');$path=realpath(__DIR__.'/'.$relative);
if(!$path || !$root || strpos($path,$root.DIRECTORY_SEPARATOR)!==0 || strtolower(pathinfo($path,PATHINFO_EXTENSION))!=='html' || !is_file($path)){http_response_code(404);exit('Prueba no encontrada.');}
if(simceIsAdmin()){header('Content-Type:text/html; charset=utf-8');header('Cache-Control:no-store');readfile($path);exit;}
$db=simceDatabase();simceSyncStoredTests($db);
$q=$db->prepare('SELECT id FROM pruebas WHERE ruta=:r AND activa=1');$q->execute(array(':r'=>str_replace('\\','/',substr($path,strlen(__DIR__)+1))));$id=$q->fetchColumn();
$base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/');
header('Location: '.$base.(!empty($_COOKIE['SIMCEALUMNO']) && $id?'/rendir_prueba.php?id='.(int)$id:'/portal_estudiante.php'));exit;
