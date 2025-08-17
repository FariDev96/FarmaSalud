<?php
session_start();
require_once __DIR__ . '/../config/db.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// -------- util: columnas dinámicas --------
function tableCols(PDO $c, string $t): array {
  try { $out=[]; foreach($c->query("SHOW COLUMNS FROM `{$t}`") as $r) $out[]=$r['Field']; return $out; }
  catch(Throwable $e){ return []; }
}
$uCols = tableCols($conn, 'usuarios');
$hasUltAcc   = in_array('ultimo_acceso',   $uCols, true);
$hasIP       = in_array('ultimo_login_ip', $uCols, true);
$hasUA       = in_array('ultimo_login_ua', $uCols, true);
$hasIntentos = in_array('intentos_fallidos',$uCols, true);
$hasBloqueo  = in_array('bloqueado_hasta', $uCols, true);
$hasEstado   = in_array('estado',          $uCols, true); // ej: 'activo'

// -------- CSRF token --------
if (empty($_SESSION['csrf_login'])) {
  $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
}

$mensaje = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// -------- throttling por sesión (fallback si BD no trae columnas de lockout) --------
if (!isset($_SESSION['login_fail'])) $_SESSION['login_fail'] = ['count'=>0,'last'=>0];
$fail = &$_SESSION['login_fail'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // CSRF
  if (!hash_equals($_SESSION['csrf_login'], $_POST['csrf'] ?? '')) {
    $mensaje = "❌ Sesión inválida. Recarga la página.";
  } elseif (!empty($_POST['website'])) { // honeypot
    $mensaje = "❌ Solicitud rechazada.";
  } else {
    $correo = strtolower(trim($_POST['correo'] ?? ''));
    $contrasena = (string)($_POST['contrasena'] ?? '');
    $remember = isset($_POST['remember']);

    try {
      // Buscar usuario
      $stmt = $conn->prepare("SELECT * FROM usuarios WHERE LOWER(correo)=LOWER(?) LIMIT 1");
      $stmt->execute([$correo]);
      $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

      // ¿Bloqueado por BD?
      if ($usuario && $hasBloqueo && !empty($usuario['bloqueado_hasta']) && strtotime($usuario['bloqueado_hasta']) > time()) {
        $mins = max(1, ceil((strtotime($usuario['bloqueado_hasta']) - time())/60));
        $mensaje = "⛔ Cuenta temporalmente bloqueada. Intenta en {$mins} min.";
      }

      // ¿Estado activo?
      if (!$mensaje && $usuario && $hasEstado) {
        $ok = in_array(strtolower((string)$usuario['estado']), ['activo','1','true','enabled'], true);
        if (!$ok) $mensaje = "⛔ Tu cuenta no está activa.";
      }

      // Backoff por sesión si no hay columnas de bloqueo
      if (!$mensaje && !$hasBloqueo) {
        $wait = 0;
        if ($fail['count'] >= 3) { // exponencial: 5, 15, 30s...
          $steps = min(5, $fail['count'] - 2);
          $wait = [5,15,30,45,60][$steps-1];
        }
        if ($wait && (time() - $fail['last']) < $wait) {
          $mensaje = "⏳ Espera ".($wait - (time()-$fail['last']))."s antes de reintentar.";
        }
      }

      if (!$mensaje && $usuario && password_verify($contrasena, $usuario['contrasena'])) {
        // Éxito: regenerar sesión
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int)$usuario['id'];
        $_SESSION['rol_id']     = (int)$usuario['rol_id'];
        $_SESSION['nombre']     = (string)$usuario['nombre'];

        // limpiar fallos
        $fail = ['count'=>0,'last'=>0];
        if ($hasIntentos) {
          $conn->prepare("UPDATE usuarios SET intentos_fallidos=0".($hasBloqueo?", bloqueado_hasta=NULL":"")." WHERE id=?")
               ->execute([(int)$usuario['id']]);
        }

        // actualizar metadatos si existen
        $sets=[]; $params=[];
        if ($hasUltAcc) { $sets[]="ultimo_acceso=NOW()"; }
        if ($hasIP)     { $sets[]="ultimo_login_ip=?";  $params[]=$ip; }
        if ($hasUA)     { $sets[]="ultimo_login_ua=?";  $params[]=$ua; }
        if ($sets) {
          $sql="UPDATE usuarios SET ".implode(',', $sets)." WHERE id=?";
          $params[]=(int)$usuario['id'];
          $conn->prepare($sql)->execute($params);
        }

        // Recordarme (solo correo)
        if ($remember) {
          setcookie('fs_remember_email', $correo, time()+60*60*24*30, "/");
        } else {
          setcookie('fs_remember_email','', time()-3600, "/");
        }

        // Redirección por rol
        switch ((int)$usuario['rol_id']) {
          case 1: header("Location: ./admin.php");              break;
          case 2: header("Location: ./cliente.php");            break;
          case 3: header("Location: ./soporte_soporte.php");    break; // <-- soporte (rol 3)
          case 4: header("Location: ./farmaceutico.php");       break;
          case 5: header("Location: ./contador.php");           break;
          case 6: header("Location: ./distribuidor.php");       break;
          case 7: header("Location: ./visitante.php");          break;
          default:
            $mensaje = "❌ Rol no reconocido.";
        }
        if (!$mensaje) exit;

      } else if(!$mensaje) {
        // Credenciales inválidas
        $mensaje = "❌ Correo o contraseña incorrectos";

        // subir contadores
        $fail['count']++; $fail['last']=time();
        if ($usuario && ($hasIntentos || $hasBloqueo)) {
          $int = (int)($usuario['intentos_fallidos'] ?? 0) + 1;
          $bloqSet = '';
          $params = [$int, (int)$usuario['id']];
          if ($hasBloqueo && $int >= 5) {
            // 5 fallos -> 15 minutos
            $bloqSet = ", bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE)";
          }
          $conn->prepare("UPDATE usuarios SET intentos_fallidos=?{$bloqSet} WHERE id=?")->execute($params);
        }
      }
    } catch (PDOException $e) {
      $mensaje = "❌ Error de base de datos.";
    }
  }
}

