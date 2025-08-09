<?php
$host = 'localhost';
$dbname = 'farmasalud_db'; // Asegúrate que se llame exactamente así en phpMyAdmin
$user = 'root';
$pass = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
    exit;
}
?>
