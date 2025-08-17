<?php
// modulos/ver_danados.php
session_start();
require_once __DIR__ . '/../config/db.php';

/* ===== Acceso por rol =====
   1 = Admin, 4 = Farmacéutico, 5 = Contador (lectura)
*/
$ALLOWED_ROLES = [1,4,5];
if (!isset($_SESSION['usuario_id']) || !in_array((int)($_SESSION['rol_id'] ?? 0), $ALLOWED_ROLES, true)) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableCols(PDO $conn, string $table): array {
  try {
    $cols = [];
    foreach ($conn->query("SHOW COLUMNS FROM `{$table}`") as $r) $cols[] = $r['Field'];
    return $cols;
  } catch (Throwable $e) { return []; }
}

/* ===== Back link según rol ===== */
$rol = (int)($_SESSION['rol_id'] ?? 0);
$backLink = 'index.php';
switch ($rol) {
  case 1: $backLink = 'admin.php'; break;
  case 4: $backLink = 'farmaceutico.php'; break;
  case 5: $backLink = 'contador.php'; break;
}

/* ===== Columnas disponibles ===== */
$cols = tableCols($conn, 'productos_danados');
if (!$cols) { $error = "No existe la tabla productos_danados."; }

$colProductoId = null; foreach (['producto_id','id_producto'] as $c) if (in_array($c,$cols,true)) { $colProductoId=$c; break; }
$colCantidad   = null; foreach (['cantidad','qty','unidades'] as $c) if (in_array($c,$cols,true)) { $colCantidad=$c; break; }
$colMotivo     = null; foreach (['motivo','descripcion','detalle','observaciones'] as $c) if (in_array($c,$cols,true)) { $colMotivo=$c; break; }
$colFecha      = null; foreach (['fecha','created_at','fecha_reporte'] as $c) if (in_array($c,$cols,true)) { $colFecha=$c; break; }
$colEstado     = null; foreach (['estado','estatus'] as $c) if (in_array($c,$cols,true)) { $colEstado=$c; break; }
$colUsuarioId  = null; foreach (['usuario_id','reportado_por','creado_por'] as $c) if (in_array($c,$cols,true)) { $colUsuarioId=$c; break; }
$colAdjunto    = null; foreach (['archivo','evidencia','foto','imagen'] as $c) if (in_array($c,$cols,true)) { $colAdjunto=$c; break; }
$colProductoTx = null; foreach (['producto','nombre_producto'] as $c) if (in_array($c,$cols,true)) { $colProductoTx=$c; break; }

/* ===== SELECT dinámico ===== */
$select = "d.id";
if ($colFecha)     $select .= ", d.`$colFecha` AS fecha";
if ($colCantidad)  $select .= ", d.`$colCantidad` AS cantidad";
if ($colMotivo)    $select .= ", d.`$colMotivo` AS motivo";
if ($colEstado)    $select .= ", d.`$colEstado` AS estado";
if ($colAdjunto)   $select .= ", d.`$colAdjunto` AS adjunto";
if ($colProductoTx)$select .= ", d.`$colProductoTx` AS prod_txt";

$join = "";
$selectProd = "'' AS producto";
if ($colProductoId) { $join .= " LEFT JOIN productos p ON p.id = d.`$colProductoId`"; $selectProd = "COALESCE(p.nombre,'') AS producto"; }
$select .= ", $selectProd";

$selectUser = "'' AS reportado_por";
if ($colUsuarioId) { $join .= " LEFT JOIN usuarios u ON u.id = d.`$colUsuarioId`"; $selectUser = "COALESCE(u.nombre,'') AS reportado_por"; }
$select .= ", $selectUser";

/* ===== Filtros ===== */
$q       = trim($_GET['q']  ?? '');
$desde   = trim($_GET['d1'] ?? '');
$hasta   = trim($_GET['d2'] ?? '');
$estadoF = trim($_GET['est']?? '');
$perPage = max(5, min(50, (int)($_GET['pp'] ?? 10)));
$page    = max(1, (int)($_GET['p'] ?? 1));

$where = []; $params = [];
if ($q !== '') {
  $like = "%$q%";
  $parts = [];
  if ($colMotivo)     { $parts[] = "d.`$colMotivo` LIKE ?"; $params[]=$like; }
  if ($colProductoTx) { $parts[] = "d.`$colProductoTx` LIKE ?"; $params[]=$like; }
  $parts[] = "COALESCE(p.nombre,'') LIKE ?"; $params[]=$like;
  $parts[] = "COALESCE(u.nombre,'') LIKE ?"; $params[]=$like;
  $where[] = '('.implode(' OR ', $parts).')';
}
if ($colFecha && $desde!==''){ $where[] = "DATE(d.`$colFecha`) >= ?"; $params[]=$desde; }
if ($colFecha && $hasta!==''){ $where[] = "DATE(d.`$colFecha`) <= ?"; $params[]=$hasta; }
if ($colEstado && $estadoF!==''){ $where[] = "d.`$colEstado` = ?"; $params[]=$estadoF; }

$sqlBase = "FROM productos_danados d $join";
if ($where) $sqlBase .= " WHERE ".implode(' AND ', $where);

/* ===== Conteo y paginación ===== */
$total = 0; $rows = [];
if (empty($error)) {
  $st = $conn->prepare("SELECT COUNT(*) $sqlBase");
  $st->execute($params); $total = (int)$st->fetchColumn();

  $pages  = max(1, (int)ceil($total / $perPage));
  $page   = min($page, $pages);
  $offset = ($page-1) * $perPage;

  $orderCol = $colFecha ? "d.`$colFecha`" : "d.id";
  $st = $conn->prepare("SELECT $select $sqlBase ORDER BY $orderCol DESC LIMIT $perPage OFFSET $offset");
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} else { $pages = 1; $page = 1; }

