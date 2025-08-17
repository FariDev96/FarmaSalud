<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array((int)$_SESSION['rol_id'], [1,4], true)) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ====== Parámetros ======
$buscar      = trim($_GET['buscar'] ?? '');
$orden       = $_GET['orden'] ?? 'nombre';
$dir         = strtoupper($_GET['dir'] ?? 'ASC');
$umbral      = (int)($_GET['umbral'] ?? 10);
$stock_bajo  = isset($_GET['stock_bajo']);
$caduca_en   = (int)($_GET['caduca_en'] ?? 0); // días; 0 = no filtrar

$orden_permitido = ['nombre','stock','precio','fecha_caducidad'];
if (!in_array($orden, $orden_permitido, true)) $orden = 'nombre';
$dir = ($dir === 'DESC') ? 'DESC' : 'ASC';
if ($umbral < 0) $umbral = 0;

// ====== Construir SQL (filtros) ======
$where  = [];
$params = [];

if ($buscar !== '') {
  $where[] = "nombre LIKE ?";
  $params[] = "%$buscar%";
}
if ($stock_bajo) {
  $where[] = "stock < ?";
  $params[] = $umbral;
}
if ($caduca_en > 0) {
  // IMPORTANTE: no usar INTERVAL ? DAY; calculamos la fecha en PHP
  $hasta = (new DateTime('today'))->modify("+$caduca_en days")->format('Y-m-d');
  $where[] = "fecha_caducidad IS NOT NULL AND fecha_caducidad BETWEEN CURDATE() AND ?";
  $params[] = $hasta;
}

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// ====== Consulta principal ======
$sql = "SELECT id, nombre, precio, stock, fecha_caducidad FROM productos $whereSql ORDER BY $orden $dir";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====== Export CSV inline ======
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=inventario.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID','Nombre','Precio','Stock','Fecha caducidad']);
  foreach ($productos as $p) {
    fputcsv($out, [$p['id'], $p['nombre'], $p['precio'], $p['stock'], $p['fecha_caducidad']]);
  }
  fclose($out);
  exit;
}

// ====== Métricas (conteos) ======
$baseWhere  = $where;
$baseParams = $params;

// stock bajos
$wLow = $baseWhere; $pLow = $baseParams;
$wLow[] = "stock < ?";
$pLow[] = $umbral;
$sqlLow = "SELECT COUNT(*) FROM productos ".($wLow ? 'WHERE '.implode(' AND ', $wLow) : '');
$low = $conn->prepare($sqlLow); $low->execute($pLow);
$conteo_bajos = (int)$low->fetchColumn();

// próximos a caducar (30 días)
$wExp = $baseWhere; $pExp = $baseParams;
$hasta30 = (new DateTime('today'))->modify('+30 days')->format('Y-m-d');
$wExp[] = "fecha_caducidad IS NOT NULL AND fecha_caducidad BETWEEN CURDATE() AND ?";
$pExp[] = $hasta30;
$sqlExp = "SELECT COUNT(*) FROM productos ".($wExp ? 'WHERE '.implode(' AND ', $wExp) : '');
$exp = $conn->prepare($sqlExp); $exp->execute($pExp);
$conteo_proximos = (int)$exp->fetchColumn();

$total = count($productos);

// ====== Helpers ======
function qkeep(array $extra = []){
  $keep = $_GET;
  foreach ($extra as $k=>$v){
    if ($v===null) unset($keep[$k]); else $keep[$k]=$v;
  }
  return '?'.http_build_query($keep);
}

// botón volver
$back = 'admin.php';
if ((int)($_SESSION['rol_id'] ?? 0) === 4) {
  $back = file_exists(__DIR__.'/farmaceutico.php') ? 'farmaceutico.php' : 'admin.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Inventario con Filtros — FarmaSalud</title>
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
      --chip-ok:#ecfdf5; --chip-ok-text:#065f46;
      --chip-warn:#fff7ed; --chip-warn-text:#9a3412;
      --chip-exp:#fef2f2; --chip-exp-text:#991b1b;
    }
    body{ font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:var(--fs-bg); color:var(--fs-text); }
    .fs-card{ border-radius:14px; background:var(--fs-surface); border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }
    .btn-fs-primary{ --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary); --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600); --bs-btn-color:#fff; font-weight:600; border-radius:12px; }
    .fs-chip{ display:inline-flex; align-items:center; gap:.4rem; padding:.25rem .55rem; border-radius:999px; font-size:.82rem; font-weight:600; }
    .chip-ok{ background:var(--chip-ok); color:var(--chip-ok-text); }
    .chip-warn{ background:var(--chip-warn); color:var(--chip-warn-text); }
    .chip-exp{ background:var(--chip-exp); color:var(--chip-exp-text); }
    .table thead th a{ color:inherit; text-decoration:none; }
    .table thead th a:hover{ text-decoration:underline; }
    .toolbar .btn{ border-radius:10px; }
  </style>
