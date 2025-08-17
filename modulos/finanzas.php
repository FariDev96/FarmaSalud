<?php
// módulos/finanzas.php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo admins
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
  header("Location: login.php");
  exit;
}

/* ============ Helpers ============ */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n){ return '$'.number_format((float)$n, 0, ',', '.'); }

function tableExists(PDO $conn, string $t): bool {
  $q = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $q->execute([$t]);
  return (int)$q->fetchColumn() > 0;
}
function colExists(PDO $conn, string $t, string $c): bool {
  $q = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $q->execute([$t,$c]);
  return (int)$q->fetchColumn() > 0;
}

/* ============ Detección de fuente de datos ============ */
$hasVentas          = tableExists($conn,'ventas');
$ventasTotalCol     = $hasVentas && colExists($conn,'ventas','total') ? 'total'
                     : ($hasVentas && colExists($conn,'ventas','monto') ? 'monto' : null);
$ventasFechaCol     = $hasVentas && colExists($conn,'ventas','fecha') ? 'fecha'
                     : ($hasVentas && colExists($conn,'ventas','fecha_venta') ? 'fecha_venta' : null);
$ventasProdCol      = null;
foreach (['producto_id','id_producto','product_id'] as $c) {
  if ($hasVentas && colExists($conn,'ventas',$c)) { $ventasProdCol = $c; break; }
}

$hasDetalles        = tableExists($conn,'pedido_detalles');
$detallesCant       = $hasDetalles && colExists($conn,'pedido_detalles','cantidad');
$detallesPrecioU    = $hasDetalles && colExists($conn,'pedido_detalles','precio_unitario');
$detallesPedidoId   = $hasDetalles && colExists($conn,'pedido_detalles','pedido_id');
// columna de producto (id o nombre) flexible en detalles
$detallesProdCol    = null;
if ($hasDetalles) {
  foreach (['producto_id','id_producto','product_id','producto','producto_nombre','nombre_producto'] as $c) {
    if (colExists($conn,'pedido_detalles',$c)) { $detallesProdCol = $c; break; }
  }
}

$hasPedidos         = tableExists($conn,'pedidos');
$pedidosFechaCol    = $hasPedidos && colExists($conn,'pedidos','fecha_pedido') ? 'fecha_pedido' : null;

$hasProductos       = tableExists($conn,'productos');
// nombre en productos (intenta varios)
$productosNombreCol = null;
if ($hasProductos) {
  foreach (['nombre','descripcion','titulo'] as $c) {
    if (colExists($conn,'productos',$c)) { $productosNombreCol = $c; break; }
  }
}
// clave primaria en productos (para join por id si existiera)
$productosKeyCol = null;
if ($hasProductos) {
  foreach (['id','producto_id'] as $c) {
    if (colExists($conn,'productos',$c)) { $productosKeyCol = $c; break; }
  }
}

$SOURCE = 'none';
$sourceNote = 'Sin datos: faltan tablas requeridas.';

if ($ventasTotalCol) {
  $SOURCE = 'ventas';
  $sourceNote = "Usando ventas($ventasTotalCol".($ventasFechaCol? ", $ventasFechaCol":"").")";
} elseif ($detallesCant && $detallesPrecioU && $detallesPedidoId && $pedidosFechaCol) {
  $SOURCE = 'detalles';
  $sourceNote = "Usando pedido_detalles(cantidad, precio_unitario) + pedidos($pedidosFechaCol)";
}

/* ============ Parámetros de rango ============ */
$range = (int)($_GET['range'] ?? 30);
if (!in_array($range, [7,30,90], true)) $range = 30;

$end = (new DateTime('today'))->format('Y-m-d');
$start = (new DateTime("-".($range-1)." days"))->format('Y-m-d');

$monthStart = (new DateTime('first day of this month'))->format('Y-m-d');
$monthEnd   = (new DateTime('last day of this month'))->format('Y-m-d');

