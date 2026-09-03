<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
simceSecurityHeaders();
simceStartSession();

$error = '';
$database = null;
try {
    $database = simceDatabase();
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$alreadyInstalled = false;
if ($database instanceof PDO) {
    $alreadyInstalled = (int) $database->query('SELECT COUNT(*) FROM administradores')->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled && $database instanceof PDO) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals(simceCsrfToken(), $postedToken)) {
        $error = 'La sesión de instalación venció. Recarga la página.';
    } else {
        $keyPath = __DIR__ . '/data/setup.key';
        $expectedKey = is_file($keyPath) ? trim((string) file_get_contents($keyPath)) : '';
        $providedKey = isset($_POST['setup_key']) ? trim((string) $_POST['setup_key']) : '';
        $username = isset($_POST['usuario']) ? trim((string) $_POST['usuario']) : '';
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
        $confirmation = isset($_POST['password_confirm']) ? (string) $_POST['password_confirm'] : '';

        if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
            $error = 'La clave de instalación no es correcta.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $error = 'El usuario debe tener entre 3 y 50 caracteres y usar sólo letras, números, punto, guion o guion bajo.';
        } elseif (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'La contraseña debe tener al menos 12 caracteres, mayúscula, minúscula y número.';
        } elseif ($password !== $confirmation) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $statement = $database->prepare("INSERT INTO administradores (colegio_id, usuario, password_hash, rol) VALUES (NULL, :usuario, :password_hash, 'plataforma_superadmin')");
            $statement->execute(array(':usuario' => $username, ':password_hash' => $passwordHash));
            @unlink($keyPath);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $database->lastInsertId();
            $_SESSION['admin_usuario'] = $username;
            $_SESSION['admin_rol'] = 'plataforma_superadmin';
            $_SESSION['colegio_id'] = 0;
            $_SESSION['admin_password_stamp'] = hash('sha256', $passwordHash);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['last_activity'] = time();
            header('Location: plataforma.php');
            exit;
        }
    }
}

$csrfToken = simceCsrfToken();
$isHttps = simceIsHttps();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalar acceso administrador</title>
    <style>
        *{box-sizing:border-box} body{margin:0;font-family:Arial,sans-serif;background:#f1f5f9;color:#1e293b;padding:32px}
        .panel{max-width:560px;margin:0 auto;background:#fff;border:1px solid #cbd5e1;border-radius:18px;padding:28px;box-shadow:0 15px 45px rgba(15,23,42,.1)}
        h1{margin-top:0;color:#1e3a5f} label{display:block;font-weight:700;margin-top:16px;margin-bottom:6px}
        input{width:100%;padding:12px;border:1px solid #94a3b8;border-radius:9px;font-size:16px}
        button{margin-top:22px;width:100%;padding:13px;border:0;border-radius:9px;background:#1d4ed8;color:#fff;font-weight:800;font-size:16px;cursor:pointer}
        .notice{padding:12px;border-radius:9px;margin:14px 0;background:#fff7ed;border:1px solid #fdba74}.error{background:#fef2f2;border-color:#fca5a5;color:#991b1b}
        code{background:#e2e8f0;padding:2px 5px;border-radius:4px} a{color:#1d4ed8}
    </style>
</head>
<body>
<main class="panel">
    <h1>Configuración inicial segura</h1>
    <?php if (!$isHttps): ?><div class="notice">Completa esta instalación desde la red local. Antes de abrir el sitio a Internet, habilita HTTPS.</div><?php endif; ?>
    <?php if ($alreadyInstalled): ?>
        <div class="notice">El administrador ya fue configurado. Esta instalación está cerrada.</div>
        <p><a href="index.html">Volver al acceso</a></p>
    <?php else: ?>
        <p>Usa la clave privada guardada en <code>data/setup.key</code>. El archivo se eliminará al crear la primera cuenta.</p>
        <?php if ($error !== ''): ?><div class="notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <label for="setup_key">Clave de instalación</label>
            <input id="setup_key" name="setup_key" type="password" required>
            <label for="usuario">Usuario administrador</label>
            <input id="usuario" name="usuario" required minlength="3" maxlength="50">
            <label for="password">Nueva contraseña</label>
            <input id="password" name="password" type="password" required minlength="12">
            <label for="password_confirm">Repetir contraseña</label>
            <input id="password_confirm" name="password_confirm" type="password" required minlength="12">
            <button type="submit">Crear administrador</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