/* ===== Totales de unidades en la página ===== */
$totalCant = 0;
if ($colCantidad) foreach ($rows as $r) $totalCant += (int)$r['cantidad'];

/* ===== Detección de página para registrar daño ===== */
$reportPath = null;
if (file_exists(__DIR__ . '/reportar_danado.php'))       $reportPath = 'reportar_danado.php';
elseif (file_exists(__DIR__ . '/registrar_danado.php'))  $reportPath = 'registrar_danado.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Productos dañados — FarmaSalud</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{ --fs-border:#e8edf3; }
.table td, .table th{ vertical-align:middle; }
.badge-state{ font-size:.85rem; }
</style>
</head>
<body class="bg-light">

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">🧯 Productos dañados</h3>
    <div class="d-flex gap-2">
      <a href="<?= h($backLink) ?>" class="btn btn-outline-secondary btn-sm">← Panel</a>
      <a href="logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php else: ?>

  <!-- Filtros -->
  <form class="row g-2 align-items-end mb-3">
    <div class="col-12 col-md-4">
      <label class="form-label">Buscar</label>
      <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Producto, motivo o usuario…">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="d1" value="<?= h($desde) ?>" class="form-control">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="d2" value="<?= h($hasta) ?>" class="form-control">
    </div>
    <?php if ($colEstado): ?>
    <div class="col-6 col-md-2">
      <label class="form-label">Estado</label>
      <select name="est" class="form-select">
        <option value="">Todos</option>
        <?php foreach (['Pendiente','Descartado','Repuesto','En revisión'] as $e): ?>
          <option value="<?= h($e) ?>" <?= $estadoF===$e?'selected':'' ?>><?= h($e) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md-2">
      <label class="form-label">Por página</label>
      <select name="pp" class="form-select">
        <?php foreach([10,20,30,50] as $n): ?>
          <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 d-grid d-md-block">
      <button class="btn btn-primary mt-2 mt-md-0"><i class="bi bi-funnel"></i> Aplicar</button>
    </div>
  </form>

  <?php if (!$rows): ?>
    <div class="alert alert-info">No hay registros con los filtros actuales.</div>
  <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="text-secondary small">
        Total registros: <strong><?= $total ?></strong>
        <?php if ($colCantidad): ?> · Unidades: <strong><?= (int)$totalCant ?></strong><?php endif; ?>
      </div>
      <?php if ($reportPath && $rol !== 5): /* el contador no registra, solo lectura */ ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= h($reportPath) ?>">➕ Registrar daño</a>
      <?php endif; ?>
    </div>

    <div class="table-responsive shadow bg-white rounded">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <?php if ($colFecha):    ?><th>Fecha</th><?php endif; ?>
            <th>Producto</th>
            <?php if ($colCantidad): ?><th class="text-center">Cantidad</th><?php endif; ?>
            <?php if ($colMotivo):   ?><th>Motivo</th><?php endif; ?>
            <?php if ($colEstado):   ?><th>Estado</th><?php endif; ?>
            <?php if ($colUsuarioId):?><th>Reportado por</th><?php endif; ?>
            <?php if ($colAdjunto):  ?><th>Adjunto</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <?php if ($colFecha): ?><td><?= h($r['fecha']) ?></td><?php endif; ?>
              <td>
                <?php
                  $nombre = trim((string)($r['producto'] ?? ''));
                  if (!$nombre && $colProductoTx) $nombre = (string)$r['prod_txt'];
                  echo $nombre ? h($nombre) : '<span class="text-muted">—</span>';
                ?>
              </td>
              <?php if ($colCantidad): ?>
                <td class="text-center"><strong><?= (int)$r['cantidad'] ?></strong></td>
              <?php endif; ?>
              <?php if ($colMotivo): ?>
                <td style="max-width:320px"><?= nl2br(h($r['motivo'])) ?></td>
              <?php endif; ?>
              <?php if ($colEstado): ?>
                <td>
                  <?php
                    $state = (string)($r['estado'] ?? '');
                    $cls = 'bg-secondary';
                    if ($state === 'Pendiente')  $cls = 'bg-warning';
                    if ($state === 'Descartado') $cls = 'bg-danger';
                    if ($state === 'Repuesto')   $cls = 'bg-success';
                    if ($state === 'En revisión')$cls = 'bg-info';
                  ?>
                  <span class="badge <?= $cls ?> badge-state"><?= $state ? h($state) : '—' ?></span>
                </td>
              <?php endif; ?>
              <?php if ($colUsuarioId): ?>
                <td><?= $r['reportado_por'] ? h($r['reportado_por']) : '<span class="text-muted">—</span>' ?></td>
              <?php endif; ?>
              <?php if ($colAdjunto): ?>
                <td>
                  <?php if (!empty($r['adjunto'])): ?>
                    <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= '../uploads/'.rawurlencode($r['adjunto']) ?>">Ver</a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <nav class="mt-3">
      <ul class="pagination pagination-sm">
        <?php
          $qs = $_GET; unset($qs['p']);
          $base = '?'.http_build_query($qs);
          for ($i=1; $i<=$pages; $i++):
        ?>
          <li class="page-item <?= $i===$page ? 'active' : '' ?>">
            <a class="page-link" href="<?= $base.'&p='.$i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>

  <?php endif; // fin if tabla ?>
</div>

</body>
</html>