/* ============ Export CSV si corresponde ============ */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=finanzas_'.$range.'dias.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['fecha','ingresos']);

  if ($SOURCE === 'ventas' && $ventasFechaCol) {
    $sql = "SELECT DATE($ventasFechaCol) d, SUM($ventasTotalCol) s
            FROM ventas
            WHERE DATE($ventasFechaCol) BETWEEN :s AND :e
            GROUP BY DATE($ventasFechaCol) ORDER BY d";
    $st = $conn->prepare($sql);
    $st->execute([':s'=>$start, ':e'=>$end]);
    while($r = $st->fetch(PDO::FETCH_ASSOC)){ fputcsv($out, [$r['d'], (float)$r['s']]); }
  } elseif ($SOURCE === 'detalles') {
    $sql = "SELECT DATE(p.$pedidosFechaCol) d, SUM(d.cantidad * d.precio_unitario) s
            FROM pedido_detalles d
            JOIN pedidos p ON p.id = d.pedido_id
            WHERE DATE(p.$pedidosFechaCol) BETWEEN :s AND :e
            GROUP BY DATE(p.$pedidosFechaCol) ORDER BY d";
    $st = $conn->prepare($sql);
    $st->execute([':s'=>$start, ':e'=>$end]);
    while($r = $st->fetch(PDO::FETCH_ASSOC)){ fputcsv($out, [$r['d'], (float)$r['s']]); }
  }
  fclose($out);
  exit;
}

/* ============ KPI: ingresos hoy y del mes ============ */
$ingresosHoy = 0; $ingresosMes = 0;

try {
  if ($SOURCE === 'ventas') {
    if ($ventasFechaCol) {
      $st = $conn->prepare("SELECT COALESCE(SUM($ventasTotalCol),0) FROM ventas WHERE DATE($ventasFechaCol)=CURDATE()");
      $st->execute(); $ingresosHoy = (float)$st->fetchColumn();
      $st = $conn->prepare("SELECT COALESCE(SUM($ventasTotalCol),0) FROM ventas WHERE DATE($ventasFechaCol) BETWEEN :ms AND :me");
      $st->execute([':ms'=>$monthStart, ':me'=>$monthEnd]);
      $ingresosMes = (float)$st->fetchColumn();
    }
  } elseif ($SOURCE === 'detalles') {
    $st = $conn->prepare("SELECT COALESCE(SUM(d.cantidad * d.precio_unitario),0)
                          FROM pedido_detalles d
                          JOIN pedidos p ON p.id = d.pedido_id
                          WHERE DATE(p.$pedidosFechaCol)=CURDATE()");
    $st->execute(); $ingresosHoy = (float)$st->fetchColumn();

    $st = $conn->prepare("SELECT COALESCE(SUM(d.cantidad * d.precio_unitario),0)
                          FROM pedido_detalles d
                          JOIN pedidos p ON p.id = d.pedido_id
                          WHERE DATE(p.$pedidosFechaCol) BETWEEN :ms AND :me");
    $st->execute([':ms'=>$monthStart, ':me'=>$monthEnd]);
    $ingresosMes = (float)$st->fetchColumn();
  }
} catch(Throwable $e){ /* deja 0 */ }

/* ============ Serie (últimos N días) ============ */
$labels = []; $map = [];
for ($i=$range-1; $i>=0; $i--){
  $d = (new DateTime("-$i days"))->format('Y-m-d');
  $labels[] = (new DateTime($d))->format('d/m');
  $map[$d] = 0.0;
}

try {
  if ($SOURCE === 'ventas' && $ventasFechaCol) {
    $sql = "SELECT DATE($ventasFechaCol) d, SUM($ventasTotalCol) s
            FROM ventas
            WHERE DATE($ventasFechaCol) BETWEEN :s AND :e
            GROUP BY DATE($ventasFechaCol) ORDER BY d";
    $st = $conn->prepare($sql); $st->execute([':s'=>$start, ':e'=>$end]);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){ $map[$r['d']] = (float)$r['s']; }
  } elseif ($SOURCE === 'detalles') {
    $sql = "SELECT DATE(p.$pedidosFechaCol) d, SUM(d.cantidad * d.precio_unitario) s
            FROM pedido_detalles d
            JOIN pedidos p ON p.id = d.pedido_id
            WHERE DATE(p.$pedidosFechaCol) BETWEEN :s AND :e
            GROUP BY DATE(p.$pedidosFechaCol) ORDER BY d";
    $st = $conn->prepare($sql); $st->execute([':s'=>$start, ':e'=>$end]);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){ $map[$r['d']] = (float)$r['s']; }
  }
} catch(Throwable $e){ /* zeros */ }

$series = array_values($map);

