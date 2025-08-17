<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo Farmacéutico
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 4) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ------- Filtros -------
$q       = trim($_GET['q']   ?? '');
$tipo    = trim($_GET['tipo']?? '');                // entrada | salida | (vacío=todos)
$desde   = trim($_GET['d1']  ?? '');
$hasta   = trim($_GET['d2']  ?? '');
$perPage = max(5, min(50, (int)($_GET['pp'] ?? 15)));
$page    = max(1, (int)($_GET['p'] ?? 1));

// Armar WHERE
$where = []; $params = [];
if ($q !== '') {
  $where[] = "(p.nombre LIKE ? OR u.nombre LIKE ? OR m.motivo LIKE ?)";
  $like = "%$q%"; array_push($params, $like, $like, $like);
}
if ($tipo === 'entrada' || $tipo === 'salida') {
  $where[] = "m.tipo = ?"; $params[] = $tipo;
}
if ($desde !== '') { $where[] = "DATE(m.fecha) >= ?"; $params[] = $desde; }
if ($hasta !== '') { $where[] = "DATE(m.fecha) <= ?"; $params[] = $hasta; }
$wsql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

// ------- Export CSV -------
if (isset($_GET['export']) && $_GET['export'] === '1') {
  $sqlExp = "SELECT p.nombre AS producto, m.cantidad, m.motivo, m.tipo, m.fecha, u.nombre AS responsable
             FROM movimientos_stock m
             JOIN productos p ON m.producto_id=p.id
             JOIN usuarios u ON m.usuario_id=u.id
             $wsql
             ORDER BY m.fecha DESC";
  $st = $conn->prepare($sqlExp); $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="movimientos_stock.csv"');
  $out = fopen('php://output','w');
  fprintf($out, "\xEF\xBB\xBF"); // BOM UTF-8
  fputcsv($out, ['Producto','Cantidad','Motivo','Tipo','Fecha','Responsable']);
  foreach($rows as $r){
    fputcsv($out, [
      $r['producto'], $r['cantidad'], $r['motivo'],
      ucfirst($r['tipo']), $r['fecha'], $r['responsable']
    ]);
  }
  fclose($out);
  exit;
}

// ------- Conteo total para paginar -------
$sqlCount = "SELECT COUNT(*) FROM movimientos_stock m
             JOIN productos p ON m.producto_id=p.id
             JOIN usuarios u ON m.usuario_id=u.id
             $wsql";
$stc = $conn->prepare($sqlCount); $stc->execute($params);
$total = (int)$stc->fetchColumn();
$pages = max(1, (int)ceil($total/$perPage));
$page  = min($page, $pages);
$off   = ($page-1)*$perPage;

// ------- Query principal -------
$sql = "SELECT m.id, p.nombre AS producto, m.cantidad, COALESCE(m.motivo,'') AS motivo,
               m.tipo, m.fecha, u.nombre AS responsable
        FROM movimientos_stock m
        JOIN productos p ON m.producto_id = p.id
        JOIN usuarios u ON m.usuario_id = u.id
        $wsql
        ORDER BY m.fecha DESC
        LIMIT $perPage OFFSET $off";
$stm = $conn->prepare($sql); $stm->execute($params);
$movimientos = $stm->fetchAll(PDO::FETCH_ASSOC);

// ------- Totales del rango (entradas/salidas) -------
$sqlAgg = "SELECT
             SUM(CASE WHEN m.tipo='entrada' THEN m.cantidad ELSE 0 END) AS entradas,
             SUM(CASE WHEN m.tipo='salida'  THEN m.cantidad ELSE 0 END) AS salidas
           FROM movimientos_stock m
           JOIN productos p ON m.producto_id=p.id
           JOIN usuarios u ON m.usuario_id=u.id
           $wsql";
$sta = $conn->prepare($sqlAgg); $sta->execute($params);
$agg = $sta->fetch(PDO::FETCH_ASSOC) ?: ['entradas'=>0,'salidas'=>0];

