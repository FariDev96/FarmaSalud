<?php
/**
 * config/db.php
 * Conexión a la base de datos MySQL usando PDO con manejo de errores y fallbacks.
 */

// Ajustes por defecto (configuración local para XAMPP)
$defaults = [
  'host'    => '127.0.0.1',           // Usamos 127.0.0.1 para evitar problemas de 'localhost'
  'port'    => 3306,
  'dbname'  => 'farmasalud_db',       // Nombre de la base de datos
  'user'    => 'root',                // Usuario de la base de datos
  'pass'    => '',                    // Contraseña de la base de datos
  'charset' => 'utf8mb4',
  'socket'  => null,                  // Si tu servidor usa un socket, configúralo aquí
];

// Posibles overrides por entorno (pueden venir de variables de entorno, útil para producción)
$cfg = [
  'host'    => getenv('DB_HOST')    ?: $defaults['host'],
  'port'    => getenv('DB_PORT')    ?: $defaults['port'],
  'dbname'  => getenv('DB_NAME')    ?: $defaults['dbname'],
  'user'    => getenv('DB_USER')    ?: $defaults['user'],
  'pass'    => getenv('DB_PASS')    ?: $defaults['pass'],
  'charset' => getenv('DB_CHARSET') ?: $defaults['charset'],
  'socket'  => getenv('DB_SOCKET')  ?: $defaults['socket'],
];

// Detectar si estamos en local (para mostrar errores detallados)
$hostHttp = $_SERVER['HTTP_HOST']   ?? '';
$servName = $_SERVER['SERVER_NAME'] ?? '';
$isLocal  = in_array($hostHttp, ['localhost','127.0.0.1','::1'], true)
         || in_array($servName, ['localhost','127.0.0.1','::1'], true)
         || php_sapi_name() === 'cli';

// Construir DSN para conexión PDO
$dsnCandidates = [];
if (!empty($cfg['socket'])) {
  $dsnCandidates[] = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $cfg['socket'], $cfg['dbname'], $cfg['charset']);
}

$hostOrder = $isLocal
  ? [$cfg['host'], '127.0.0.1', 'localhost']   // Priorizar 127.0.0.1 en local
  : [$cfg['host'], 'localhost', '127.0.0.1'];  // Priorizar 'localhost' en producción

foreach ($hostOrder as $h) {
  $dsnCandidates[] = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $h,
    (string)$cfg['port'],
    $cfg['dbname'],
    $cfg['charset']
  );
}

// Opciones de conexión PDO
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

// Intentar la conexión con múltiples DSN
$lastException = null;
foreach ($dsnCandidates as $dsn) {
  try {
    $conn = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
    break;  // Conexión exitosa
  } catch (PDOException $e) {
    $lastException = $e;
  }
}

// Manejo de errores si todos los intentos fallan
if (!isset($conn)) {
  $code = (int)($lastException ? $lastException->getCode() : 0);
  $msg  = $lastException ? $lastException->getMessage() : 'No se pudo determinar la causa.';
  
  // Mensajes de ayuda según el tipo de error
  $hint = '';
  switch ($code) {
    case 1045: $hint = 'Credenciales incorrectas (usuario/clave).'; break;
    case 1049: $hint = 'La base de datos no existe o el nombre es incorrecto.'; break;
    case 2002: $hint = 'No se puede conectar al host/puerto (¿MySQL apagado?).'; break;
    case 1130: $hint = 'El host no tiene permiso para conectarse. Prueba 127.0.0.1 o ajusta permisos GRANT.'; break;
  }

  // Si estamos en local, mostramos detalles; de lo contrario, mensaje genérico
  if ($isLocal) {
    exit("❌ Error de conexión ($code): $msg\n$hint");
  } else {
    exit("❌ Error de conexión a la base de datos. Contacta al administrador.");
  }
}
?>