/* ============ Top productos (mes) ============ */
$topProductos = [];
try {
  if ($SOURCE === 'detalles' && $detallesProdCol) {

    // ¿parece id? (unible por clave a productos)
    $detallesEsID = in_array($detallesProdCol, ['producto_id','id_producto','product_id'], true);

    if ($detallesEsID && $hasProductos && $productosKeyCol && $productosNombreCol) {
      // Join por id
      $sql = "SELECT pr.$productosNombreCol AS nombre,
                     SUM(d.cantidad) cant,
                     SUM(d.cantidad * d.precio_unitario) total
              FROM pedido_detalles d
              JOIN pedidos p ON p.id = d.pedido_id
              JOIN productos pr ON pr.$productosKeyCol = d.$detallesProdCol
              WHERE DATE(p.$pedidosFechaCol) BETWEEN :ms AND :me
              GROUP BY pr.$productosNombreCol
              ORDER BY total DESC
              LIMIT 8";

    } elseif (!$detallesEsID && $hasProductos && $productosNombreCol) {
      // Join por nombre (cuando el detalle guarda el nombre del producto)
      $sql = "SELECT COALESCE(pr.$productosNombreCol, d.$detallesProdCol) AS nombre,
                     SUM(d.cantidad) cant,
                     SUM(d.cantidad * d.precio_unitario) total
              FROM pedido_detalles d
              JOIN pedidos p ON p.id = d.pedido_id
              LEFT JOIN productos pr ON pr.$productosNombreCol = d.$detallesProdCol
              WHERE DATE(p.$pedidosFechaCol) BETWEEN :ms AND :me
              GROUP BY COALESCE(pr.$productosNombreCol, d.$detallesProdCol)
              ORDER BY total DESC
              LIMIT 8";

    } else {
      // Sin tabla productos: agrupar directo por la columna del detalle
      $sql = "SELECT d.$detallesProdCol AS nombre,
                     SUM(d.cantidad) cant,
                     SUM(d.cantidad * d.precio_unitario) total
              FROM pedido_detalles d
              JOIN pedidos p ON p.id = d.pedido_id
              WHERE DATE(p.$pedidosFechaCol) BETWEEN :ms AND :me
              GROUP BY d.$detallesProdCol
              ORDER BY total DESC
              LIMIT 8";
    }

    $st = $conn->prepare($sql);
    $st->execute([':ms'=>$monthStart, ':me'=>$monthEnd]);
    $topProductos = $st->fetchAll(PDO::FETCH_ASSOC);

  } elseif ($SOURCE === 'ventas' && $ventasProdCol) {
    // Fallback si el desglose está en ventas
    if ($hasProductos && $productosKeyCol && $productosNombreCol && $ventasFechaCol && $ventasTotalCol) {
      $sql = "SELECT pr.$productosNombreCol AS nombre,
                     COUNT(v.$ventasProdCol) cant,
                     SUM(v.$ventasTotalCol) total
              FROM ventas v
              JOIN productos pr ON pr.$productosKeyCol = v.$ventasProdCol
              WHERE DATE(v.$ventasFechaCol) BETWEEN :ms AND :me
              GROUP BY pr.$productosNombreCol
              ORDER BY total DESC
              LIMIT 8";
      $st = $conn->prepare($sql);
      $st->execute([':ms'=>$monthStart, ':me'=>$monthEnd]);
      $topProductos = $st->fetchAll(PDO::FETCH_ASSOC);
    }
  }
} catch(Throwable $e){ $topProductos = []; }

