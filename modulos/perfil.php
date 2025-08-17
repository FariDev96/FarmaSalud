<?php
session_start();

// Solo clientes (rol_id = 2)
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 2) {
  header("Location: login.php");
  exit;
}

require_once __DIR__ . '/../config/db.php';

$userId       = (int)$_SESSION['usuario_id'];
$nombreSesion = $_SESSION['nombre'] ?? 'Cliente';

/* ===== Detectar columnas existentes en `usuarios` ===== */
function cols(PDO $c, string $t): array {
  try {
    $q = $c->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $q->execute([$t]);
    return array_map('strtolower', array_column($q->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
  } catch (Throwable $e) { return []; }
}
$existingCols = cols($conn, 'usuarios');

/* Campos que este formulario sabe manejar (usando nombres reales de tu BD) */
$whitelist = [
  'nombre','correo','telefono','direccion','ciudad','departamento','codigo_postal',
  'documento','fecha_nacimiento','genero','alergias','acepta_datos'
];
$usable = array_values(array_intersect($whitelist, $existingCols));

/* helpers */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function v($arr,$k){ return isset($arr[$k]) ? (string)$arr[$k] : ''; }

function cargarUsuario(PDO $conn, int $id, array $cols): array {
  $cols = array_unique(array_merge(['id'], $cols));
  $list = implode(',', array_map(fn($c)=>"`$c`", $cols));
  $q    = $conn->prepare("SELECT $list FROM usuarios WHERE id = :id LIMIT 1");
  $q->execute([':id'=>$id]);
  return $q->fetch(PDO::FETCH_ASSOC) ?: ['id'=>$id];
}

/* CSRF simple */
if (empty($_SESSION['csrf_pf'])) $_SESSION['csrf_pf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_pf'];

$okMsg = $errMsg = '';
$data  = cargarUsuario($conn, $userId, $usable);

/* ===== Guardar perfil ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'perfil') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $errMsg = 'Sesión inválida. Recarga la página.';
  } else {
    try {
      $updates = [];
      $params  = [':id' => $userId];

      foreach ($usable as $field) {
        // checkbox
        if ($field === 'acepta_datos') {
          $val = isset($_POST['acepta_datos']) ? 1 : 0;
          $updates[] = "`$field` = :$field";
          $params[":$field"] = $val;
          continue;
        }

        $val = trim($_POST[$field] ?? '');

        if ($field === 'correo' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
          throw new Exception('El correo no es válido.');
        }
        if ($field === 'fecha_nacimiento' && $val !== '') {
          $ts = strtotime($val);
          if ($ts === false) throw new Exception('Fecha de nacimiento inválida.');
          $val = date('Y-m-d', $ts);
        }
        if ($field === 'genero') {
          $map = ['M'=>'M','F'=>'F','ND'=>'ND'];
          $val = strtoupper($val);
          if (!isset($map[$val])) $val = null;
        }

        $updates[] = "`$field` = :$field";
        $params[":$field"] = ($val === '' ? null : $val);
      }

      if ($updates) {
        $sql = "UPDATE usuarios SET ".implode(', ', $updates)." WHERE id = :id";
        $st  = $conn->prepare($sql);
        $st->execute($params);

        // refrescar datos y nombre de sesión
        $data = cargarUsuario($conn, $userId, $usable);
        if (in_array('nombre', $usable, true) && !empty($data['nombre'])) {
          $_SESSION['nombre'] = $data['nombre'];
          $nombreSesion = $data['nombre'];
        }
      }

      $okMsg = 'Datos actualizados correctamente.';
    } catch (Throwable $e) {
      $errMsg = 'No se pudo guardar: ' . h($e->getMessage());
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mi perfil — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">

  <!-- Inter + Bootstrap + Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b;
      --fs-primary:#2563eb; --fs-primary-600:#1d4ed8; --fs-accent:#10b981;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    body{
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
      background:
        radial-gradient(1000px 600px at 12% -18%, rgba(37,99,235,.06), transparent 60%),
        radial-gradient(900px 550px at 100% 0%, rgba(16,185,129,.06), transparent 60%),
        linear-gradient(180deg, var(--fs-bg) 0%, #fff 100%);
      color:var(--fs-text);
      letter-spacing:.2px;
    }
    a{text-decoration:none}
    .fs-navbar{ backdrop-filter:saturate(1.15) blur(10px); background:rgba(255,255,255,.9); border-bottom:1px solid var(--fs-border); }
    .fs-brand{ display:inline-flex; align-items:center; gap:.6rem; font-weight:700; color:var(--fs-text); }
    .fs-badge{ width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-weight:900;color:#052e1a;background:linear-gradient(135deg,#93c5fd,var(--fs-primary)); box-shadow:0 6px 14px rgba(37,99,235,.22); }
    .fs-link{ color:rgba(15,23,42,.72)!important; border-radius:10px; padding:.45rem .75rem; }
    .fs-link:hover,.fs-link.active{ color:var(--fs-text)!important; background:rgba(2,6,23,.04); }
    .fs-card{ border-radius:14px; background:var(--fs-surface); border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }
    .btn-fs-primary{ --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary); --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600); --bs-btn-color:#fff; font-weight:600; border-radius:12px; box-shadow:0 8px 22px rgba(37,99,235,.25); }
    .btn-fs-ghost{ background:#fff; color:var(--fs-text); border:1px solid var(--fs-border); border-radius:12px; font-weight:600; }
    .btn-fs-ghost:hover{ background:rgba(2,6,23,.04); }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fs-navbar py-3">
  <div class="container">
    <a class="navbar-brand fs-brand" href="cliente.php">
      <span class="fs-badge" aria-hidden="true">FS</span><span>FarmaSalud</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#fsNav" aria-label="Abrir menú">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="fsNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link fs-link" href="cliente.php"><i class="bi bi-house-door"></i> Inicio</a></li>
        <li class="nav-item"><a class="nav-link fs-link" href="productos.php"><i class="bi bi-box-seam"></i> Catálogo</a></li>
        <li class="nav-item"><a class="nav-link fs-link" href="soporte.php"><i class="bi bi-life-preserver"></i> Soporte</a></li>
        <li class="nav-item"><a class="nav-link fs-link active" aria-current="page" href="perfil.php"><i class="bi bi-person"></i> Mi perfil</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <span class="text-secondary small d-none d-md-inline">Hola, <strong><?= h($nombreSesion) ?></strong></span>
        <a href="carrito.php" class="btn btn-fs-ghost btn-sm"><i class="bi bi-cart3"></i> Carrito</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
      </div>
    </div>
  </div>
</nav>

<main class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-12">
        <?php if ($okMsg): ?><div class="alert alert-success"><?= h($okMsg) ?></div><?php endif; ?>
        <?php if ($errMsg): ?><div class="alert alert-danger"><?= h($errMsg) ?></div><?php endif; ?>
      </div>

      <div class="col-12 col-lg-8">
        <div class="fs-card p-3">
          <h1 class="h5 mb-3">Mis datos</h1>
          <form method="post" novalidate>
            <input type="hidden" name="accion" value="perfil">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">

            <div class="row g-3">
              <?php if (in_array('nombre', $usable, true)): ?>
              <div class="col-12 col-md-6">
                <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control" value="<?= h(v($data,'nombre')) ?>" maxlength="120" required>
              </div>
              <?php endif; ?>

              <?php if (in_array('correo', $usable, true)): ?>
              <div class="col-12 col-md-6">
                <label class="form-label">Correo <span class="text-danger">*</span></label>
                <input type="email" name="correo" class="form-control" value="<?= h(v($data,'correo')) ?>" maxlength="150" required>
              </div>
              <?php endif; ?>

              <?php if (in_array('telefono', $usable, true)): ?>
              <div class="col-12 col-md-6">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" class="form-control" value="<?= h(v($data,'telefono')) ?>" maxlength="30">
              </div>
              <?php endif; ?>

              <?php if (in_array('documento', $usable, true)): ?>
              <div class="col-12 col-md-6">
                <label class="form-label">Documento</label>
                <input type="text" name="documento" class="form-control" value="<?= h(v($data,'documento')) ?>" maxlength="40">
              </div>
              <?php endif; ?>

              <?php if (in_array('direccion', $usable, true)): ?>
              <div class="col-12">
                <label class="form-label">Dirección</label>
                <input type="text" name="direccion" class="form-control" value="<?= h(v($data,'direccion')) ?>" maxlength="180">
              </div>
              <?php endif; ?>

              <div class="col-12 col-md-6">
                <?php if (in_array('ciudad', $usable, true)): ?>
                  <label class="form-label">Ciudad</label>
                  <input type="text" name="ciudad" class="form-control" value="<?= h(v($data,'ciudad')) ?>" maxlength="80">
                <?php endif; ?>
              </div>

              <div class="col-12 col-md-6">
                <?php if (in_array('departamento', $usable, true)): ?>
                  <label class="form-label">Departamento</label>
                  <input type="text" name="departamento" class="form-control" value="<?= h(v($data,'departamento')) ?>" maxlength="80">
                <?php endif; ?>
              </div>

              <?php if (in_array('codigo_postal', $usable, true)): ?>
              <div class="col-12 col-md-6">
                <label class="form-label">Código postal</label>
                <input type="text" name="codigo_postal" class="form-control" value="<?= h(v($data,'codigo_postal')) ?>" maxlength="12">
              </div>
              <?php endif; ?>

              <?php if (in_array('fecha_nacimiento', $usable, true)): ?>
              <div class="col-12 col-md-6">
                <label class="form-label">Fecha de nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="form-control" value="<?= h(v($data,'fecha_nacimiento')) ?>">
              </div>
              <?php endif; ?>

              <?php if (in_array('genero', $usable, true)):
                $g = strtoupper(v($data,'genero'));
              ?>
              <div class="col-12">
                <label class="form-label">Sexo</label>
                <div class="d-flex gap-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="genero" id="genM" value="M" <?= ($g==='M'?'checked':'') ?>>
                    <label class="form-check-label" for="genM">M</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="genero" id="genF" value="F" <?= ($g==='F'?'checked':'') ?>>
                    <label class="form-check-label" for="genF">F</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="genero" id="genND" value="ND" <?= ($g==='ND' || $g==='' ? 'checked':'') ?>>
                    <label class="form-check-label" for="genND">Prefiero no decirlo</label>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <?php if (in_array('alergias', $usable, true)): ?>
              <div class="col-12">
                <label class="form-label">Alergias / indicaciones</label>
                <textarea name="alergias" class="form-control" rows="3" maxlength="500"><?= h(v($data,'alergias')) ?></textarea>
              </div>
              <?php endif; ?>

              <?php if (in_array('acepta_datos', $usable, true)): ?>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="acepta_datos" name="acepta_datos" <?= (intval(v($data,'acepta_datos')) ? 'checked':'') ?>>
                  <label class="form-check-label" for="acepta_datos">
                    Acepto el tratamiento de datos personales (Ley 1581 de 2012).
                  </label>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <div class="mt-3 d-flex gap-2">
              <button type="submit" class="btn btn-fs-primary"><i class="bi bi-save"></i> Guardar cambios</button>
              <a href="cliente.php" class="btn btn-fs-ghost">Volver</a>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</main>

<footer class="py-4 mt-4" style="border-top:1px solid var(--fs-border); background:#fff;">
  <div class="container small d-flex flex-wrap justify-content-between gap-2 text-secondary">
    <div>© <?= date('Y') ?> FarmaSalud — Mi perfil.</div>
    <div class="d-flex gap-3">
      <a class="link-secondary" href="soporte.php">Soporte</a>
      <a class="link-secondary" href="#">Términos</a>
      <a class="link-secondary" href="#">Privacidad</a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


