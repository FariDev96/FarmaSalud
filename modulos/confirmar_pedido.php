<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['carrito'])) {
    echo "<p>Tu carrito está vacío.</p>";
    exit;
}

try {
    $conn->beginTransaction();

    $usuario_id = $_SESSION['usuario_id'];
    $stmt = $conn->prepare("INSERT INTO pedidos (usuario_id) VALUES (?)");
    $stmt->execute([$usuario_id]);
    $pedido_id = $conn->lastInsertId();

    $stmtDetalle = $conn->prepare("
        INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario)
        VALUES (?, ?, ?, ?)
    ");
    $stmtStock = $conn->prepare("
        UPDATE productos SET stock = stock - ? WHERE id = ?
    ");

    $total = 0;
    $resumen = [];

    foreach ($_SESSION['carrito'] as $item) {
        $stmtDetalle->execute([
            $pedido_id,
            $item['id'],
            $item['cantidad'],
            $item['precio']
        ]);

        $stmtStock->execute([
            $item['cantidad'],
            $item['id']
        ]);

        $subtotal = $item['precio'] * $item['cantidad'];
        $total += $subtotal;

        $resumen[] = [
            'nombre' => $item['nombre'],
            'precio' => $item['precio'],
            'cantidad' => $item['cantidad'],
            'subtotal' => $subtotal
        ];
    }

    $conn->commit();
    $_SESSION['carrito'] = [];
    $mensaje = "✅ Pedido confirmado exitosamente.";
} catch (Exception $e) {
    $conn->rollBack();
    $mensaje = "❌ Error al confirmar el pedido: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Pedido - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAV -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <span class="navbar-brand">FarmaSalud | Confirmación de Pedido</span>
        <a href="productos.php" class="btn btn-sm btn-light ms-auto">← Volver</a>
    </div>
</nav>

<div class="container mt-5">
    <h2 class="mb-4 text-center">🧾 Confirmar Pedido</h2>

    <?php if (!empty($mensaje)): ?>
        <div class="alert <?= strpos($mensaje, '✅') !== false ? 'alert-success' : 'alert-danger' ?>">
            <?= $mensaje ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($resumen)): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumen as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['nombre']) ?></td>
                        <td>$<?= number_format($item['precio'], 0, ',', '.') ?></td>
                        <td><?= $item['cantidad'] ?></td>
                        <td>$<?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-end mt-3">
            <h4 class="text-primary">Total pagado: $<?= number_format($total, 0, ',', '.') ?></h4>
        </div>
    <?php endif; ?>
</div>

</body>
</html>