// Helper para mantener querystring
function qs(array $extra=[]){
  $q = array_merge($_GET, $extra);
  return '?'.http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Movimientos de Stock — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Inter + Bootstrap + Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b; --fs-primary:#2563eb;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    body{ font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:var(--fs-bg); color:var(--fs-text); }
    .card-fs{ border:1px solid var(--fs-border); border-radius:var(--fs-radius); background:var(--fs-surface); box-shadow:var(--fs-shadow); }
    .kpi{ border:1px solid var(--fs-border); border-radius:14px; padding:12px; background:#fff; }
    .kpi .num{ font-size:clamp(20px,3.2vw,28px); font-weight:700; }
    .table thead th{ background:#f8fafc; }
    .badge-pill{ border-radius:999px; padding:.45rem .7rem; }
  </style>
</head>
<body>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><i class="bi bi-clipboard2-data"></i> Historial de movimientos</h2>
    <div class="d-flex gap-2">
      <a href="farmaceutico.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house"></i> Panel</a>
      <a href="<?= qs(['export'=>1]) ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-csv"></i> Exportar CSV</a>
      <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
      <a href="logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="kpi text-center">
        <div class="text-secondary">Entradas</div>
        <div class="num text-success"><?= (int)$agg['entradas'] ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="kpi text-center">
        <div class="text-secondary">Salidas</div>
        <div class="num text-danger"><?= (int)$agg['salidas'] ?></div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="kpi">
        <div class="small text-secondary mb-1">Resumen del filtro</div>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge bg-light text-dark border"><?= $q!=='' ? 'Búsqueda: '.h($q) : 'Sin búsqueda' ?></span>
          <span class="badge bg-light text-dark border"><?= $tipo!=='' ? 'Tipo: '.ucfirst($tipo) : 'Tipo: Todos' ?></span>
          <span class="badge bg-light text-dark border"><?= $desde!=='' ? 'Desde: '.h($desde) : 'Desde: —' ?></span>
          <span class="badge bg-light text-dark border"><?= $hasta!=='' ? 'Hasta: '.h($hasta) : 'Hasta: —' ?></span>
          <span class="badge bg-light text-dark border">Resultados: <?= $total ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <form class="card-fs p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label">Buscar</label>
        <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Producto, responsable o motivo">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select">
          <option value="">Todos</option>
          <option value="entrada" <?= $tipo==='entrada'?'selected':'' ?>>Entrada</option>
          <option value="salida"  <?= $tipo==='salida'?'selected':'' ?>>Salida</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" name="d1" value="<?= h($desde) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" name="d2" value="<?= h($hasta) ?>">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label">Por pág.</label>
        <select name="pp" class="form-select">
          <?php foreach([5,10,15,20,30,50] as $n): ?>
            <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-1 d-grid">
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Aplicar</button>
      </div>
    </div>
  </form>

  <!-- Tabla -->
  <div class="card-fs p-3">
    <?php if (!$movimientos): ?>
      <div class="alert alert-info mb-0">No hay movimientos registrados con los filtros actuales.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Fecha</th>
              <th>Producto</th>
              <th class="text-center">Cantidad</th>
              <th>Motivo</th>
              <th>Tipo</th>
              <th>Responsable</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($movimientos as $m): ?>
              <tr>
                <td class="text-nowrap"><?= h($m['fecha']) ?></td>
                <td><?= h($m['producto']) ?></td>
                <td class="text-center"><strong><?= (int)$m['cantidad'] ?></strong></td>
                <td><?= $m['motivo'] !== '' ? h($m['motivo']) : '<span class="text-secondary">—</span>' ?></td>
                <td>
                  <?php if ($m['tipo'] === 'entrada'): ?>
                    <span class="badge badge-pill bg-success">Entrada</span>
                  <?php else: ?>
                    <span class="badge badge-pill bg-danger">Salida</span>
                  <?php endif; ?>
                </td>
                <td><?= h($m['responsable']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php
            $qs = $_GET; unset($qs['p']);
            $base = '?'.http_build_query($qs);
            for ($i=1; $i<=$pages; $i++):
          ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
              <a class="page-link" href="<?= $base.'&p='.$i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

</body>
</html>


