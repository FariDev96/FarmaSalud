<?php
// modulos/soporte.php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo clientes (rol 2)
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 2) {
  header("Location: login.php");
  exit;
}

/* ================= Helpers ================= */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExists(PDO $c, string $t): bool {
  try { $c->query("SELECT 1 FROM `$t` LIMIT 1"); return true; } catch(Throwable $e){ return false; }
}
function colExists(PDO $c, string $t, string $col): bool {
  try { $q=$c->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $q->execute([$t,$col]); return (int)$q->fetchColumn() > 0; } catch(Throwable $e){ return false; }
}

/* ================= Esquema flexible ================= */
$hasTabla       = tableExists($conn, 'mensajes_soporte');
$hasEstadoCol   = $hasTabla && colExists($conn,'mensajes_soporte','estado');
$hasFechaCol    = $hasTabla && colExists($conn,'mensajes_soporte','fecha');
$hasArchivoCol  = $hasTabla && colExists($conn,'mensajes_soporte','archivo');

if (!$hasTabla) {
  http_response_code(500);
  die('<div style="font-family:system-ui,Segoe UI,Roboto;padding:24px"><h3>Tabla faltante</h3><p>Falta la tabla <code>mensajes_soporte</code> en la base de datos.</p></div>');
}

/* ================= CSRF ================= */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$mensaje = '';
$usuario_id = (int)$_SESSION['usuario_id'];

/* ================= POST (crear ticket) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    $mensaje = '❌ Sesión expirada, vuelve a intentarlo.';
  } else {
    $asunto   = trim((string)($_POST['asunto'] ?? ''));
    $contenido= trim((string)($_POST['mensaje'] ?? ''));
    $archivoFinal = null;

    // Validaciones simples
    if (mb_strlen($asunto) < 3 || mb_strlen($asunto) > 120) {
      $mensaje = '❌ El asunto debe tener entre 3 y 120 caracteres.';
    } elseif (mb_strlen($contenido) < 10) {
      $mensaje = '❌ El mensaje es muy corto (mínimo 10 caracteres).';
    } else {
      // Adjuntos (opcional)
      if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
          $maxBytes = 8 * 1024 * 1024; // 8MB
          if ($_FILES['archivo']['size'] > $maxBytes) {
            $mensaje = '❌ El archivo supera los 8 MB.';
          } else {
            $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
            $permitidosExt  = ['jpg','jpeg','png','gif','pdf','txt','mp4'];
            $permitidosMime = [
              'image/jpeg','image/png','image/gif',
              'application/pdf','text/plain','video/mp4'
            ];
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($_FILES['archivo']['tmp_name']) ?: 'application/octet-stream';
            if (!in_array($ext, $permitidosExt, true) || !in_array($mime, $permitidosMime, true)) {
              $mensaje = '❌ Tipo de archivo no permitido.';
            } else {
              // Asegurar carpeta
              $dir = realpath(__DIR__ . '/../uploads');
              if (!$dir) { @mkdir(__DIR__.'/../uploads', 0775, true); $dir = realpath(__DIR__.'/../uploads'); }
              if (!$dir || !is_writable($dir)) $mensaje = '❌ No se puede escribir en /uploads.';

              if (!$mensaje) {
                $archivoFinal = 'soporte_'.bin2hex(random_bytes(8)).'.'.$ext;
                $dest = $dir . DIRECTORY_SEPARATOR . $archivoFinal;
                if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $dest)) {
                  $mensaje = '❌ Error al guardar el archivo.';
                }
              }
            }
          }
        } else {
          $mensaje = '❌ Error al subir el archivo.';
        }
      }

      // Insertar ticket
      if (!$mensaje) {
        try {
          $cols = ['usuario_id','asunto','mensaje'];
          $vals = [':u',':a',':m'];
          $params = [':u'=>$usuario_id, ':a'=>$asunto, ':m'=>$contenido];

          if ($hasArchivoCol) { $cols[]='archivo'; $vals[]=':f'; $params[':f']=$archivoFinal; }
          if ($hasEstadoCol)  { $cols[]='estado';  $vals[]="'Pendiente'"; } // valor fijo
          if ($hasFechaCol)   { $cols[]='fecha';   $vals[]='NOW()'; }

          $sql = "INSERT INTO mensajes_soporte (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
          $st = $conn->prepare($sql);
          $st->execute($params);

          $_SESSION['flash'] = '✅ Tu mensaje fue enviado correctamente.';
          // PRG: evitar reenvíos
          header("Location: soporte.php");
          exit;
        } catch (Throwable $e) {
          $mensaje = '❌ Error al enviar el mensaje: '.h($e->getMessage());
        }
      }
    }
  }
}

/* ================= Historial del usuario ================= */
$historial = [];
try {
  $select = "id, asunto, ".($hasEstadoCol?"COALESCE(estado,'Pendiente') AS estado":" '—' AS estado").", ".
            ($hasFechaCol?"fecha":" NULL AS fecha").", ".
            ($hasArchivoCol?"archivo":" NULL AS archivo");
  $q = $conn->prepare("SELECT $select FROM mensajes_soporte WHERE usuario_id = ? ORDER BY ".($hasFechaCol?"fecha DESC":"id DESC")." LIMIT 10");
  $q->execute([$usuario_id]);
  $historial = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* sin romper la vista */ }

