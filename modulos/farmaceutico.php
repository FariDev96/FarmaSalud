<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 4) {
  header("Location: login.php");
  exit;
}

/* ================= Helpers ================= */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function safeScalar(PDO $conn, string $sql, array $params = [], $default = 0) {
  try {
    $st = $conn->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return is_numeric($v) ? (float)$v : ($v ?? $default);
  } catch(Throwable $e){ return $default; }
}
function tableExists(PDO $conn, string $name): bool {
  try { $conn->query("SELECT 1 FROM `$name` LIMIT 1"); return true; }
  catch(Throwable $e){ return false; }
}
function colExists(PDO $c, string $t, string $col): bool {
  try{
    $q=$c->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$t,$col]); return (int)$q->fetchColumn()>0;
  }catch(Throwable $e){ return false; }
}
function nameInitials(string $name): string {
  $name = trim(preg_replace('/\s+/', ' ', $name));
  if ($name==='') return 'FA';
  $p = explode(' ', $name);
  $first = mb_substr($p[0]??'',0,1);
  $last  = mb_substr($p[count($p)-1]??'',0,1);
  $ini = strtoupper($first.($last?:''));
  return $ini ?: 'FA';
}
function resolveAvatarUrl(?string $avatar): ?string {
  if (!$avatar) return null;
  if (preg_match('~^(https?:)?//~i', $avatar) || str_starts_with($avatar,'data:image/')) return $avatar;
  $p1 = __DIR__ . '/../uploads/'  . $avatar;
  $p2 = __DIR__ . '/../imagenes/' . $avatar;
  if (is_file($p1)) return '../uploads/'  . rawurlencode($avatar);
  if (is_file($p2)) return '../imagenes/' . rawurlencode($avatar);
  return null;
}

/* ================= Perfil rápido (avatar + contacto) ================= */
$uid    = (int)$_SESSION['usuario_id'];
$nombre = $_SESSION['nombre'] ?? 'Farmacéutico';

// detectar columnas en `usuarios`
$emailCol   = colExists($conn,'usuarios','email') ? 'email' : (colExists($conn,'usuarios','correo') ? 'correo' : null);
$hasAvatar  = colExists($conn,'usuarios','avatar');
$hasTel     = colExists($conn,'usuarios','telefono');
$hasCel     = colExists($conn,'usuarios','celular') || colExists($conn,'usuarios','whatsapp'); // alguno de los dos
$celCol     = colExists($conn,'usuarios','celular') ? 'celular' : (colExists($conn,'usuarios','whatsapp') ? 'whatsapp' : null);
$hasExt     = colExists($conn,'usuarios','extension');
$hasDir     = colExists($conn,'usuarios','direccion');
$hasHorario = colExists($conn,'usuarios','horario');
$hasNotas   = colExists($conn,'usuarios','notas');

$cols = ['id','nombre'];
if ($emailCol)  $cols[]=$emailCol;
if ($hasAvatar) $cols[]='avatar';
if ($hasTel)    $cols[]='telefono';
if ($celCol)    $cols[]=$celCol;
if ($hasExt)    $cols[]='extension';
if ($hasDir)    $cols[]='direccion';
if ($hasHorario)$cols[]='horario';
if ($hasNotas)  $cols[]='notas';

$list = implode(',', array_map(fn($c)=>"`$c`",$cols));
$st = $conn->prepare("SELECT $list FROM usuarios WHERE id=? LIMIT 1");
$st->execute([$uid]);
$perfil = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$avatarUrl = $hasAvatar ? resolveAvatarUrl($perfil['avatar'] ?? null) : null;
$avatarIni = nameInitials($nombre);

