<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 4])) {
    header("Location: login.php");
    exit;
}

$mensaje = '';

// Obtener productos y clientes
$productos = $conn->query("SELECT id, nombre, precio, stock FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$clientes = $conn->query("SELECT id, nombre FROM usuarios WHERE rol_id = 2 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $producto_id = $_POST['producto_id'];
    $cliente_id = $_POST['cliente_id'];
    $cantidad = intval($_POST['cantidad']);
    $usuario_id = $_SESSION['usuario_id'];

    // Obtener datos del producto
    $stmt = $conn->prepare("SELECT precio, stock FROM productos WHERE id = ?");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($producto && $cantidad > 0 && $producto['stock'] >= $cantidad) {
        try {
            $conn->beginTransaction();

            // Registrar venta
            $stmt = $conn->prepare("INSERT INTO ventas (usuario_id, cliente_id, producto_id, cantidad, precio_unitario)
                                    VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $cliente_id, $producto_id, $cantidad, $producto['precio']]);

            // Actualizar stock
            $stmt = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$cantidad, $producto_id]);

            $conn->commit();
            $mensaje = "✅ Venta registrada correctamente.";
        } catch (Exception $e) {
            $conn->rollBack();
            $mensaje = "❌ Error al registrar la venta: " . $e->getMessage();
        }
    } else {
        $mensaje = "❌ Stock insuficiente o datos inválidos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Venta - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAV -->
<nav class="navbar navbar-dark bg-success px-3">
    <span class="navbar-brand">FarmaSalud</span>
    <div>
        <a href="farmaceutico.php" class="btn btn-light btn-sm me-2">🏠 Panel</a>
        <a href="logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
    </div>
</nav>

<!-- CONTENIDO -->
<div class="container mt-5">
    <div class="card shadow p-4">
        <h2 class="mb-4 text-center">💸 Registrar Venta</h2>

        <?php if ($mensaje): ?>
            <div class="alert <?= str_contains($mensaje, '✅') ? 'alert-success' : 'alert-danger' ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Cliente:</label>
                <select name="cliente_id" class="form-select" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Producto:</label>
                <select name="producto_id" class="form-select" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= $p['nombre'] ?> (Stock: <?= $p['stock'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Cantidad:</label>
                <input type="number" name="cantidad" class="form-control" min="1" required>
            </div>

            <button type="submit" class="btn btn-success w-100">✅ Registrar venta</button>
        </form>
    </div>
</div>

</body>
</html>

