<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array((int)($_SESSION['rol_id'] ?? 0), [1,4])) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$mensaje = '';
$rol      = (int)$_SESSION['rol_id'];
$backLink = ($rol === 1) ? 'admin.php' : 'farmaceutico.php';
$usuarioId = (int)$_SESSION['usuario_id'];

// Productos (id, nombre, stock)
$stmt = $conn->query("SELECT id, nombre, stock FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mapa id->stock (para mostrar disponibilidad en UI)
$stocks = [];
foreach ($productos as $p) { $stocks[$p['id']] = (int)$p['stock']; }

// Registrar reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $producto_id = (int)($_POST['producto_id'] ?? 0);
  $cantidad    = (int)($_POST['cantidad'] ?? 0);
  $motivoSel   = trim($_POST['motivo'] ?? '');
  $motivoOtro  = trim($_POST['motivo_otro'] ?? '');
  $motivo      = $motivoSel === 'Otro' ? $motivoOtro : $motivoSel;

  if ($producto_id <= 0 || $cantidad <= 0 || $motivo === '') {
    $mensaje = '❌ Todos los campos son obligatorios y la cantidad debe ser mayor que 0.';
  } else {
    try {
      $conn->beginTransaction();

      // Bloquea la fila del producto para evitar condiciones de carrera
      $st = $conn->prepare("SELECT stock FROM productos WHERE id = :id FOR UPDATE");
      $st->execute([':id'=>$producto_id]);
      $row = $st->fetch(PDO::FETCH_ASSOC);

      if (!$row) {
        throw new Exception('Producto no encontrado.');
      }
      $stockActual = (int)$row['stock'];
      if ($cantidad > $stockActual) {
        throw new Exception('La cantidad excede el stock disponible (stock actual: '.$stockActual.').');
      }

      // Inserta reporte
      $ins = $conn->prepare("INSERT INTO productos_danados (producto_id, usuario_id, motivo, cantidad) VALUES (:p,:u,:m,:c)");
      $ins->execute([':p'=>$producto_id, ':u'=>$usuarioId, ':m'=>$motivo, ':c'=>$cantidad]);

      // Actualiza stock
      $upd = $conn->prepare("UPDATE productos SET stock = stock - :c WHERE id = :id");
      $upd->execute([':c'=>$cantidad, ':id'=>$producto_id]);

      $conn->commit();
      $mensaje = '✅ Reporte registrado correctamente.';
      // refrescar mapa de stocks en la vista (opcional: recargar productos)
      $stocks[$producto_id] = $stockActual - $cantidad;
    } catch (Throwable $e) {
      if ($conn->inTransaction()) { $conn->rollBack(); }
      $mensaje = '❌ Error al guardar: ' . h($e->getMessage());
    }
  }
}

// Últimos 10 reportes
$ultimos = [];
try {
  $q = $conn->query("
    SELECT d.id, p.nombre AS producto, d.cantidad, d.motivo, u.nombre AS usuario
    FROM productos_danados d
    JOIN productos p ON p.id = d.producto_id
    JOIN usuarios  u ON u.id = d.usuario_id
    ORDER BY d.id DESC
    LIMIT 10
  ");
  $ultimos = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* sin romper la vista */ }

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reportar producto dañado — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{ --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3; --fs-text:#0f172a; --fs-dim:#64748b; --fs-primary:#2563eb; --fs-primary-600:#1d4ed8; --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06); }
    body{ font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:var(--fs-bg); color:var(--fs-text); }
    .fs-card{ border-radius:14px; background:var(--fs-surface); border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }
    .btn-fs-primary{ --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary); --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600); --bs-btn-color:#fff; border-radius:12px; font-weight:600; }
    .muted{ color:var(--fs-dim); }
  </style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-clipboard2-x"></i> Reportar producto dañado</h3>
    <div class="d-flex gap-2">
      <a href="<?= h($backLink) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
      <a href="ver_danados.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-check"></i> Ver reportes</a>
      <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
    </div>
  </div>

  <?php if ($mensaje): ?>
    <div class="alert <?= str_starts_with($mensaje,'✅') ? 'alert-success' : 'alert-danger' ?>"><?= $mensaje ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="fs-card p-3">
        <form method="POST" novalidate id="form-danado">
          <div class="mb-3">
            <label class="form-label">Producto</label>
            <select name="producto_id" id="producto_id" class="form-select" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($productos as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= h($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text muted" id="stockHelp">Selecciona un producto para ver el stock disponible.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Cantidad afectada</label>
            <input type="number" min="1" name="cantidad" id="cantidad" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Motivo</label>
            <div class="row g-2">
              <div class="col-12 col-md-6">
                <select name="motivo" id="motivo" class="form-select" required>
                  <option value="">— Selecciona —</option>
                  <option>Vencido</option>
                  <option>Rotura</option>
                  <option>Mal estado</option>
                  <option>Pérdida</option>
                  <option>Otro</option>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <input type="text" name="motivo_otro" id="motivo_otro" class="form-control" placeholder="Especifica el motivo (si elegiste ‘Otro’)" disabled>
              </div>
            </div>
          </div>

          <button class="btn btn-fs-primary w-100"><i class="bi bi-check2-circle"></i> Registrar daño</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="fs-card p-3">
        <h6 class="mb-2"><i class="bi bi-clock-history"></i> Últimos reportes</h6>
        <?php if (empty($ultimos)): ?>
          <div class="alert alert-light border mb-0">Aún no hay reportes registrados.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>#</th><th>Producto</th><th class="text-end">Cantidad</th><th>Motivo</th><th>Usuario</th></tr></thead>
              <tbody>
                <?php foreach ($ultimos as $r): ?>
                  <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= h($r['producto']) ?></td>
                    <td class="text-end"><span class="badge text-bg-warning"><?= (int)$r['cantidad'] ?></span></td>
                    <td><?= h($r['motivo']) ?></td>
                    <td class="text-muted"><?= h($r['usuario']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  // Mapa de stock disponible por producto (inyectado desde PHP)
  const STOCKS = <?= json_encode($stocks) ?>;

  const sel = document.getElementById('producto_id');
  const qty = document.getElementById('cantidad');
  const help = document.getElementById('stockHelp');
  const motivo = document.getElementById('motivo');
  const motivoOtro = document.getElementById('motivo_otro');

  function updateStockHelp(){
    const id = sel.value;
    if (!id || !STOCKS[id]) {
      help.textContent = 'Selecciona un producto para ver el stock disponible.';
      qty.removeAttribute('max');
      return;
    }
    const s = Number(STOCKS[id] || 0);
    help.textContent = 'Stock disponible: ' + s;
    qty.max = Math.max(1, s);
  }
  sel.addEventListener('change', updateStockHelp);
  document.addEventListener('DOMContentLoaded', updateStockHelp);

  motivo.addEventListener('change', () => {
    if (motivo.value === 'Otro') {
      motivoOtro.disabled = false;
      motivoOtro.required = true;
      motivoOtro.focus();
    } else {
      motivoOtro.value = '';
      motivoOtro.required = false;
      motivoOtro.disabled = true;
    }
  });
</script>
</body>
</html>


