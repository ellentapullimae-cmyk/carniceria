<?php
/**
 * RECUPERACION DE CONTRASENAS.
 * Genera un token de un solo uso (64 bytes) del cual solo se guarda
 * su hash SHA-256 en la base de datos.
 */

require_once __DIR__ . '/config/bootstrap.php';
arrancar_sesion();

$mensaje = '';
$enlace  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $correo = strtolower(trim((string) ($_POST['correo'] ?? '')));

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Correo electronico invalido.';
    } else {
        $enlace = crear_reset($correo);
        if ($enlace === null) {
            // No se revela si el correo existe o no
            $mensaje = 'Si el correo esta registrado, recibira un enlace de recuperacion.';
        } elseif (env('APP_ENV', 'development') === 'production') {
            // En produccion: enviar por correo (requiere SMTP configurado en el hosting)
            @mail(
                $correo,
                'Recuperacion de contrasena - La Tradicion Carniceria',
                "Hola, use este enlace para restablecer su contrasena (válido 30 minutos):\n\n{$enlace}\n\nSi no lo pidio, ignore este mensaje."
            );
            $mensaje = 'Si el correo esta registrado, recibira un enlace de recuperacion.';
        } else {
            // Desarrollo (XAMPP): sin servidor de correo, se muestra el enlace
            $mensaje = 'ENLACE DE RECUPERACION (solo visible en desarrollo):';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recuperar contrasena | La Tradicion Carniceria</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/auth.css')) ?>">
</head>
<body class="auth-body">

<div class="auth-card">
  <div class="auth-logo">
    <span>&#127830;</span>
    <strong>La Tradici&oacute;n<small>Carnicer&iacute;a</small></strong>
  </div>
  <h1>Recuperar contrase&ntilde;a</h1>
  <p class="auth-sub">Te enviaremos un enlace para crear una nueva contrase&ntilde;a</p>

  <?php if ($mensaje): ?>
    <div class="auth-ok visible"><?= e($mensaje) ?></div>
  <?php endif; ?>
  <?php if ($enlace): ?>
    <div class="auth-ok visible">
      <a href="<?= e($enlace) ?>"><b><?= e($enlace) ?></b></a>
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label class="campo"><span>Correo electronico</span>
      <input type="email" name="correo" required autofocus></label>
    <button type="submit" class="btn-auth">Enviar enlace</button>
  </form>

  <div class="auth-links">
    <a href="<?= e(url('login.php')) ?>">&larr; Volver a iniciar sesion</a>
  </div>
</div>

</body>
</html>
