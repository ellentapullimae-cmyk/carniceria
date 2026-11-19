<?php
/** REGISTRO DE CLIENTES: crea usuario (rol cliente) + perfil de cliente. */

require_once __DIR__ . '/config/bootstrap.php';
arrancar_sesion();

if (esta_logueado()) {
    redirigir(destino_por_rol(usuario_actual()['rol']));
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $nombre    = trim((string) ($_POST['nombre'] ?? ''));
    $usuario   = strtolower(trim((string) ($_POST['usuario'] ?? '')));
    $correo    = strtolower(trim((string) ($_POST['correo'] ?? '')));
    $telefono  = trim((string) ($_POST['telefono'] ?? ''));
    $clave     = (string) ($_POST['clave'] ?? '');
    $clave2    = (string) ($_POST['clave2'] ?? '');

    if ($nombre === '' || mb_strlen($nombre) < 3) {
        $errores[] = 'Ingrese su nombre completo (minimo 3 caracteres).';
    }
    if (!preg_match('/^[a-z0-9_.]{4,50}$/', $usuario)) {
        $errores[] = 'Usuario invalido: use 4-50 caracteres (letras, numeros, . o _).';
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Correo electronico invalido.';
    }
    if (strlen($clave) < 8) {
        $errores[] = 'La contrasena debe tener al menos 8 caracteres.';
    }
    if ($clave !== $clave2) {
        $errores[] = 'Las contrasenas no coinciden.';
    }

    if (!$errores) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            // Unicidad de usuario y correo
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE usuario = ? OR correo = ?');
            $stmt->execute([$usuario, $correo]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new RuntimeException('Ese usuario o correo ya esta registrado.');
            }

            $idRol = (int) $pdo->query("SELECT id_rol FROM roles WHERE nombre = 'cliente'")->fetchColumn();

            $pdo->prepare(
                'INSERT INTO usuarios (id_rol, usuario, correo, password_hash, nombre) VALUES (?, ?, ?, ?, ?)'
            )->execute([$idRol, $usuario, $correo, password_hash($clave, PASSWORD_DEFAULT), $nombre]);

            $idUsuario = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO clientes (id_usuario, nombre, telefono, correo) VALUES (?, ?, ?, ?)'
            )->execute([$idUsuario, $nombre, $telefono, $correo]);

            $pdo->commit();

            // Inicio automatico de sesion
            session_regenerate_id(true);
            $_SESSION['usuario'] = [
                'id'      => $idUsuario,
                'rol'     => 'cliente',
                'nombre'  => $nombre,
                'usuario' => $usuario,
                'correo'  => $correo,
            ];
            flash('ok', 'Cuenta creada correctamente. Bienvenido!');
            redirigir('cliente/index.php');
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errores[] = $ex->getMessage();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('[registro] ' . $ex->getMessage());
            $errores[] = 'Error al crear la cuenta. Intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear cuenta | La Tradicion Carniceria</title>
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
  <h1>Crear cuenta de cliente</h1>
  <p class="auth-sub">Registrate para comprar y seguir tus pedidos</p>

  <div class="auth-error <?= $errores ? 'visible' : '' ?>" role="alert">
    <?php foreach ($errores as $ex): ?>&bull; <?= e($ex) ?><br><?php endforeach; ?>
  </div>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label class="campo"><span>Nombre completo</span>
      <input type="text" name="nombre" required value="<?= e($_POST['nombre'] ?? '') ?>"></label>
    <label class="campo"><span>Usuario</span>
      <input type="text" name="usuario" required value="<?= e($_POST['usuario'] ?? '') ?>"></label>
    <label class="campo"><span>Correo electronico</span>
      <input type="email" name="correo" required value="<?= e($_POST['correo'] ?? '') ?>"></label>
    <label class="campo"><span>Telefono</span>
      <input type="tel" name="telefono" placeholder="987 654 321" value="<?= e($_POST['telefono'] ?? '') ?>"></label>
    <label class="campo"><span>Contrasena (minimo 8 caracteres)</span>
      <input type="password" name="clave" required minlength="8"></label>
    <label class="campo"><span>Confirmar contrasena</span>
      <input type="password" name="clave2" required minlength="8"></label>
    <button type="submit" class="btn-auth">Crear mi cuenta</button>
  </form>

  <div class="auth-links">
    <span>¿Ya tienes cuenta? <a href="<?= e(url('login.php')) ?>">Inicia sesion</a></span>
    <a href="<?= e(url('index.php')) ?>">&larr; Volver a la tienda</a>
  </div>
</div>

</body>
</html>
