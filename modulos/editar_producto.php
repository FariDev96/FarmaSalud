<?php 
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1 || !isset($_GET['id'])) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'];
$mensaje = '';

// Obtener producto
$stmt = $conn->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die("Producto no encontrado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    $fecha_caducidad = $_POST['fecha_caducidad'];
    $imagen = $producto['imagen']; // valor por defecto

    // Subida de nueva imagen si existe
    if (!empty($_FILES['imagen']['name'])) {
        $ruta_imagen = basename($_FILES['imagen']['name']);
        $destino = "../imagenes/" . $ruta_imagen;
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
            $imagen = $ruta_imagen;
        }
    }

    // Actualizar en DB
    $stmt = $conn->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, fecha_caducidad=?, imagen=? WHERE id=?");
    $stmt->execute([$nombre, $descripcion, $precio, $stock, $fecha_caducidad, $imagen, $id]);

    $mensaje = '✅ Producto actualizado correctamente.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .preview-img {
            max-height: 100px;
            object-fit: contain;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>✏️ Editar Producto</h2>
        <a href="gestionar_productos.php" class="btn btn-secondary">← Volver</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white p-4 shadow rounded">
        <div class="mb-3">
            <label class="form-label">Nombre del producto</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="3" required><?= htmlspecialchars($producto['descripcion']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Precio</label>
            <input type="number" name="precio" step="0.01" class="form-control" value="<?= $producto['precio'] ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Stock</label>
            <input type="number" name="stock" class="form-control" value="<?= $producto['stock'] ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha de caducidad</label>
            <input type="date" name="fecha_caducidad" class="form-control" value="<?= $producto['fecha_caducidad'] ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Imagen actual</label><br>
            <img src="../imagenes/<?= htmlspecialchars($producto['imagen'] ?? 'generico.png') ?>" class="preview-img mb-2"><br>
            <input type="file" name="imagen" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">💾 Actualizar producto</button>
    </form>
</div>

</body>
</html>



