<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ---------- Parámetros / filtros ----------
$today = date('Y-m-d');
$defaultFrom = date('Y-m-01', strtotime('-5 months')); // últimos 6 meses contando el actual
$from = $_GET['from'] ?? $defaultFrom;
$to   = $_GET['to']   ?? $today;
$minStock = (int)($_GET['min_stock'] ?? 10);
if (!$from || strtotime($from) === false) $from = $defaultFrom;
if (!$to   || strtotime($to)   === false) $to   = $today;

// ---------- Exportaciones CSV ----------
if (isset($_GET['export'])) {
  $export = $_GET['export'];
  if ($export === 'pedidos_estado') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pedidos_por_estado_'.$from.'_a_'.$to.'.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Estado', 'Cantidad', 'Desde', 'Hasta']);

    $st = $conn->prepare("
      SELECT estado, COUNT(*) AS cantidad
      FROM pedidos
      WHERE fecha_pedido BETWEEN :f AND :t
      GROUP BY estado
      ORDER BY cantidad DESC
    ");
    $st->execute([':f'=>$from.' 00:00:00', ':t'=>$to.' 23:59:59']);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      fputcsv($out, [$row['estado'], $row['cantidad'], $from, $to]);
    }
    fclose($out);
    exit;
  }

  if ($export === 'pedidos_mes') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pedidos_por_mes_'.$from.'_a_'.$to.'.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Mes', 'Cantidad']);

    $st = $conn->prepare("
      SELECT DATE_FORMAT(fecha_pedido, '%Y-%m') AS mes, COUNT(*) AS cantidad
      FROM pedidos
      WHERE fecha_pedido BETWEEN :f AND :t
      GROUP BY mes
      ORDER BY mes ASC
    ");
    $st->execute([':f'=>$from.' 00:00:00', ':t'=>$to.' 23:59:59']);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      fputcsv($out, [$row['mes'], $row['cantidad']]);
    }
    fclose($out);
    exit;
  }

  if ($export === 'stock_bajo') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=productos_stock_menor_a_'.$minStock.'.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Nombre','Precio','Stock','Caducidad']);

    $st = $conn->prepare("SELECT id, nombre, precio, stock, fecha_caducidad FROM productos WHERE stock < :m ORDER BY stock ASC, nombre ASC");
    $st->execute([':m'=>$minStock]);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      fputcsv($out, [$row['id'], $row['nombre'], $row['precio'], $row['stock'], $row['fecha_caducidad']]);
    }
    fclose($out);
    exit;
  }
}

// ---------- Consultas ----------
$paramsRange = [':f'=>$from.' 00:00:00', ':t'=>$to.' 23:59:59'];

// KPIs de pedidos (en rango)
$kpis = ['total'=>0, 'pendiente'=>0, 'enviado'=>0, 'cancelado'=>0];
$st = $conn->prepare("SELECT COUNT(*) FROM pedidos WHERE fecha_pedido BETWEEN :f AND :t");
$st->execute($paramsRange);
$kpis['total'] = (int)$st->fetchColumn();

$st = $conn->prepare("SELECT estado, COUNT(*) c FROM pedidos WHERE fecha_pedido BETWEEN :f AND :t GROUP BY estado");
$st->execute($paramsRange);
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
  $estado = strtolower($r['estado']);
  if (isset($kpis[$estado])) $kpis[$estado] = (int)$r['c'];
}

// Productos (totales generales y bajo stock)
$totalProductos = (int)$conn->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$st = $conn->prepare("SELECT COUNT(*) FROM productos WHERE stock < :m");
$st->execute([':m'=>$minStock]);
$stockBajo = (int)$st->fetchColumn();

