<?php
require_once __DIR__ . '/../config/db.php';
$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $rol_id = 2; // Cliente por defecto

    if ($nombre && $correo && $_POST['contrasena']) {
        try {
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, correo, contrasena, rol_id, fecha_registro) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$nombre, $correo, $contrasena, $rol_id]);
            $mensaje = "✅ Registro exitoso. Ya puedes iniciar sesión.";
        } catch (PDOException $e) {
            $mensaje = "❌ Error: " . $e->getMessage();
        }
    } else {
        $mensaje = "⚠️ Todos los campos son obligatorios";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">

            <div class="card shadow p-4">
                <h3 class="mb-4 text-center"> Registro</h3>

                <?php if ($mensaje): ?>
                    <div class="alert <?= str_contains($mensaje, '✅') ? 'alert-success' : 'alert-danger' ?>">
                        <?= $mensaje ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Nombre:</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Correo electrónico:</label>
                        <input type="email" name="correo" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contraseña:</label>
                        <input type="password" name="contrasena" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-success w-100">Registrarse</button>
                </form>

                <div class="text-center mt-3">
                    ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>

