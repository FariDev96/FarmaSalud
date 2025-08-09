<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 2) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->query("SELECT id, nombre, descripcion, precio, stock, fecha_caducidad, imagen FROM productos");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nombre = $_SESSION['nombre'] ?? 'Cliente';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .producto-img {
            height: 180px;
            object-fit: contain;
            background-color: #f8f9fa;
            transition: transform 0.3s ease, filter 0.3s ease;
            padding: 10px;
        }

        .card:hover .producto-img {
            transform: scale(1.05);
            filter: brightness(85%);
        }

        .card-title {
            font-weight: bold;
        }

        .card-text {
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <span class="navbar-brand">FarmaSalud</span>
        <div class="text-white">
            Bienvenido, <?= htmlspecialchars($nombre) ?> |
            <a href="cliente.php" class="btn btn-sm btn-light ms-2">🏠 Inicio</a>
            <a href="carrito.php" class="btn btn-sm btn-light ms-2">🛒 Carrito</a>
            <a href="logout.php" class="btn btn-sm btn-danger ms-2">Cerrar sesión</a>
        </div>
    </div>
</nav>

<!-- CONTENIDO -->
<div class="container mt-5">
    <h2 class="mb-4">🧴 Catálogo de Productos</h2>

    <?php if (empty($productos)): ?>
        <div class="alert alert-warning">No hay productos disponibles.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($productos as $producto): ?>
                <div class="col-sm-6 col-md-4">
                    <div class="card h-100 shadow-sm border-0">
                        <img 
                            src="../imagenes/<?= $producto['imagen'] ? htmlspecialchars($producto['imagen']) : 'generico.png' ?>" 
                            class="card-img-top producto-img mx-auto d-block" 
                            alt="Imagen del producto">
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>
                            <p class="card-text text-muted"><?= htmlspecialchars($producto['descripcion']) ?></p>
                            <p class="mb-1"><strong>$<?= number_format($producto['precio'], 0, ',', '.') ?></strong></p>
                            <p class="text-secondary small">Stock: <?= $producto['stock'] ?> | Caduca: <?= $producto['fecha_caducidad'] ?></p>
                            <a href="carrito.php?agregar=<?= $producto['id'] ?>" class="btn btn-success mt-auto">🛒 Agregar</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>





