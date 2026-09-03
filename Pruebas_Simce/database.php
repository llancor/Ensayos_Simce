<?php
declare(strict_types=1);

function simceDatabase(): PDO {
    static $db = null;
    if ($db instanceof PDO) return $db;
    $dsn = trim((string)getenv('SIMCE_DB_DSN'));
    $user = (string)getenv('SIMCE_DB_USER'); $pass = (string)getenv('SIMCE_DB_PASSWORD');
    if ($dsn === '') {
        $path = trim((string)getenv('SIMCE_DATABASE_PATH')) ?: __DIR__.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'simce.sqlite';
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0770, true) && !is_dir(dirname($path))) throw new RuntimeException('No se pudo crear la carpeta privada de datos.');
        $dsn = 'sqlite:'.$path;
    }
    try { $db = new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false)); }
    catch (PDOException $e) { throw new RuntimeException(strpos($dsn,'mysql:')===0 ? 'No se pudo conectar a MySQL/MariaDB. Revisa el servicio y las variables SIMCE_DB_*.' : 'No se pudo abrir la base de datos. Verifica PDO_SQLite.'); }
    if (simceDbDriver($db)==='sqlite') { $db->exec('PRAGMA foreign_keys=ON'); $db->exec('PRAGMA busy_timeout=5000'); $db->exec('PRAGMA journal_mode=WAL'); }
    else { $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"); }
    simceInitializeSchema($db); return $db;
}
function simceDbDriver(PDO $db): string { return (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME); }
function simceColumnExists(PDO $db, string $table, string $column): bool {
    if (simceDbDriver($db)==='sqlite') { foreach ($db->query('PRAGMA table_info('.$table.')')->fetchAll() as $r) if ((string)$r['name']===$column) return true; return false; }
    $s=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c'); $s->execute(array(':t'=>$table,':c'=>$column)); return (int)$s->fetchColumn()>0;
}
function simceInitializeSchema(PDO $db): void {
    $mysql=simceDbDriver($db)==='mysql'; $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT'; $bool=$mysql?'TINYINT(1)':'INTEGER'; $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $db->exec("CREATE TABLE IF NOT EXISTS administradores (id $id, usuario VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, rol VARCHAR(20) NOT NULL DEFAULT 'docente', activo $bool NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, last_login_at TIMESTAMP NULL)$engine");
    if (!simceColumnExists($db,'administradores','rol')) $db->exec("ALTER TABLE administradores ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'administrador'");
    $first=$db->query('SELECT MIN(id) FROM administradores')->fetchColumn(); if ($first!==false && $first!==null) { $s=$db->prepare("UPDATE administradores SET rol='superadmin' WHERE id=:id AND rol<>'superadmin'"); $s->execute(array(':id'=>(int)$first)); }
    $db->exec("CREATE TABLE IF NOT EXISTS intentos_login (ip VARCHAR(64) PRIMARY KEY, intentos INTEGER NOT NULL DEFAULT 0, bloqueado_hasta BIGINT NOT NULL DEFAULT 0, actualizado_en BIGINT NOT NULL)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS alumnos (id $id, public_id VARCHAR(40) NULL UNIQUE, rut VARCHAR(40) NOT NULL, rut_normalizado VARCHAR(40) NOT NULL UNIQUE, nombre VARCHAR(150) NOT NULL, curso VARCHAR(80) NOT NULL, idgrado INTEGER NOT NULL, nivel VARCHAR(10) NOT NULL, activo $bool NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)$engine");
    if (!simceColumnExists($db,'alumnos','public_id')) { $db->exec('ALTER TABLE alumnos ADD COLUMN public_id VARCHAR(40) NULL'); $db->exec('CREATE UNIQUE INDEX idx_alumnos_public_id ON alumnos(public_id)'); }
    foreach ($db->query("SELECT id FROM alumnos WHERE public_id IS NULL OR public_id='' ")->fetchAll() as $r) { $s=$db->prepare('UPDATE alumnos SET public_id=:p WHERE id=:id'); $s->execute(array(':p'=>bin2hex(random_bytes(16)),':id'=>(int)$r['id'])); }
    $db->exec("CREATE TABLE IF NOT EXISTS cursos (id $id, nombre VARCHAR(80) NOT NULL UNIQUE, idgrado INTEGER NOT NULL, nivel VARCHAR(10) NOT NULL, pin_hash VARCHAR(255) NULL, requiere_pin $bool NOT NULL DEFAULT 1, activo $bool NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS pruebas (id $id, ruta VARCHAR(500) NOT NULL UNIQUE, titulo VARCHAR(200) NOT NULL, curso_codigo VARCHAR(30) NOT NULL, asignatura VARCHAR(60) NOT NULL, creador_id BIGINT NULL, activa $bool NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS asignaciones (id $id, prueba_id BIGINT NOT NULL, curso_id BIGINT NOT NULL, activa $bool NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(prueba_id,curso_id))$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS resultados (id $id, alumno_id BIGINT NOT NULL, prueba_id BIGINT NOT NULL, intento INTEGER NOT NULL DEFAULT 1, correctas INTEGER NOT NULL, total INTEGER NOT NULL, puntaje DECIMAL(10,2) NOT NULL, puntaje_max DECIMAL(10,2) NOT NULL, porcentaje DECIMAL(6,2) NOT NULL, nota DECIMAL(4,2) NULL, respuestas_json TEXT NOT NULL, iniciado_at TIMESTAMP NULL, finalizado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ip VARCHAR(64) NOT NULL DEFAULT '', UNIQUE(alumno_id,prueba_id,intento))$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS importaciones_alumnos (id $id, archivo VARCHAR(200) NOT NULL, total INTEGER NOT NULL, insertados INTEGER NOT NULL, actualizados INTEGER NOT NULL, rechazados INTEGER NOT NULL, administrador_id BIGINT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS auditoria_administracion (id $id, accion VARCHAR(80) NOT NULL, detalle TEXT NOT NULL, administrador_id BIGINT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS configuracion (clave VARCHAR(80) PRIMARY KEY, valor TEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)$engine");
    simceSyncCourses($db);
}

