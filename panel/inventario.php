<?php
/**
 * MODULO: Inventario
 * Entradas, salidas, stock minimo e historial de movimientos
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('administrador', 'empleado');

$activo = 'inventario';
require __DIR__ . '/../includes/panel/sidebar.php';
?>
<div class="panel-wrap">
<?php require __DIR__ . '/../includes/panel/topbar.php'; ?>

<main class="panel-main">
  <div class="caja-cabeza">
    <div class="caja-titulo"><i data-lucide="warehouse"></i>
      <div><h2>Inventario</h2><small>La Tradicion Carniceria - Sistema de gestion</small></div>
    </div>
  </div>

  <div class="modulo-pend">
    <div class="mp-ico"><i data-lucide="warehouse"></i></div>
    <span class="fase-chip">Fase C</span>
    <h2>Modulo Inventario</h2>
    <p>Este modulo sera implementado en la <b>Fase C</b> del proyecto:
       movimientos de stock con historial y alertas. El menu, los permisos por rol y la navegacion ya estan
       operativos; las operaciones se conectaran directamente a la base de datos
       <b>carniceria_db</b> con consultas preparadas PDO.</p>
    <a class="btn-fantasma" href="<?= e(url('panel/index.php')) ?>">
      <i data-lucide="arrow-left"></i> Volver al dashboard</a>
  </div>
</main>
<?php require __DIR__ . '/../includes/panel/shell_end.php';
