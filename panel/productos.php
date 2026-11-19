<?php
/**
 * MODULO: Productos
 * CRUD completo con imagenes, precios, categorias y estados
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('administrador', 'empleado');

$activo = 'productos';
require __DIR__ . '/../includes/panel/sidebar.php';
?>
<div class="panel-wrap">
<?php require __DIR__ . '/../includes/panel/topbar.php'; ?>

<main class="panel-main">
  <div class="caja-cabeza">
    <div class="caja-titulo"><i data-lucide="package"></i>
      <div><h2>Productos</h2><small>La Tradicion Carniceria - Sistema de gestion</small></div>
    </div>
  </div>

  <div class="modulo-pend">
    <div class="mp-ico"><i data-lucide="package"></i></div>
    <span class="fase-chip">Fase B</span>
    <h2>Modulo Productos</h2>
    <p>Este modulo sera implementado en la <b>Fase B</b> del proyecto:
       alta, edicion, baja logica, subida de fotos y control de precios. El menu, los permisos por rol y la navegacion ya estan
       operativos; las operaciones se conectaran directamente a la base de datos
       <b>carniceria_db</b> con consultas preparadas PDO.</p>
    <a class="btn-fantasma" href="<?= e(url('panel/index.php')) ?>">
      <i data-lucide="arrow-left"></i> Volver al dashboard</a>
  </div>
</main>
<?php require __DIR__ . '/../includes/panel/shell_end.php';
