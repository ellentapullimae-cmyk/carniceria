<?php
/**
 * FORMULARIO PRODUCTO - creacion y edicion con subida de imagen
 * (jpg/png/webp, max 3 MB). Empleado y administrador.
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('administrador', 'empleado');

$pdo = db();
$id  = (int) ($_GET['id'] ?? $_POST['id_producto'] ?? 0);
$producto = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id_producto = ?');
    $stmt->execute([$id]);
    $producto = $stmt->fetch();
    if (!$producto) {
        http_response_code(404);
        exit('404 - Producto no encontrado.');
    }
}

$errores = [];
$campos  = $producto ?: [
    'nombre' => '', 'id_categoria' => 0, 'precio' => '', 'stock' => '',
    'stock_minimo' => 5, 'activo' => 1, 'descripcion' => '', 'imagen' => null,
];

/* ---------- Guardar ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $campos = [
        'nombre'        => trim((string) ($_POST['nombre'] ?? '')),
        'id_categoria'  => (int) ($_POST['id_categoria'] ?? 0),
        'precio'        => (string) ($_POST['precio'] ?? ''),
        'stock'         => (string) ($_POST['stock'] ?? ''),
        'stock_minimo'  => (string) ($_POST['stock_minimo'] ?? '5'),
        'activo'        => isset($_POST['activo']) ? 1 : 0,
        'descripcion'   => trim((string) ($_POST['descripcion'] ?? '')),
        'imagen'        => $producto['imagen'] ?? null,
    ];

    /* --- validaciones --- */
    if ($campos['nombre'] === '')          { $errores[] = 'El nombre es obligatorio.'; }
    if ($campos['id_categoria'] <= 0)      { $errores[] = 'Seleccione una categoria.'; }
    if (!is_numeric($campos['precio']) || (float) $campos['precio'] <= 0) {
        $errores[] = 'El precio debe ser un numero mayor a 0.';
    }
    if (!is_numeric($campos['stock']) || (int) $campos['stock'] < 0) {
        $errores[] = 'El stock debe ser un numero mayor o igual a 0.';
    }
    if (!is_numeric($campos['stock_minimo']) || (int) $campos['stock_minimo'] < 0) {
        $errores[] = 'El stock minimo debe ser un numero mayor o igual a 0.';
    }
    $catStmt = $pdo->prepare('SELECT 1 FROM categorias WHERE id_categoria = ?');
    $catStmt->execute([$campos['id_categoria']]);
    if (!$catStmt->fetch()) { $errores[] = 'La categoria seleccionada no existe.'; }

    /* --- imagen --- */
    if (!empty($_FILES['imagen']['name'])) {
        $archivo = $_FILES['imagen'];
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'No se pudo subir la imagen (codigo ' . $archivo['error'] . ').';
        } elseif ($archivo['size'] > 3 * 1024 * 1024) {
            $errores[] = 'La imagen no puede superar 3 MB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
            $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
            if (!$ext) {
                $errores[] = 'Formato no valido: use JPG, PNG o WEBP.';
            } else {
                $nombreNuevo = 'p_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destino    = BASE_PATH . '/assets/img/uploads/' . $nombreNuevo;
                if (!is_dir(dirname($destino))) { mkdir(dirname($destino), 0775, true); }
                if (move_uploaded_file($archivo['tmp_name'], $destino)) {
                    if ($campos['imagen'] && is_readable(BASE_PATH . '/assets/img/uploads/' . $campos['imagen'])) {
                        @unlink(BASE_PATH . '/assets/img/uploads/' . $campos['imagen']);
                    }
                    $campos['imagen'] = $nombreNuevo;
                } else {
                    $errores[] = 'Error al guardar la imagen en el servidor.';
                }
            }
        }
    } elseif (isset($_POST['quitar_imagen']) && $campos['imagen']) {
        if (is_readable(BASE_PATH . '/assets/img/uploads/' . $campos['imagen'])) {
            @unlink(BASE_PATH . '/assets/img/uploads/' . $campos['imagen']);
        }
        $campos['imagen'] = null;
    }

    /* --- persistir --- */
    if (!$errores) {
        if ($producto) {
            $pdo->prepare(
                'UPDATE productos SET nombre=?, id_categoria=?, precio=?, stock=?, stock_minimo=?,
                        activo=?, descripcion=?, imagen=? WHERE id_producto=?'
            )->execute([
                $campos['nombre'], $campos['id_categoria'], $campos['precio'], $campos['stock'],
                $campos['stock_minimo'], $campos['activo'], $campos['descripcion'],
                $campos['imagen'], $id,
            ]);
            flash('ok', 'Producto "' . $campos['nombre'] . '" actualizado.');
        } else {
            $pdo->prepare(
                'INSERT INTO productos (nombre, id_categoria, precio, stock, stock_minimo, activo, descripcion, imagen)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                $campos['nombre'], $campos['id_categoria'], $campos['precio'], $campos['stock'],
                $campos['stock_minimo'], $campos['activo'], $campos['descripcion'], $campos['imagen'],
            ]);
            $id = (int) $pdo->lastInsertId();
            flash('ok', 'Producto "' . $campos['nombre'] . '" creado.');
        }
        redirigir('panel/productos.php');
    }
}

