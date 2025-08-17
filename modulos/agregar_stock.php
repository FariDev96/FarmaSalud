<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo Farmacéuticos
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 4) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$mensaje = '';
$nuevoStock = null;

// Cargar productos con datos útiles para UI
$stmt = $conn->query("SELECT id, nombre, stock, fecha_caducidad FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preselección si viene en la URL
$preselect = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $producto_id = (int)($_POST['producto_id'] ?? 0);
  $cantidad    = (int)($_POST['cantidad'] ?? 0);
  $motivo      = trim($_POST['motivo'] ?? '');
  $usuario_id  = (int)$_SESSION['usuario_id'];

  if ($producto_id <= 0) {
    $mensaje = "❌ Selecciona un producto válido.";
  } elseif ($cantidad <= 0) {
    $mensaje = "❌ La cantidad debe ser mayor a cero.";
  } else {
    try {
      $conn->beginTransaction();

      // Verificar producto y obtener stock actual (bloqueo de fila)
      $chk = $conn->prepare("SELECT stock FROM productos WHERE id = ? FOR UPDATE");
      $chk->execute([$producto_id]);
      $row = $chk->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        throw new RuntimeException("Producto no encontrado.");
      }

      $stockActual = (int)$row['stock'];
      $nuevoStock  = $stockActual + $cantidad;

      // Actualizar stock
      $up = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
      $up->execute([$cantidad, $producto_id]);

      // Registrar movimiento
      $mov = $conn->prepare("
        INSERT INTO movimientos_stock (producto_id, usuario_id, tipo, cantidad, motivo)
        VALUES (?, ?, 'entrada', ?, ?)
      ");
      $mov->execute([$producto_id, $usuario_id, $cantidad, $motivo]);

      $conn->commit();
      $mensaje = "✅ Stock agregado correctamente. Nuevo stock: " . number_format($nuevoStock, 0, ',', '.');
      // Mantener selección del producto en el form
      $preselect = $producto_id;
    } catch (Throwable $e) {
      if ($conn->inTransaction()) $conn->rollBack();
      $mensaje = "❌ Error al registrar: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Stock — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b; --fs-primary:#2563eb;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    body{
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
      background:
        radial-gradient(900px 500px at 8% -10%, rgba(37,99,235,.05), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgba(16,185,129,.05), transparent 60%),
        linear-gradient(180deg, var(--fs-bg) 0%, #fff 100%);
      color:var(--fs-text);
    }
    .fs-navbar{ background:var(--fs-primary); }
    .fs-brand{ color:#fff; font-weight:700; }
    .card-fs{ background:#fff; border:1px solid var(--fs-border); border-radius:16px; box-shadow:var(--fs-shadow); }
    .hint{ color:var(--fs-dim); }
    .badge-soft-warn{ background:rgba(245,158,11,.14); color:#92400e; }
    .badge-soft-danger{ background:rgba(239,68,68,.14); color:#7f1d1d; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg fs-navbar py-3">
  <div class="container">
    <span class="navbar-brand fs-brand">FarmaSalud | Farmacéutico</span>
    <a href="farmaceutico.php" class="btn btn-light btn-sm ms-auto"><i class="bi bi-arrow-left"></i> Volver</a>
  </div>
</nav>

<main class="py-4">
  <div class="container" style="max-width: 880px;">
    <div class="card-fs p-4">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-box-arrow-in-down"></i> Agregar stock</h4>
        <span class="hint small">Registra ingresos y deja un motivo para trazabilidad.</span>
      </div>

      <?php if ($mensaje): ?>
        <div class="alert <?= str_contains($mensaje,'✅') ? 'alert-success' : 'alert-danger' ?>"><?= $mensaje ?></div>
      <?php endif; ?>

      <form method="POST" class="row g-3">
        <div class="col-12">
          <label class="form-label">Producto</label>
          <select name="producto_id" id="producto" class="form-select" required>
            <option value="">— Selecciona —</option>
            <?php foreach ($productos as $p):
              $sel = ($preselect && (int)$preselect === (int)$p['id']) ? 'selected' : '';
              $cad = $p['fecha_caducidad'] ?? '';
            ?>
              <option
                value="<?= (int)$p['id'] ?>" <?= $sel ?>
                data-stock="<?= (int)$p['stock'] ?>"
                data-cad="<?= h($cad) ?>"
              ><?= h($p['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div id="infoProd" class="form-text mt-1"></div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Cantidad a agregar</label>
          <input type="number" name="cantidad" id="cantidad" class="form-control" inputmode="numeric" min="1" step="1" required>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Motivo (opcional)</label>
          <input type="text" name="motivo" class="form-control" placeholder="Reabastecimiento, corrección, donación…">
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check2-circle"></i> Agregar stock
          </button>
        </div>
      </form>
    </div>

    <?php if ($nuevoStock !== null): ?>
      <p class="text-success small mt-3 mb-0"><i class="bi bi-info-circle"></i> Nuevo stock reflejado en inventario.</p>
    <?php endif; ?>
  </div>
</main>

<script>
  const sel = document.getElementById('producto');
  const info = document.getElementById('infoProd');
  const cantidad = document.getElementById('cantidad');

  function renderInfo(){
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) { info.textContent = ''; return; }

    const stock = opt.getAttribute('data-stock') || '0';
    const cad   = opt.getAttribute('data-cad') || '';
    let badge = '';

    if (cad) {
      const today = new Date(); today.setHours(0,0,0,0);
      const cadDate = new Date(cad+'T00:00:00');
      const diffDays = Math.ceil((cadDate - today) / (1000*60*60*24));
      if (diffDays < 0) badge = ` <span class="badge badge-soft-danger">Vencido</span>`;
      else if (diffDays <= 30) badge = ` <span class="badge badge-soft-warn">Pronto</span>`;
    }

    info.innerHTML = `Stock actual: <strong>${new Intl.NumberFormat().format(stock)}</strong>` + (cad ? ` • Caducidad: <strong>${cad}</strong>${badge}` : '');
  }

  sel.addEventListener('change', renderInfo);
  document.addEventListener('DOMContentLoaded', () => {
    renderInfo();
    // Si hay producto preseleccionado, enfoca cantidad para ir más rápido
    if (sel.value) cantidad.focus();
  });
</script>
</body>
</html>


