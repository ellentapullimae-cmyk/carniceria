<?php
/**
 * Barra superior premium: buscador global, fecha, notificaciones,
 * pantalla completa y menu de perfil.
 */

$u = usuario_actual();
$iniciales = mb_strtoupper(mb_substr(explode(' ', $u['nombre'])[0], 0, 1));

// Notificaciones reales: pedidos pendientes + stock bajo
$notificaciones = [];
try {
    $pdo = db();
    $nPed = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn();
    if ($nPed > 0) {
        $notificaciones[] = ['icono' => 'clipboard-list', 'titulo' => "{$nPed} pedido(s) pendiente(s)", 'sub' => 'Esperando confirmacion del staff', 'url' => url('panel/pedidos.php')];
    }
    $nConf = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'confirmado'")->fetchColumn();
    if ($nConf > 0) {
        $notificaciones[] = ['icono' => 'truck', 'titulo' => "{$nConf} pedido(s) por entregar", 'sub' => 'Confirmados, pendiente de despacho', 'url' => url('panel/pedidos.php')];
    }
    $bajos = $pdo->query('SELECT nombre, stock FROM v_stock_bajo ORDER BY stock ASC LIMIT 3')->fetchAll();
    foreach ($bajos as $b) {
        $notificaciones[] = ['icono' => 'alert-triangle', 'titulo' => 'Stock bajo: ' . $b['nombre'], 'sub' => 'Quedan ' . (int) $b['stock'] . ' unidades', 'url' => url('panel/inventario.php')];
    }
} catch (Throwable $ex) { /* sin BD */ }

$fecha = ucfirst(strftime('%A %d %B %Y', time()));
?>
<header class="topbar">
  <button class="top-btn" id="btnSidebar" title="Contraer menu"><i data-lucide="panel-left"></i></button>

  <div class="buscador">
    <i data-lucide="search"></i>
    <input type="text" id="buscadorGlobal" placeholder="Buscar productos, clientes, pedidos..."
           autocomplete="off">
    <div class="resultados" id="resultadosBusqueda"></div>
  </div>

  <div class="top-fecha"><i data-lucide="calendar-days" style="width:14px;vertical-align:-2px"></i>
    <b><?= e($fecha) ?></b></div>

  <div class="dropdown campita">
    <button class="top-btn" id="btnNotif" title="Notificaciones">
      <i data-lucide="bell"></i>
      <?php if ($notificaciones): ?><span class="punto"></span><?php endif; ?>
    </button>
    <div class="dropdown-caja" id="cajaNotif">
      <div class="dd-titulo">Notificaciones (<?= count($notificaciones) ?>)</div>
      <?php if (!$notificaciones): ?>
        <div class="dd-vacio">Sin alertas por ahora</div>
      <?php else: foreach ($notificaciones as $n): ?>
        <a class="dd-item" href="<?= e($n['url']) ?>">
          <i data-lucide="<?= $n['icono'] ?>"></i>
          <span><b><?= e($n['titulo']) ?></b><?= e($n['sub']) ?></span>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <button class="top-btn" id="btnPantalla" title="Pantalla completa"><i data-lucide="maximize"></i></button>

  <div class="dropdown">
    <button class="perfil-btn" id="btnPerfil">
      <span class="avatar"><?= e($iniciales) ?></span>
      <span class="perfil-txt"><b><?= e($u['nombre']) ?></b><small><?= e($u['rol']) ?></small></span>
      <i data-lucide="chevron-down"></i>
    </button>
    <div class="dropdown-caja dd-perfil" id="cajaPerfil">
      <div class="dd-titulo">Mi cuenta</div>
      <div class="dd-item"><i data-lucide="user"></i>
        <span><b><?= e($u['nombre']) ?></b><?= e($u['correo']) ?></span></div>
      <div class="dd-item"><i data-lucide="shield-check"></i>
        <span><b>Rol</b><?= e($u['rol']) ?></span></div>
      <a class="dd-item salir" href="<?= e(url('logout.php')) ?>">
        <i data-lucide="log-out"></i><span><b>Cerrar sesion</b>Salir del sistema</span></a>
    </div>
  </div>
</header>
