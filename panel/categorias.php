<?php
/**
 * MODULO CATEGORIAS - solo administrador. Crear, editar, eliminar
 * con proteccion: no elimina categorias con productos asociados.
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('administrador');

$pdo = db();
$errores = [];
$mensaje = '';

/* ---------- Acciones POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $id     = (int) ($_POST['id_categoria'] ?? 0);
    $nombre = trim((string) ($_POST['nombre'] ?? ''));

    if ($accion === 'crear' || $accion === 'editar') {
        if ($nombre === '') {
            $errores[] = 'El nombre de la categoria es obligatorio.';
        } elseif (mb_strlen($nombre) > 60) {
            $errores[] = 'El nombre no puede superar 60 caracteres.';
        } else {
            // nombre unico
            $dup = $pdo->prepare('SELECT id_categoria FROM categorias WHERE nombre = ? AND id_categoria <> ?');
            $dup->execute([$nombre, $accion === 'editar' ? $id : 0]);
            if ($dup->fetch()) {
                $errores[] = 'Ya existe una categoria con ese nombre.';
            } elseif ($accion === 'crear') {
                $pdo->prepare('INSERT INTO categorias (nombre) VALUES (?)')->execute([$nombre]);
                flash('ok', 'Categoria "' . $nombre . '" creada.');
                redirigir('panel/categorias.php');
            } else {
                $pdo->prepare('UPDATE categorias SET nombre = ? WHERE id_categoria = ?')
                    ->execute([$nombre, $id]);
                flash('ok', 'Categoria actualizada.');
                redirigir('panel/categorias.php');
            }
        }
    } elseif ($accion === 'eliminar' && $id > 0) {
        $uso = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE id_categoria = ?');
        $uso->execute([$id]);
        if ((int) $uso->fetchColumn() > 0) {
            $errores[] = 'No se puede eliminar: existen productos en esta categoria. Cambie esos productos de categoria primero.';
        } else {
            $pdo->prepare('DELETE FROM categorias WHERE id_categoria = ?')->execute([$id]);
            flash('ok', 'Categoria eliminada.');
            redirigir('panel/categorias.php');
        }
    }
}

/* ---------- Datos ---------- */
$categorias = $pdo->query(
    'SELECT c.*, COUNT(p.id_producto) total_productos
       FROM categorias c
       LEFT JOIN productos p ON p.id_categoria = c.id_categoria
      GROUP BY c.id_categoria
      ORDER BY c.nombre'
)->fetchAll();

$editar = null;
if (!empty($_GET['editar'])) {
    foreach ($categorias as $c) {
        if ((int) $c['id_categoria'] === (int) $_GET['editar']) { $editar = $c; break; }
    }
}

$activo = 'categorias';
require __DIR__ . '/../includes/panel/sidebar.php';
?>
<div class="panel-wrap">
<?php require __DIR__ . '/../includes/panel/topbar.php'; ?>

<main class="panel-main">
  <div class="caja-cabeza">
    <div class="caja-titulo"><i data-lucide="tags"></i>
      <div><h2>Categorias</h2><small><?= count($categorias) ?> categoria(s) · solo administrador</small></div>
    </div>
  </div>

  <?php if ($msg = flash('ok')): ?>
    <div class="flash ok"><i data-lucide="check-circle"></i><?= e($msg) ?></div>
  <?php endif; ?>
  <?php foreach ($errores as $ex): ?>
    <div class="flash err"><i data-lucide="alert-circle"></i><?= e($ex) ?></div>
  <?php endforeach; ?>

  <!-- Formulario crear / editar -->
  <div class="caja" style="margin-bottom:1.2rem">
    <h3 style="margin-bottom:.9rem;font-size:.95rem">
      <?= $editar ? 'Editar categoria #' . (int) $editar['id_categoria'] : 'Nueva categoria' ?></h3>
    <form method="post" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:flex-end">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
      <?php if ($editar): ?>
        <input type="hidden" name="id_categoria" value="<?= (int) $editar['id_categoria'] ?>">
      <?php endif; ?>
      <label class="campo" style="flex:1;min-width:240px;margin:0">
        <span>Nombre *</span>
        <input type="text" name="nombre" maxlength="60" required
               value="<?= e($editar['nombre'] ?? '') ?>" placeholder="Ej: Vacuno">
      </label>
      <button class="btn-prem" type="submit">
        <i data-lucide="<?= $editar ? 'save' : 'plus' ?>"></i>
        <?= $editar ? 'Guardar' : 'Crear' ?></button>
      <?php if ($editar): ?>
        <a class="btn-fantasma" href="<?= e(url('panel/categorias.php')) ?>">Cancelar</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Listado -->
  <div class="caja">
    <div class="tabla-env" data-tabla data-ppag="10">
      <table class="tabla">
        <thead>
          <tr><th>#</th><th>Categoria</th><th>Productos</th><th style="text-align:right">Acciones</th></tr>
        </thead>
        <tbody>
          <?php if (!$categorias): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--gris)">Sin categorias</td></tr>
          <?php else: foreach ($categorias as $c): ?>
            <tr>
              <td><?= (int) $c['id_categoria'] ?></td>
              <td><b class="num"><?= e($c['nombre']) ?></b></td>
              <td>
                <span class="etiqueta etag-<?= (int) $c['total_productos'] > 0 ? 'ok' : 'baja' ?>">
                  <?= (int) $c['total_productos'] ?> producto(s)</span>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <a class="btn-fantasma" style="padding:.4rem .8rem;font-size:.74rem"
                   href="<?= e(url('panel/categorias.php?editar=' . (int) $c['id_categoria'])) ?>">
                  <i data-lucide="pencil" style="width:13px"></i> Editar</a>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Eliminar categoria <?= e(addslashes($c['nombre'])) ?>?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id_categoria" value="<?= (int) $c['id_categoria'] ?>">
                  <button class="btn-fantasma" style="padding:.4rem .8rem;font-size:.74rem;color:#ff7a80;border-color:rgba(213,16,25,.4)">
                    <i data-lucide="trash-2" style="width:13px"></i> Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="paginacion">
      <span class="pag-info"></span>
      <button class="pag-btn" data-nav="prev">&laquo;</button>
      <button class="pag-btn" data-p="1">1</button>
      <button class="pag-btn" data-p="2">2</button>
      <button class="pag-btn" data-p="3">3</button>
      <button class="pag-btn" data-nav="next">&raquo;</button>
    </div>
  </div>
</main>
<?php require __DIR__ . '/../includes/panel/shell_end.php';
