<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 4])) {
    header("Location: login.php");
    exit;
}

$busqueda = $_GET['buscar'] ?? '';
$orden = $_GET['orden'] ?? 'nombre';
$direccion = $_GET['dir'] ?? 'ASC';
$stock_bajo = isset($_GET['stock_bajo']);

$orden_permitido = ['nombre', 'stock', 'precio', 'fecha_caducidad'];
if (!in_array($orden, $orden_permitido)) {
    $orden = 'nombre';
}
$direccion = strtoupper($direccion) === 'DESC' ? 'DESC' : 'ASC';

$sql = "SELECT * FROM productos WHERE 1";
$params = [];

if ($busqueda !== '') {
    $sql .= " AND nombre LIKE ?";
    $params[] = "%$busqueda%";
}
if ($stock_bajo) {
    $sql .= " AND stock < 10";
}
$sql .= " ORDER BY $orden $direccion";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario con Filtros</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">📦 Inventario con Filtros</h2>
        <div>
            <a href="farmaceutico.php" class="btn btn-secondary btn-sm">← Volver</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
        </div>
    </div>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <input type="text" name="buscar" class="form-control" placeholder="🔍 Buscar producto..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <div class="col-md-2">
            <select name="orden" class="form-select">
                <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                <option value="stock" <?= $orden === 'stock' ? 'selected' : '' ?>>Stock</option>
                <option value="precio" <?= $orden === 'precio' ? 'selected' : '' ?>>Precio</option>
                <option value="fecha_caducidad" <?= $orden === 'fecha_caducidad' ? 'selected' : '' ?>>Caducidad</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="dir" class="form-select">
                <option value="ASC" <?= $direccion === 'ASC' ? 'selected' : '' ?>>Ascendente</option>
                <option value="DESC" <?= $direccion === 'DESC' ? 'selected' : '' ?>>Descendente</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-center">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="stock_bajo" id="stock_bajo" <?= $stock_bajo ? 'checked' : '' ?>>
                <label for="stock_bajo" class="form-check-label">Solo stock bajo (&lt; 10)</label>
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Aplicar filtros</button>
        </div>
    </form>

    <?php if (empty($productos)): ?>
        <div class="alert alert-info">No se encontraron productos.</div>
    <?php else: ?>
        <div class="table-responsive shadow-sm">
            <table class="table table-bordered table-hover align-middle bg-white">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Caducidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                        <tr class="<?= ($stock_bajo && $p['stock'] < 10) ? 'table-warning' : '' ?>">
                            <td><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><?= '$' . number_format($p['precio'], 0, ',', '.') ?></td>
                            <td><?= $p['stock'] ?></td>
                            <td><?= $p['fecha_caducidad'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

