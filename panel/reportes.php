<?php
/**
 * MODULO: Reportes
 * Ventas diarias, semanales, mensuales, ingresos y productos mas vendidos
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('administrador');

$activo = 'reportes';
require __DIR__ . '/../includes/panel/sidebar.php';
?>
<div class="panel-wrap">
<?php require __DIR__ . '/../includes/panel/topbar.php'; ?>

<main class="panel-main">
  <div class="caja-cabeza">
    <div class="caja-titulo"><i data-lucide="bar-chart-3"></i>
      <div><h2>Reportes</h2><small>La Tradicion Carniceria - Sistema de gestion</small></div>
    </div>
  </div>

  <div class="modulo-pend">
    <div class="mp-ico"><i data-lucide="bar-chart-3"></i></div>
    <span class="fase-chip">Fase F</span>
    <h2>Modulo Reportes</h2>
    <p>Este modulo sera implementado en la <b>Fase F</b> del proyecto:
       informes con exportacion y filtros de fecha. El menu, los permisos por rol y la navegacion ya estan
       operativos; las operaciones se conectaran directamente a la base de datos
       <b>carniceria_db</b> con consultas preparadas PDO.</p>
    <a class="btn-fantasma" href="<?= e(url('panel/index.php')) ?>">
      <i data-lucide="arrow-left"></i> Volver al dashboard</a>
  </div>
</main>
<?php require __DIR__ . '/../includes/panel/shell_end.php';
