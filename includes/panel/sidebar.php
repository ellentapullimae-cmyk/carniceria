<?php
/**
 * Menu lateral premium del panel.
 * Requiere: bootstrap + sesion activa. Filtra items por rol.
 * $activo = clave de la seccion actual (ej: 'dashboard').
 */
$rol = usuario_actual()['rol'] ?? '';

$pendientes = 0;
try {
    $pendientes = (int) db()->query(
        "SELECT COUNT(*) FROM pedidos WHERE estado IN ('pendiente','confirmado')"
    )->fetchColumn();
} catch (Throwable $ex) { /* BD no disponible */ }

// [clave, etiqueta, icono lucide, archivo, roles permitidos]
$menu = [
    ['SECCION', 'General', '', '', ['administrador', 'empleado']],
    ['dashboard',  'Dashboard',       'layout-dashboard', 'index.php',         ['administrador', 'empleado']],
    ['productos',  'Productos',       'package',          'productos.php',     ['administrador', 'empleado']],
    ['categorias', 'Categor&iacute;as', 'tags',            'categorias.php',    ['administrador']],
    ['inventario', 'Inventario',      'warehouse',        'inventario.php',    ['administrador', 'empleado']],
    ['ventas',     'Ventas',          'shopping-cart',    'ventas.php',        ['administrador', 'empleado']],
    ['pedidos',    'Pedidos',         'clipboard-list',   'pedidos.php',       ['administrador', 'empleado']],
    ['SECCION', 'Gesti&oacute;n', '', '', ['administrador', 'empleado']],
    ['clientes',   'Clientes',        'contact',          'clientes.php',      ['administrador', 'empleado']],
    ['usuarios',   'Usuarios',        'user-cog',         'usuarios.php',      ['administrador']],
    ['reportes',   'Reportes',        'bar-chart-3',      'reportes.php',      ['administrador']],
    ['ofertas',    'Ofertas',         'percent',          'ofertas.php',       ['administrador']],
    ['SECCION', 'Sistema', '', '', ['administrador']],
    ['config',     'Configuraci&oacute;n', 'settings',     'configuracion.php', ['administrador']],
];
$activo = $activo ?? 'dashboard';
?>
<aside class="sidebar" id="sidebar">
  <a class="sidebar-logo" href="<?= e(url('panel/index.php')) ?>">
    <span class="logo-ico">&#127830;</span>
    <span class="logo-txt">La Tradici&oacute;n<small>Carnicer&iacute;a</small></span>
  </a>

  <nav class="sidebar-nav">
    <?php foreach ($menu as $item): ?>
      <?php
      if ($item[0] === 'SECCION') {
          if (!in_array($rol, $item[4], true)) { continue; }
          echo '<div class="nav-seccion">' . $item[1] . '</div>';
          continue;
      }
      if (!in_array($rol, $item[4], true)) { continue; }
      $esActivo = ($activo === $item[0]);
      ?>
      <a class="nav-item <?= $esActivo ? 'activo' : '' ?>"
         href="<?= e(url('panel/' . $item[3])) ?>"
         title="<?= strip_tags($item[1]) ?>">
        <i data-lucide="<?= $item[2] ?>"></i>
        <span><?= $item[1] ?></span>
        <?php if ($item[0] === 'pedidos' && $pendientes > 0): ?>
          <span class="nav-badge" title="<?= $pendientes ?> pedidos pendientes"><?= $pendientes ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-pie">La Tradici&oacute;n &copy; <?= date('Y') ?> &middot; ERP v1.0</div>
</aside>
