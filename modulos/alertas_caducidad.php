<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array((int)($_SESSION['rol_id'] ?? 0), [1, 4], true)) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Filtros
$hoy     = date('Y-m-d');
$rangos  = [7, 15, 30, 60, 90];
$rango   = (int)($_GET['rango'] ?? 30);
if (!in_array($rango, $rangos, true)) $rango = 30;

$q       = trim($_GET['q'] ?? '');
$vencidos= isset($_GET['vencidos']) ? 1 : 0;

// Construcción de consulta
$sql = "
  SELECT
    id, nombre, descripcion, stock, fecha_caducidad,
    DATEDIFF(fecha_caducidad, CURDATE()) AS dias_restantes
  FROM productos
  WHERE fecha_caducidad IS NOT NULL
";

$params = [];
// Búsqueda
if ($q !== '') {
  $sql .= " AND nombre LIKE :q ";
  $params[':q'] = "%{$q}%";
}

// Rango y vencidos
$sql .= " AND (fecha_caducidad BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :rango DAY)";
$params[':rango'] = $rango;
if ($vencidos) {
  $sql .= " OR fecha_caducidad < CURDATE()";
}
$sql .= ") ORDER BY fecha_caducidad ASC";

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $stmt = $conn->prepare($sql);
  foreach ($params as $k=>$v) {
    if ($k === ':rango') $stmt->bindValue($k, $v, PDO::PARAM_INT);
    else $stmt->bindValue($k, $v, PDO::PARAM_STR);
  }
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename=productos_caducidad.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID','Nombre','Descripción','Stock','Fecha caducidad','Días restantes']);
  foreach ($rows as $r) {
    fputcsv($out, [
      $r['id'],$r['nombre'],$r['descripcion'],$r['stock'],$r['fecha_caducidad'],$r['dias_restantes']
    ]);
  }
  fclose($out);
  exit;
}

// Datos para pantalla
$stmt = $conn->prepare($sql);
foreach ($params as $k=>$v) {
  if ($k === ':rango') $stmt->bindValue($k, $v, PDO::PARAM_INT);
  else $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$esFarmaceutico = (int)($_SESSION['rol_id'] ?? 0) === 4;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Alertas de Caducidad — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b; --fs-primary:#2563eb;
      --fs-shadow:0 12px 28px rgba(2,6,23,.06); --fs-radius:16px;
    }
    body{
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
      background:
        radial-gradient(900px 500px at 8% -10%, rgba(37,99,235,.05), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgba(16,185,129,.05), transparent 60%),
        linear-gradient(180deg, var(--fs-bg) 0%, #fff 100%);
      color:var(--fs-text);
    }
    .navbar{ background: var(--fs-primary); }
    .card-fs{ background:#fff; border:1px solid var(--fs-border); border-radius:var(--fs-radius); box-shadow:var(--fs-shadow); }
    .badge-soft{ border:1px solid var(--fs-border); background:#fff; color:var(--fs-dim); }
    .row-sev-expired{ background: #fff5f5 !important; }
    .row-sev-7{ background: #fff7ed !important; }
    .row-sev-15{ background: #fffbeb !important; }
    .row-sev-30{ background: #f8fafc !important; }
    .table thead th{ background:#eaf1ff; }
    @media (max-width: 575.98px){
      .actions > *{ width:100%; }
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container-fluid">
    <span class="navbar-brand fw-semibold">FarmaSalud | <?= $esFarmaceutico ? 'Farmacéutico' : 'Admin' ?></span>
    <div class="ms-auto d-flex gap-2">
      <a href="<?= $esFarmaceutico ? 'farmaceutico.php':'admin.php' ?>" class="btn btn-light btn-sm">← Volver</a>
      <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar sesión</a>
    </div>
  </div>
</nav>

<main class="container py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0">⚠️ Productos próximos a caducar</h2>
    <div class="actions d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>">
        Exportar CSV
      </a>
      <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">Imprimir</button>
    </div>
  </div>

  <!-- Filtros -->
  <form class="card-fs p-3 mb-3" method="get">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label">Buscar</label>
        <input type="text" name="q" class="form-control" placeholder="🔎 Nombre del producto" value="<?= h($q) ?>">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Rango (días)</label>
        <select name="rango" class="form-select">
          <?php foreach($rangos as $r): ?>
            <option value="<?= $r ?>" <?= $rango===$r?'selected':'' ?>><?= $r ?> días</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <div class="form-check mt-4">
          <input class="form-check-input" type="checkbox" id="vencidos" name="vencidos" <?= $vencidos? 'checked':'' ?>>
          <label class="form-check-label" for="vencidos">Incluir vencidos</label>
        </div>
      </div>
      <div class="col-12 col-md-2">
        <button class="btn btn-primary w-100">Aplicar</button>
      </div>
    </div>
    <div class="mt-2 small text-secondary">
      Mostrando productos con caducidad entre <strong><?= h($hoy) ?></strong> y
      <strong><?= date('Y-m-d', strtotime("+{$rango} days")) ?></strong><?= $vencidos ? " + vencidos" : "" ?>.
    </div>
  </form>

  <?php if (empty($productos)): ?>
    <div class="alert alert-success card-fs">✅ No hay productos en riesgo dentro del rango seleccionado.</div>
  <?php else: ?>
    <div class="table-responsive card-fs">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Producto</th>
            <th style="min-width:220px">Descripción</th>
            <th class="text-end">Stock</th>
            <th>Caduca</th>
            <th class="text-center">Días</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($productos as $p):
            $dias = (int)$p['dias_restantes'];
            $rowClass = '';
            $badge = ['class'=>'badge-soft','txt'=>"$dias días"];
            if ($dias <= 0) { $rowClass='row-sev-expired'; $badge=['class'=>'badge text-bg-danger','txt'=>'Vencido']; }
            elseif ($dias <= 7) { $rowClass='row-sev-7';  $badge=['class'=>'badge text-bg-warning','txt'=>$dias.' días']; }
            elseif ($dias <= 15){ $rowClass='row-sev-15'; $badge=['class'=>'badge text-bg-warning','txt'=>$dias.' días']; }
            elseif ($dias <= 30){ $rowClass='row-sev-30'; $badge=['class'=>'badge text-bg-info','txt'=>$dias.' días']; }
          ?>
          <tr class="<?= $rowClass ?>">
            <td><strong><?= h($p['nombre']) ?></strong></td>
            <td class="text-secondary small"><?= h($p['descripcion']) ?></td>
            <td class="text-end"><?= (int)$p['stock'] ?></td>
            <td><?= h($p['fecha_caducidad']) ?></td>
            <td class="text-center"><span class="<?= h($badge['class']) ?>"><?= h($badge['txt']) ?></span></td>
            <td class="text-center">
              <a class="btn btn-sm btn-outline-danger"
                 href="reportar_danado.php?producto_id=<?= (int)$p['id'] ?>">
                Reportar dañado
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- Leyenda -->
  <div class="small text-secondary mt-3">
    <span class="badge text-bg-danger">Vencido</span> |
    <span class="badge text-bg-warning">≤ 7 días</span> |
    <span class="badge text-bg-warning">≤ 15 días</span> |
    <span class="badge text-bg-info">≤ 30 días</span>
  </div>

</main>

</body>
</html>


