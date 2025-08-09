<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 4])) {
    header("Location: login.php");
    exit;
}

$mensaje = '';
$stmt = $conn->query("SELECT id, nombre FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $producto_id = $_POST['producto_id'];
    $cantidad = intval($_POST['cantidad']);
    $motivo = trim($_POST['motivo']);
    $usuario_id = $_SESSION['usuario_id'];

    if ($cantidad > 0 && $motivo) {
        try {
            $stmt = $conn->prepare("INSERT INTO productos_danados (producto_id, usuario_id, motivo, cantidad)
                                    VALUES (?, ?, ?, ?)");
            $stmt->execute([$producto_id, $usuario_id, $motivo, $cantidad]);

            $stmt = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$cantidad, $producto_id]);

            $mensaje = "✅ Reporte registrado correctamente.";
        } catch (PDOException $e) {
            $mensaje = "❌ Error al guardar: " . $e->getMessage();
        }
    } else {
        $mensaje = "❌ Todos los campos son obligatorios.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportar Producto Dañado</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>🧾 Reportar Producto Dañado</h3>
        <div>
            <a href="farmaceutico.php" class="btn btn-secondary me-2">← Volver al panel</a>
            <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert <?= str_contains($mensaje, '✅') ? 'alert-success' : 'alert-danger' ?>">
            <?= $mensaje ?>
        </div>
    <?php endif; ?>

    <div class="card shadow p-4">
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
                <label class="form-label">Cantidad afectada:</label>
                <input type="number" name="cantidad" class="form-control" min="1" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Motivo:</label>
                <input type="text" name="motivo" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-warning w-100">✅ Registrar</button>
        </form>
    </div>
</div>

</body>
</html>

