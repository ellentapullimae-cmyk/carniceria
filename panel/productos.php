<?php
/**
 * MODULO PRODUCTOS - CRUD completo con imagenes, precios,
 * categorias y estados. Empleado: crea/edita; Administrador: + eliminar.
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('administrador', 'empleado');

$pdo = db();
$errores = [];
$mensaje = '';

/* ---------- Acciones POST (validan CSRF) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $id     = (int) ($_POST['id_producto'] ?? 0);

    if ($accion === 'estado' && $id > 0) {
        $pdo->prepare('UPDATE productos SET activo = IF(activo = 1, 0, 1) WHERE id_producto = ?')
            ->execute([$id]);
        $mensaje = 'Estado del producto actualizado.';

    } elseif ($accion === 'eliminar' && $id > 0) {
        if (!es_admin()) {
            http_response_code(403);
            exit('403 - Solo el administrador puede eliminar productos.');
        }
        // No eliminar si tiene historial de ventas o pedidos (integridad referencial)
        $usos = $pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM detalle_ventas WHERE id_producto = ?) v,
                    (SELECT COUNT(*) FROM detalle_pedidos WHERE id_producto = ?) p'
        );
        $usos->execute([$id, $id]);
        $u = $usos->fetch();
        if ((int) $u['v'] > 0 || (int) $u['p'] > 0) {
            $errores[] = 'No se puede eliminar: el producto tiene ventas o pedidos registrados. Use "Desactivar" en su lugar.';
        } else {
            $fila = $pdo->prepare('SELECT imagen FROM productos WHERE id_producto = ?');
            $fila->execute([$id]);
            $img = $fila->fetchColumn();
            $pdo->prepare('DELETE FROM productos WHERE id_producto = ?')->execute([$id]);
            if ($img && is_readable(BASE_PATH . '/assets/img/uploads/' . $img)) {
                @unlink(BASE_PATH . '/assets/img/uploads/' . $img);
            }
            $mensaje = 'Producto eliminado definitivamente.';
        }
    }
}

/* ---------- Filtros ---------- */
$q     = trim((string) ($_GET['q'] ?? ''));
$fCat  = (int) ($_GET['cat'] ?? 0);
$fEst  = (string) ($_GET['est'] ?? '');

$sql = 'SELECT p.*, c.nombre categoria FROM productos p
          JOIN categorias c ON c.id_categoria = p.id_categoria
         WHERE 1 = 1';
$params = [];
if ($q !== '')   { $sql .= ' AND (p.nombre LIKE ? OR p.descripcion LIKE ?)'; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }
if ($fCat > 0)   { $sql .= ' AND p.id_categoria = ?';                        $params[] = $fCat; }
if ($fEst !== ''){ $sql .= ' AND p.activo = ?';                              $params[] = (int) $fEst; }
$sql .= ' ORDER BY p.activo DESC, p.creado_en DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$categorias = $pdo->query('SELECT id_categoria, nombre FROM categorias ORDER BY nombre')->fetchAll();

$activo = 'productos';
require __DIR__ . '/../includes/panel/sidebar.php';
?>
<div class="panel-wrap">
<?php require __DIR__ . '/../includes/panel/topbar.php'; ?>