$nombre = $_SESSION['nombre'] ?? 'Cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Soporte — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{ background:#f6f9fc; }
    .card-soft{ background:#fff; border:1px solid #e8edf3; border-radius:14px; box-shadow:0 8px 22px rgba(2,6,23,.06); }
    .count{ font-size:.85rem; color:#6b7280; }
  </style>
</head>
<body>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">📨 Soporte al cliente</h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="cliente.php">← Volver al panel</a>
      <span class="align-self-center text-secondary small d-none d-sm-inline">Hola, <strong><?= h($nombre) ?></strong></span>
      <a class="btn btn-danger btn-sm" href="logout.php">Cerrar sesión</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= $flash ?></div>
  <?php endif; ?>
  <?php if ($mensaje): ?>
    <div class="alert <?= str_starts_with($mensaje,'✅') ? 'alert-success' : 'alert-danger' ?>"><?= $mensaje ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <!-- Formulario -->
    <div class="col-12 col-lg-6">
      <div class="card-soft p-3">
        <h6 class="mb-2">Crear ticket</h6>
        <form method="POST" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">

          <div class="mb-3">
            <label class="form-label">Asunto</label>
            <input type="text" name="asunto" class="form-control" maxlength="120" required>
            <div class="form-text">Máx. 120 caracteres.</div>
          </div>

          <div class="mb-2">
            <label class="form-label">Mensaje</label>
            <textarea name="mensaje" id="msg" class="form-control" rows="6" minlength="10" maxlength="4000" required></textarea>
            <div class="d-flex justify-content-between">
              <div class="form-text">Describe tu caso. Incluye pedido o producto si aplica.</div>
              <div class="count"><span id="cc">0</span>/4000</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Adjuntar archivo (opcional)</label>
            <input type="file" name="archivo" class="form-control">
            <div class="form-text">Permitidos: jpg, jpeg, png, gif, pdf, txt, mp4 · Máx. 8 MB.</div>
          </div>

          <button class="btn btn-primary w-100">Enviar</button>
        </form>
      </div>
    </div>

    <!-- Historial -->
    <div class="col-12 col-lg-6">
      <div class="card-soft p-3 h-100">
        <h6 class="mb-2">Tus últimos tickets</h6>
        <?php if (!$historial): ?>
          <div class="alert alert-light border mb-0">Aún no has enviado tickets.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>#</th><th>Asunto</th><th>Fecha</th><th>Estado</th><th>Adjunto</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($historial as $t): ?>
                <tr>
                  <td><?= (int)$t['id'] ?></td>
                  <td><?= h($t['asunto']) ?></td>
                  <td><?= h($t['fecha'] ?? '') ?></td>
                  <td>
                    <?php
                      $est = strtolower((string)($t['estado'] ?? ''));
                      $badge = 'secondary'; $text = $t['estado'] ?? '—';
                      if ($est==='pendiente' || $text==='Pendiente') $badge='warning';
                      if ($est==='resuelto' ) $badge='success';
                    ?>
                    <span class="badge text-bg-<?= $badge ?>"><?= h($text) ?></span>
                  </td>
                  <td>
                    <?php if (!empty($t['archivo'])): ?>
                      <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= '../uploads/'.rawurlencode($t['archivo']) ?>">Ver</a>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  // Contador de caracteres
  const msg = document.getElementById('msg');
  const cc  = document.getElementById('cc');
  function upd(){ cc.textContent = msg.value.length; }
  msg.addEventListener('input', upd); upd();
</script>
</body>
</html>



