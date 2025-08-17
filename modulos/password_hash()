<?php
// Conexión a la base de datos
require_once __DIR__ . '/../config/db.php';

// Obtener todos los usuarios con contraseñas en texto plano
$sql = "SELECT id, contrasena FROM usuarios";
$stmt = $conn->query($sql);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recorrer cada usuario y actualizar la contraseña
foreach ($usuarios as $usuario) {
    // Generar un hash de la contraseña
    $hashedPassword = password_hash($usuario['contrasena'], PASSWORD_DEFAULT);

    // Actualizar la contraseña en la base de datos
    $updateStmt = $conn->prepare("UPDATE usuarios SET contrasena = :contrasena WHERE id = :id");
    $updateStmt->execute([':contrasena' => $hashedPassword, ':id' => $usuario['id']]);
}

echo "Contraseñas actualizadas con éxito.";
?>
