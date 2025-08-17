<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array((int)$_SESSION['rol_id'], [1,4,6])) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableCols(PDO $conn, string $table): array {
  try {
    $cols = [];
    foreach ($conn->query("SHOW COLUMNS FROM `{$table}`") as $r) $cols[] = $r['Field'];
    return $cols;
  } catch(Throwable $e){ return []; }
}
$ventasCols = tableCols($conn, 'ventas');
$dirCols    = tableCols($conn, 'direcciones');

$allowedStates = ['Pendiente','En camino','Entregado'];

// Acciones por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Cambiar estado (admin/farmaceutico)
  if (in_array((int)$_SESSION['rol_id'], [1,4]) && ($_POST['action'] ?? '') === 'cambiar_estado') {
    $venta_id = (int)($_POST['venta_id'] ?? 0);
    $nuevo    = $_POST['nuevo_estado'] ?? '';
    if ($venta_id && in_array($nuevo, $allowedStates, true)) {
      try {
        if (in_array('estado_entrega', $ventasCols, true)) {
          $stmt = $conn->prepare("UPDATE ventas SET estado_entrega = ? WHERE id = ?");
          $stmt->execute([$nuevo, $venta_id]);
        } else {
          // Fallback: no hay columna estado_entrega, guardamos en observaciones
          $stmt = $conn->prepare("UPDATE ventas SET observaciones = CONCAT(COALESCE(observaciones,''),' | Estado: ',?) WHERE id = ?");
          $stmt->execute([$nuevo, $venta_id]);
        }
        $_SESSION['mensaje'] = "✅ Estado actualizado a «$nuevo».";
      } catch(Throwable $e){
        $_SESSION['mensaje'] = "❌ No se pudo actualizar: ".$e->getMessage();
      }
    }
    header("Location: ver_ventas.php"); exit;
  }

  // Actualizar desde distribuidor (estado + observaciones)
  if ((int)$_SESSION['rol_id'] === 6 && ($_POST['action'] ?? '') === 'actualizar_distrib') {
    $venta_id = (int)($_POST['venta_id'] ?? 0);
    $nuevo    = $_POST['estado_entrega'] ?? '';
    $obs      = trim($_POST['observaciones'] ?? '');
    if ($venta_id && in_array($nuevo, $allowedStates, true)) {
      try {
        if (in_array('estado_entrega', $ventasCols, true)) {
          $sql = "UPDATE ventas SET estado_entrega = ?";
          $params = [$nuevo];
          if (in_array('observaciones',$ventasCols,true) && $obs !== '') {
            $sql .= ", observaciones = CONCAT(COALESCE(observaciones,''), CASE WHEN COALESCE(observaciones,'')='' THEN '' ELSE ' | ' END, ?)";
            $params[] = $obs;
          }
          $sql .= " WHERE id = ?";
          $params[] = $venta_id;
          $stmt = $conn->prepare($sql);
          $stmt->execute($params);
        } else {
          // Sin columna estado_entrega
          if ($obs !== '') {
            $stmt = $conn->prepare("UPDATE ventas SET observaciones = CONCAT(COALESCE(observaciones,''), CASE WHEN COALESCE(observaciones,'')='' THEN '' ELSE ' | ' END, ?) WHERE id = ?");
            $stmt->execute(["Estado: $nuevo; $obs", $venta_id]);
          }
        }
        $_SESSION['mensaje'] = "✅ Venta actualizada.";
      } catch(Throwable $e){
        $_SESSION['mensaje'] = "❌ No se pudo actualizar: ".$e->getMessage();
      }
    }
    header("Location: ver_ventas.php"); exit;
  }
}

// Filtros
$estado = $_GET['estado'] ?? '';
$desde  = $_GET['desde']  ?? '';
$hasta  = $_GET['hasta']  ?? '';
$q      = trim($_GET['q'] ?? '');

