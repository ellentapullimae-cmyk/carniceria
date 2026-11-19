<?php
/**
 * API: serie de ventas por dia (para el filtro de la grafica).
 * GET ?dias=30  ->  [{fecha, total}, ...] (dias sin venta = 0)
 * Requiere sesion de administrador o empleado.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requiere_rol('administrador', 'empleado');

header('Content-Type: application/json; charset=utf-8');

$dias = (int) ($_GET['dias'] ?? 30);
if ($dias < 1 || $dias > 92) { $dias = 30; }

$filas = db()->query(
    "SELECT DATE(creado_en) fecha, SUM(total) total
       FROM ventas
      WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL " . ($dias - 1) . " DAY)
      GROUP BY DATE(creado_en)"
)->fetchAll();

$mapa = [];
foreach ($filas as $f) { $mapa[$f['fecha']] = (float) $f['total']; }

$salida = [];
$fecha  = new DateTimeImmutable('-' . ($dias - 1) . ' days');
$fin    = new DateTimeImmutable('today');
while ($fecha <= $fin) {
    $clave = $fecha->format('Y-m-d');
    $salida[] = ['fecha' => $clave, 'total' => $mapa[$clave] ?? 0];
    $fecha = $fecha->modify('+1 day');
}

echo json_encode($salida, JSON_UNESCAPED_UNICODE);
