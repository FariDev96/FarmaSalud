<?php
session_start();

// Verificar sesión y rol cliente
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 2) {
    header("Location: login.php");
    exit;
}

$nombre = $_SESSION['nombre'] ?? 'Cliente';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <span class="navbar-brand">FarmaSalud | Cliente</span>
        <div class="text-white">
            Bienvenido, <?= htmlspecialchars($nombre) ?> |
            <a href="logout.php" class="btn btn-sm btn-light ms-2">Cerrar sesión</a>
        </div>
    </div>
</nav>

<!-- CONTENIDO -->
<div class="container mt-5">
    <div class="card shadow p-4">
        <h2 class="mb-4 text-center">🎉 Bienvenido <?= htmlspecialchars($nombre) ?> (Cliente)</h2>

        <div class="d-grid gap-3 col-6 mx-auto">
            <a href="productos.php" class="btn btn-outline-success">🛒 Ver productos disponibles</a>
            <a href="carrito.php" class="btn btn-outline-success">🧺 Ver carrito</a>
            <a href="soporte.php" class="btn btn-outline-success">📨 Enviar mensaje al soporte</a>
        </div>
    </div>
</div>

</body>
</html>

