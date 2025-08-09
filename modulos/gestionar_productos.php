<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: login.php");
    exit;
}

$stmt = $conn->query("SELECT * FROM productos");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Productos - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📦 Gestión de Productos</h2>
        <div>
            <a href="admin.php" class="btn btn-secondary me-2">← Volver</a>
            <a href="agregar_producto.php" class="btn btn-success">➕ Agregar Producto</a>
        </div>
    </div>

    <div class="table-responsive shadow bg-white p-3 rounded">
        <table class="table table-hover align-middle">
<thead class="table-primary">
    <tr>
        <th>ID</th>
        <th>Imagen</th>
        <th>Nombre</th>
        <th>Precio</th>
        <th>Stock</th>
        <th>Acciones</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($productos as $p): ?>
        <tr>
            <td><?= $p['id'] ?></td>
            <td>
                <img src="../imagenes/<?= htmlspecialchars($p['imagen'] ?? 'generico.png') ?>" alt="Imagen" width="60" height="60" style="object-fit: contain;">
            </td>
            <td><?= htmlspecialchars($p['nombre']) ?></td>
            <td><?= '$' . number_format($p['precio'], 0, ',', '.') ?></td>
            <td><?= $p['stock'] ?></td>
            <td>
                <a href="editar_producto.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                <a href="eliminar_producto.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este producto?')">🗑 Eliminar</a>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>

        </table>
    </div>
</div>

</body>
</html>

