<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo admin
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
    header("Location: login.php");
    exit;
}

// Validar ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    echo "❌ ID de pedido inválido.";
    exit;
}
$pedido_id = (int)$_GET['id'];

// Helpers
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n){ return '$' . number_format((float)$n, 0, ',', '.'); }

// Cambio de estado
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_estado'])) {
    $nuevo = $_POST['nuevo_estado'];
    $validos = ['Pendiente','Enviado','Entregado','Cancelado'];
    if (in_array($nuevo, $validos, true)) {
        $stmt = $conn->prepare("UPDATE pedidos SET estado=? WHERE id=?");
        $stmt->execute([$nuevo, $pedido_id]);
        header("Location: ver_pedido.php?id=".$pedido_id);
        exit;
    } else {
        $mensaje = "❌ Estado inválido.";
    }
}

// Pedido + cliente
$stmt = $conn->prepare("
    SELECT p.id, p.fecha_pedido, p.estado, p.usuario_id, u.nombre AS cliente, u.correo
    FROM pedidos p
    JOIN usuarios u ON u.id = p.usuario_id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pedido) { echo "❌ Pedido no encontrado."; exit; }

// Detalles
$stmt = $conn->prepare("
    SELECT d.producto_id, d.cantidad, d.precio_unitario, pr.nombre
    FROM pedido_detalles d
    JOIN productos pr ON pr.id = d.producto_id
    WHERE d.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== Direcciones (tolerante de esquema) ==========
function tableCols(PDO $conn, string $table): array {
    try {
        $cols = [];
        foreach ($conn->query("SHOW COLUMNS FROM `{$table}`") as $r) $cols[] = $r['Field'];
        return $cols;
    } catch (Throwable $e) { return []; }
}
$dirCols = tableCols($conn, 'direcciones');

// ¿Qué columna relaciona con el usuario?
$dirUserCol = null;
foreach (['usuario_id','user_id','cliente_id'] as $c) {
    if (in_array($c, $dirCols, true)) { $dirUserCol = $c; break; }
}

// Campos de texto posibles para armar dirección
$addrFields = array_values(array_intersect(
    ['direccion','calle','numero','barrio','colonia','ciudad','localidad','municipio','estado','departamento','codigo_postal','cp','pais','referencia'],
    $dirCols
));

// Lat/Lng si existen
$latCol = null; $lngCol = null;
foreach (['lat','latitude','latitud'] as $c) if (in_array($c,$dirCols,true)) { $latCol=$c; break; }
foreach (['lng','lon','longitud','longitude'] as $c) if (in_array($c,$dirCols,true)) { $lngCol=$c; break; }

// Cargar últimas direcciones del cliente (si la tabla y columna existen)
$direcciones = [];
if ($dirCols && $dirUserCol) {
    $sel = "SELECT id".($addrFields?(', '.implode(',', $addrFields)):'')
          .($latCol?(", $latCol AS _lat"):'')
          .($lngCol?(", $lngCol AS _lng"):'')
          ." FROM direcciones WHERE $dirUserCol = ? ORDER BY id DESC LIMIT 3";
    $st = $conn->prepare($sel);
    $st->execute([$pedido['usuario_id']]);
    $direcciones = $st->fetchAll(PDO::FETCH_ASSOC);
}

function buildAddr(array $r): string {
    // Prioriza campo 'direccion' si existe
    if (isset($r['direccion']) && trim($r['direccion'])!=='') return $r['direccion'];
    $parts=[];
    foreach (['calle','numero','barrio','colonia','ciudad','localidad','municipio','estado','departamento','codigo_postal','cp','pais'] as $f) {
        if (isset($r[$f]) && trim((string)$r[$f])!=='') $parts[]=$r[$f];
    }
    $txt = $parts?implode(', ',$parts):'';
    if (isset($r['referencia']) && trim($r['referencia'])!=='') $txt .= ($txt?' — ':'').$r['referencia'];
    return $txt;
}

// Totales
$total = 0; $items = 0;
foreach ($detalles as $it) { $total += $it['precio_unitario'] * $it['cantidad']; $items += (int)$it['cantidad']; }

// Timeline utils
$estados = ['Pendiente','Enviado','Entregado','Cancelado'];
$current = array_search($pedido['estado'], $estados, true);
if ($current === false) $current = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Detalle del Pedido #<?= (int)$pedido['id'] ?> - FarmaSalud</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
.badge-state{font-size:.9rem}
.timeline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.step{display:flex;align-items:center;gap:8px}
.step .dot{width:12px;height:12px;border-radius:50%;background:#e5e7eb}
.step.active .dot{background:#0d6efd}
.step.cancel .dot{background:#dc3545}
.addr-card{border:1px solid #e9eef5;border-radius:12px;padding:12px;background:#fff}
@media (max-width:576px){ .w-50{width:100%!important} }
</style>
</head>
<body class="bg-light">

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">📝 Pedido #<?= (int)$pedido['id'] ?></h2>
    <div class="d-flex gap-2">
      <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer"></i> Imprimir</button>
      <a href="admin.php" class="btn btn-secondary btn-sm">← Panel</a>
      <a href="logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
    </div>
  </div>

  <?php if ($mensaje): ?>
    <div class="alert alert-danger"><?= h($mensaje) ?></div>
  <?php endif; ?>

  <!-- Cabecera -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="row">
            <div class="col-sm-6 mb-2">
              <div class="text-muted small">Cliente</div>
              <div class="fw-semibold"><?= h($pedido['cliente']) ?></div>
              <div class="small"><?= h($pedido['correo']) ?></div>
            </div>
            <div class="col-sm-3 mb-2">
              <div class="text-muted small">Fecha</div>
              <div class="fw-semibold"><?= h($pedido['fecha_pedido']) ?></div>
            </div>
            <div class="col-sm-3 mb-2">
              <div class="text-muted small">Estado</div>
              <?php
                $badge = 'secondary';
                if ($pedido['estado']==='Pendiente') $badge='warning';
                if ($pedido['estado']==='Enviado')   $badge='info';
                if ($pedido['estado']==='Entregado') $badge='success';
                if ($pedido['estado']==='Cancelado') $badge='danger';
              ?>
              <span class="badge text-bg-<?= $badge ?> badge-state"><?= h($pedido['estado']) ?></span>
            </div>
          </div>

          <!-- Timeline -->
          <div class="mt-3">
            <div class="text-muted small mb-1">Seguimiento</div>
            <div class="timeline">
              <?php foreach ($estados as $idx=>$st): ?>
                <div class="step <?= ($st==='Cancelado'?'cancel':($idx <= $current?'active':'')) ?>">
                  <div class="dot"></div><div class="<?= $idx<=$current?'fw-semibold':'' ?>"><?= $st ?></div>
                  <?php if ($idx < count($estados)-1): ?><div class="text-muted">›</div><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Totales -->
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="text-muted">Artículos</div>
            <div class="fw-semibold"><?= (int)$items ?></div>
          </div>
          <div class="d-flex justify-content-between">
            <div class="text-muted">Subtotal</div>
            <div class="fw-semibold"><?= money($total) ?></div>
          </div>
          <hr class="my-2">
          <div class="d-flex justify-content-between fs-5">
            <div class="fw-semibold">Total</div>
            <div class="fw-bold text-primary"><?= money($total) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Detalles -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-light"><strong>🧾 Productos</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-primary">
            <tr>
              <th>Producto</th>
              <th class="text-end">Precio</th>
              <th class="text-center">Cantidad</th>
              <th class="text-end">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($detalles as $it):
              $sub = $it['precio_unitario'] * $it['cantidad']; ?>
              <tr>
                <td><?= h($it['nombre']) ?></td>
                <td class="text-end"><?= money($it['precio_unitario']) ?></td>
                <td class="text-center"><?= (int)$it['cantidad'] ?></td>
                <td class="text-end"><?= money($sub) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Direcciones del cliente (opcionales) -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <strong>📌 Direcciones del cliente</strong>
          <?php if (!$direcciones): ?><span class="small text-muted">No se encontraron direcciones registradas</span><?php endif; ?>
        </div>
        <div class="card-body">
          <?php if ($direcciones): ?>
            <div class="row g-3">
              <?php foreach ($direcciones as $d):
                $addr = buildAddr($d);
                $maps = $addr ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($addr) : '';
              ?>
                <div class="col-12">
                  <div class="addr-card d-flex justify-content-between align-items-start gap-3">
                    <div>
                      <div class="fw-semibold"><?= $addr? h($addr) : '<span class="text-muted">—</span>' ?></div>
                      <?php if (isset($d['_lat'],$d['_lng'])): ?>
                        <div class="small text-muted">Lat: <?= h($d['_lat']) ?> · Lng: <?= h($d['_lng']) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                      <?php if ($maps): ?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= h($maps) ?>"><i class="bi bi-map"></i> Mapa</a><?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-muted">Puedes registrar direcciones desde el perfil del cliente.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Cambio de estado -->
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-light"><strong>⚙️ Cambiar estado</strong></div>
        <div class="card-body">
          <form method="post" class="w-50">
            <label for="nuevo_estado" class="form-label">Nuevo estado</label>
            <select name="nuevo_estado" id="nuevo_estado" class="form-select mb-3" required>
              <?php foreach ($estados as $st): ?>
                <option value="<?= $st ?>" <?= $st===$pedido['estado']?'selected':'' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-repeat"></i> Actualizar</button>
          </form>
          <div class="small text-muted mt-3">
            Sugerencia: “Pendiente → Enviado → Entregado”. Usa “Cancelado” solo si el cliente cancela o hay incidencias.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Acciones extras -->
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-primary" href="reportes.php"><i class="bi bi-graph-up"></i> Ir a reportes</a>
    <a class="btn btn-outline-secondary" href="gestionar_productos.php"><i class="bi bi-box-seam"></i> Ver productos</a>
  </div>

</div>
</body>
</html>



