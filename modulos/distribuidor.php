<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 6) {
    header("Location: login.php");
    exit;
}

// Marcar como entregado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entregar_id'])) {
    $id = $_POST['entregar_id'];
    $obs = trim($_POST['observaciones'] ?? '');

    $stmt = $conn->prepare("UPDATE ventas SET estado_entrega = 'Entregado', observaciones = ? WHERE id = ?");
    $stmt->execute([$obs, $id]);
}

// Ventas pendientes
$stmt = $conn->prepare("
    SELECT v.id, v.fecha, u.nombre AS cliente, p.nombre AS producto, v.cantidad, v.estado_entrega
    FROM ventas v
    JOIN usuarios u ON v.cliente_id = u.id
    JOIN productos p ON v.producto_id = p.id
    WHERE v.estado_entrega = 'Pendiente'
    ORDER BY v.fecha DESC
");
$stmt->execute();
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Distribuidor - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🚛 Panel del Distribuidor</h2>
        <div>
            <a href="ver_ventas.php" class="btn btn-info me-2">📄 Ver entregas</a>
            <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
        </div>
    </div>

    <p class="text-muted">Bienvenido, <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong></p>

    <?php if (empty($ventas)): ?>
        <div class="alert alert-info">No hay entregas pendientes.</div>
    <?php else: ?>
        <div class="table-responsive shadow bg-white p-3 rounded">
            <table class="table table-hover align-middle">
                <thead class="table-success">
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Estado</th>
                        <th>Confirmar Entrega</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $v): ?>
                        <tr>
                            <td><?= $v['fecha'] ?></td>
                            <td><?= htmlspecialchars($v['cliente']) ?></td>
                            <td><?= htmlspecialchars($v['producto']) ?></td>
                            <td><?= $v['cantidad'] ?></td>
                            <td><span class="badge bg-warning"><?= $v['estado_entrega'] ?></span></td>
                            <td>
                                <form method="POST" class="d-flex flex-column gap-2">
                                    <input type="hidden" name="entregar_id" value="<?= $v['id'] ?>">
                                    <textarea name="observaciones" class="form-control" placeholder="Observaciones..." rows="2"></textarea>
                                    <button type="submit" class="btn btn-sm btn-success">📦 Marcar Entregado</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>



