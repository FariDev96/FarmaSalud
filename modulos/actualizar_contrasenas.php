<?php
require_once __DIR__ . '/../config/db.php';  // Asegúrate de que el archivo de conexión a la base de datos esté correctamente referenciado

// Obtener todos los usuarios
$sql = "SELECT id FROM usuarios";
$stmt = $conn->query($sql);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recorrer cada usuario y actualizar la contraseña
foreach ($usuarios as $usuario) {
    // Generar un hash de la contraseña "123456"
    $hashedPassword = password_hash('123456', PASSWORD_DEFAULT);

    // Actualizar la contraseña en la base de datos
    $updateStmt = $conn->prepare("UPDATE usuarios SET contrasena = :contrasena WHERE id = :id");
    $updateStmt->execute([':contrasena' => $hashedPassword, ':id' => $usuario['id']]);
}

echo "Contraseñas actualizadas con éxito.";
?>
