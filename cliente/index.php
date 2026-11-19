<?php
/**
 * PANEL DEL CLIENTE. Ruta protegida: solo rol cliente.
 * Muestra datos REALES del perfil y pedidos desde carniceria_db.
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('cliente');

$pdo = db();
$u   = usuario_actual();

$stmt = $pdo->prepare(
    'SELECT c.* FROM clientes c WHERE c.id_usuario = ? LIMIT 1'
);
$stmt->execute([$u['id']]);
$perfil = $stmt->fetch();

$pedidos = [];
$totalPedidos = 0;
if ($perfil) {
    $stmt = $pdo->prepare(
        'SELECT id_pedido, estado, total, creado_en FROM pedidos
          WHERE id_cliente = ? ORDER BY creado_en DESC LIMIT 10'
    );
    $stmt->execute([(int) $perfil['id_cliente']]);
    $pedidos = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE id_cliente = ?');
    $stmt->execute([(int) $perfil['id_cliente']]);
    $totalPedidos = (int) $stmt->fetchColumn();
}

$etiquetas = ['pendiente' => 'Pendiente', 'confirmado' => 'Confirmado', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi cuenta | La Tradicion Carniceria</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/panel.css')) ?>">
</head>
<body class="panel-body">

<header class="panel-top">
  <div class="panel-marca">
    <span>&#127830;</span>
    <strong>La Tradici&oacute;n<small>Mi cuenta</small></strong>
  </div>
  <div class="panel-usuario">
    <span class="rol">cliente</span>
    <span><?= e($u['nombre']) ?></span>
    <a href="<?= e(url('index.php')) ?>">Tienda</a>
    <a href="<?= e(url('logout.php')) ?>">Cerrar sesion</a>
  </div>
</header>

<main class="panel-main">
  <div class="panel-titulo">
    <h1>Mi cuenta</h1>
    <p>Bienvenido, <?= e($u['nombre']) ?></p>
  </div>

  <?php if (flash('ok')): ?>
    <div class="panel-caja" style="border-left:5px solid #2e7d32"><?= e(flash('ok')) ?></div>
  <?php endif; ?>

  <div class="tarjetas">
    <div class="tarjeta"><div class="dato"><?= $totalPedidos ?></div><div class="etiqueta">Pedidos realizados</div></div>
    <div class="tarjeta ok"><div class="dato"><?= e($perfil['dni'] ?? '—') ?></div><div class="etiqueta">DNI</div></div>
    <div class="tarjeta"><div class="dato" style="font-size:1.1rem"><?= e($perfil['telefono'] ?? '—') ?></div><div class="etiqueta">Telefono</div></div>
    <div class="tarjeta"><div class="dato" style="font-size:1.1rem"><?= e($perfil['correo'] ?? $u['correo']) ?></div><div class="etiqueta">Correo</div></div>
  </div>

  <div class="panel-caja">
    <h2>Mis pedidos</h2>
    <?php if (!$pedidos): ?>
      <p class="panel-vacio">Aun no has realizado pedidos. Visita la tienda para comprar.</p>
    <?php else: ?>
      <ul class="lista-alerta">
        <?php foreach ($pedidos as $p): ?>
          <li>
            <span>Pedido #<?= (int) $p['id_pedido'] ?> &middot; <?= date('d/m/Y', strtotime($p['creado_en'])) ?></span>
            <span><?= e($etiquetas[$p['estado']] ?? $p['estado']) ?> &middot; <b>S/ <?= number_format((float) $p['total'], 2) ?></b></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="panel-nota">
    <b>Fase 1 completada:</b> registro, inicio de sesion de clientes y seguimiento basico de pedidos.
    Carrito, checkout y edicion de perfil se habilitan en la Fase 5.
  </div>
</main>

</body>
</html>
