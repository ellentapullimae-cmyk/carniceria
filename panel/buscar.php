<?php
/**
 * BUSCADOR GLOBAL: productos, clientes y pedidos (consulta real PDO).
 * GET ?q=texto  ->  JSON [{titulo, sub, tipo, icono, url}, ...]
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('administrador', 'empleado');

header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) { echo '[]'; exit; }

$like = '%' . $q . '%';
$pdo  = db();
$res  = [];

// Productos
$stmt = $pdo->prepare(
    'SELECT p.nombre, p.precio, p.stock, c.nombre categoria
       FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
      WHERE p.nombre LIKE ? OR c.nombre LIKE ?
      ORDER BY p.activo DESC, p.nombre LIMIT 5'
);
$stmt->execute([$like, $like]);
foreach ($stmt->fetchAll() as $p) {
    $res[] = [
        'titulo' => $p['nombre'],
        'sub'    => 'S/ ' . number_format((float) $p['precio'], 2) . ' · stock ' . (int) $p['stock'] . ' · ' . $p['categoria'],
        'tipo'   => 'producto',
        'icono'  => 'package',
        'url'    => url('panel/productos.php') . '?q=' . rawurlencode($q),
    ];
}

// Clientes
$stmt = $pdo->prepare(
    'SELECT nombre, correo, telefono FROM clientes
      WHERE nombre LIKE ? OR correo LIKE ? OR dni LIKE ?
      ORDER BY nombre LIMIT 4'
);
$stmt->execute([$like, $like, $like]);
foreach ($stmt->fetchAll() as $c) {
    $res[] = [
        'titulo' => $c['nombre'],
        'sub'    => trim(($c['correo'] ?? '') . ' · ' . ($c['telefono'] ?? ''), ' ·'),
        'tipo'   => 'cliente',
        'icono'  => 'contact',
        'url'    => url('panel/clientes.php') . '?q=' . rawurlencode($q),
    ];
}

// Pedidos
$stmt = $pdo->prepare(
    'SELECT pd.id_pedido, pd.estado, pd.total, pd.creado_en, cl.nombre
       FROM pedidos pd JOIN clientes cl ON cl.id_cliente = pd.id_cliente
      WHERE pd.id_pedido LIKE ? OR cl.nombre LIKE ? OR pd.estado LIKE ?
      ORDER BY pd.creado_en DESC LIMIT 4'
);
$stmt->execute(['%' . $q . '%', $like, $like]);
foreach ($stmt->fetchAll() as $p) {
    $res[] = [
        'titulo' => 'Pedido #' . $p['id_pedido'] . ' · ' . $p['nombre'],
        'sub'    => 'S/ ' . number_format((float) $p['total'], 2) . ' · ' . $p['estado'] . ' · ' . date('d/m/Y', strtotime($p['creado_en'])),
        'tipo'   => 'pedido',
        'icono'  => 'clipboard-list',
        'url'    => url('panel/pedidos.php') . '?q=' . rawurlencode($q),
    ];
}

echo json_encode(array_slice($res, 0, 10), JSON_UNESCAPED_UNICODE);