</head>
<body>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><i class="bi bi-box-seam"></i> Inventario con Filtros</h2>
    <div class="d-flex gap-2">
      <a href="<?= h($back) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
      <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
    </div>
  </div>

  <!-- Resumen -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="fs-card p-3">
        <div class="small text-secondary">Total (filtrado)</div>
        <div class="h4 mb-0"><?= number_format($total) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="fs-card p-3">
        <div class="small text-secondary">Stock bajo (&lt; <?= (int)$umbral ?>)</div>
        <div class="h4 mb-0 text-warning"><?= number_format($conteo_bajos) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="fs-card p-3">
        <div class="small text-secondary">Próx. a caducar (30 días)</div>
        <div class="h4 mb-0 text-danger"><?= number_format($conteo_proximos) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="fs-card p-3 d-flex align-items-center justify-content-between">
        <span class="small text-secondary">Exportar</span>
        <a class="btn btn-fs-primary btn-sm" href="<?= h(qkeep(['export'=>'csv'])) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> CSV</a>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <form method="GET" class="fs-card p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label small text-secondary">Buscar</label>
        <input type="text" name="buscar" class="form-control" placeholder="🔍 Nombre del producto…" value="<?= h($buscar) ?>">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-secondary">Ordenar por</label>
        <select name="orden" class="form-select">
          <option value="nombre" <?= $orden==='nombre'?'selected':'' ?>>Nombre</option>
          <option value="stock" <?= $orden==='stock'?'selected':'' ?>>Stock</option>
          <option value="precio" <?= $orden==='precio'?'selected':'' ?>>Precio</option>
          <option value="fecha_caducidad" <?= $orden==='fecha_caducidad'?'selected':'' ?>>Caducidad</option>
        </select>
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-secondary">Dirección</label>
        <select name="dir" class="form-select">
          <option value="ASC"  <?= $dir==='ASC'?'selected':''  ?>>Ascendente</option>
          <option value="DESC" <?= $dir==='DESC'?'selected':'' ?>>Descendente</option>
        </select>
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-secondary">Umbral stock</label>
        <input type="number" name="umbral" class="form-control" min="0" value="<?= (int)$umbral ?>">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label small text-secondary">Caduca en (días)</label>
        <input type="number" name="caduca_en" class="form-control" min="0" value="<?= (int)$caduca_en ?>" placeholder="0 = sin filtro">
      </div>

      <div class="col-12 col-md-4">
        <div class="form-check mt-1">
          <input class="form-check-input" type="checkbox" id="stock_bajo" name="stock_bajo" <?= $stock_bajo?'checked':'' ?>>
          <label class="form-check-label" for="stock_bajo">Solo stock bajo (&lt; umbral)</label>
        </div>
      </div>

      <div class="col-12 col-md-8 d-flex gap-2 justify-content-end toolbar">
        <a class="btn btn-outline-secondary" href="<?= h(basename(__FILE__)) ?>"><i class="bi bi-arrow-counterclockwise"></i> Limpiar</a>
        <button type="submit" class="btn btn-fs-primary"><i class="bi bi-funnel"></i> Aplicar filtros</button>
      </div>
    </div>
  </form>

  <!-- Tabla -->
  <?php if (!$productos): ?>
    <div class="alert alert-info fs-card">No se encontraron productos.</div>
  <?php else:
      $nextDir = ($dir==='ASC'?'DESC':'ASC');
      function sortLink($col, $label, $orden, $dir){
        $now = ($orden===$col);
        $icon = '';
        if ($now) $icon = $dir==='ASC' ? '↑' : '↓';
        $q = $_GET;
        $q['orden'] = $col;
        $q['dir']   = $now ? ($dir==='ASC'?'DESC':'ASC') : 'ASC';
        return '<a href="?'.htmlspecialchars(http_build_query($q)).'">'.$label.' '.$icon.'</a>';
      }
  ?>
  <div class="table-responsive fs-card">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th><?= sortLink('nombre','Nombre',$orden,$dir) ?></th>
          <th class="text-end"><?= sortLink('precio','Precio',$orden,$dir) ?></th>
          <th class="text-center"><?= sortLink('stock','Stock',$orden,$dir) ?></th>
          <th class="text-center"><?= sortLink('fecha_caducidad','Caducidad',$orden,$dir) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $today = new DateTime('today');
        foreach ($productos as $p):
          $chipStockClass = ($p['stock'] < $umbral) ? 'chip-warn' : 'chip-ok';
          $chipStockText  = ($p['stock'] < $umbral) ? 'Bajo' : 'OK';

          $chipCadClass = '';
          $chipCadText  = '';
          if (!empty($p['fecha_caducidad'])) {
            $dt = DateTime::createFromFormat('Y-m-d', $p['fecha_caducidad']);
            if ($dt) {
              $diff = (int)$today->diff($dt)->format('%r%a');
              if     ($diff < 0)  { $chipCadClass='chip-exp';  $chipCadText='Vencido'; }
              elseif ($diff <=30) { $chipCadClass='chip-warn'; $chipCadText='Próximo'; }
              else                { $chipCadClass='chip-ok';   $chipCadText='Lejos'; }
            }
          }
        ?>
        <tr>
          <td><?= h($p['nombre']) ?></td>
          <td class="text-end">$<?= number_format((float)$p['precio'], 0, ',', '.') ?></td>
          <td class="text-center">
            <span class="fs-chip <?= $chipStockClass ?>"><i class="bi bi-archive"></i><?= (int)$p['stock'] ?> · <?= $chipStockText ?></span>
          </td>
          <td class="text-center">
            <?php if (!empty($p['fecha_caducidad'])): ?>
              <span class="fs-chip <?= $chipCadClass ?>"><i class="bi bi-calendar-event"></i> <?= h($p['fecha_caducidad']) ?> · <?= $chipCadText ?></span>
            <?php else: ?>
              <span class="text-secondary">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</body>
</html>


