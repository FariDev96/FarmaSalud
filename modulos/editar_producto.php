<?php
// editar_producto.php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo admin y con ID válido
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1 || !isset($_GET['id'])) {
  header("Location: login.php");
  exit;
}

$id = (int)$_GET['id'];
$okMsg = $errMsg = '';

// CSRF
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Obtener producto
$st = $conn->prepare("SELECT * FROM productos WHERE id = ? LIMIT 1");
$st->execute([$id]);
$producto = $st->fetch(PDO::FETCH_ASSOC);
if (!$producto) { die("Producto no encontrado."); }

// Helper seguro
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// POST: actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // CSRF
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
      throw new Exception('Token inválido. Refresca la página.');
    }

    // Datos
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = $_POST['precio'] ?? '';
    $stock  = $_POST['stock'] ?? '';
    $noCad  = isset($_POST['sin_caducidad']);
    $fecha_caducidad = $noCad ? null : trim($_POST['fecha_caducidad'] ?? '');

    if ($nombre === '' || $descripcion === '') {
      throw new Exception('Nombre y descripción son obligatorios.');
    }

    // precio: admite 0.01+
    if (!is_numeric($precio) || (float)$precio < 0) {
      throw new Exception('El precio no es válido.');
    }
    $precio = number_format((float)$precio, 2, '.', '');

    // stock: entero >=0
    if (!is_numeric($stock) || (int)$stock < 0) {
      throw new Exception('El stock no es válido.');
    }
    $stock = (int)$stock;

    // fecha (opcional). Si llega, validar formato Y-m-d
    if (!$noCad && $fecha_caducidad !== '') {
      $ts = strtotime($fecha_caducidad);
      if ($ts === false) throw new Exception('Fecha de caducidad inválida.');
      $fecha_caducidad = date('Y-m-d', $ts);
    } else {
      $fecha_caducidad = null;
    }

    // Imagen (opcional)
    $imagen = $producto['imagen'] ?? null;

    if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir la imagen.');
      }
      // Validar tamaño (2MB)
      if ($_FILES['imagen']['size'] > 2 * 1024 * 1024) {
        throw new Exception('La imagen supera 2 MB.');
      }
      // Validar tipo
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($_FILES['imagen']['tmp_name']);
      $allowed = [
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
        'image/webp' => '.webp'
      ];
      if (!isset($allowed[$mime])) {
        throw new Exception('Formato de imagen no permitido. Usa JPG, PNG o WEBP.');
      }
      $ext = $allowed[$mime];
      $nuevoNombre = 'prd_' . bin2hex(random_bytes(6)) . $ext;
      $destino = __DIR__ . '/../imagenes/' . $nuevoNombre;

      if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
        throw new Exception('No se pudo mover la imagen al destino.');
      }
      $imagen = $nuevoNombre;
    }

    // Actualizar
    $up = $conn->prepare("UPDATE productos
                          SET nombre=?, descripcion=?, precio=?, stock=?, fecha_caducidad=?, imagen=?
                          WHERE id=?");
    $up->execute([$nombre, $descripcion, $precio, $stock, $fecha_caducidad, $imagen, $id]);

    // Refrescar producto para mostrar valores actuales
    $st->execute([$id]);
    $producto = $st->fetch(PDO::FETCH_ASSOC);

    $okMsg = 'Producto actualizado correctamente.';
  } catch (Throwable $e) {
    $errMsg = $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar producto — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">

  <!-- Inter + Bootstrap + Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b;
      --fs-primary:#2563eb; --fs-primary-600:#1d4ed8; --fs-accent:#10b981;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    body{
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
      background:
        radial-gradient(900px 500px at 8% -10%, rgba(37,99,235,.05), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgba(16,185,129,.05), transparent 60%),
        linear-gradient(180deg, var(--fs-bg) 0%, #fff 100%);
      color:var(--fs-text);
      letter-spacing:.2px;
    }
    a{text-decoration:none}

    .fs-card{ background:var(--fs-surface); border:1px solid var(--fs-border); border-radius:16px; box-shadow:var(--fs-shadow); }
    .btn-fs-primary{
      --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary);
      --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600);
      --bs-btn-color:#fff; border-radius:12px; font-weight:600; box-shadow:0 8px 22px rgba(37,99,235,.25);
    }
    .btn-fs-ghost{ background:#fff; color:var(--fs-text); border:1px solid var(--fs-border); border-radius:12px; font-weight:600; }
    .btn-fs-ghost:hover{ background:rgba(2,6,23,.04); }

    .dropzone{
      display:flex; align-items:center; justify-content:center; text-align:center;
      border:2px dashed #cbd5e1; border-radius:14px; padding:16px; background:#fff;
      transition:border-color .2s ease, background .2s ease;
    }
    .dropzone.drag{ border-color:#93c5fd; background:#f8fbff; }
    .preview-img{ width:140px; height:140px; object-fit:contain; border:1px solid var(--fs-border); border-radius:12px; background:#fff; }
    .help{ color:var(--fs-dim); font-size:.9rem; }
  </style>
</head>
<body>

<main class="py-4">
  <div class="container" style="max-width: 980px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-fs-ghost btn-sm" href="gestionar_productos.php"><i class="bi bi-arrow-left"></i> Volver</a>
        <h1 class="h5 mb-0">Editar producto</h1>
      </div>
      <span class="text-secondary small">ID #<?= (int)$producto['id'] ?></span>
    </div>

    <?php if ($okMsg): ?><div class="alert alert-success"><i class="bi bi-check2-circle"></i> <?= h($okMsg) ?></div><?php endif; ?>
    <?php if ($errMsg): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= h($errMsg) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="fs-card p-3 p-md-4">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <div class="row g-3">
        <div class="col-12 col-md-8">
          <label class="form-label">Nombre del producto <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control" maxlength="120" value="<?= h($producto['nombre']) ?>" required>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Precio <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="precio" class="form-control" step="0.01" min="0" value="<?= h(number_format((float)$producto['precio'], 2, '.', '')) ?>" required>
          </div>
          <div class="help mt-1">Usa punto para decimales (ej: 7500.00)</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Stock <span class="text-danger">*</span></label>
          <input type="number" name="stock" class="form-control" min="0" step="1" value="<?= (int)$producto['stock'] ?>" required>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Fecha de caducidad</label>
          <input type="date" name="fecha_caducidad" id="fec" class="form-control" value="<?= h($producto['fecha_caducidad'] ?? '') ?>" <?= empty($producto['fecha_caducidad']) ? 'disabled' : '' ?>>
          <div class="form-check mt-1">
            <input class="form-check-input" type="checkbox" id="sinCad" name="sin_caducidad" <?= empty($producto['fecha_caducidad']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="sinCad">Sin fecha de caducidad</label>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Descripción <span class="text-danger">*</span></label>
          <textarea name="descripcion" class="form-control" rows="3" maxlength="500" required><?= h($producto['descripcion']) ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label d-flex align-items-center gap-2">
            <i class="bi bi-image"></i> Imagen
            <span class="help">(JPG/PNG/WEBP · máx 2MB)</span>
          </label>

          <div class="row g-3 align-items-center">
            <div class="col-auto">
              <img id="prev" class="preview-img"
                   src="../imagenes/<?= h($producto['imagen'] ?: 'generico.png') ?>"
                   alt="preview">
            </div>
            <div class="col">
              <label class="dropzone w-100" id="dz" for="inpImg">
                <div>
                  <div class="fw-semibold">Arrastra y suelta aquí o haz clic para seleccionar</div>
                  <div class="help">Se conservará la imagen actual si no subes una nueva.</div>
                </div>
              </label>
              <input id="inpImg" class="form-control d-none" type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp">
            </div>
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-fs-primary"><i class="bi bi-save"></i> Guardar cambios</button>
        <a href="gestionar_productos.php" class="btn btn-fs-ghost">Cancelar</a>
      </div>
    </form>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // toggle caducidad
  const sinCad = document.getElementById('sinCad');
  const fec = document.getElementById('fec');
  sinCad?.addEventListener('change', () => {
    fec.disabled = sinCad.checked;
    if (sinCad.checked) fec.value = '';
  });

  // drag & drop imagen + preview
  const dz = document.getElementById('dz');
  const inp = document.getElementById('inpImg');
  const prev = document.getElementById('prev');

  function setPreview(file){
    if (!file) return;
    const ok = ['image/jpeg','image/png','image/webp'].includes(file.type);
    if (!ok) { alert('Formato no permitido. Usa JPG, PNG o WEBP.'); inp.value=''; return; }
    if (file.size > 2*1024*1024) { alert('La imagen supera 2 MB.'); inp.value=''; return; }
    const url = URL.createObjectURL(file);
    prev.src = url;
  }

  dz?.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag'); });
  dz?.addEventListener('dragleave', () => dz.classList.remove('drag'));
  dz?.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('drag');
    const f = e.dataTransfer.files?.[0]; if (!f) return;
    inp.files = e.dataTransfer.files;
    setPreview(f);
  });
  dz?.addEventListener('click', () => inp.click());
  inp?.addEventListener('change', () => setPreview(inp.files?.[0]));
</script>
</body>
</html>





