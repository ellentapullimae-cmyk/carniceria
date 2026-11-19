<?php
/** RESTABLECER CONTRASENA con token de un solo uso (30 minutos de vigencia). */

require_once __DIR__ . '/config/bootstrap.php';
arrancar_sesion();

$token  = (string) ($_GET['token'] ?? '');
$idUser = validar_reset($token);

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $token  = (string) ($_POST['token'] ?? '');
    $idUser = validar_reset($token);
    $clave  = (string) ($_POST['clave'] ?? '');
    $clave2 = (string) ($_POST['clave2'] ?? '');

    if ($idUser === null) {
        $errores[] = 'El enlace no es valido o ya expiro. Solicite uno nuevo.';
    } elseif (strlen($clave) < 8) {
        $errores[] = 'La contrasena debe tener al menos 8 caracteres.';
    } elseif ($clave !== $clave2) {
        $errores[] = 'Las contrasenas no coinciden.';
    } else {
        actualizar_password($idUser, $clave);
        consumir_reset($token);
        session_regenerate_id(true);
        flash('ok', 'Contrasena actualizada correctamente. Inicie sesion.');
        redirigir('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nueva contrasena | La Tradicion Carniceria</title>
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
  <h1>Nueva contrase&ntilde;a</h1>
  <p class="auth-sub">El enlace es de un solo uso y dura 30 minutos</p>

  <?php if ($idUser === null && $_SERVER['REQUEST_METHOD'] === 'GET'): ?>
    <div class="auth-error visible">El enlace no es valido o ya expiro. <a href="<?= e(url('recuperar.php')) ?>">Solicite uno nuevo</a>.</div>
  <?php endif; ?>

  <div class="auth-error <?= $errores ? 'visible' : '' ?>" role="alert">
    <?php foreach ($errores as $ex): ?>&bull; <?= e($ex) ?><br><?php endforeach; ?>
  </div>

  <?php if ($idUser !== null): ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label class="campo"><span>Nueva contrasena (minimo 8 caracteres)</span>
      <input type="password" name="clave" required minlength="8" autofocus></label>
    <label class="campo"><span>Confirmar contrasena</span>
      <input type="password" name="clave2" required minlength="8"></label>
    <button type="submit" class="btn-auth">Guardar contrasena</button>
  </form>
  <?php endif; ?>

  <div class="auth-links">
    <a href="<?= e(url('login.php')) ?>">&larr; Volver a iniciar sesion</a>
  </div>
</div>

</body>
</html>
