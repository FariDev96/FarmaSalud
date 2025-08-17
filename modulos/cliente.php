<?php
session_start();

// Verificar sesión y rol cliente
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 2) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

$uid    = (int)$_SESSION['usuario_id'];
$nombre = $_SESSION['nombre'] ?? 'Cliente';

/* ===== Helpers ===== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function mod_existe(string $archivo): bool { return file_exists(__DIR__ . '/' . $archivo); }

function colExists(PDO $c, string $t, string $col): bool {
  $q = $c->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $q->execute([$t,$col]);
  return (int)$q->fetchColumn() > 0;
}
function nameInitials(string $name): string {
  $name = trim(preg_replace('/\s+/', ' ', $name));
  if ($name==='') return 'CL';
  $p = explode(' ', $name);
  $first = mb_substr($p[0]??'',0,1);
  $last  = mb_substr($p[count($p)-1]??'',0,1);
  $ini = strtoupper($first.($last?:''));
  return $ini ?: 'CL';
}
function resolveAvatarUrl(?string $avatar): ?string {
  if (!$avatar) return null;
  if (preg_match('~^(https?:)?//~i', $avatar) || str_starts_with($avatar, 'data:image/')) return $avatar;
  $p1 = __DIR__ . '/../uploads/'  . $avatar;
  $p2 = __DIR__ . '/../imagenes/' . $avatar;
  if (is_file($p1)) return '../uploads/'  . rawurlencode($avatar);
  if (is_file($p2)) return '../imagenes/' . rawurlencode($avatar);
  return null;
}

/* ===== Detección de columnas ===== */
$hasNombre = colExists($conn,'usuarios','nombre');
$emailCol  = colExists($conn,'usuarios','email') ? 'email' : (colExists($conn,'usuarios','correo') ? 'correo' : null);
$hasAvatar = colExists($conn,'usuarios','avatar');
$hasTel    = colExists($conn,'usuarios','telefono');
$hasDir    = colExists($conn,'usuarios','direccion');
$hasCiudad = colExists($conn,'usuarios','ciudad');
$hasCP     = colExists($conn,'usuarios','codigo_postal');

/* ===== Cargar perfil ===== */
$cols = ['id'];
if ($hasNombre) $cols[]='nombre';
if ($emailCol)  $cols[]=$emailCol;
if ($hasAvatar) $cols[]='avatar';
if ($hasTel)    $cols[]='telefono';
if ($hasDir)    $cols[]='direccion';
if ($hasCiudad) $cols[]='ciudad';
if ($hasCP)     $cols[]='codigo_postal';

$list = implode(',', array_map(fn($c)=>"`$c`", $cols));
$st = $conn->prepare("SELECT $list FROM usuarios WHERE id=? LIMIT 1");
$st->execute([$uid]);
$perfil = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$avatarUrl = $hasAvatar ? resolveAvatarUrl($perfil['avatar'] ?? null) : null;
$avatarIni = nameInitials($nombre);