// CSRF
if (empty($_SESSION['csrf_farma'])) $_SESSION['csrf_farma'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_farma'];

$flash = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='quick_profile') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $flash = '❌ Sesión inválida. Recarga la página.';
  } else {
    try{
      $updates=[]; $params=[];
      if ($hasTel) { $tel=trim($_POST['telefono']??''); $updates[]='telefono=?'; $params[] = ($tel===''?null:$tel); }
      if ($celCol) { $cel=trim($_POST['cel']??''); $updates[]="`$celCol`=?"; $params[] = ($cel===''?null:$cel); }
      if ($hasExt) { $ext=trim($_POST['extension']??''); $updates[]='extension=?'; $params[] = ($ext===''?null:$ext); }
      if ($hasDir) { $dir=trim($_POST['direccion']??''); $updates[]='direccion=?'; $params[] = ($dir===''?null:$dir); }
      if ($hasHorario){ $hor=trim($_POST['horario']??''); $updates[]='horario=?'; $params[] = ($hor===''?null:$hor); }
      if ($hasNotas){ $not=trim($_POST['notas']??''); $updates[]='notas=?'; $params[] = ($not===''?null:$not); }

      if ($hasAvatar && isset($_FILES['avatar']) && $_FILES['avatar']['error']!==UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) throw new Exception('Error al subir la imagen.');
        if ($_FILES['avatar']['size'] > 2*1024*1024) throw new Exception('La imagen supera 2 MB.');
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'],true)) throw new Exception('Formato no permitido (jpg, png, webp).');
        if (!is_dir(__DIR__.'/../uploads')) @mkdir(__DIR__.'/../uploads',0775,true);
        $fname = 'farm_avatar_'.$uid.'_'.time().'.'.$ext;
        $dest = __DIR__.'/../uploads/'.$fname;
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'],$dest)) throw new Exception('No se pudo guardar la imagen.');
        $updates[] = 'avatar=?'; $params[] = $fname;
        $avatarUrl = resolveAvatarUrl($fname);
      }

      if ($updates){
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

/* ================= KPIs ================= */
$totalProductos = safeScalar($conn, "SELECT COUNT(*) FROM productos");
$stockBajo      = safeScalar($conn, "SELECT COUNT(*) FROM productos WHERE stock < 10");
$expiran30      = safeScalar($conn, "SELECT COUNT(*) FROM productos WHERE fecha_caducidad IS NOT NULL AND fecha_caducidad BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");

if (tableExists($conn,'ventas')) {
  $ventasHoy = safeScalar($conn, "SELECT COALESCE(SUM(total),0) FROM ventas WHERE DATE(fecha_venta)=CURDATE()");
} else {
  $ventasHoy = safeScalar($conn, "
    SELECT COALESCE(SUM(d.cantidad * d.precio_unitario),0)
    FROM pedido_detalles d
    JOIN pedidos p ON p.id = d.pedido_id
    WHERE DATE(p.fecha_pedido) = CURDATE()
  ");
}

/* ================= Productos (solo lectura) ================= */
$stmt = $conn->query("SELECT id, nombre, descripcion, precio, stock, fecha_caducidad FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel del Farmacéutico — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b;
      --fs-primary:#2563eb; --fs-accent:#10b981;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
      --fs-warn:#f59e0b; --fs-danger:#ef4444;
    }
    body{
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
      background:
        radial-gradient(900px 500px at 8% -10%, rgba(37,99,235,.05), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgba(16,185,129,.05), transparent 60%),
        linear-gradient(180deg, var(--fs-bg) 0%, #fff 100%);
      color:var(--fs-text);
      letter-spacing:.2px;
    }
    .fs-navbar{ background:var(--fs-primary); }
    .fs-brand{ color:#fff; font-weight:700; }
    .avatar { width:36px; height:36px; border-radius:50%; overflow:hidden; border:2px solid rgba(255,255,255,.25); display:grid; place-items:center; background:#0f172a; color:#fff; font-weight:700; cursor:pointer; }
    .avatar img{ width:100%; height:100%; object-fit:cover; display:block; }

    .kpi{
      background:var(--fs-surface); border:1px solid var(--fs-border); border-radius:20px;
      box-shadow:var(--fs-shadow); padding:18px 16px; transition:transform .12s, box-shadow .22s, border-color .22s;
    }
    .kpi:hover{ transform:translateY(-2px); border-color:#dbeafe; box-shadow:0 16px 36px rgba(2,6,23,.10); }
    .kpi h6{ color:var(--fs-dim); font-weight:600; margin:0 0 6px; }
    .kpi b{ font-size:clamp(20px, 3.5vw, 28px); }

    .tile{ background:#fff; border:1px solid var(--fs-border); border-radius:16px; box-shadow:var(--fs-shadow);
      padding:12px 14px; display:flex; align-items:center; gap:12px; transition:transform .12s, border-color .22s, box-shadow .22s, background .22s; overflow:hidden; }
    .tile .ico{ width:42px;height:42px;border-radius:12px;display:grid;place-items:center; background:#ecfdf5; color:var(--fs-accent); font-size:1.2rem; }
    .tile small{ color:var(--fs-dim); display:block; line-height:1.1; }
    .tile:hover{ transform:translateY(-3px); border-color:#dbeafe; box-shadow:0 16px 36px rgba(2,6,23,.12); background:rgba(2,6,23,.02); }

    .card-fs{ background:#fff; border:1px solid var(--fs-border); border-radius:16px; box-shadow:var(--fs-shadow); }

    .table thead th{ background:#eff6ff; border-bottom:1px solid var(--fs-border); }
    .badge-warn{ background:rgba(245,158,11,.12); color:#92400e; }
    .badge-danger-soft{ background:rgba(239,68,68,.14); color:#7f1d1d; }

    .search{ border-radius:12px; border:1px solid var(--fs-border); padding:.55rem .8rem; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg fs-navbar py-3">
  <div class="container">
    <span class="navbar-brand fs-brand">FarmaSalud | Farmacéutico</span>
    <div class="ms-auto d-flex align-items-center gap-2 text-white">
      <span class="small d-none d-sm-inline">Hola, <strong><?= h($nombre) ?></strong></span>

      <!-- Avatar abre el panel lateral -->
      <a class="avatar" data-bs-toggle="offcanvas" href="#offPerfilFarma" role="button" aria-controls="offPerfilFarma" title="Mi perfil">
        <?php if ($avatarUrl): ?>
          <img src="<?= h($avatarUrl) ?>" alt="Avatar de <?= h($nombre) ?>">
        <?php else: ?>
          <span><?= h($avatarIni) ?></span>
        <?php endif; ?>
      </a>

      <a href="logout.php" class="btn btn-light btn-sm"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
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

    <!-- KPIs -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3"><div class="kpi h-100"><h6><i class="bi bi-cash-coin"></i> Ventas hoy</h6><b>$<?= number_format($ventasHoy,0,',','.') ?></b></div></div>
      <div class="col-6 col-md-3"><div class="kpi h-100"><h6><i class="bi bi-box-seam"></i> Productos</h6><b><?= number_format($totalProductos,0,',','.') ?></b></div></div>
      <div class="col-6 col-md-3"><div class="kpi h-100"><h6><i class="bi bi-thermometer-low"></i> Stock bajo</h6><b><?= number_format($stockBajo,0,',','.') ?></b></div></div>
      <div class="col-6 col-md-3"><div class="kpi h-100"><h6><i class="bi bi-alarm"></i> Caducan (&le;30d)</h6><b><?= number_format($expiran30,0,',','.') ?></b></div></div>
    </div>

    <!-- Acciones rápidas -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4 col-lg-3">
        <a class="tile d-block" href="registrar_venta.php">
          <div class="ico"><i class="bi bi-bag-check"></i></div>
          <div><strong>Registrar venta</strong><small>Punto de venta</small></div>
        </a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="tile d-block" href="agregar_stock.php">
          <div class="ico"><i class="bi bi-box-arrow-in-down"></i></div>
          <div><strong>Entrada de stock</strong><small>Registrar ingreso</small></div>
        </a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="tile d-block" href="movimientos_stock.php">
          <div class="ico"><i class="bi bi-clock-history"></i></div>
          <div><strong>Movimientos</strong><small>Historial de stock</small></div>
        </a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="tile d-block" href="alertas_caducidad.php">
          <div class="ico"><i class="bi bi-exclamation-triangle"></i></div>
          <div><strong>Alertas caducidad</strong><small>Próximos 30 días</small></div>
        </a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="tile d-block" href="reportar_danado.php">
          <div class="ico"><i class="bi bi-x-octagon"></i></div>
          <div><strong>Reportar daño</strong><small>Merma y roturas</small></div>
        </a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="tile d-block" href="ver_danados.php">
          <div class="ico"><i class="bi bi-file-earmark-text"></i></div>
          <div><strong>Ver daños</strong><small>Historial de reportes</small></div>
        </a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="tile d-block" href="inventario_filtro.php">
          <div class="ico"><i class="bi bi-funnel"></i></div>
          <div><strong>Inventario</strong><small>Filtros y consulta</small></div>
        </a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="tile d-block" href="ver_ventas.php">
          <div class="ico"><i class="bi bi-receipt"></i></div>
          <div><strong>Ventas</strong><small>Historial</small></div>
        </a>
      </div>
    </div>

    <!-- Inventario -->
    <div class="card-fs p-3 mb-2">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h5 class="mb-0"><i class="bi bi-archive"></i> Inventario de productos</h5>
        <input id="q" type="search" class="search" placeholder="🔍 Buscar por nombre o descripción…">
      </div>
    </div>

    <div class="table-responsive card-fs p-2">
      <table class="table table-hover align-middle mb-0" id="tbl">
        <thead>
          <tr>
            <th>Nombre</th>
            <th class="d-none d-md-table-cell">Descripción</th>
            <th class="text-end">Precio</th>
            <th class="text-center">Stock</th>
            <th class="text-center">Caducidad</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $hoy = new DateTime('today');
          $limit = (clone $hoy)->modify('+30 days');

          foreach ($productos as $p):
            $cad = $p['fecha_caducidad'] ? DateTime::createFromFormat('Y-m-d', $p['fecha_caducidad']) : null;
            $badge = '';
            if ($cad) {
              if ($cad < $hoy)       $badge = '<span class="badge badge-danger-soft">Vencido</span>';
              elseif ($cad <= $limit) $badge = '<span class="badge badge-warn">Pronto</span>';
            }
            $low = ((int)$p['stock'] < 10);
        ?>
          <tr class="<?= ($cad && $cad < $hoy) ? 'table-danger' : ($low ? 'table-warning' : '') ?>">
            <td><?= h($p['nombre']) ?></td>
            <td class="d-none d-md-table-cell"><?= h($p['descripcion']) ?></td>
            <td class="text-end">$<?= number_format((float)$p['precio'], 0, ',', '.') ?></td>
            <td class="text-center"><?= (int)$p['stock'] ?><?= $low ? ' <span class="badge text-bg-warning">bajo</span>' : '' ?></td>
            <td class="text-center">
              <?= h($p['fecha_caducidad'] ?: '—') ?>
              <?= $badge ? "<br>$badge" : '' ?>
            </td>
            <td class="text-center">
              <div class="btn-group btn-group-sm" role="group">
                <a class="btn btn-outline-success" title="Entrada de stock"
                   href="agregar_stock.php?producto_id=<?= (int)$p['id'] ?>"><i class="bi bi-plus-square"></i></a>
                <a class="btn btn-outline-secondary" title="Movimientos"
                   href="movimientos_stock.php?producto_id=<?= (int)$p['id'] ?>"><i class="bi bi-clock-history"></i></a>
                <a class="btn btn-outline-danger" title="Reportar dañado"
                   href="reportar_danado.php?producto_id=<?= (int)$p['id'] ?>"><i class="bi bi-x-octagon"></i></a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="text-secondary small mt-2">
      * Este panel es <strong>solo lectura</strong> en productos. La creación/edición/eliminación se realiza desde el panel de <em>Administración</em>.
    </p>

  </div>
</main>

<!-- OFFCANVAS: Perfil rápido del farmacéutico -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offPerfilFarma" aria-labelledby="offPerfilFarmaLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offPerfilFarmaLabel"><i class="bi bi-person-gear"></i> Mi información</h5>
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
            <label class="form-label">Foto (JPG/PNG/WebP, máx 2 MB)</label>
            <input type="file" name="avatar" id="avatarInput" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*">
          </div>
        <?php endif; ?>
      </div>

      <div class="mb-2">
        <label class="form-label">Nombre</label>
        <input type="text" class="form-control" value="<?= h($perfil['nombre'] ?? $nombre) ?>" disabled>
      </div>

      <?php if ($emailCol): ?>
      <div class="mb-2">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" value="<?= h($perfil[$emailCol] ?? '') ?>" disabled>
      </div>
      <?php endif; ?>

      <?php if ($hasTel): ?>
      <div class="mb-2">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="<?= h($perfil['telefono'] ?? '') ?>" placeholder="Ej. 601 555 1234">
      </div>
      <?php endif; ?>

      <?php if ($celCol): ?>
      <div class="mb-2">
        <label class="form-label"><?= $celCol==='whatsapp'?'WhatsApp / Celular':'Celular' ?></label>
        <input type="text" name="cel" class="form-control" value="<?= h($perfil[$celCol] ?? '') ?>" placeholder="Ej. 300 123 4567">
      </div>
      <?php endif; ?>

      <?php if ($hasExt): ?>
      <div class="mb-2">
        <label class="form-label">Extensión</label>
        <input type="text" name="extension" class="form-control" value="<?= h($perfil['extension'] ?? '') ?>" placeholder="Ej. 104">
      </div>
      <?php endif; ?>

      <?php if ($hasDir): ?>
      <div class="mb-2">
        <label class="form-label">Dirección (sede)</label>
        <input type="text" name="direccion" class="form-control" value="<?= h($perfil['direccion'] ?? '') ?>">
      </div>
      <?php endif; ?>

      <?php if ($hasHorario): ?>
      <div class="mb-2">
        <label class="form-label">Horario</label>
        <input type="text" name="horario" class="form-control" value="<?= h($perfil['horario'] ?? '') ?>" placeholder="L–V 8:00–17:00 / S 9:00–13:00">
      </div>
      <?php endif; ?>

      <?php if ($hasNotas): ?>
      <div class="mb-3">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="2" placeholder="Observaciones, especialidad, etc."><?= h($perfil['notas'] ?? '') ?></textarea>
      </div>
      <?php endif; ?>

      <div class="d-grid gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Filtro instantáneo por texto (nombre/descripcion)
  const q = document.getElementById('q');
  const rows = [...document.querySelectorAll('#tbl tbody tr')];
  if (q) {
    q.addEventListener('input', () => {
      const t = q.value.trim().toLowerCase();
      rows.forEach(tr => {
        const txt = tr.innerText.toLowerCase();
        tr.style.display = txt.includes(t) ? '' : 'none';
      });
    });
  }

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
        document.querySelector('#offPerfilFarma .avatar').appendChild(prev);
      }
      prev.src = url;
    });
  }
</script>
</body>
</html>




