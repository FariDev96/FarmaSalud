<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo clientes autenticados pueden enviar soporte
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 2) {
    header("Location: login.php");
    exit;
}

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $asunto = trim($_POST['asunto']);
    $contenido = trim($_POST['mensaje']);
    $usuario_id = $_SESSION['usuario_id'];
    $archivo = null; // inicializamos la variable del archivo

    // Validación de archivo subido (si existe)
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION);
        $archivo = uniqid('soporte_') . '.' . $ext;
        $ruta_destino = __DIR__ . '/../uploads/' . $archivo;

        // Validación básica de tipos de archivo (permitidos: imagenes, pdf, txt, etc)
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'mp4'];
        if (in_array($ext, $tipos_permitidos)) {
            move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_destino);
        } else {
            $mensaje = "❌ Tipo de archivo no permitido.";
            $archivo = null;
        }
    }

    // Validar si los campos obligatorios fueron llenados
    if ($asunto && $contenido) {
        try {
            // Insertar mensaje
            $stmt = $conn->prepare("INSERT INTO mensajes_soporte (usuario_id, asunto, mensaje, archivo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $asunto, $contenido, $archivo]);
            $mensaje = "✅ Tu mensaje fue enviado correctamente.";
        } catch (PDOException $e) {
            $mensaje = "❌ Error al enviar el mensaje: " . $e->getMessage();
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
    <title>Soporte - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📨 Enviar Mensaje al Soporte</h2>
        <div>
            <a href="cliente.php" class="btn btn-secondary me-2">← Volver al panel</a>
            <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert <?= str_contains($mensaje, '✅') ? 'alert-success' : 'alert-danger' ?>">
            <?= $mensaje ?>
        </div>
    <?php endif; ?>

    <div class="card shadow p-4">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="asunto" class="form-label">Asunto:</label>
                <input type="text" name="asunto" id="asunto" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="mensaje" class="form-label">Mensaje:</label>
                <textarea name="mensaje" id="mensaje" class="form-control" rows="6" required></textarea>
            </div>

            <div class="mb-3">
                <label for="archivo" class="form-label">Adjuntar archivo (opcional):</label>
                <input type="file" name="archivo" id="archivo" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary w-100">📨 Enviar Mensaje</button>
        </form>
    </div>
</div>

</body>
</html>