$where = []; $params = [];
if ($estado !== '' && in_array('estado_entrega',$ventasCols,true)) {
  $where[] = "v.estado_entrega = ?"; $params[] = $estado;
}
if ($desde !== '') { $where[] = "DATE(v.fecha) >= ?"; $params[] = $desde; }
if ($hasta !== '') { $where[] = "DATE(v.fecha) <= ?"; $params[] = $hasta; }
if ($q !== '') {
  $where[] = "(u1.nombre LIKE ? OR u2.nombre LIKE ? OR p.nombre LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}

// Join direcciones (si hay direccion_id)
$selectDir = ''; $joinDir = '';
$dirFields = [];
if (in_array('direccion_id',$ventasCols,true) && $dirCols) {
  $dirPick = [];
  foreach (['direccion','calle','numero','ciudad','estado','departamento','codigo_postal','cp','pais','referencia'] as $c) {
    if (in_array($c,$dirCols,true)) $dirPick[] = "d.$c";
  }
  if ($dirPick) {
    $selectDir = ", d.id AS dir_id, ".implode(',', $dirPick);
    $joinDir   = " LEFT JOIN direcciones d ON v.direccion_id = d.id";
    $dirFields = array_map(function($x){ return str_contains($x,'.') ? explode('.',$x)[1] : $x; }, $dirPick);
  }
}

$sql = "
  SELECT v.id, v.fecha,
         u1.nombre AS registrado_por,
         u2.nombre AS cliente,
         p.nombre  AS producto,
         v.cantidad, v.precio_unitario,
         ".(in_array('estado_entrega',$ventasCols,true) ? "v.estado_entrega" : "NULL AS estado_entrega").",
         ".(in_array('observaciones', $ventasCols,true) ? "v.observaciones" : "'' AS observaciones")."
         $selectDir
  FROM ventas v
  JOIN usuarios u1 ON v.usuario_id  = u1.id
  JOIN usuarios u2 ON v.cliente_id  = u2.id
  JOIN productos p ON v.producto_id = p.id
  $joinDir
";

if ($where) $sql .= " WHERE ".implode(' AND ', $where);
$sql .= " ORDER BY v.fecha DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper para etiqueta de dirección
function dirLabel(array $row, array $dirFields): string {
  if (!$dirFields) return '';
  if (!empty($row['direccion'])) return $row['direccion'];
  $parts = [];
  foreach (['calle','numero','ciudad','estado','departamento','codigo_postal','cp','pais'] as $f) {
    if (in_array($f,$dirFields,true) && !empty($row[$f])) $parts[] = $row[$f];
  }
  $label = $parts ? implode(', ', $parts) : '';
  if (in_array('referencia',$dirFields,true) && !empty($row['referencia'])) $label .= ($label? ' — ':'').$row['referencia'];
  return $label;
}

function badgeClass(?string $estado): string {
  $e = strtolower((string)$estado);
  return match($e){
    'pendiente' => 'secondary',
    'en camino' => 'warning',
    'entregado' => 'success',
    default     => 'secondary'
  };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Seguimiento de Entregas - FarmaSalud</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{ --fs-border:#e8edf3; }
.card{ border:1px solid var(--fs-border); border-radius:16px; }
.table thead th{ background:#eef5ff; }
</style>
</head>
<body class="bg-light">
<div class="container my-4">

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h2 class="mb-2 mb-sm-0">🚚 Seguimiento de entregas</h2>
    <div>
      <a href="<?= (int)$_SESSION['rol_id'] === 6 ? 'distribuidor.php' : 'farmaceutico.php' ?>" class="btn btn-secondary btn-sm me-2">← Volver</a>
      <a href="logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
    </div>
  </div>

  <?php if (!empty($_SESSION['mensaje'])): ?>
    <div class="alert alert-success"><?= $_SESSION['mensaje'] ?></div>
    <?php unset($_SESSION['mensaje']); ?>
  <?php endif; ?>

  <!-- Filtros -->
  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label">Estado</label>
          <select name="estado" class="form-select">
            <option value="">— Todos —</option>
            <?php foreach ($allowedStates as $st): ?>
              <option value="<?= $st ?>" <?= $estado===$st?'selected':'' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Desde</label>
          <input type="date" name="desde" value="<?= h($desde) ?>" class="form-control">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Hasta</label>
          <input type="date" name="hasta" value="<?= h($hasta) ?>" class="form-control">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Buscar</label>
          <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Cliente, producto…">
        </div>
        <div class="col-12 col-md-2">
          <button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button>
        </div>
      </form>
    </div>
  </div>

  <?php if (!$ventas): ?>
    <div class="alert alert-info">No hay ventas que coincidan con los filtros.</div>
  <?php else: ?>
    <div class="table-responsive shadow bg-white rounded">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Registrado por</th>
            <th>Cliente</th>
            <th>Producto</th>
            <th class="text-center">Cant.</th>
            <th>Total</th>
            <th>Estado</th>
            <?php if (in_array('direccion_id',$ventasCols,true) && $dirCols): ?><th>Dirección</th><?php endif; ?>
            <th>Observaciones</th>
            <th style="min-width:160px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ventas as $v): ?>
            <tr>
              <td><?= h($v['fecha']) ?></td>
              <td><?= h($v['registrado_por']) ?></td>
              <td><?= h($v['cliente']) ?></td>
              <td><?= h($v['producto']) ?></td>
              <td class="text-center"><?= (int)$v['cantidad'] ?></td>
              <td>$<?= number_format(((float)$v['precio_unitario'] * (int)$v['cantidad']), 0, ',', '.') ?></td>
              <td>
                <span class="badge bg-<?= badgeClass($v['estado_entrega']) ?>"><?= h($v['estado_entrega']) ?: '—' ?></span>
              </td>
              <?php if (in_array('direccion_id',$ventasCols,true) && $dirCols): ?>
                <td>
                  <?php $lbl = dirLabel($v, $dirFields); echo $lbl ? h($lbl) : '—'; ?>
                </td>
              <?php endif; ?>
              <td style="max-width:240px"><?= nl2br(h($v['observaciones'])) ?></td>
              <td>
                <?php if (in_array((int)$_SESSION['rol_id'], [1,4])): ?>
                  <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($allowedStates as $st): if ($st === ($v['estado_entrega'] ?? '')) continue; ?>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="cambiar_estado">
                        <input type="hidden" name="venta_id" value="<?= (int)$v['id'] ?>">
                        <input type="hidden" name="nuevo_estado" value="<?= h($st) ?>">
                        <button class="btn btn-sm btn-outline-primary"><?= "Cambiar a $st" ?></button>
                      </form>
                    <?php endforeach; ?>
                  </div>
                <?php elseif ((int)$_SESSION['rol_id'] === 6): ?>
                  <form method="POST" class="d-inline-block">
                    <input type="hidden" name="action" value="actualizar_distrib">
                    <input type="hidden" name="venta_id" value="<?= (int)$v['id'] ?>">
                    <div class="row g-1">
                      <div class="col-12">
                        <select name="estado_entrega" class="form-select form-select-sm" required>
                          <option value="">-- Estado --</option>
                          <?php foreach ($allowedStates as $st): ?>
                            <option value="<?= $st ?>" <?= $st===($v['estado_entrega']??'')?'selected':'' ?>><?= $st ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-12">
                        <textarea name="observaciones" rows="2" class="form-control form-control-sm" placeholder="Observaciones…"></textarea>
                      </div>
                      <div class="col-12">
                        <button class="btn btn-sm btn-success w-100">Actualizar</button>
                      </div>
                    </div>
                  </form>
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