function simceBranding(PDO $db): array {
    $values=array('nombre_plataforma'=>'Pruebas Educativas','nombre_establecimiento'=>'Colegio Carlos Miranda','subtitulo'=>'Plataforma Evaluativa','saludo'=>'Hola, bienvenido al sistema','seleccion_perfil'=>'Selecciona tu perfil','logo'=>'logo_carlos_miranda.jpg');
    foreach($db->query("SELECT clave,valor FROM configuracion WHERE clave LIKE 'marca_%'")->fetchAll() as $row){$key=substr((string)$row['clave'],6);if(array_key_exists($key,$values))$values[$key]=(string)$row['valor'];}
    return $values;
}
function simceSyncCourses(PDO $db): void { foreach ($db->query('SELECT curso,idgrado,nivel FROM alumnos WHERE activo=1 GROUP BY curso,idgrado,nivel')->fetchAll() as $r) { $s=$db->prepare('SELECT id FROM cursos WHERE nombre=:n'); $s->execute(array(':n'=>$r['curso'])); if(!$s->fetch()){ $i=$db->prepare('INSERT INTO cursos(nombre,idgrado,nivel) VALUES(:n,:g,:l)'); $i->execute(array(':n'=>$r['curso'],':g'=>$r['idgrado'],':l'=>$r['nivel'])); } } }
function simceSyncStoredTests(PDO $db): void {
    $root=__DIR__.DIRECTORY_SEPARATOR.'pruebas'; if(!is_dir($root))return;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){
        if(!$file->isFile()||strtolower($file->getExtension())!=='html')continue;
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen(__DIR__)+1)); $parts=explode('/',$relative); if(count($parts)<4)continue;
        $code=$parts[1];$subject=$parts[2];$title=str_replace(array('_','-'),' ',pathinfo($file->getFilename(),PATHINFO_FILENAME));
        $s=$db->prepare('SELECT id FROM pruebas WHERE ruta=:r');$s->execute(array(':r'=>$relative));$testId=$s->fetchColumn();
        if(!$testId){$i=$db->prepare('INSERT INTO pruebas(ruta,titulo,curso_codigo,asignatura) VALUES(:r,:t,:c,:a)');$i->execute(array(':r'=>$relative,':t'=>substr($title,0,200),':c'=>$code,':a'=>$subject));$testId=(int)$db->lastInsertId();}
        foreach($db->query('SELECT id,nombre FROM cursos WHERE activo=1')->fetchAll() as $course){if(!simceCourseCodeMatches((string)$course['nombre'],$code))continue;$q=$db->prepare('SELECT id FROM asignaciones WHERE prueba_id=:p AND curso_id=:c');$q->execute(array(':p'=>$testId,':c'=>$course['id']));if(!$q->fetch()){$a=$db->prepare('INSERT INTO asignaciones(prueba_id,curso_id) VALUES(:p,:c)');$a->execute(array(':p'=>$testId,':c'=>$course['id']));}}
    }
}
function simceCourseCodeMatches(string $name,string $code): bool { $t=strtolower(strtr($name,array('á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','°'=>'','º'=>''))); return preg_match('/([1-8])\s*(basico|medio)/',$t,$m)===1 && $code===$m[1].($m[2]==='basico'?'basico':'medio'); }
function simceJson(array $p,int $s=200): void { http_response_code($s); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function simceReadJson(int $max=15728640): array { $len=isset($_SERVER['CONTENT_LENGTH'])?(int)$_SERVER['CONTENT_LENGTH']:0; if($len>$max)simceJson(array('ok'=>false,'error'=>'La solicitud supera el tamaño permitido.'),413); $raw=file_get_contents('php://input'); if($raw===false||strlen($raw)>$max)simceJson(array('ok'=>false,'error'=>'No se pudo leer la solicitud.'),400); $d=json_decode($raw,true); if(!is_array($d))simceJson(array('ok'=>false,'error'=>'El contenido JSON no es válido.'),400); return $d; }
function simceClientIp(): string { return isset($_SERVER['REMOTE_ADDR'])?substr((string)$_SERVER['REMOTE_ADDR'],0,64):'unknown'; }
