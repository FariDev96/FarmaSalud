<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo Admin
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$okMsg = $errMsg = '';
$POST  = ['nombre'=>'','descripcion'=>'','precio'=>'','stock'=>'','fecha_caducidad'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Tomar datos (trim y normalizar)
  $POST['nombre']          = trim($_POST['nombre'] ?? '');
  $POST['descripcion']     = trim($_POST['descripcion'] ?? '');
  $POST['precio']          = trim($_POST['precio'] ?? '');
  $POST['stock']           = trim($_POST['stock'] ?? '');
  $POST['fecha_caducidad'] = trim($_POST['fecha_caducidad'] ?? '');

  try {
    // Validaciones básicas
    if ($POST['nombre'] === '' || $POST['descripcion'] === '') {
      throw new Exception('Nombre y descripción son obligatorios.');
    }

    if (!is_numeric($POST['precio'])) throw new Exception('El precio debe ser numérico.');
    $precio = (float)$POST['precio'];
    if ($precio < 0) throw new Exception('El precio no puede ser negativo.');

    if (!ctype_digit($POST['stock'])) throw new Exception('El stock debe ser un entero.');
    $stock = (int)$POST['stock'];
    if ($stock < 0) throw new Exception('El stock no puede ser negativo.');

    $fecha_cad = null;
    if ($POST['fecha_caducidad'] !== '') {
      $ts = strtotime($POST['fecha_caducidad']);
      if ($ts === false) throw new Exception('La fecha de caducidad no es válida.');
      $fecha_cad = date('Y-m-d', $ts);
    }

    // Manejo de imagen (opcional)
    $nombre_imagen = null;
    if (!empty($_FILES['imagen']['name'])) {
      if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se pudo subir la imagen.');
      }

      // Límite de tamaño (3 MB)
      if ($_FILES['imagen']['size'] > 3 * 1024 * 1024) {
        throw new Exception('La imagen excede 3 MB.');
      }

      // Verificación MIME real
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime  = $finfo->file($_FILES['imagen']['tmp_name']) ?: '';
      $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
      ];
      if (!isset($permitidos[$mime])) {
        throw new Exception('Formato de imagen no permitido (usa JPG, PNG, WEBP o GIF).');
      }

      // Asegurar carpeta
      $dir = realpath(__DIR__ . '/../imagenes');
      if ($dir === false) {
        $dir = __DIR__ . '/../imagenes';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
          throw new Exception('No se pudo crear la carpeta de imágenes.');
        }
      }

      // Nombre de archivo único
      $ext = $permitidos[$mime];
      $nombre_imagen = 'prod_' . bin2hex(random_bytes(8)) . '.' . $ext;
      $destino = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombre_imagen;

      if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
        throw new Exception('No se pudo guardar la imagen en el servidor.');
      }
    }

    // Insertar en la BD
    $sql = "INSERT INTO productos (nombre, descripcion, precio, stock, fecha_caducidad, imagen)
            VALUES (:nombre, :descripcion, :precio, :stock, :fecha_caducidad, :imagen)";
    $st  = $conn->prepare($sql);
    $st->execute([
      ':nombre'           => $POST['nombre'],
      ':descripcion'      => $POST['descripcion'],
      ':precio'           => $precio,
      ':stock'            => $stock,
      ':fecha_caducidad'  => $fecha_cad,
      ':imagen'           => $nombre_imagen, // puede ser null
    ]);

    $okMsg = 'Producto agregado correctamente.';
    // Limpiar el form tras éxito
    $POST = ['nombre'=>'','descripcion'=>'','precio'=>'','stock'=>'','fecha_caducidad'=>''];

  } catch (Throwable $e) {
    $errMsg = $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Producto — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b;
      --fs-primary:#2563eb; --fs-primary-600:#1d4ed8;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    body{ font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:var(--fs-bg); color:var(--fs-text); }
    .fs-card{ border-radius:14px; background:var(--fs-surface); border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }
    .btn-fs-primary{ --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary); --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600); --bs-btn-color:#fff; font-weight:600; border-radius:12px; }
    .preview-wrap{ display:flex; align-items:center; gap:12px; }
    .preview-img{ width:72px; height:72px; object-fit:cover; border-radius:10px; border:1px solid var(--fs-border); background:#f8fafc; }
  </style>
</head>
<body>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><i class="bi bi-plus-circle"></i> Agregar producto</h2>
    <div class="d-flex gap-2">
      <a href="gestionar_productos.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
      <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
    </div>
  </div>

  <?php if ($okMsg): ?>
    <div class="alert alert-success fs-card"><?= h($okMsg) ?></div>
  <?php endif; ?>
  <?php if ($errMsg): ?>
    <div class="alert alert-danger fs-card">❌ <?= h($errMsg) ?></div>
  <?php endif; ?>

  <div class="fs-card p-3">
    <form method="POST" enctype="multipart/form-data" novalidate>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" maxlength="150" value="<?= h($POST['nombre']) ?>" required>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Precio</label>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" name="precio" step="0.01" min="0" class="form-control" value="<?= h($POST['precio']) ?>" required>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Descripción</label>
          <textarea name="descripcion" class="form-control" rows="3" maxlength="1000" required><?= h($POST['descripcion']) ?></textarea>
        </div>

        <div class="col-6 col-md-3">
          <label class="form-label">Stock</label>
          <input type="number" name="stock" min="0" step="1" class="form-control" value="<?= h($POST['stock']) ?>" required>
        </div>

        <div class="col-6 col-md-3">
          <label class="form-label">Fecha de caducidad (opcional)</label>
          <input type="date" name="fecha_caducidad" class="form-control" value="<?= h($POST['fecha_caducidad']) ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Imagen (JPG/PNG/WEBP/GIF, máx. 3 MB)</label>
          <div class="preview-wrap">
            <img id="preview" class="preview-img" alt="Previsualización" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72'%3E%3Crect width='72' height='72' fill='%23eef2ff'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-size='10' fill='%2364758b'%3Epreview%3C/text%3E%3C/svg%3E">
            <input type="file" name="imagen" id="imagen" accept="image/*" class="form-control">
          </div>
        </div>
      </div>

      <div class="mt-3 d-flex justify-content-end">
        <button type="submit" class="btn btn-fs-primary"><i class="bi bi-save"></i> Guardar producto</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Vista previa de imagen
  const input = document.getElementById('imagen');
  const prev  = document.getElementById('preview');
  input?.addEventListener('change', (e) => {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    const url = URL.createObjectURL(f);
    prev.src = url;
  });
</script>
</body>
</html>



