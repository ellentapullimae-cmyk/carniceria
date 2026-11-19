<?php
/**
 * INICIO DE SESION - administradores, empleados y clientes.
 * Valida con password_verify() contra carniceria_db.
 */
require_once __DIR__ . '/config/bootstrap.php';
arrancar_sesion();

// Si ya tiene sesion, no muestra el formulario
if (esta_logueado()) {
    redirigir(destino_por_rol(usuario_actual()['rol']));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $identificador = trim((string) ($_POST['identificador'] ?? ''));
    $clave         = (string) ($_POST['clave'] ?? '');

    if ($identificador === '' || $clave === '') {
        $error = 'Complete todos los campos.';
    } else {
        [$ok, $msg] = intentar_login($identificador, $clave);
        if ($ok) {
            $rol      = usuario_actual()['rol'];
            $destino  = $_SESSION['intento'] ?? null;
            unset($_SESSION['intento']);
            // Solo permite destinos internos del proyecto
            if ($destino && str_starts_with($destino, base_path() . '/') && !str_contains($destino, '..')) {
                header('Location: ' . $destino);
                exit;
            }
            redirigir(destino_por_rol($rol));
        }
        $error = $msg;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesion | La Tradicion Carniceria</title>
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
  <h1>Iniciar sesi&oacute;n</h1>
  <p class="auth-sub">Acceso al sistema de gesti&oacute;n y a tu cuenta</p>

  <div class="auth-error <?= $error ? 'visible' : '' ?>" role="alert"><?= e($error) ?></div>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label class="campo">
      <span>Usuario o correo</span>
      <input type="text" name="identificador" required autofocus
             value="<?= e($_POST['identificador'] ?? '') ?>">
    </label>
    <label class="campo">
      <span>Contrase&ntilde;a</span>
      <input type="password" name="clave" required>
    </label>
    <button type="submit" class="btn-auth">Entrar</button>
  </form>

  <div class="auth-links">
    <a href="<?= e(url('recuperar.php')) ?>">¿Olvidaste tu contrase&ntilde;a?</a>
    <a href="<?= e(url('index.php')) ?>">&larr; Volver a la tienda</a>
    <span>¿Eres cliente nuevo? <a href="<?= e(url('registro.php')) ?>">Crea tu cuenta</a></span>
  </div>

  <div class="auth-demo">
    <b>Cuentas de prueba:</b><br>
    <code>admin / Admin123!</code> &middot; <code>empleado / Empleado123!</code><br>
    <code>cliente / Cliente123!</code>
  </div>
</div>

</body>
</html>