/* ===== CSRF & guardar quick profile ===== */
if (empty($_SESSION['csrf_cli'])) $_SESSION['csrf_cli'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_cli'];

$flash = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='quick_profile') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $flash = '❌ Sesión inválida. Recarga la página.';
  } else {
    try{
      $updates=[]; $params=[];
      if ($hasTel) { $tel=trim($_POST['telefono']??''); $updates[]='telefono=?'; $params[] = ($tel===''?null:$tel); }
      if ($hasDir) { $dir=trim($_POST['direccion']??''); $updates[]='direccion=?'; $params[] = ($dir===''?null:$dir); }
      if ($hasCiudad) { $ci=trim($_POST['ciudad']??''); $updates[]='ciudad=?'; $params[] = ($ci===''?null:$ci); }
      if ($hasCP) { $cp=trim($_POST['codigo_postal']??''); $updates[]='codigo_postal=?'; $params[] = ($cp===''?null:$cp); }

      if ($hasAvatar && isset($_FILES['avatar']) && $_FILES['avatar']['error']!==UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) throw new Exception('Error al subir la imagen.');
        if ($_FILES['avatar']['size'] > 2*1024*1024) throw new Exception('La imagen supera 2 MB.');
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'],true)) throw new Exception('Formato no permitido (jpg, png, webp).');
        if (!is_dir(__DIR__.'/../uploads')) @mkdir(__DIR__.'/../uploads',0775,true);
        $fname = 'cli_avatar_'.$uid.'_'.time().'.'.$ext;
        $dest = __DIR__.'/../uploads/'.$fname;
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'],$dest)) throw new Exception('No se pudo guardar la imagen.');
        $updates[] = 'avatar=?'; $params[] = $fname;
        $avatarUrl = resolveAvatarUrl($fname);
      }

      if ($updates) {
        $params[]=$uid;
        $sql="UPDATE usuarios SET ".implode(', ',$updates)." WHERE id=?";
        $conn->prepare($sql)->execute($params);
      }

      // recargar perfil
      $st = $conn->prepare("SELECT $list FROM usuarios WHERE id=? LIMIT 1");
      $st->execute([$uid]);
      $perfil = $st->fetch(PDO::FETCH_ASSOC) ?: $perfil;

      $flash = '✅ Datos guardados.';
    }catch(Throwable $e){ $flash = '❌ '.$e->getMessage(); }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel del Cliente — FarmaSalud</title>
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
      color:var(--fs-text); letter-spacing:.2px;
    }
    a{text-decoration:none}

    /* NAV */
    .fs-navbar{ backdrop-filter:saturate(1.15) blur(10px); background:rgba(255,255,255,.9); border-bottom:1px solid var(--fs-border); }
    .fs-brand{ display:inline-flex; align-items:center; gap:.6rem; font-weight:700; color:var(--fs-text); }
    .fs-badge{ width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-weight:900;color:#052e1a;background:linear-gradient(135deg,#93c5fd,var(--fs-primary)); box-shadow:0 6px 14px rgba(37,99,235,.22); }
    .fs-link{ color:rgba(15,23,42,.72)!important; border-radius:10px; padding:.45rem .75rem; }
    .fs-link:hover,.fs-link.active{ color:var(--fs-text)!important; background:rgba(2,6,23,.04); }
    .avatar { width:36px; height:36px; border-radius:50%; overflow:hidden; border:2px solid rgba(2,6,23,.08); display:grid; place-items:center; background:#0f172a; color:#fff; font-weight:700; cursor:pointer; }
    .avatar img { width:100%; height:100%; object-fit:cover; display:block; }

    /* HERO / bienvenida */
    .fs-hero-card{ border-radius:var(--fs-radius); background:var(--fs-surface); border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }
    .fs-hero-body{ padding:24px; }
    .fs-title{ font-size: clamp(24px, 2vw + 12px, 32px); margin:0 0 6px; }
    .fs-lead{ color:var(--fs-dim); margin:0 0 14px; }

    /* Acción cards */
    .fs-card{
      height:100%; border-radius:14px; background:var(--fs-surface);
      border:1px solid var(--fs-border); box-shadow:var(--fs-shadow);
      transition: transform .12s ease, box-shadow .22s ease, border-color .22s ease; overflow:hidden;
    }
    .fs-card .fs-ico{ width:48px;height:48px;border-radius:12px;display:grid;place-items:center;font-size:1.3rem; background:#eff6ff; color:var(--fs-primary); }
    .fs-card .fs-ico.green{ background:#ecfdf5; color:var(--fs-accent); }
    .fs-card .fs-ico.gray{ background:#f1f5f9; color:#0f172a; }
    .fs-card .hat{ position:relative; }
    .fs-card .hat::after{ content:""; position:absolute; inset:0; background:rgba(2,6,23,0); transition:background .25s ease; pointer-events:none; }
    .fs-card:hover{ transform:translateY(-3px); box-shadow:0 16px 36px rgba(2,6,23,.12); border-color:#dbeafe; }
    .fs-card:hover .hat::after{ background:linear-gradient(to bottom, rgba(2,6,23,.10), rgba(2,6,23,0) 70%); }

    .btn-fs-primary{ --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary); --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600); --bs-btn-color:#fff; font-weight:600; border-radius:12px; box-shadow:0 8px 22px rgba(37,99,235,.25); }
    .btn-fs-ghost{ background:#fff; color:var(--fs-text); border:1px solid var(--fs-border); border-radius:12px; font-weight:600; }
    .btn-fs-ghost:hover{ background:rgba(2,6,23,.04); }

    @media (hover:none) and (pointer:coarse){
      .fs-card:active{ transform:translateY(-2px); box-shadow:0 12px 28px rgba(2,6,23,.10); border-color:#dbeafe; }
    }
    @media (max-width:480px){
      .fs-hero-body{ padding:18px; }
      .fs-title{ font-size:22px; }
      .fs-cta .btn{ width:100%; }
    }
    @media (prefers-reduced-motion:reduce){
      .fs-card,.fs-card .hat::after{ transition:none!important; }
    }
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
        <li class="nav-item"><a class="nav-link fs-link active" aria-current="page" href="cliente.php"><i class="bi bi-house-door"></i> Inicio</a></li>
        <li class="nav-item"><a class="nav-link fs-link" href="productos.php"><i class="bi bi-box-seam"></i> Catálogo</a></li>
        <li class="nav-item"><a class="nav-link fs-link" href="soporte.php"><i class="bi bi-life-preserver"></i> Soporte</a></li>
        <?php if (mod_existe('perfil.php')): ?>
          <li class="nav-item"><a class="nav-link fs-link" href="perfil.php"><i class="bi bi-person"></i> Mi perfil</a></li>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <span class="text-secondary small d-none d-md-inline">Hola, <strong><?= h($nombre) ?></strong></span>
        <a href="carrito.php" class="btn btn-fs-ghost btn-sm"><i class="bi bi-cart3"></i> Carrito</a>

        <!-- Avatar: abre panel lateral -->
        <a class="avatar" data-bs-toggle="offcanvas" href="#offPerfilCli" role="button" aria-controls="offPerfilCli" title="Mi perfil">
          <?php if ($avatarUrl): ?>
            <img src="<?= h($avatarUrl) ?>" alt="Avatar de <?= h($nombre) ?>">
          <?php else: ?>
            <span><?= h($avatarIni) ?></span>
          <?php endif; ?>
        </a>

        <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
      </div>
    </div>
  </div>
</nav>

<?php if ($flash): ?>
  <div class="container mt-3">
    <div class="alert <?= str_starts_with($flash,'✅')?'alert-success':'alert-danger' ?>"><?= h($flash) ?></div>
  </div>
<?php endif; ?>

<main class="py-4">
  <div class="container">
    <!-- HERO -->
    <section class="mb-3">
      <div class="fs-hero-card">
        <div class="fs-hero-body">
          <div class="row g-3 align-items-center">
            <div class="col-lg-8">
              <h1 class="fs-title">¡Hola, <?= h($nombre) ?>!</h1>
              <p class="fs-lead">Tu panel de acceso rápido para comprar, revisar tu carrito y contactar soporte.</p>
              <div class="fs-cta d-flex gap-2 flex-wrap">
                <a href="productos.php" class="btn btn-fs-primary"><i class="bi bi-cart3"></i> Ver catálogo</a>
                <a href="carrito.php" class="btn btn-fs-ghost"><i class="bi bi-bag"></i> Ir al carrito</a>
              </div>
            </div>
            <div class="col-lg-4"><!-- espacio visual --></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ACCIONES PRINCIPALES -->
    <section class="mt-4">
      <div class="row g-3">
        <div class="col-12 col-sm-6 col-lg-3">
          <a class="fs-card d-block p-3 h-100" href="productos.php" aria-label="Ver catálogo">
            <div class="hat d-flex align-items-start gap-3">
              <div class="fs-ico"><i class="bi bi-box-seam"></i></div>
              <div>
                <h6 class="mb-1">Catálogo</h6>
                <p class="mb-0 text-secondary small">Explora todos los productos disponibles.</p>
              </div>
            </div>
          </a>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
          <a class="fs-card d-block p-3 h-100" href="carrito.php" aria-label="Ver carrito">
            <div class="hat d-flex align-items-start gap-3">
              <div class="fs-ico green"><i class="bi bi-cart3"></i></div>
              <div>
                <h6 class="mb-1">Mi carrito</h6>
                <p class="mb-0 text-secondary small">Revisa y finaliza tus compras.</p>
              </div>
            </div>
          </a>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
          <a class="fs-card d-block p-3 h-100" href="soporte.php" aria-label="Contactar soporte">
            <div class="hat d-flex align-items-start gap-3">
              <div class="fs-ico gray"><i class="bi bi-headset"></i></div>
              <div>
                <h6 class="mb-1">Soporte</h6>
                <p class="mb-0 text-secondary small">Escríbenos si necesitas ayuda.</p>
              </div>
            </div>
          </a>
        </div>

        <?php if (mod_existe('pedidos.php')): ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <a class="fs-card d-block p-3 h-100" href="pedidos.php" aria-label="Ver mis pedidos">
            <div class="hat d-flex align-items-start gap-3">
              <div class="fs-ico"><i class="bi bi-receipt"></i></div>
              <div>
                <h6 class="mb-1">Mis pedidos</h6>
                <p class="mb-0 text-secondary small">Consulta estados y detalles.</p>
              </div>
            </div>
          </a>
        </div>
        <?php endif; ?>

        <?php if (mod_existe('perfil.php')): ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <a class="fs-card d-block p-3 h-100" href="perfil.php" aria-label="Mi perfil">
            <div class="hat d-flex align-items-start gap-3">
              <div class="fs-ico"><i class="bi bi-person"></i></div>
              <div>
                <h6 class="mb-1">Mi perfil</h6>
                <p class="mb-0 text-secondary small">Datos personales y preferencias.</p>
              </div>
            </div>
          </a>
        </div>
        <?php endif; ?>

        <?php if (mod_existe('direcciones.php')): ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <a class="fs-card d-block p-3 h-100" href="direcciones.php" aria-label="Direcciones">
            <div class="hat d-flex align-items-start gap-3">
              <div class="fs-ico gray"><i class="bi bi-geo-alt"></i></div>
              <div>
                <h6 class="mb-1">Direcciones</h6>
                <p class="mb-0 text-secondary small">Gestiona tus direcciones de envío.</p>
              </div>
            </div>
          </a>
        </div>
        <?php endif; ?>

        <?php if (mod_existe('pagos.php')): ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <a class="fs-card d-block p-3 h-100" href="pagos.php" aria-label="Métodos de pago">
            <div class="hat d-flex align-items-start gap-3">
              <div class="fs-ico"><i class="bi bi-credit-card"></i></div>
              <div>
                <h6 class="mb-1">Métodos de pago</h6>
                <p class="mb-0 text-secondary small">Añade o edita tus tarjetas.</p>
              </div>
            </div>
          </a>
        </div>
        <?php endif; ?>

        <?php if (mod_existe('notificaciones.php')): ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <a class="fs-card d-block p-3 h-100" href="notificaciones.php" aria-label="Notificaciones">
            <div class="hat d-flex align-items-start gap-3">
              <div class="fs-ico green"><i class="bi bi-bell"></i></div>
              <div>
                <h6 class="mb-1">Notificaciones</h6>
                <p class="mb-0 text-secondary small">Preferencias de alertas y correos.</p>
              </div>
            </div>
          </a>
        </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>

<footer class="py-4 mt-4" style="border-top:1px solid var(--fs-border); background:#fff;">
  <div class="container small d-flex flex-wrap justify-content-between gap-2 text-secondary">
    <div>© <?= date('Y') ?> FarmaSalud — Panel del cliente.</div>
    <div class="d-flex gap-3">
      <a class="link-secondary" href="soporte.php">Soporte</a>
      <a class="link-secondary" href="#">Términos</a>
      <a class="link-secondary" href="#">Privacidad</a>
    </div>
  </div>
</footer>

<!-- OFFCANVAS PERFIL RÁPIDO -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offPerfilCli" aria-labelledby="offPerfilCliLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offPerfilCliLabel"><i class="bi bi-person-circle"></i> Mi perfil</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body">
    <form method="POST" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="action" value="quick_profile">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">

      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="avatar" style="width:64px;height:64px;">
          <?php if ($avatarUrl): ?>
            <img id="avatarPreview" src="<?= h($avatarUrl) ?>" alt="Avatar">
          <?php else: ?>
            <span id="avatarInitials" style="font-size:1.2rem;"><?= h($avatarIni) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($hasAvatar): ?>
          <div class="flex-fill">
            <label class="form-label">Foto de perfil (JPG/PNG/WebP, máx 2 MB)</label>
            <input type="file" name="avatar" id="avatarInput" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*">
          </div>
        <?php endif; ?>
      </div>

      <?php if ($emailCol): ?>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" value="<?= h($perfil[$emailCol] ?? '') ?>" disabled>
          <div class="form-text">Para cambiar tu email usa la página <a href="perfil.php">Mi perfil</a>.</div>
        </div>
      <?php endif; ?>

      <?php if ($hasTel): ?>
        <div class="mb-3">
          <label class="form-label">Teléfono</label>
          <input type="text" class="form-control" name="telefono" value="<?= h($perfil['telefono'] ?? '') ?>">
        </div>
      <?php endif; ?>

      <?php if ($hasDir): ?>
        <div class="mb-3">
          <label class="form-label">Dirección</label>
          <input type="text" class="form-control" name="direccion" value="<?= h($perfil['direccion'] ?? '') ?>">
        </div>
      <?php endif; ?>

      <?php if ($hasCiudad): ?>
        <div class="mb-3">
          <label class="form-label">Ciudad</label>
          <input type="text" class="form-control" name="ciudad" value="<?= h($perfil['ciudad'] ?? '') ?>">
        </div>
      <?php endif; ?>

      <?php if ($hasCP): ?>
        <div class="mb-3">
          <label class="form-label">Código postal</label>
          <input type="text" class="form-control" name="codigo_postal" value="<?= h($perfil['codigo_postal'] ?? '') ?>">
        </div>
      <?php endif; ?>

      <div class="d-grid gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
        <?php if (mod_existe('perfil.php')): ?>
          <a class="btn btn-outline-secondary" href="perfil.php">Editar perfil completo</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Feedback táctil (simula hover en móviles)
  document.querySelectorAll('.fs-card').forEach(card => {
    card.addEventListener('touchstart', () => card.classList.add('is-touch'), {passive:true});
    const off = () => card.classList.remove('is-touch');
    card.addEventListener('touchend', off);
    card.addEventListener('touchcancel', off);
  });
  (function(){
    const style = document.createElement('style');
    style.textContent = `.fs-card.is-touch{transform:translateY(-2px);box-shadow:0 12px 28px rgba(2,6,23,.10);border-color:#dbeafe}`;
    document.head.appendChild(style);
  })();

  // Preview avatar
  const input = document.getElementById('avatarInput');
  if (input) {
    input.addEventListener('change', () => {
      const f = input.files && input.files[0];
      if (!f) return;
      if (!/^image\//.test(f.type)) { alert('Selecciona una imagen.'); input.value=''; return; }
      const url = URL.createObjectURL(f);
      let prev = document.getElementById('avatarPreview');
      const ini  = document.getElementById('avatarInitials');
      if (ini) ini.style.display='none';
      if (!prev) {
        prev = document.createElement('img');
        prev.id = 'avatarPreview';
        prev.style.width='100%'; prev.style.height='100%'; prev.style.objectFit='cover';
        document.querySelector('#offPerfilCli .avatar').appendChild(prev);
      }
      prev.src = url;
    });
  }
</script>
</body>
</html>