// correo recordado
$remembered = $_COOKIE['fs_remember_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>FarmaSalud - Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body{
      background:
        radial-gradient(900px 600px at 0% -10%, rgba(37,99,235,.06), transparent 60%),
        radial-gradient(900px 600px at 100% 0%, rgba(16,185,129,.06), transparent 60%),
        linear-gradient(180deg, #f6f9fc 0%, #ffffff 100%);
    }
    .card{ border-radius:16px; box-shadow:0 16px 36px rgba(2,6,23,.12); }
    .form-control:focus{ box-shadow:0 0 0 .25rem rgba(13,110,253,.15); }
    .brand{ max-width:150px; }
  </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">

  <div class="container text-center">
    <div class="row justify-content-center">
      <div class="col-md-4">
        <img src="../imagenes/Logo Farmasalud.png" alt="FarmaSalud" class="img-fluid mb-4 brand">
        <div class="card p-4">
          <h2 class="mb-3">Iniciar sesión</h2>

          <?php if (!empty($mensaje)): ?>
            <div class="alert <?= str_starts_with($mensaje,'❌') || str_starts_with($mensaje,'⛔') ? 'alert-danger' : 'alert-warning' ?> mb-3">
              <?= h($mensaje) ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="" novalidate>
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_login']) ?>">
            <!-- honeypot -->
            <input type="text" name="website" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;height:0;width:0">

            <div class="mb-3 text-start">
              <label for="correo" class="form-label">Correo</label>
              <input type="email" name="correo" id="correo" class="form-control" required value="<?= h($remembered) ?>">
            </div>

            <div class="mb-2 text-start">
              <label for="contrasena" class="form-label">Contraseña</label>
              <div class="input-group">
                <input type="password" name="contrasena" id="contrasena" class="form-control" required autocomplete="current-password">
                <button type="button" class="btn btn-outline-secondary" id="togglePwd" tabindex="-1" aria-label="Mostrar u ocultar contraseña">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" <?= $remembered?'checked':'' ?>>
                <label class="form-check-label" for="remember">Recordarme</label>
              </div>
              <a href="#" class="small text-decoration-none">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
          </form>

          <div class="mt-3">
            <a href="registro.php" class="text-decoration-none">Registrarse</a>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
document.getElementById('togglePwd').addEventListener('click', ()=>{
  const i = document.getElementById('contrasena');
  const b = document.getElementById('togglePwd').querySelector('i');
  if (i.type === 'password'){ i.type='text'; b.classList.replace('bi-eye','bi-eye-slash'); }
  else { i.type='password'; b.classList.replace('bi-eye-slash','bi-eye'); }
});
// Normaliza correo a minúsculas mientras escribe
document.getElementById('correo').addEventListener('blur', e=> e.target.value = (e.target.value||'').trim().toLowerCase());
</script>
</body>
</html>








