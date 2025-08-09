<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];

    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE LOWER(correo) = LOWER(?)");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['rol_id'] = $usuario['rol_id'];
            $_SESSION['nombre'] = $usuario['nombre'];

            $stmtUpdate = $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $stmtUpdate->execute([$usuario['id']]);

            // Redirección por rol
            switch ($usuario['rol_id']) {
                case 1:
                    header("Location: ./admin.php");
                    break;
                case 2:
                    header("Location: ./cliente.php");
                    break;
                case 3:
                    header("Location: ./soporte.php");
                    break;
                case 4:
                    header("Location: ./farmaceutico.php");
                    break;
                case 5:
                    header("Location: ./contador.php");
                    break;
                case 6:
                    header("Location: ./distribuidor.php");
                    break;
                case 7:
                    header("Location: ./visitante.php");
                    break;
                default:
                    $mensaje = "❌ Rol no reconocido.";
            }
            exit;
        } else {
            $mensaje = "❌ Correo o contraseña incorrectos";
        }
    } catch (PDOException $e) {
        $mensaje = "❌ Error de base de datos: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FarmaSalud - Iniciar sesión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">

    <div class="container text-center">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <img src="../imagenes/Logo Farmasalud.png" alt="FarmaSalud Logo" class="img-fluid mb-4" style="max-width: 150px;">
                <div class="card shadow p-4">
                    <h2 class="mb-3">Iniciar Sesión</h2>

                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-danger"><?php echo $mensaje; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3 text-start">
                            <label for="correo" class="form-label">Correo:</label>
                            <input type="email" name="correo" id="correo" class="form-control" required>
                        </div>
                        <div class="mb-3 text-start">
                            <label for="contrasena" class="form-label">Contraseña:</label>
                            <input type="password" name="contrasena" id="contrasena" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                    </form>

                    <div class="mt-3">
                        <a href="#" class="text-decoration-none">¿Olvidaste tu contraseña?</a><br>
                        <a href="registro.php" class="text-decoration-none">Registrarse</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>