$nombre = $_SESSION['nombre'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Finanzas — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">

  <!-- Inter + Bootstrap + Icons + Chart.js -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b;
      --fs-primary:#2563eb; --fs-primary-600:#1d4ed8; --fs-accent:#10b981;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    body{
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
      background: var(--fs-bg);
      color:var(--fs-text);
      letter-spacing:.2px;
    }
    .fs-navbar{ background:var(--fs-primary); }
    .fs-brand{ color:#fff; font-weight:700; }
    .card-fs{ background:var(--fs-surface); border:1px solid var(--fs-border); border-radius:16px; box-shadow:var(--fs-shadow); }
    .kpi{ padding:16px; border-radius:16px; border:1px solid var(--fs-border); background:var(--fs-surface); box-shadow:var(--fs-shadow); }
    .kpi h6{ margin:0; color:var(--fs-dim); font-weight:600; }
    .kpi b{ font-size:clamp(20px,2.6vw,28px); }
    .chart-wrap{ height: clamp(260px, 36vh, 380px); }
    .btn-chip{ border-radius:999px; }
    .table-sm td, .table-sm th{ padding:.55rem .6rem; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg fs-navbar py-3">
  <div class="container-fluid">
    <a href="admin.php" class="btn btn-light btn-sm me-2"><i class="bi bi-arrow-left"></i> Volver</a>
    <span class="navbar-brand fs-brand">Finanzas</span>
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="text-white small d-none d-sm-inline">Hola, <strong><?= h($nombre) ?></strong></span>
      <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-lock"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>

<main class="container py-4">

  <!-- KPIs -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <div class="kpi h-100">
        <h6><i class="bi bi-lightning-charge"></i> Ingresos hoy</h6>
        <b><?= money($ingresosHoy) ?></b>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="kpi h-100">
        <h6><i class="bi bi-calendar3"></i> Ingresos del mes</h6>
        <b><?= money($ingresosMes) ?></b>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="kpi h-100 d-flex justify-content-between align-items-center">
        <div>
          <h6 class="mb-1"><i class="bi bi-database"></i> Fuente de datos</h6>
          <div class="text-secondary small"><?= h($sourceNote) ?></div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-primary btn-chip btn-sm" href="?range=7">7 días</a>
          <a class="btn btn-outline-primary btn-chip btn-sm" href="?range=30">30 días</a>
          <a class="btn btn-outline-primary btn-chip btn-sm" href="?range=90">90 días</a>
          <a class="btn btn-primary btn-sm" href="?range=<?= $range ?>&export=csv"><i class="bi bi-download"></i> CSV</a>
        </div>
      </div>
    </div>
  </div>

  <!-- GRID: Chart + Top productos -->
  <div class="row g-3">
    <div class="col-12 col-lg-8">
      <div class="card-fs p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0"><i class="bi bi-graph-up"></i> Ingresos últimos <?= (int)$range ?> días</h6>
          <div class="text-secondary small">
            <?= h((new DateTime($start))->format('d/m/Y')) ?> – <?= h((new DateTime($end))->format('d/m/Y')) ?>
          </div>
        </div>
        <div class="chart-wrap">
          <canvas id="chartIngresos" role="img" aria-label="Ingresos últimos <?= (int)$range ?> días"></canvas>
        </div>
        <?php if ($SOURCE==='none'): ?>
          <div class="alert alert-warning mt-3 mb-0 small">
            Para ver datos, crea la tabla <code>ventas(total, fecha)</code> o usa <code>pedido_detalles(cantidad, precio_unitario)</code> + <code>pedidos(fecha_pedido)</code>.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card-fs p-3 h-100">
        <h6 class="mb-2"><i class="bi bi-trophy"></i> Top productos (mes)</h6>
        <?php if (!$topProductos): ?>
          <div class="text-secondary small">No hay datos para el rango seleccionado.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Producto</th>
                  <th class="text-end">Cant.</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($topProductos as $tp): ?>
                  <tr>
                    <td><?= h($tp['nombre']) ?></td>
                    <td class="text-end"><?= (int)$tp['cant'] ?></td>
                    <td class="text-end"><?= money($tp['total']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</main>

<script>
const labels = <?= json_encode($labels) ?>;
const data   = <?= json_encode(array_map('floatval',$series)) ?>;

const ctx = document.getElementById('chartIngresos');
const grad = (() => {
  const g = ctx.getContext('2d').createLinearGradient(0,0,0,ctx.parentElement.clientHeight);
  g.addColorStop(0,'rgba(37,99,235,.28)');
  g.addColorStop(1,'rgba(37,99,235,0)');
  return g;
})();

new Chart(ctx, {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Ingresos',
      data,
      fill: true,
      backgroundColor: grad,
      borderColor: 'rgba(37,99,235,1)',
      borderWidth: 2,
      tension: .35,
      pointRadius: 2.5,
      pointHoverRadius: 5
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display:false },
      tooltip: {
        callbacks:{
          label: (ctx) => {
            const v = ctx.parsed.y || 0;
            return ' ' + new Intl.NumberFormat('es-CO').format(v);
          }
        }
      }
    },
    scales: {
      x: { grid:{ display:false } },
      y: {
        beginAtZero:true,
        grid:{ color:'rgba(2,6,23,.06)' },
        ticks:{
          callback: (v) => new Intl.NumberFormat('es-CO',{notation:'compact'}).format(v)
        }
      }
    }
  }
});
</script>
</body>
</html>