// Usuarios por rol
$usuariosPorRol = $conn->query("
  SELECT r.nombre AS rol, COUNT(u.id) AS cantidad
  FROM usuarios u
  JOIN roles r ON u.rol_id = r.id
  GROUP BY r.nombre
  ORDER BY cantidad DESC, r.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Pedidos por estado (en rango)
$estadisticas = $conn->prepare("
  SELECT estado, COUNT(*) AS cantidad
  FROM pedidos
  WHERE fecha_pedido BETWEEN :f AND :t
  GROUP BY estado
  ORDER BY cantidad DESC
");
$estadisticas->execute($paramsRange);
$estadisticas = $estadisticas->fetchAll(PDO::FETCH_ASSOC);

// Pedidos por mes (en rango)
$pedidosMes = $conn->prepare("
  SELECT DATE_FORMAT(fecha_pedido, '%Y-%m') AS mes, COUNT(*) AS cantidad
  FROM pedidos
  WHERE fecha_pedido BETWEEN :f AND :t
  GROUP BY mes
  ORDER BY mes ASC
");
$pedidosMes->execute($paramsRange);
$pedidosMes = $pedidosMes->fetchAll(PDO::FETCH_ASSOC);

// Top productos con stock más bajo
$lowStockList = $conn->prepare("
  SELECT id, nombre, stock, precio, fecha_caducidad
  FROM productos
  WHERE stock < :m
  ORDER BY stock ASC, nombre ASC
  LIMIT 10
");
$lowStockList->execute([':m'=>$minStock]);
$lowStockList = $lowStockList->fetchAll(PDO::FETCH_ASSOC);

// Intento opcional: Top productos más pedidos (si existe pedido_detalles)
$topVendidos = [];
try {
  $q = $conn->prepare("
    SELECT p.nombre, SUM(d.cantidad) as total
    FROM pedido_detalles d
    JOIN productos p ON d.producto_id = p.id
    JOIN pedidos pe ON pe.id = d.pedido_id
    WHERE pe.fecha_pedido BETWEEN :f AND :t
    GROUP BY p.nombre
    ORDER BY total DESC
    LIMIT 5
  ");
  $q->execute($paramsRange);
  $topVendidos = $q->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {
  // si no existe la tabla, simplemente no mostramos esta sección
}

$nombre = $_SESSION['nombre'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reportes — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b;
      --fs-primary:#2563eb; --fs-primary-600:#1d4ed8; --fs-accent:#10b981;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    body{ font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:var(--fs-bg); color:var(--fs-text); }
    .fs-card{ border-radius:14px; background:var(--fs-surface); border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }
    .kpi{ border-radius:14px; background:var(--fs-surface); border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); padding:18px; }
    .kpi h6{ margin-bottom:6px; color:var(--fs-dim); }
    .kpi .num{ font-size: clamp(22px, 2vw + 8px, 28px); font-weight: 800; }
    .btn-fs-primary{ --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary); --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600); --bs-btn-color:#fff; border-radius:12px; font-weight:600; }
    .toolbar .btn{ border-radius:10px; }
    .table thead th{ background:#eff6ff; }
    @media (max-width: 575.98px){
      .kpi-row > *{ flex: 0 0 50%; max-width: 50%; }
    }
  </style>
</head>
<body>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Reportes</h2>
    <div class="d-flex gap-2">
      <a href="admin.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
      <span class="badge text-bg-light d-none d-md-inline">Hola, <?= h($nombre) ?></span>
      <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
    </div>
  </div>

  <!-- Filtros -->
  <div class="fs-card p-3 mb-3">
    <form class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <label class="form-label">Desde</label>
        <input type="date" name="from" value="<?= h($from) ?>" class="form-control">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" name="to" value="<?= h($to) ?>" class="form-control">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Umbral stock bajo</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-box-seam"></i></span>
          <input type="number" name="min_stock" value="<?= h($minStock) ?>" min="1" class="form-control">
        </div>
      </div>
      <div class="col-6 col-md-3 d-grid">
        <button class="btn btn-fs-primary"><i class="bi bi-funnel"></i> Aplicar</button>
      </div>
    </form>
  </div>

  <!-- KPIs -->
  <div class="row g-3 kpi-row mb-3">
    <div class="col-6 col-md-3"><div class="kpi"><h6>Total pedidos</h6><div class="num"><?= $kpis['total'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><h6>Pendientes</h6><div class="num"><?= $kpis['pendiente'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><h6>Enviados</h6><div class="num"><?= $kpis['enviado'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><h6>Cancelados</h6><div class="num"><?= $kpis['cancelado'] ?></div></div></div>
  </div>

  <!-- Acciones rápidas -->
  <div class="toolbar d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-primary btn-sm" href="?from=<?= h($from) ?>&to=<?= h($to) ?>&min_stock=<?= h($minStock) ?>&export=pedidos_estado"><i class="bi bi-download"></i> Exportar pedidos por estado (CSV)</a>
    <a class="btn btn-outline-primary btn-sm" href="?from=<?= h($from) ?>&to=<?= h($to) ?>&min_stock=<?= h($minStock) ?>&export=pedidos_mes"><i class="bi bi-download"></i> Exportar pedidos por mes (CSV)</a>
    <a class="btn btn-outline-primary btn-sm" href="?from=<?= h($from) ?>&to=<?= h($to) ?>&min_stock=<?= h($minStock) ?>&export=stock_bajo"><i class="bi bi-download"></i> Exportar stock bajo (CSV)</a>
  </div>

  <!-- Gráficos -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-4">
      <div class="fs-card p-3 h-100">
        <h6 class="mb-3"><i class="bi bi-pie-chart"></i> Pedidos por estado</h6>
        <canvas id="chartEstado" height="220"></canvas>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="fs-card p-3 h-100">
        <h6 class="mb-3"><i class="bi bi-bar-chart"></i> Usuarios por rol</h6>
        <canvas id="chartRoles" height="220"></canvas>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="fs-card p-3 h-100">
        <h6 class="mb-3"><i class="bi bi-activity"></i> Pedidos por mes</h6>
        <canvas id="chartMes" height="220"></canvas>
      </div>
    </div>
  </div>

  <!-- Tablas -->
  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="fs-card p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0"><i class="bi bi-boxes"></i> Resumen de productos</h6>
          <span class="small text-secondary">Total: <strong><?= $totalProductos ?></strong> | Stock &lt; <?= h($minStock) ?>: <strong class="text-danger"><?= $stockBajo ?></strong></span>
        </div>
        <?php if (empty($lowStockList)): ?>
          <div class="alert alert-info mb-0">No hay productos con stock por debajo de <?= h($minStock) ?>.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>Producto</th><th class="text-end">Stock</th><th class="text-end">Precio</th><th>Caducidad</th></tr></thead>
              <tbody>
                <?php foreach ($lowStockList as $p): ?>
                  <tr>
                    <td><?= h($p['nombre']) ?></td>
                    <td class="text-end"><span class="badge text-bg-warning"><?= (int)$p['stock'] ?></span></td>
                    <td class="text-end">$<?= number_format((float)$p['precio'], 0, ',', '.') ?></td>
                    <td><?= h($p['fecha_caducidad']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="fs-card p-3">
        <h6 class="mb-2"><i class="bi bi-trophy"></i> Top productos más vendidos (rango)</h6>
        <?php if (empty($topVendidos)): ?>
          <div class="alert alert-light border mb-0">Aún no hay datos (o no existe la tabla de detalles de pedidos).</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($topVendidos as $t): ?>
              <li class="list-group-item d-flex justify-content-between">
                <span><?= h($t['nombre']) ?></span>
                <strong><?= (int)$t['total'] ?></strong>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  // Datos desde PHP
  const estados = <?= json_encode(array_map(fn($r)=>$r['estado'], $estadisticas), JSON_UNESCAPED_UNICODE) ?>;
  const estadosValores = <?= json_encode(array_map(fn($r)=>(int)$r['cantidad'], $estadisticas)) ?>;

  const roles = <?= json_encode(array_map(fn($r)=>$r['rol'], $usuariosPorRol), JSON_UNESCAPED_UNICODE) ?>;
  const rolesValores = <?= json_encode(array_map(fn($r)=>(int)$r['cantidad'], $usuariosPorRol)) ?>;

  const meses = <?= json_encode(array_map(fn($r)=>$r['mes'], $pedidosMes), JSON_UNESCAPED_UNICODE) ?>;
  const mesesValores = <?= json_encode(array_map(fn($r)=>(int)$r['cantidad'], $pedidosMes)) ?>;

  // Utilidad para colores suaves
  function pastel(n){
    const base = [ 'rgba(37,99,235,0.85)','rgba(16,185,129,0.85)','rgba(234,179,8,0.85)','rgba(239,68,68,0.85)','rgba(99,102,241,0.85)' ];
    const b = [], l = base.length;
    for (let i=0;i<n;i++) b.push(base[i%l]);
    return b;
  }

  // Doughnut: Estado
  new Chart(document.getElementById('chartEstado'), {
    type: 'doughnut',
    data: {
      labels: estados.length ? estados : ['Sin datos'],
      datasets: [{ data: estadosValores.length ? estadosValores : [1], backgroundColor: pastel(estadosValores.length||1) }]
    },
    options: {
      plugins: { legend: { position: 'bottom' } }
    }
  });

  // Bar: Roles
  new Chart(document.getElementById('chartRoles'), {
    type: 'bar',
    data: {
      labels: roles.length ? roles : ['Sin datos'],
      datasets: [{ label:'Usuarios', data: rolesValores.length ? rolesValores : [0], backgroundColor: pastel(rolesValores.length||1) }]
    },
    options: {
      scales: { y: { beginAtZero:true } },
      plugins: { legend: { display:false } }
    }
  });

  // Line: Meses
  new Chart(document.getElementById('chartMes'), {
    type: 'line',
    data: {
      labels: meses.length ? meses : ['Sin datos'],
      datasets: [{
        label:'Pedidos',
        data: mesesValores.length ? mesesValores : [0],
        tension:.35,
        fill:false,
        borderColor:'rgba(37,99,235,0.9)',
        pointRadius:3
      }]
    },
    options: {
      scales: { y: { beginAtZero:true } },
      plugins: { legend: { display:true } }
    }
  });
</script>
</body>
</html>



