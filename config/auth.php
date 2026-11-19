<?php
/**
 * Autenticacion: sesiones seguras, CSRF, acceso por roles y
 * recuperacion de contrasenas con tokens de un solo uso.
 */

require_once __DIR__ . '/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

/** Inicia la sesion con cookies protegidas (httponly, samesite, strict). */
function arrancar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https && env('APP_ENV', 'development') === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('CARNICERIA_SESION');
    session_start();
}

/* ============================ CSRF ============================ */

function csrf_token(): string
{
    arrancar_sesion();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Rechaza el request si el token CSRF no coincide (proteccion anti-CSRF). */
function verificar_csrf(): void
{
    arrancar_sesion();
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        exit('Sesion CSRF invalida. Vuelva a la pagina e intente nuevamente.');
    }
}

/* ======================== LOGIN / LOGOUT ======================== */

/**
 * Valida credenciales contra la BD con password_verify().
 * Devuelve [bool ok, string error].
 */
function intentar_login(string $identificador, string $clave): array
{
    arrancar_sesion();

    // Limita intentos fallidos: 5 intentos -> bloqueo de 15 minutos
    $intentos = $_SESSION['login_intentos'] ?? ['n' => 0, 'bloqueado_hasta' => 0];
    if ($intentos['bloqueado_hasta'] > time()) {
        $min = ceil(($intentos['bloqueado_hasta'] - time()) / 60);
        return [false, "Demasiados intentos fallidos. Intente de nuevo en {$min} minuto(s)."];
    }

    $stmt = db()->prepare(
        'SELECT u.id_usuario, u.usuario, u.correo, u.nombre, u.password_hash, u.activo, r.nombre AS rol
           FROM usuarios u
           JOIN roles r ON r.id_rol = u.id_rol
          WHERE (u.usuario = ? OR u.correo = ?) AND u.activo = 1
          LIMIT 1'
    );
    $stmt->execute([$identificador, $identificador]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($clave, $usuario['password_hash'])) {
        $intentos['n']++;
        if ($intentos['n'] >= 5) {
            $intentos['bloqueado_hasta'] = time() + 900;
            $intentos['n'] = 0;
        }
        $_SESSION['login_intentos'] = $intentos;
        return [false, 'Usuario o contrasena incorrectos.'];
    }

    // Actualiza el hash si cambio el costo de cifrado
    if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?')
            ->execute([password_hash($clave, PASSWORD_DEFAULT), $usuario['id_usuario']]);
    }

    session_regenerate_id(true); // evita fijacion de sesion
    unset($_SESSION['login_intentos'], $_SESSION['csrf']);
    $_SESSION['usuario'] = [
        'id'      => (int) $usuario['id_usuario'],
        'rol'     => $usuario['rol'],
        'nombre'  => $usuario['nombre'],
        'usuario' => $usuario['usuario'],
        'correo'  => $usuario['correo'],
    ];

    db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?')
        ->execute([$usuario['id_usuario']]);

    return [true, ''];
}

function usuario_actual(): ?array
{
    arrancar_sesion();
    return $_SESSION['usuario'] ?? null;
}

function esta_logueado(): bool
{
    return usuario_actual() !== null;
}

function es_admin(): bool
{
    $u = usuario_actual();
    return $u !== null && $u['rol'] === 'administrador';
}

function cerrar_sesion(): void
{
    arrancar_sesion();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ========================= PROTECCION DE RUTAS ========================= */

function requiere_login(): void
{
    if (!esta_logueado()) {
        $_SESSION['intento'] = $_SERVER['REQUEST_URI'] ?? '';
        redirigir('login.php');
    }
}

/** Exige uno o varios roles; si no coincide -> 403. */
function requiere_rol(string ...$roles): void
{
    requiere_login();
    $u = usuario_actual();
    if (!in_array($u['rol'], $roles, true)) {
        http_response_code(403);
        exit('<div style="font-family:sans-serif;text-align:center;margin-top:15vh">'
            . '<h1 style="color:#b3261e">403 - Acceso denegado</h1>'
            . '<p>Su rol <b>' . e($u['rol']) . '</b> no tiene permiso para esta seccion.</p>'
            . '<a href="' . e(url('panel/index.php')) . '" style="color:#b3261e">Volver al panel</a></div>');
    }
}

/** Ruta de destino tras iniciar sesion segun el rol. */
function destino_por_rol(string $rol): string
{
    return match ($rol) {
        'administrador', 'empleado' => 'panel/index.php',
        'cliente'                   => 'cliente/index.php',
        default                     => 'login.php',
    };
}

/* ================= RECUPERACION DE CONTRASENAS ================= */

/**
 * Crea un token de restablecimiento (64 bytes aleatorios).
 * En la BD se guarda SOLO el hash SHA-256; el token puro va en el enlace.
 * Devuelve el enlace o null si el correo no existe (no se filtra informacion).
 */
function crear_reset(string $correo): ?string
{
    $stmt = db()->prepare('SELECT id_usuario FROM usuarios WHERE correo = ? AND activo = 1');
    $stmt->execute([$correo]);
    $fila = $stmt->fetch();
    if (!$fila) {
        return null;
    }

    $token  = bin2hex(random_bytes(32));
    $hash   = hash('sha256', $token);
    $expira = date('Y-m-d H:i:s', time() + 1800); // 30 minutos

    db()->prepare(
        'INSERT INTO password_resets (id_usuario, token_hash, expira_en) VALUES (?, ?, ?)'
    )->execute([(int) $fila['id_usuario'], $hash, $expira]);

    return url('restablecer.php') . '?token=' . $token;
}

/** Devuelve el id_usuario si el token es valido, vigente y no usado. */
function validar_reset(string $token): ?int
{
    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT id_usuario FROM password_resets
          WHERE token_hash = ? AND usado = 0 AND expira_en > NOW()
          ORDER BY id_reset DESC LIMIT 1'
    );
    $stmt->execute([$hash]);
    $fila = $stmt->fetch();
    return $fila ? (int) $fila['id_usuario'] : null;
}

/** Marca el token como usado (un solo uso). */
function consumir_reset(string $token): void
{
    db()->prepare('UPDATE password_resets SET usado = 1 WHERE token_hash = ?')
        ->execute([hash('sha256', $token)]);
}

/** Actualiza la contrasena con password_hash(). */
function actualizar_password(int $idUsuario, string $clave): void
{
    db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?')
        ->execute([password_hash($clave, PASSWORD_DEFAULT), $idUsuario]);
}
