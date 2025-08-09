<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 4) {
    header("Location: login.php");
    exit;
}

$stmt = $conn->query("SELECT id, nombre, descripcion, precio, stock, fecha_caducidad FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Farmacéutico</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Panel del Farmacéutico</h2>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Cerrar sesión</a>
        </div>

        <div class="mb-4">
            <a href="agregar_stock.php" class="btn btn-success me-2">📥 Registrar entrada de stock</a>
            <a href="movimientos_stock.php" class="btn btn-primary me-2">📋 Historial de movimientos</a>
            <a href="alertas_caducidad.php" class="btn btn-warning me-2">⚠️ Alertas por caducidad</a>
            <a href="reportar_danado.php" class="btn btn-secondary me-2">🧾 Reportar producto dañado</a>
            <a href="ver_danados.php" class="btn btn-info me-2">📜 Ver reportes dañados</a>
            <a href="inventario_filtro.php" class="btn btn-dark me-2">🔍 Inventario con filtros</a>
            <a href="registrar_venta.php" class="btn btn-outline-success me-2">💸 Registrar venta</a>
            <a href="ver_ventas.php" class="btn btn-outline-primary">📄 Historial de ventas</a>
        </div>

        <h4 class="mb-3">📦 Inventario de productos</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Caducidad</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                        <td><?= htmlspecialchars($p['descripcion']) ?></td>
                        <td>$<?= number_format($p['precio'], 2) ?></td>
                        <td><?= $p['stock'] ?></td>
                        <td><?= $p['fecha_caducidad'] ?></td>
                        <td>
                            <a href="editar_producto.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">✏️</a>
                            <a href="eliminar_producto.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este producto?')">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <a href="agregar_producto.php" class="btn btn-success">➕ Agregar nuevo producto</a>
        </div>
    </div>
</body>
</html>


