<?php
/**
 * PANEL DE STAFF (administrador y empleado).
 * Ruta protegida: exige sesion con rol administrador o empleado.
 * Dashboard basico con datos REALES de carniceria_db.
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('administrador', 'empleado');

$pdo = db();
$u   = usuario_actual();

$totalProductos   = (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
$disponibles      = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock > 0')->fetchColumn();
$pocoStock        = $pdo->query('SELECT nombre, stock, stock_minimo FROM v_stock_bajo ORDER BY stock ASC')->fetchAll();
$totalClientes    = (int) $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
$ventasHoy        = $pdo->query('SELECT COALESCE(SUM(total),0) FROM ventas WHERE DATE(creado_en) = CURDATE()')->fetchColumn();
$ventasMes        = $pdo->query('SELECT COALESCE(SUM(total),0) FROM ventas WHERE MONTH(creado_en) = MONTH(NOW()) AND YEAR(creado_en) = YEAR(NOW())')->fetchColumn();
$pedidosPendientes = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('pendiente','confirmado')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel | La Tradicion Carniceria</title>
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
    <strong>La Tradici&oacute;n<small>Panel de control</small></strong>
  </div>
  <div class="panel-usuario">
    <span class="rol"><?= e($u['rol']) ?></span>
    <span><?= e($u['nombre']) ?></span>
    <a href="<?= e(url('logout.php')) ?>">Cerrar sesion</a>
  </div>
</header>

<main class="panel-main">
  <div class="panel-titulo">
    <h1>Hola, <?= e(explode(' ', $u['nombre'])[0]) ?></h1>
    <p>Resumen general del sistema &middot; <?= date('d/m/Y H:i') ?></p>
  </div>

  <div class="tarjetas">
    <div class="tarjeta"><div class="dato"><?= $totalProductos ?></div><div class="etiqueta">Total de productos</div></div>
    <div class="tarjeta ok"><div class="dato"><?= $disponibles ?></div><div class="etiqueta">Productos disponibles</div></div>
    <div class="tarjeta alerta"><div class="dato"><?= count($pocoStock) ?></div><div class="etiqueta">Poco stock</div></div>
    <div class="tarjeta"><div class="dato">S/ <?= number_format((float) $ventasHoy, 2) ?></div><div class="etiqueta">Ventas del dia</div></div>
    <div class="tarjeta"><div class="dato"><?= $totalClientes ?></div><div class="etiqueta">Total de clientes</div></div>
    <div class="tarjeta alerta"><div class="dato"><?= $pedidosPendientes ?></div><div class="etiqueta">Pedidos por atender</div></div>
  </div>

  <div class="panel-caja">
    <h2>Alertas de stock bajo</h2>
    <?php if (!$pocoStock): ?>
      <p class="panel-vacio">Sin alertas: todos los productos tienen stock suficiente.</p>
    <?php else: ?>
      <ul class="lista-alerta">
        <?php foreach ($pocoStock as $p): ?>
          <li>
            <span><?= e($p['nombre']) ?></span>
            <span>Stock: <b><?= (int) $p['stock'] ?></b> / minimo <?= (int) $p['stock_minimo'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="panel-nota">
    <b>Fase 1 completada:</b> autenticacion, roles y base de datos funcionando.
    Modulos de productos, inventario, ventas, pedidos, reportes y configuracion
    se habilitan en las siguientes fases.
    <?= $u['rol'] === 'administrador' ? '<br><b>Administrador:</b> tiene acceso completo (gestion de usuarios incluida).' : '<br><b>Empleado:</b> acceso a ventas, pedidos e inventario.' ?>
  </div>
</main>

</body>
</html>
