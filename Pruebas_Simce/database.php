<?php
declare(strict_types=1);

function simceDatabase(): PDO
{
    static $database = null;
    if ($database instanceof PDO) {
        return $database;
    }

    $configuredPath = getenv('SIMCE_DATABASE_PATH');
    $databasePath = is_string($configuredPath) && trim($configuredPath) !== ''
        ? trim($configuredPath)
        : __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'simce.sqlite';
    $dataDirectory = dirname($databasePath);
    if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0770, true) && !is_dir($dataDirectory)) {
        throw new RuntimeException('No se pudo crear la carpeta privada de datos.');
    }

    try {
        $database = new PDO('sqlite:' . $databasePath);
    } catch (PDOException $exception) {
        throw new RuntimeException('No se pudo abrir la base de datos. Verifica que PHP tenga habilitado PDO_SQLite.');
    }

    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $database->exec('PRAGMA foreign_keys = ON');
    $database->exec('PRAGMA busy_timeout = 5000');
    $database->exec('PRAGMA journal_mode = WAL');

    simceInitializeSchema($database);
    return $database;
}

function simceInitializeSchema(PDO $database): void
{
    $database->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS administradores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    activo INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TEXT
);

CREATE TABLE IF NOT EXISTS intentos_login (
    ip TEXT PRIMARY KEY,
    intentos INTEGER NOT NULL DEFAULT 0,
    bloqueado_hasta INTEGER NOT NULL DEFAULT 0,
    actualizado_en INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS alumnos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rut TEXT NOT NULL,
    rut_normalizado TEXT NOT NULL UNIQUE,
    nombre TEXT NOT NULL,
    curso TEXT NOT NULL,
    idgrado INTEGER NOT NULL CHECK (idgrado BETWEEN 1 AND 12),
    nivel TEXT NOT NULL CHECK (nivel IN ('basica', 'media')),
    activo INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_alumnos_curso ON alumnos(curso);
CREATE INDEX IF NOT EXISTS idx_alumnos_idgrado ON alumnos(idgrado);
CREATE INDEX IF NOT EXISTS idx_alumnos_nombre ON alumnos(nombre);

CREATE TABLE IF NOT EXISTS importaciones_alumnos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    archivo TEXT NOT NULL,
    total INTEGER NOT NULL,
    insertados INTEGER NOT NULL,
    actualizados INTEGER NOT NULL,
    rechazados INTEGER NOT NULL,
    administrador_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (administrador_id) REFERENCES administradores(id)
);

CREATE TABLE IF NOT EXISTS auditoria_administracion (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    accion TEXT NOT NULL,
    detalle TEXT NOT NULL DEFAULT '',
    administrador_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (administrador_id) REFERENCES administradores(id)
);

CREATE INDEX IF NOT EXISTS idx_auditoria_created_at ON auditoria_administracion(created_at);
SQL);
}

function simceJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function simceReadJson(int $maxBytes = 15728640): array
{
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > $maxBytes) {
        simceJson(array('ok' => false, 'error' => 'La solicitud supera el tamaño permitido.'), 413);
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > $maxBytes) {
        simceJson(array('ok' => false, 'error' => 'No se pudo leer la solicitud.'), 400);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        simceJson(array('ok' => false, 'error' => 'El contenido JSON no es válido.'), 400);
    }
    return $decoded;
}

function simceClientIp(): string
{
    return isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 64) : 'unknown';
}