$categorias = $pdo->query('SELECT id_categoria, nombre FROM categorias ORDER BY nombre')->fetchAll();

$titulo = $producto ? 'Editar producto' : 'Nuevo producto';
$activo = 'productos';
require __DIR__ . '/../includes/panel/sidebar.php';
?>
<div class="panel-wrap">
<?php require __DIR__ . '/../includes/panel/topbar.php'; ?>

<main class="panel-main">
  <div class="caja-cabeza">
    <div class="caja-titulo"><i data-lucide="<?= $producto ? 'pencil' : 'plus-circle' ?>"></i>
      <div><h2><?= $titulo ?></h2>
        <small><?= $producto ? 'id #' . (int) $producto['id_producto'] : 'agregar al catalogo' ?></small></div>
    </div>
    <a class="btn-fantasma" href="<?= e(url('panel/productos.php')) ?>">
      <i data-lucide="arrow-left"></i> Volver al listado</a>
  </div>

  <div class="caja">
    <?php foreach ($errores as $ex): ?>
      <div class="flash err"><i data-lucide="alert-circle"></i><?= e($ex) ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" class="form-grid"
          style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;max-width:960px">
      <?= csrf_field() ?>
      <?php if ($producto): ?>
        <input type="hidden" name="id_producto" value="<?= (int) $id ?>">
      <?php endif; ?>

      <label class="campo">
        <span>Nombre *</span>
        <input type="text" name="nombre" maxlength="120" required
               value="<?= e($campos['nombre']) ?>" placeholder="Ej: Bife ancho de res">
      </label>

      <label class="campo">
        <span>Categoria *</span>
        <select name="id_categoria" required>
          <option value="">-- Seleccione --</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= (int) $c['id_categoria'] ?>"
              <?= (int) $campos['id_categoria'] === (int) $c['id_categoria'] ? 'selected' : '' ?>>
              <?= e($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="campo">
        <span>Precio (S/) *</span>
        <input type="number" name="precio" step="0.01" min="0.01" required
               value="<?= e($campos['precio']) ?>" placeholder="38.00">
      </label>

      <label class="campo">
        <span>Stock actual *</span>
        <input type="number" name="stock" min="0" required
               value="<?= e($campos['stock']) ?>" placeholder="40">
      </label>

      <label class="campo">
        <span>Stock minimo (alerta)</span>
        <input type="number" name="stock_minimo" min="0"
               value="<?= e($campos['stock_minimo']) ?>">
      </label>

      <label class="campo" style="justify-content:center">
        <span>Estado</span>
        <span style="display:flex;gap:.6rem;align-items:center;padding-top:.4rem">
          <input type="checkbox" name="activo" id="chkAct" style="width:auto;accent-color:#D51019"
                 <?= $campos['activo'] ? 'checked' : '' ?>>
          <label for="chkAct" style="color:var(--texto)">Producto activo (visible)</label>
        </span>
      </label>

      <label class="campo" style="grid-column:1/-1">
        <span>Descripcion</span>
        <textarea name="descripcion" rows="3" maxlength="400"
                  placeholder="Corte, procedencia, notas..."><?= e($campos['descripcion']) ?></textarea>
      </label>

      <div class="campo" style="grid-column:1/-1">
        <span>Imagen (JPG/PNG/WEBP, max 3 MB)</span>
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
          <span class="top-foto" style="width:72px;height:72px">
            <?= !empty($campos['imagen']) && is_readable(BASE_PATH . '/assets/img/uploads/' . $campos['imagen'])
                ? '<img src="' . e(url('assets/img/uploads/' . $campos['imagen'])) . '" alt="">'
                : '&#129385;' ?>
          </span>
          <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
          <?php if (!empty($campos['imagen'])): ?>
            <label style="display:flex;gap:.4rem;align-items:center;font-size:.8rem;color:var(--gris)">
              <input type="checkbox" name="quitar_imagen" style="width:auto;accent-color:#D51019">
              Quitar imagen actual</label>
          <?php endif; ?>
        </div>
      </div>

      <div style="grid-column:1/-1;display:flex;gap:.8rem;margin-top:.5rem">
        <button class="btn-prem" type="submit">
          <i data-lucide="save"></i> <?= $producto ? 'Guardar cambios' : 'Crear producto' ?></button>
        <a class="btn-fantasma" href="<?= e(url('panel/productos.php')) ?>">Cancelar</a>
      </div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/panel/shell_end.php';