<main class="panel-main">
  <div class="caja-cabeza">
    <div class="caja-titulo"><i data-lucide="package"></i>
      <div><h2>Productos</h2><small><?= count($productos) ?> producto(s) · gestion de catalogo</small></div>
    </div>
    <a class="btn-prem" href="<?= e(url('panel/producto_form.php')) ?>">
      <i data-lucide="plus"></i> Nuevo producto</a>
  </div>

  <?php if ($msg = flash('ok')): ?>
    <div class="flash ok"><i data-lucide="check-circle"></i><?= e($msg) ?></div>
  <?php endif; ?>
  <?php foreach ($errores as $ex): ?>
    <div class="flash err"><i data-lucide="alert-circle"></i><?= e($ex) ?></div>
  <?php endforeach; ?>

  <div class="caja">
    <!-- Filtros -->
    <form method="get" class="filtros" style="margin-bottom:1rem;align-items:center">
      <div class="tabla-busca" style="flex:1;min-width:220px;margin:0">
        <i data-lucide="search"></i>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Buscar producto...">
      </div>
      <select name="cat" class="filtro" style="padding:.55rem .9rem">
        <option value="0">Todas las categorias</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= (int) $c['id_categoria'] ?>" <?= $fCat === (int) $c['id_categoria'] ? 'selected' : '' ?>>
            <?= e($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="est" class="filtro" style="padding:.55rem .9rem">
        <option value="">Todos los estados</option>
        <option value="1" <?= $fEst === '1' ? 'selected' : '' ?>>Activos</option>
        <option value="0" <?= $fEst === '0' ? 'selected' : '' ?>>Inactivos</option>
      </select>
      <button class="filtro activo" type="submit">Filtrar</button>
      <?php if ($q || $fCat || $fEst): ?>
        <a class="filtro" href="<?= e(url('panel/productos.php')) ?>">Limpiar</a>
      <?php endif; ?>
    </form>

    <div class="tabla-env" data-tabla data-ppag="10">
      <table class="tabla">
        <thead>
          <tr>
            <th>Producto</th><th>Categoria</th><th>Precio</th><th>Stock</th>
            <th>Estado</th><th style="text-align:right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$productos): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--gris)">Sin productos que coincidan</td></tr>
          <?php else: foreach ($productos as $p): ?>
            <tr>
              <td>
                <div style="display:flex;gap:.7rem;align-items:center">
                  <span class="top-foto" style="width:38px;height:38px">
                    <?= $p['imagen'] && is_readable(BASE_PATH . '/assets/img/uploads/' . $p['imagen'])
                        ? '<img src="' . e(url('assets/img/uploads/' . $p['imagen'])) . '" alt="">'
                        : '&#129385;' ?>
                  </span>
                  <span>
                    <b class="num"><?= e($p['nombre']) ?></b>
                    <small style="display:block;color:var(--gris);font-size:.7rem">
                      <?= e(mb_strimwidth((string) $p['descripcion'], 0, 45, '…')) ?></small>
                  </span>
                </div>
              </td>
              <td><?= e($p['categoria']) ?></td>
              <td class="total">S/ <?= number_format((float) $p['precio'], 2) ?></td>
              <td>
                <b style="color:<?= $p['stock'] <= $p['stock_minimo'] ? '#ff7a80' : '#fff' ?>">
                  <?= (int) $p['stock'] ?></b>
                <small style="color:var(--gris)"> / min <?= (int) $p['stock_minimo'] ?></small>
              </td>
              <td>
                <span class="etiqueta <?= $p['activo'] ? 'etag-ok' : 'etag-bajo' ?>">
                  <?= $p['activo'] ? 'Activo' : 'Inactivo' ?></span>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <a class="btn-fantasma" style="padding:.4rem .8rem;font-size:.74rem"
                   href="<?= e(url('panel/producto_form.php?id=' . (int) $p['id_producto'])) ?>">
                  <i data-lucide="pencil" style="width:13px"></i> Editar</a>

                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="estado">
                  <input type="hidden" name="id_producto" value="<?= (int) $p['id_producto'] ?>">
                  <button class="btn-fantasma" style="padding:.4rem .8rem;font-size:.74rem;
                          <?= $p['activo'] ? 'color:#ff7a80;border-color:rgba(213,16,25,.4)' : 'color:#6fdc8c' ?>">
                    <?= $p['activo'] ? 'Desactivar' : 'Activar' ?></button>
                </form>

                <?php if (es_admin()): ?>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Eliminar definitivamente <?= e(addslashes($p['nombre'])) ?>?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id_producto" value="<?= (int) $p['id_producto'] ?>">
                  <button class="btn-fantasma" style="padding:.4rem .8rem;font-size:.74rem;color:#ff7a80;border-color:rgba(213,16,25,.4)">
                    <i data-lucide="trash-2" style="width:13px"></i></button>
                </form>
                <?php endif; ?>
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
