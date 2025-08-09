<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo Farmacéutico
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 4) {
    header("Location: login.php");
    exit;
}

// Obtener movimientos de stock
$sql = "SELECT m.id, p.nombre AS producto, m.cantidad, m.motivo, m.tipo, m.fecha,
               u.nombre AS responsable
        FROM movimientos_stock m
        JOIN productos p ON m.producto_id = p.id
        JOIN usuarios u ON m.usuario_id = u.id
        ORDER BY m.fecha DESC";

$stmt = $conn->query($sql);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Movimientos - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📋 Historial de Movimientos de Stock</h2>
        <div>
            <a href="farmaceutico.php" class="btn btn-secondary me-2">← Volver al panel</a>
            <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
        </div>
    </div>

    <?php if (empty($movimientos)): ?>
        <div class="alert alert-info">No hay movimientos registrados.</div>
    <?php else: ?>
        <div class="table-responsive shadow bg-white p-3 rounded">
            <table class="table table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Motivo</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['producto']) ?></td>
                            <td><strong><?= $m['cantidad'] ?></strong></td>
                            <td><?= htmlspecialchars($m['motivo']) ?></td>
                            <td>
                                <span class="badge bg-<?= $m['tipo'] === 'entrada' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($m['tipo']) ?>
                                </span>
                            </td>
                            <td><?= $m['fecha'] ?></td>
                            <td><?= htmlspecialchars($m['responsable']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

