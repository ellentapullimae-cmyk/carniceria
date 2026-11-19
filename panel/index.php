<?php
/**
 * DASHBOARD PREMIUM - La Tradicion Carniceria
 * Todas las cifras se calculan en tiempo real desde carniceria_db.
 */

require_once __DIR__ . '/../config/bootstrap.php';
requiere_rol('administrador', 'empleado');

$pdo  = db();
$u    = usuario_actual();
$esAdmin = es_admin();

/* ================= KPIs ================= */
$ventasHoy = $pdo->query(
    'SELECT COALESCE(SUM(total),0) t, COUNT(*) n FROM ventas WHERE DATE(creado_en) = CURDATE()'
)->fetch();
$ventasAyer = $pdo->query(
    'SELECT COALESCE(SUM(total),0) t FROM ventas WHERE DATE(creado_en) = CURDATE() - INTERVAL 1 DAY'
)->fetchColumn();
$ingresosMes = $pdo->query(
    'SELECT COALESCE(SUM(total),0) FROM ventas WHERE MONTH(creado_en) = MONTH(NOW()) AND YEAR(creado_en) = YEAR(NOW())'
)->fetchColumn();
$mesAnterior = $pdo->query(
    'SELECT COALESCE(SUM(total),0) FROM ventas
      WHERE YEAR(creado_en) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        AND MONTH(creado_en) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))'
)->fetchColumn();
$totalProductos = (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
$totalClientes  = (int) $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
$pendientes     = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('pendiente','confirmado')")->fetchColumn();
$stockBajo      = (int) $pdo->query('SELECT COUNT(*) FROM v_stock_bajo')->fetchColumn();

function tendencia(?float $actual, ?float $anterior): array
{
    if ($anterior === null || $anterior == 0.0) {
        return ['clase' => 'flat', 'txt' => '—', 'icono' => 'minus'];
    }
    $pct = (($actual - $anterior) / $anterior) * 100;
    if (abs($pct) < 0.5) return ['clase' => 'flat', 'txt' => '0%', 'icono' => 'minus'];
    return [
        'clase'  => $pct > 0 ? 'up' : 'down',
        'txt'    => ($pct > 0 ? '+' : '') . number_format($pct, 1) . '%',
        'icono'  => $pct > 0 ? 'trending-up' : 'trending-down',
    ];
}
$tHoy  = tendencia((float) $ventasHoy['t'], $ventasAyer !== null ? (float) $ventasAyer : null);
$tMes  = tendencia((float) $ingresosMes, (float) $mesAnterior);

/* ================= Grafica 1: ultimos 7 dias (barras + tendencia) ================= */
/** Rellena los dias faltantes de una serie [{fecha,total}] en PHP. */
function serie_dias(array $filas, int $dias): array
{
    $mapa = [];
    foreach ($filas as $f) { $mapa[$f['fecha']] = (float) $f['total']; }
    $salida = [];
    for ($i = $dias - 1; $i >= 0; $i--) {
        $fecha = date('Y-m-d', strtotime("-{$i} days"));
        $salida[] = ['fecha' => $fecha, 'total' => $mapa[$fecha] ?? 0];
    }
    return $salida;
}

$serie7 = $pdo->query(
    'SELECT DATE(creado_en) fecha, SUM(total) total FROM ventas
      WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(creado_en)'
)->fetchAll();
$s7 = serie_dias($serie7, 7);

/* ================= Grafica 2: ventas por categoria ================= */
$porCategoria = $pdo->query(
    'SELECT c.nombre, COALESCE(SUM(dv.cantidad * dv.precio_unitario), 0) total
       FROM detalle_ventas dv
       JOIN ventas v    ON v.id_venta = dv.id_venta
       JOIN productos p ON p.id_producto = dv.id_producto
       JOIN categorias c ON c.id_categoria = p.id_categoria
      WHERE v.creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      GROUP BY c.id_categoria, c.nombre
      HAVING total > 0
      ORDER BY total DESC'
)->fetchAll();

/* ================= Grafica 3: ingresos mensuales del anio ================= */
$mesesRaw = $pdo->query(
    'SELECT MONTH(creado_en) m, SUM(total) total FROM ventas
      WHERE YEAR(creado_en) = YEAR(NOW())
      GROUP BY MONTH(creado_en)'
)->fetchAll();
$mesesMap = [];
foreach ($mesesRaw as $m) { $mesesMap[(int) $m['m']] = (float) $m['total']; }
$mesesNombres = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$serieAnio = [];
for ($i = 1; $i <= 12; $i++) { $serieAnio[] = $mesesMap[$i] ?? 0; }

/* ================= Ultimas ventas ================= */
$ultimas = $pdo->query(
    'SELECT v.id_venta, v.total, v.metodo_pago, v.creado_en,
            COALESCE(cl.nombre, "Venta de mostrador") cliente,
            COUNT(dv.id_detalle) n_prod,
            GROUP_CONCAT(p.nombre ORDER BY dv.id_detalle SEPARATOR ", ") productos
       FROM ventas v
       LEFT JOIN clientes cl     ON cl.id_cliente = v.id_cliente
       LEFT JOIN detalle_ventas dv ON dv.id_venta = v.id_venta
       LEFT JOIN productos p     ON p.id_producto = dv.id_producto
      GROUP BY v.id_venta, v.total, v.metodo_pago, v.creado_en, cl.nombre
      ORDER BY v.creado_en DESC
      LIMIT 20'
)->fetchAll();

/* ================= Alertas de stock ================= */
$alertas = $pdo->query(
    'SELECT p.id_producto, p.nombre, p.stock, p.stock_minimo, p.imagen
       FROM productos p
      WHERE p.activo = 1 AND p.stock <= p.stock_minimo
      ORDER BY p.stock ASC'
)->fetchAll();

/* ================= Top 5 productos vendidos (30 dias) ================= */
$topProductos = $pdo->query(
    'SELECT p.nombre, p.precio, p.imagen, p.id_producto,
            SUM(dv.cantidad) unidades, SUM(dv.cantidad * dv.precio_unitario) ingreso
       FROM detalle_ventas dv
       JOIN ventas v     ON v.id_venta = dv.id_venta
       JOIN productos p  ON p.id_producto = dv.id_producto
      WHERE v.creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      GROUP BY p.id_producto
      ORDER BY unidades DESC
      LIMIT 5'
)->fetchAll();

/* ================= Actividad reciente ================= */
$actividad = [];
try {
    $movs = $pdo->query(
        'SELECT m.tipo, m.cantidad, m.motivo, m.creado_en, p.nombre producto, us.nombre usuario
           FROM movimientos_inventario m
           JOIN productos p ON p.id_producto = m.id_producto
           LEFT JOIN usuarios us ON us.id_usuario = m.id_usuario
          ORDER BY m.creado_en DESC LIMIT 4'
    )->fetchAll();
    foreach ($movs as $m) {
        $actividad[] = [
            'icono' => $m['tipo'] === 'entrada' ? 'arrow-down-circle' : 'arrow-up-circle',
            'html'  => '<b>' . e($m['usuario'] ?? 'Sistema') . '</b> registró una ' .
                       ($m['tipo'] === 'entrada' ? 'entrada' : 'salida') . ' de <b>' .
                       (int) $m['cantidad'] . '</b> u. de <b>' . e($m['producto']) . '</b>',
            'sub'   => e($m['motivo']) . ' · ' . date('d/m H:i', strtotime($m['creado_en'])),
            'ts'    => strtotime($m['creado_en']),
        ];
    }
    $nuevosClientes = $pdo->query(
        'SELECT nombre, creado_en FROM clientes ORDER BY creado_en DESC LIMIT 2'
    )->fetchAll();
    foreach ($nuevosClientes as $c) {
        $actividad[] = [
            'icono' => 'user-plus',
            'html'  => 'Nuevo cliente registrado: <b>' . e($c['nombre']) . '</b>',
            'sub'   => date('d/m/Y H:i', strtotime($c['creado_en'])),
            'ts'    => strtotime($c['creado_en']),
        ];
    }
} catch (Throwable $ex) { /* silencioso */ }
usort($actividad, fn($a, $b) => $b['ts'] - $a['ts']);
$actividad = array_slice($actividad, 0, 6);

/* ================= Banner con foto real si existe ================= */
$fotoBanner = '';
if (is_readable(BASE_PATH . '/assets/img/banner.jpg')) {
    $fotoBanner = url('assets/img/banner.jpg');
}

$datosG1 = json_encode($s7, JSON_UNESCAPED_UNICODE);
$datosG2 = json_encode($porCategoria, JSON_UNESCAPED_UNICODE);
$datosG3 = json_encode($serieAnio);
$mesesJs = json_encode($mesesNombres);

$activo = 'dashboard';
require __DIR__ . '/../includes/panel/sidebar.php';
?>
<div class="panel-wrap">
<?php require __DIR__ . '/../includes/panel/topbar.php'; ?>

<main class="panel-main">

  <?php if ($msg = flash('ok')): ?>
    <div class="flash ok"><i data-lucide="check-circle"></i><?= e($msg) ?></div>
  <?php endif; ?>

  <!-- ============ BANNER ============ -->
  <section class="banner <?= $fotoBanner ? 'foto' : '' ?>" <?= $fotoBanner ? 'style="--foto:url(' . e($fotoBanner) . ')"' : '' ?>>
    <div class="banner-txt">
      <span class="banner-cintillo"><i data-lucide="sparkles" style="width:13px"></i> Panel de control</span>
      <h1>Bienvenido nuevamente, <span><?= e(explode(' ', $u['nombre'])[0]) ?></span></h1>
      <p>Panel de control y rendimiento de La Tradici&oacute;n Carnicer&iacute;a &mdash;
         datos en vivo desde MySQL &middot; <?= date('d/m/Y') ?></p>
    </div>
    <?php if (!$fotoBanner): ?><div class="banner-carne" aria-hidden="true">&#129385;</div><?php endif; ?>
    <div class="banner-sello"><div><b>100%</b>Calidad<br>Garantizada</div></div>
  </section>

  <!-- ============ KPIs ============ -->
  <section class="kpis">
    <article class="kpi">
      <div class="kpi-cabeza">
        <span class="kpi-ico"><i data-lucide="banknote"></i></span>
        <span class="kpi-tend <?= $tHoy['clase'] ?>"><i data-lucide="<?= $tHoy['icono'] ?>" style="width:12px"></i><?= $tHoy['txt'] ?></span>
      </div>
      <div class="kpi-valor">S/ <?= number_format((float) $ventasHoy['t'], 2) ?></div>
      <div class="kpi-txt">Ventas de hoy &middot; <?= (int) $ventasHoy['n'] ?> venta(s)</div>
    </article>

    <article class="kpi">
      <div class="kpi-cabeza">
        <span class="kpi-ico oro"><i data-lucide="wallet"></i></span>
        <span class="kpi-tend <?= $tMes['clase'] ?>"><i data-lucide="<?= $tMes['icono'] ?>" style="width:12px"></i><?= $tMes['txt'] ?></span>
      </div>
      <div class="kpi-valor">S/ <?= number_format((float) $ingresosMes, 2) ?></div>
      <div class="kpi-txt">Ingresos del mes vs. mes anterior</div>
    </article>

    <article class="kpi">
      <div class="kpi-cabeza">
        <span class="kpi-ico"><i data-lucide="package"></i></span>
      </div>
      <div class="kpi-valor"><?= $totalProductos ?></div>
      <div class="kpi-txt">Total de productos registrados</div>
    </article>

    <article class="kpi">
      <div class="kpi-cabeza">
        <span class="kpi-ico oro"><i data-lucide="users"></i></span>
      </div>
      <div class="kpi-valor"><?= $totalClientes ?></div>
      <div class="kpi-txt">Total de clientes</div>
    </article>

    <article class="kpi">
      <div class="kpi-cabeza">
        <span class="kpi-ico"><i data-lucide="clipboard-list"></i></span>
        <?php if ($pendientes > 0): ?><span class="kpi-tend down"><i data-lucide="clock" style="width:12px"></i>Atencion</span><?php endif; ?>
      </div>
      <div class="kpi-valor"><?= $pendientes ?></div>
      <div class="kpi-txt">Pedidos pendientes de gestion</div>
    </article>

    <article class="kpi">
      <div class="kpi-cabeza">
        <span class="kpi-ico <?= $stockBajo ? '' : 'oro' ?>"><i data-lucide="alert-triangle"></i></span>
        <?php if ($stockBajo): ?><span class="kpi-tend down"><i data-lucide="arrow-down" style="width:12px"></i>Reponer</span><?php endif; ?>
      </div>
      <div class="kpi-valor"><?= $stockBajo ?></div>
      <div class="kpi-txt">Productos con stock bajo</div>
    </article>
  </section>

  <!-- ============ GRAFICAS ============ -->
  <section class="rejilla-3">
    <div class="caja" style="grid-column: span 2">
      <div class="caja-cabeza">
        <div class="caja-titulo"><i data-lucide="bar-chart-3"></i>
          <div><h2>Ventas de los ultimos 7 dias</h2><small>Barras con linea de tendencia (S/)</small></div>
        </div>
        <div class="filtros" id="filtrosG1">
          <button class="filtro activo" data-rango="7">7 dias</button>
          <button class="filtro" data-rango="30">30 dias</button>
        </div>
      </div>
      <div class="grafica-env"><canvas id="grafica7"></canvas></div>
    </div>

    <div class="caja">
      <div class="caja-cabeza">
        <div class="caja-titulo"><i data-lucide="pie-chart"></i>
          <div><h2>Ventas por categoria</h2><small>Ultimos 30 dias</small></div>
        </div>
      </div>
      <div class="grafica-env"><canvas id="graficaCat"></canvas></div>
    </div>
  </section>

  <section class="rejilla-igual">
    <div class="caja">
      <div class="caja-cabeza">
        <div class="caja-titulo"><i data-lucide="trending-up"></i>
          <div><h2>Ingresos mensuales del anio</h2><small><?= date('Y') ?> · S/</small></div>
        </div>
      </div>
      <div class="grafica-env chica"><canvas id="graficaMes"></canvas></div>
    </div>

    <div class="caja">
      <div class="caja-cabeza">
        <div class="caja-titulo"><i data-lucide="trophy"></i>
          <div><h2>Productos mas vendidos</h2><small>Top 5 · ultimos 30 dias</small></div>
        </div>
      </div>
      <?php if (!$topProductos): ?>
        <p class="dd-vacio">Aun no hay ventas registradas.</p>
      <?php else: foreach ($topProductos as $i => $t): ?>
        <div class="top-item">
          <span class="top-rank"><?= $i + 1 ?></span>
          <span class="top-foto"><?= $t['imagen'] && is_readable(BASE_PATH . '/assets/img/uploads/' . $t['imagen'])
              ? '<img src="' . e(url('assets/img/uploads/' . $t['imagen'])) . '" alt="">'
              : '&#129385;' ?></span>
          <span class="top-info"><b><?= e($t['nombre']) ?></b>
            <span><?= (int) $t['unidades'] ?> unidades vendidas</span></span>
          <span class="top-precio"><b>S/ <?= number_format((float) $t['precio'], 2) ?></b>
            <span>S/ <?= number_format((float) $t['ingreso'], 2) ?></span></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <!-- ============ ULTIMAS VENTAS + ALERTAS ============ -->
  <section class="rejilla-2">
    <div class="caja">
      <div class="caja-cabeza">
        <div class="caja-titulo"><i data-lucide="receipt"></i>
          <div><h2>Ultimas ventas</h2><small>Historial reciente de operaciones</small></div>
        </div>
        <a class="enlace-rojo" href="<?= e(url('panel/ventas.php')) ?>">Ver todas <i data-lucide="arrow-right"></i></a>
      </div>

      <div class="tabla-busca">
        <i data-lucide="search"></i>
        <input type="text" id="buscaVentas" placeholder="Buscar por cliente, producto o numero...">
      </div>

      <div class="tabla-env" data-tabla data-busca="buscaVentas" data-ppag="8">
        <table class="tabla">
          <thead>
            <tr>
              <th># Venta</th><th>Cliente</th><th>Productos</th>
              <th>Total</th><th>Pago</th><th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$ultimas): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--gris)">Sin ventas registradas</td></tr>
            <?php else: foreach ($ultimas as $v): ?>
              <tr>
                <td class="num">V-<?= str_pad($v['id_venta'], 4, '0', STR_PAD_LEFT) ?></td>
                <td><?= e($v['cliente']) ?></td>
                <td><span class="prod-lista" title="<?= e($v['productos'] ?? '') ?>">
                    <?= e($v['productos'] ?? '—') ?></span></td>
                <td class="total">S/ <?= number_format((float) $v['total'], 2) ?></td>
                <td><span class="etiqueta <?= e($v['metodo_pago']) ?>"><?= e($v['metodo_pago']) ?></span></td>
                <td><?= date('d/m/Y H:i', strtotime($v['creado_en'])) ?></td>
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

    <div class="caja">
      <div class="caja-cabeza">
        <div class="caja-titulo"><i data-lucide="alert-triangle"></i>
          <div><h2>Alertas de inventario</h2><small>Stock bajo o en limite</small></div>
        </div>
        <a class="enlace-rojo" href="<?= e(url('panel/inventario.php')) ?>">Administrar <i data-lucide="arrow-right"></i></a>
      </div>

      <?php if (!$alertas): ?>
        <p class="dd-vacio">Sin alertas: todo el stock esta en niveles correctos.</p>
      <?php else: ?>
        <div class="lista-alertas">
          <?php foreach ($alertas as $a):
              $estado = $a['stock'] <= ($a['stock_minimo'] / 2) ? 'bajo' : 'limite'; ?>
            <div class="alerta-item <?= $estado === 'limite' ? 'limite' : '' ?>">
              <span class="alerta-foto"><?= $a['imagen'] && is_readable(BASE_PATH . '/assets/img/uploads/' . $a['imagen'])
                  ? '<img src="' . e(url('assets/img/uploads/' . $a['imagen'])) . '" alt="">'
                  : '&#129385;' ?></span>
              <span class="alerta-info"><b><?= e($a['nombre']) ?></b>
                <span class="etiqueta <?= $estado === 'bajo' ? 'etag-bajo' : 'limite' ?>"><?= $estado === 'bajo' ? 'Bajo' : 'Limite' ?></span>
              </span>
              <span class="alerta-nums"><b><?= (int) $a['stock'] ?></b> / min <?= (int) $a['stock_minimo'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============ ACTIVIDAD ============ -->
  <section class="rejilla-igual">
    <div class="caja">
      <div class="caja-cabeza">
        <div class="caja-titulo"><i data-lucide="activity"></i>
          <div><h2>Actividad reciente</h2><small>Movimientos del sistema</small></div>
        </div>
      </div>
      <div class="actividad">
        <?php if (!$actividad): ?>
          <p class="dd-vacio">Sin actividad registrada aun.</p>
        <?php else: foreach ($actividad as $a): ?>
          <div class="act-item">
            <span class="act-ico"><i data-lucide="<?= $a['icono'] ?>"></i></span>
            <span class="act-txt"><?= $a['html'] ?><small><?= $a['sub'] ?></small></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="caja">
      <div class="caja-cabeza">
        <div class="caja-titulo"><i data-lucide="badge-check"></i>
          <div><h2>Resumen de operacion</h2><small>Indicadores del periodo actual</small></div>
        </div>
      </div>
      <div class="lista-alertas">
        <div class="alerta-item" style="border-left-color:var(--oro)">
          <span class="alerta-info"><b>Ventas totales registradas</b><span>Todos los periodos</span></span>
          <span class="alerta-nums"><b><?= (int) $pdo->query('SELECT COUNT(*) FROM ventas')->fetchColumn() ?></b></span>
        </div>
        <div class="alerta-item" style="border-left-color:var(--rojo)">
          <span class="alerta-info"><b>Ingresos acumulados</b><span>Suma de todas las ventas</span></span>
          <span class="alerta-nums"><b>S/ <?= number_format((float) $pdo->query('SELECT COALESCE(SUM(total),0) FROM ventas')->fetchColumn(), 2) ?></b></span>
        </div>
        <div class="alerta-item" style="border-left-color:#2e7d32">
          <span class="alerta-info"><b>Pedidos entregados</b><span>Estado: entregado</span></span>
          <span class="alerta-nums"><b><?= (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado='entregado'")->fetchColumn() ?></b></span>
        </div>
        <div class="alerta-item" style="border-left-color:#64b5f6">
          <span class="alerta-info"><b>Movimientos de inventario</b><span>Entradas y salidas</span></span>
          <span class="alerta-nums"><b><?= (int) $pdo->query('SELECT COUNT(*) FROM movimientos_inventario')->fetchColumn() ?></b></span>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ BENEFICIOS ============ -->
  <section class="beneficios">
    <div class="beneficio"><span class="beneficio-ico"><i data-lucide="truck"></i></span>
      <div><b>Entrega rapida</b><span>Despacho en el dia</span></div></div>
    <div class="beneficio"><span class="beneficio-ico"><i data-lucide="award"></i></span>
      <div><b>Calidad garantizada</b><span>Carnes seleccionadas</span></div></div>
    <div class="beneficio"><span class="beneficio-ico"><i data-lucide="shield-check"></i></span>
      <div><b>Higiene y seguridad</b><span>Normas sanitarias</span></div></div>
    <div class="beneficio"><span class="beneficio-ico"><i data-lucide="tag"></i></span>
      <div><b>Los mejores precios</b><span>Directo del proveedor</span></div></div>
  </section>

</main>
<?php require __DIR__ . '/../includes/panel/shell_end.php'; ?>

<script>
const SERIE7  = <?= $datosG1 ?>;
const CATS    = <?= $datosG2 ?>;
const ANIO    = <?= $datosG3 ?>;
const MESES   = <?= $mesesJs ?>;

document.addEventListener('DOMContentLoaded', () => {
  /* ---- Grafica 1: barras + linea de tendencia ---- */
  function pintar7dias(dias) {
    const ctx = document.getElementById('grafica7');
    if (!ctx || typeof Chart === 'undefined') return;
    if (ctx._chart) ctx._chart.destroy();

    // Para 30 dias se reutiliza la serie embebida expandiendo desde hoy
    let serie;
    if (dias === 7) {
      serie = SERIE7;
    } else {
      const mapa = Object.fromEntries(SERIE7.map(x => [x.fecha, x]));
      serie = serieDias(SERIE7, 30); // rellena con 0 los dias sin venta
      // la serie de 7 dias solo contiene 7; para 30 se pide al servidor:
      serie = window.SERIE30 && window.SERIE30.length ? window.SERIE30 : serieDias(SERIE7.map(x => ({fecha:x.fecha,total:x.total})), 30);
    }

    const labels = serie.map(s => s.etiqueta || s.fecha);
    const valores = serie.map(s => parseFloat(s.total));
    // Linea de tendencia: promedio movil de 3 puntos
    const tendencia = valores.map((v, i) => {
      const ini = Math.max(0, i - 2);
      const parte = valores.slice(ini, i + 1);
      return parte.reduce((a, b) => a + b, 0) / parte.length;
    });

    ctx._chart = new Chart(ctx, {
      data: {
        labels,
        datasets: [
          {
            type: 'bar', label: 'Ventas (S/)', data: valores,
            backgroundColor: (c) => {
              const g = c.chart.ctx.createLinearGradient(0, 0, 0, 300);
              g.addColorStop(0, 'rgba(213,16,25,.95)');
              g.addColorStop(1, 'rgba(133,0,0,.35)');
              return g;
            },
            borderRadius: 6, borderSkipped: false, maxBarThickness: 34,
          },
          {
            type: 'line', label: 'Tendencia', data: tendencia,
            borderColor: '#E8B83F', borderWidth: 2.5,
            pointBackgroundColor: '#E8B83F', pointRadius: 3,
            tension: .4, fill: false,
          },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top', align: 'end' },
          tooltip: { callbacks: { label: c => ` ${c.dataset.label}: S/ ${Number(c.raw).toFixed(2)}` } },
        },
        scales: { y: { beginAtZero: true, ticks: { callback: v => 'S/ ' + v } } },
      },
    });
    estiloEjes(ctx._chart);
  }
  pintar7dias(7);

  document.querySelectorAll('#filtrosG1 .filtro').forEach(b => {
    b.addEventListener('click', () => {
      document.querySelectorAll('#filtrosG1 .filtro').forEach(x => x.classList.remove('activo'));
      b.classList.add('activo');
      if (b.dataset.rango === '30') {
        // Carga real desde el servidor
        fetch('api/ventas_rango.php?dias=30', { credentials: 'same-origin' })
          .then(r => r.json())
          .then(datos => { window.SERIE30 = datos; pintar7dias(30); })
          .catch(() => pintar7dias(7));
      } else {
        pintar7dias(7);
      }
    });
  });

  /* ---- Grafica 2: doughnut por categoria ---- */
  const colores = ['#D51019', '#E8B83F', '#850000', '#ff6b6b', '#A5A5A5', '#5c5c5c', '#f39c12'];
  crearGrafica('graficaCat', {
    type: 'doughnut',
    data: {
      labels: CATS.map(c => c.nombre),
      datasets: [{
        data: CATS.map(c => parseFloat(c.total)),
        backgroundColor: colores,
        borderColor: '#171717', borderWidth: 3, hoverOffset: 10,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '64%',
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: c => ` ${c.label}: S/ ${Number(c.raw).toFixed(2)}` } },
      },
    },
  });

  /* ---- Grafica 3: ingresos mensuales ---- */
  crearGrafica('graficaMes', {
    type: 'bar',
    data: {
      labels: MESES,
      datasets: [{
        label: 'Ingresos (S/)', data: ANIO,
        backgroundColor: (c) => {
          const g = c.chart.ctx.createLinearGradient(0, 0, 0, 260);
          g.addColorStop(0, 'rgba(232,184,63,.95)');
          g.addColorStop(1, 'rgba(232,184,63,.15)');
          return g;
        },
        borderRadius: 6, maxBarThickness: 30,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: c => ' S/ ' + Number(c.raw).toFixed(2) } },
      },
      scales: { y: { beginAtZero: true, ticks: { callback: v => 'S/ ' + v } } },
    },
  });
});
</script>
