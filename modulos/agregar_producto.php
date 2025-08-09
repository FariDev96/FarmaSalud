<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Verificar rol admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: login.php");
    exit;
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    $fecha_caducidad = $_POST['fecha_caducidad'];
    $nombre_imagen = null;

    // Subida de imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombre_imagen = uniqid('prod_') . '.' . $ext;
        $ruta_destino = __DIR__ . '/../imagenes/' . $nombre_imagen;

        move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino);
    }

    // Insertar en base de datos
    $stmt = $conn->prepare("INSERT INTO productos (nombre, descripcion, precio, stock, fecha_caducidad, imagen) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $descripcion, $precio, $stock, $fecha_caducidad, $nombre_imagen]);

    $mensaje = '✅ Producto agregado correctamente.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>➕ Agregar Producto</h2>
        <a href="gestionar_productos.php" class="btn btn-secondary">← Volver a productos</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= $mensaje ?></div>
    <?php endif; ?>

    <div class="card shadow p-4">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre:</label>
                <input type="text" name="nombre" id="nombre" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción:</label>
                <textarea name="descripcion" id="descripcion" class="form-control" required></textarea>
            </div>

            <div class="mb-3">
                <label for="precio" class="form-label">Precio:</label>
                <input type="number" name="precio" id="precio" step="0.01" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="stock" class="form-label">Stock:</label>
                <input type="number" name="stock" id="stock" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="fecha_caducidad" class="form-label">Fecha de caducidad:</label>
                <input type="date" name="fecha_caducidad" id="fecha_caducidad" class="form-control">
            </div>

            <div class="mb-3">
                <label for="imagen" class="form-label">Imagen del producto:</label>
                <input type="file" name="imagen" id="imagen" accept="image/*" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary w-100">Guardar producto</button>
        </form>
    </div>

</div>

</body>
</html>


