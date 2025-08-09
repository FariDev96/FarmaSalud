<?php 
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo para farmacéuticos
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 4) {
    header("Location: login.php");
    exit;
}

$mensaje = '';

// Obtener productos
$stmt = $conn->query("SELECT id, nombre FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = $_POST['producto_id'];
    $cantidad = intval($_POST['cantidad']);
    $motivo = trim($_POST['motivo']);
    $usuario_id = $_SESSION['usuario_id'];

    if ($cantidad > 0) {
        try {
            $stmt = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$cantidad, $producto_id]);

            $stmt = $conn->prepare("INSERT INTO movimientos_stock (producto_id, usuario_id, tipo, cantidad, motivo)
                                    VALUES (?, ?, 'entrada', ?, ?)");
            $stmt->execute([$producto_id, $usuario_id, $cantidad, $motivo]);

            $mensaje = "✅ Stock agregado correctamente.";
        } catch (PDOException $e) {
            $mensaje = "❌ Error al registrar: " . $e->getMessage();
        }
    } else {
        $mensaje = "❌ La cantidad debe ser mayor a cero.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Stock</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAV -->
<nav class="navbar navbar-expand-lg navbar-dark bg-info">
    <div class="container-fluid">
        <span class="navbar-brand">FarmaSalud | Farmacéutico</span>
        <a href="farmaceutico.php" class="btn btn-sm btn-light">← Volver</a>
    </div>
</nav>

<div class="container mt-5">
    <div class="card shadow p-4">
        <h2 class="mb-4">➕ Agregar Stock a Producto</h2>

        <?php if ($mensaje): ?>
            <div class="alert <?= str_contains($mensaje, '✅') ? 'alert-success' : 'alert-danger' ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Producto:</label>
                <select name="producto_id" class="form-select" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Cantidad a agregar:</label>
                <input type="number" name="cantidad" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Motivo:</label>
                <input type="text" name="motivo" class="form-control" placeholder="Reabastecimiento, corrección, etc.">
            </div>

            <button type="submit" class="btn btn-primary w-100">✅ Agregar Stock</button>
        </form>
    </div>
</div>

</body>
</html>

