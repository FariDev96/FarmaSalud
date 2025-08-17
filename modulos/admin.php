<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo admins
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
  header("Location: login.php");
  exit;
}

$uid    = (int)($_SESSION['usuario_id'] ?? 0);
$nombre = $_SESSION['nombre'] ?? 'Admin';

/* ================= Helpers ================= */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function safeCount(PDO $conn, string $sql): int {
  try { return (int)$conn->query($sql)->fetchColumn(); } catch(Throwable $e){ return 0; }
}
function hasTable(PDO $conn, string $t): bool {
  try { $conn->query("SELECT 1 FROM `$t` LIMIT 1"); return true; } catch(Throwable $e){ return false; }
}
function tableExists(PDO $conn, string $t): bool {
  $q = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $q->execute([$t]);
  return (int)$q->fetchColumn() > 0;
}
function colExists(PDO $conn, string $t, string $c): bool {
  $q = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $q->execute([$t,$c]);
  return (int)$q->fetchColumn() > 0;
}

/* ============ Avatar helpers ============ */
function nameInitials(string $name): string {
  $name = trim(preg_replace('/\s+/', ' ', $name));
  if ($name === '') return 'AD';
  $parts = explode(' ', $name);
  $first = mb_substr($parts[0] ?? '', 0, 1);
  $last  = mb_substr($parts[count($parts)-1] ?? '', 0, 1);
  $ini = strtoupper($first . ($last ?: ''));
  return $ini ?: 'AD';
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

/* ============ Perfil: detección de columnas y carga de datos ============ */
$hasNombre   = colExists($conn,'usuarios','nombre');
$hasEmail    = colExists($conn,'usuarios','email') || colExists($conn,'usuarios','correo');
$emailCol    = colExists($conn,'usuarios','email') ? 'email' : (colExists($conn,'usuarios','correo') ? 'correo' : null);
$hasTel      = colExists($conn,'usuarios','telefono');
$hasDir      = colExists($conn,'usuarios','direccion');
$hasAvatar   = colExists($conn,'usuarios','avatar');

$profileCols = ['id'];
if ($hasNombre) $profileCols[] = 'nombre';
if ($emailCol)  $profileCols[] = $emailCol;
if ($hasTel)    $profileCols[] = 'telefono';
if ($hasDir)    $profileCols[] = 'direccion';
if ($hasAvatar) $profileCols[] = 'avatar';

$profile = [];
if ($profileCols) {
  $cols = implode(',', array_map(fn($c)=>"`$c`", $profileCols));
  $st = $conn->prepare("SELECT $cols FROM usuarios WHERE id=? LIMIT 1");
  $st->execute([$uid]);
  $profile = $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

$avatarUrl = $hasAvatar ? resolveAvatarUrl($profile['avatar'] ?? null) : null;
$avatarIni = nameInitials($nombre);

/* ============ CSRF perfil ============ */
if (empty($_SESSION['csrf_profile'])) $_SESSION['csrf_profile'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_profile'];

$flashProfile = '';

/* ============ Guardar perfil (POST) ============ */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save_profile') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $flashProfile = '❌ Sesión inválida. Recarga la página.';
  } else {
    try {
      $updates = [];
      $params  = [];
      // nombre
      if ($hasNombre) {
        $nombreNew = trim($_POST['nombre'] ?? '');
        if ($nombreNew === '') throw new Exception('El nombre no puede estar vacío.');
        $updates[] = "nombre = ?";
        $params[]  = $nombreNew;
      }
      // email/correo
      if ($emailCol) {
        $emailNew = trim($_POST['email'] ?? '');
        if ($emailNew !== '' && !filter_var($emailNew, FILTER_VALIDATE_EMAIL)) {
          throw new Exception('Email inválido.');
        }
        $updates[] = "`$emailCol` = ?";
        $params[]  = ($emailNew === '' ? null : $emailNew);
      }
      // telefono
      if ($hasTel) {
        $tel = trim($_POST['telefono'] ?? '');
        $updates[] = "telefono = ?";
        $params[]  = ($tel === '' ? null : $tel);
      }
      // direccion
      if ($hasDir) {
        $dir = trim($_POST['direccion'] ?? '');
        $updates[] = "direccion = ?";
        $params[]  = ($dir === '' ? null : $dir);
      }
      // avatar
      if ($hasAvatar && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) throw new Exception('Error al subir la imagen.');
        if ($_FILES['avatar']['size'] > 2*1024*1024) throw new Exception('La imagen supera 2 MB.');
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) throw new Exception('Formato no permitido (jpg, png, webp).');
        $fname = 'avatar_'.$uid.'_'.time().'.'.$ext;
        $dest  = __DIR__ . '/../uploads/' . $fname;
        if (!is_dir(__DIR__ . '/../uploads')) @mkdir(__DIR__ . '/../uploads', 0775, true);
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) throw new Exception('No se pudo guardar la imagen.');
        $updates[] = "avatar = ?";
        $params[]  = $fname;
        $avatarUrl = resolveAvatarUrl($fname); // para reflejar en la vista
      }

      if ($updates) {
        $params[] = $uid;
        $sql = "UPDATE usuarios SET ".implode(', ',$updates)." WHERE id=?";
        $conn->prepare($sql)->execute($params);

        // refrescar nombre en sesión
        if ($hasNombre) {
          $_SESSION['nombre'] = $nombreNew;
          $nombre = $nombreNew;
          $avatarIni = nameInitials($nombreNew);
        }
        // recargar perfil
        if ($profileCols) {
          $cols = implode(',', array_map(fn($c)=>"`$c`", $profileCols));
          $st = $conn->prepare("SELECT $cols FROM usuarios WHERE id=? LIMIT 1");
          $st->execute([$uid]);
          $profile = $st->fetch(PDO::FETCH_ASSOC) ?: $profile;
        }
      }

      $flashProfile = '✅ Perfil actualizado correctamente.';
    } catch (Throwable $e) {
      $flashProfile = '❌ '.$e->getMessage();
    }
  }
}

/* ============ Detección de fuente de finanzas (igual a finanzas.php) ============ */
function detectFinanceSource(PDO $conn): array {
  $hasV = tableExists($conn,'ventas');
  $vTotal = $hasV && colExists($conn,'ventas','total') ? 'total'
          : ($hasV && colExists($conn,'ventas','monto') ? 'monto' : null);
  $vFecha = $hasV && colExists($conn,'ventas','fecha') ? 'fecha'
          : ($hasV && colExists($conn,'ventas','fecha_venta') ? 'fecha_venta' : null);
  if ($vTotal && $vFecha) return ['type'=>'ventas','note'=>"Usando ventas($vTotal, $vFecha)", 'v_total'=>$vTotal, 'v_fecha'=>$vFecha];

  $hasD = tableExists($conn,'pedido_detalles');
  $hasP = tableExists($conn,'pedidos');
  if ($hasD && $hasP
      && colExists($conn,'pedido_detalles','cantidad')
      && colExists($conn,'pedido_detalles','precio_unitario')
      && colExists($conn,'pedido_detalles','pedido_id')
      && colExists($conn,'pedidos','fecha_pedido')) {
    return ['type'=>'detalles','note'=>'Usando pedido_detalles(cantidad, precio_unitario) + pedidos(fecha_pedido)', 'p_fecha'=>'fecha_pedido'];
  }
  return ['type'=>'none','note'=>'Sin datos: crea ventas(total, fecha) o usa pedido_detalles + pedidos(fecha_pedido)'];
}
function sumRevenue(PDO $conn, string $period, array $src): float {
  try{
    if ($src['type']==='ventas') {
      $date = $src['v_fecha']; $amt = $src['v_total'];
      $sql = $period==='today'
        ? "SELECT COALESCE(SUM($amt),0) FROM ventas WHERE DATE($date)=CURDATE()"
        : "SELECT COALESCE(SUM($amt),0) FROM ventas WHERE DATE_FORMAT($date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')";
      return (float)$conn->query($sql)->fetchColumn();
    }
    if ($src['type']==='detalles') {
      $pDate = 'p.'.$src['p_fecha'];
      $sql = $period==='today'
        ? "SELECT COALESCE(SUM(d.cantidad*d.precio_unitario),0) FROM pedido_detalles d JOIN pedidos p ON p.id=d.pedido_id WHERE DATE($pDate)=CURDATE()"
        : "SELECT COALESCE(SUM(d.cantidad*d.precio_unitario),0) FROM pedido_detalles d JOIN pedidos p ON p.id=d.pedido_id WHERE DATE_FORMAT($pDate,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')";
      return (float)$conn->query($sql)->fetchColumn();
    }
  }catch(Throwable $e){}
  return 0.0;
}

/* ================= KPIs ================= */
$kpi_pedidos   = safeCount($conn, "SELECT COUNT(*) FROM pedidos");
$kpi_usuarios  = safeCount($conn, "SELECT COUNT(*) FROM usuarios");
$kpi_productos = safeCount($conn, "SELECT COUNT(*) FROM productos");

// Fuente de finanzas
$FIN_SRC    = detectFinanceSource($conn);
$sourceNote = $FIN_SRC['note'];
$ingresos_mes  = sumRevenue($conn, 'month', $FIN_SRC);
$ingresos_hoy  = sumRevenue($conn, 'today', $FIN_SRC);

// Soporte
$kpi_soporte_pend = 0;
$kpi_soporte_tot  = 0;
if (hasTable($conn, 'mensajes_soporte')) {
  $kpi_soporte_tot  = safeCount($conn, "SELECT COUNT(*) FROM mensajes_soporte");
  $kpi_soporte_pend = safeCount($conn, "SELECT COUNT(*) FROM mensajes_soporte WHERE (LOWER(COALESCE(estado,''))='pendiente' OR COALESCE(estado,'')='')");
}

/* ================= Pedidos recientes ================= */
$pedidos = [];
try {
  $stmt = $conn->query("
    SELECT p.id, u.nombre AS cliente, p.fecha_pedido, p.estado
    FROM pedidos p
    JOIN usuarios u ON u.id = p.usuario_id
    ORDER BY p.fecha_pedido DESC
    LIMIT 8
  ");
  $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) { $pedidos = []; }

/* ================= Tickets de soporte recientes ================= */
$soportes = [];
if ($kpi_soporte_tot > 0) {
  try {
    $q = $conn->query("
      SELECT m.id, m.asunto, COALESCE(m.estado,'Pendiente') AS estado, m.fecha,
             u.nombre AS remitente
      FROM mensajes_soporte m
      JOIN usuarios u ON u.id = m.usuario_id
      ORDER BY m.fecha DESC
      LIMIT 5
    ");
    $soportes = $q->fetchAll(PDO::FETCH_ASSOC);
  } catch(Throwable $e) { $soportes = []; }
}

/* ================= Serie últimos 7 días (gráfico) ================= */
$labels = []; $series = [];
for ($i = 6; $i >= 0; $i--) {
  $day = new DateTime("-$i day");
  $labels[] = $day->format('d/m');
  $series[$day->format('Y-m-d')] = 0;
}
try {
  $q = $conn->query("
    SELECT DATE(fecha_pedido) d, COUNT(*) c
    FROM pedidos
    WHERE fecha_pedido >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(fecha_pedido)
    ORDER BY d
  ");
  foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $ymd = $row['d'];
    if (isset($series[$ymd])) $series[$ymd] = (int)$row['c'];
  }
} catch(Throwable $e) {}
$values = array_values($series);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Administración — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">

  <!-- Inter + Bootstrap + Icons + Chart.js -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#ffffff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b;
      --fs-primary:#2563eb; --fs-primary-600:#1d4ed8; --fs-accent:#10b981;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
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
    a{text-decoration:none}

    /* NAV */
    .fs-navbar{ backdrop-filter:saturate(1.1) blur(8px); background:var(--fs-primary); }
    .fs-brand{ color:#fff; font-weight:700; }
    .fs-nav-action .btn{ border-radius:12px; }
    .notif{ position:relative; color:#fff; }
    .notif .badge{ position:absolute; top:-6px; right:-6px; }

    /* Avatar */
    .avatar { width:36px; height:36px; border-radius:50%; overflow:hidden; border:2px solid rgba(255,255,255,.4); display:grid; place-items:center; background:#1e293b; font-weight:700; color:#fff; cursor:pointer; }
    .avatar img{ width:100%; height:100%; object-fit:cover; display:block; }

    /* KPI cards */
    .kpi{
      background:var(--fs-surface);
      border:1px solid var(--fs-border);
      border-radius:20px;
      box-shadow:var(--fs-shadow);
      padding:20px;
      transition:transform .12s ease, box-shadow .22s ease, border-color .22s ease;
    }
    .kpi:hover{ transform:translateY(-2px); border-color:#dbeafe; box-shadow:0 16px 36px rgba(2,6,23,.10); }
    .kpi h6{ color:var(--fs-dim); margin:0 0 6px; font-weight:600; }
    .kpi b{ font-size:clamp(20px, 2.6vw, 28px); }

    /* Quick actions */
    .action-tile{
      position:relative;
      background:var(--fs-surface);
      border:1px solid var(--fs-border);
      border-radius:16px;
      box-shadow:var(--fs-shadow);
      padding:14px 16px;
      display:flex; align-items:center; gap:12px;
      transition:transform .12s ease, border-color .22s ease, box-shadow .22s ease, background .22s ease;
      overflow: hidden;
    }
    .action-tile .ico{
      width:44px;height:44px;border-radius:12px;display:grid;place-items:center;
      background:#eff6ff; color:var(--fs-primary); font-size:1.2rem; flex:0 0 auto;
    }
    .action-tile .txt small{ color:var(--fs-dim); display:block; }
    .action-tile:hover{ transform:translateY(-3px); border-color:#dbeafe; box-shadow:0 16px 36px rgba(2,6,23,.12); }

    /* Cards comunes */
    .card-soft{ background:var(--fs-surface); border:1px solid var(--fs-border); border-radius:16px; box-shadow:var(--fs-shadow); padding:16px; }
    .fin-stat h6{ margin:0; color:var(--fs-dim); }
    .fin-stat b{ font-size:clamp(18px,2.4vw,26px); }

    .chart-wrap{ position:relative; height:clamp(220px, 32vh, 340px); }
    @media (min-width: 992px){ .chart-wrap{ height:clamp(260px, 34vh, 360px); } }

    .fs-table thead th{ background:#eaf1ff; color:#1e293b; border-bottom:1px solid var(--fs-border); }
    .fs-table td, .fs-table th{ vertical-align:middle; }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fs-navbar py-3">
  <div class="container-fluid">
    <span class="navbar-brand fs-brand">FarmaSalud | Admin</span>
    <div class="ms-auto fs-nav-action d-flex align-items-center gap-3 text-white">
      <?php if ($kpi_soporte_pend > 0): ?>
        <a class="notif position-relative" href="soporte_admin.php?estado=Pendiente" title="Tickets de soporte pendientes">
          <i class="bi bi-bell" style="font-size:1.25rem;"></i>
          <span class="badge bg-danger rounded-pill"><?= $kpi_soporte_pend ?></span>
        </a>
      <?php endif; ?>
      <span class="small d-none d-sm-inline">Bienvenido, <strong><?= h($nombre) ?></strong></span>

      <!-- Avatar: abre el panel de perfil -->
      <a class="avatar" data-bs-toggle="offcanvas" href="#offPerfil" role="button" aria-controls="offPerfil" title="Mi perfil">
        <?php if ($avatarUrl): ?>
          <img src="<?= h($avatarUrl) ?>" alt="Avatar de <?= h($nombre) ?>">
        <?php else: ?>
          <span><?= h($avatarIni) ?></span>
        <?php endif; ?>
      </a>

      <a href="logout.php" class="btn btn-light btn-sm"><i class="bi bi-lock"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>

<?php if ($flashProfile): ?>
  <div class="container mt-3">
    <div class="alert <?= str_starts_with($flashProfile,'✅') ? 'alert-success' : 'alert-danger' ?>"><?= h($flashProfile) ?></div>
  </div>
<?php endif; ?>

<main class="py-4">
  <div class="container">

    <!-- KPI GRID -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3"><div class="kpi h-100"><h6><i class="bi bi-receipt-cutoff"></i> Pedidos</h6><b><?= number_format($kpi_pedidos,0,',','.') ?></b></div></div>
      <div class="col-6 col-md-3"><div class="kpi h-100"><h6><i class="bi bi-people"></i> Usuarios</h6><b><?= number_format($kpi_usuarios,0,',','.') ?></b></div></div>
      <div class="col-6 col-md-3"><div class="kpi h-100"><h6><i class="bi bi-box-seam"></i> Productos</h6><b><?= number_format($kpi_productos,0,',','.') ?></b></div></div>
      <div class="col-6 col-md-3"><div class="kpi h-100"><h6><i class="bi bi-cash-coin"></i> Ingresos (mes)</h6><b>$<?= number_format($ingresos_mes,0,',','.') ?></b></div></div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4 col-lg-3">
        <a class="action-tile" href="gestionar_productos.php"><div class="ico"><i class="bi bi-box-seam"></i></div><div class="txt"><strong>Productos</strong><small>Crear, editar, stock</small></div></a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="action-tile" href="gestionar_usuarios.php"><div class="ico"><i class="bi bi-people"></i></div><div class="txt"><strong>Usuarios</strong><small>Altas y permisos</small></div></a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="action-tile" href="inventario_filtro.php"><div class="ico"><i class="bi bi-funnel"></i></div><div class="txt"><strong>Inventario</strong><small>Filtros y exportar</small></div></a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="action-tile" href="reportes.php"><div class="ico"><i class="bi bi-graph-up"></i></div><div class="txt"><strong>Reportes</strong><small>Ventas y KPIs</small></div></a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="action-tile" href="registrar_venta.php"><div class="ico"><i class="bi bi-bag-check"></i></div><div class="txt"><strong>Registrar venta</strong><small>Punto de venta</small></div></a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="action-tile" href="reportar_danado.php"><div class="ico"><i class="bi bi-exclamation-octagon"></i></div><div class="txt"><strong>Daños</strong><small>Registro y control</small></div></a>
      </div>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="action-tile" href="ver_danados.php"><div class="ico"><i class="bi bi-file-earmark-text"></i></div><div class="txt"><strong>Listar daños</strong><small>Historial</small></div></a>
      </div>

      <!-- SOPORTE -->
      <div class="col-6 col-md-4 col-lg-3">
        <a class="action-tile" href="soporte_admin.php">
          <div class="ico" style="background:#fef2f2;color:#ef4444"><i class="bi bi-life-preserver"></i></div>
          <div class="txt"><strong>Soporte</strong><small>Tickets y respuestas</small></div>
          <?php if ($kpi_soporte_pend>0): ?><span class="badge bg-danger ms-auto"><?= $kpi_soporte_pend ?> pendientes</span><?php endif; ?>
        </a>
      </div>

      <div class="col-6 col-md-4 col-lg-3">
        <a class="action-tile" href="finanzas.php"><div class="ico" style="background:#ecfdf5;color:var(--fs-accent)"><i class="bi bi-cash-stack"></i></div><div class="txt"><strong>Finanzas</strong><small>Ingresos & costos</small></div></a>
      </div>
    </div>

    <!-- RESUMEN FINANCIERO + GRÁFICO -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-lg-6">
        <div class="card-soft h-100">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="mb-0"><i class="bi bi-cash-coin"></i> Resumen financiero</h6>
            <a href="finanzas.php" class="btn btn-sm btn-outline-primary">Ir a finanzas</a>
          </div>
          <div class="row g-3">
            <div class="col-6"><div class="fin-stat"><h6>Ingresos hoy</h6><b>$<?= number_format($ingresos_hoy,0,',','.') ?></b></div></div>
            <div class="col-6"><div class="fin-stat"><h6>Ingresos del mes</h6><b>$<?= number_format($ingresos_mes,0,',','.') ?></b></div></div>
          </div>
          <div class="text-secondary small mt-2"><i class="bi bi-database"></i> <?= h($sourceNote) ?></div>
        </div>
      </div>

      <div class="col-12 col-lg-6">
        <div class="card-soft h-100">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="mb-0"><i class="bi bi-activity"></i> Pedidos (últimos 7 días)</h6>
          </div>
          <div class="chart-wrap"><canvas id="chartPedidos" role="img" aria-label="Pedidos últimos siete días"></canvas></div>
        </div>
      </div>
    </div>

    <!-- SOPORTE RECIENTE -->
    <?php if ($kpi_soporte_tot > 0): ?>
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0"><i class="bi bi-life-preserver"></i> Tickets de soporte recientes</h5>
        <div class="d-flex align-items-center gap-2">
          <?php if ($kpi_soporte_pend>0): ?><span class="badge text-bg-danger"><?= $kpi_soporte_pend ?> pendientes</span><?php endif; ?>
          <a href="soporte_admin.php" class="btn btn-sm btn-outline-primary">Ir a soporte</a>
        </div>
      </div>
      <div class="table-responsive mb-4">
        <table class="table table-sm fs-table">
          <thead class="table-borderless">
            <tr><th>ID</th><th>Remitente</th><th>Asunto</th><th>Fecha</th><th>Estado</th><th class="text-center">Acciones</th></tr>
          </thead>
          <tbody>
          <?php if (!$soportes): ?>
            <tr><td colspan="6" class="text-center text-secondary py-4">Sin tickets aún.</td></tr>
          <?php else: foreach ($soportes as $s): ?>
            <tr>
              <td><?= (int)$s['id'] ?></td>
              <td><?= h($s['remitente']) ?></td>
              <td><?= h($s['asunto']) ?></td>
              <td><?= h($s['fecha']) ?></td>
              <td>
                <?php $est = strtolower($s['estado']); $badge='secondary';
                  if ($est==='pendiente'||$est==='') $badge='warning';
                  if ($est==='resuelto') $badge='success'; ?>
                <span class="badge text-bg-<?= $badge ?>"><?= h($s['estado']) ?></span>
              </td>
              <td class="text-center">
                <a href="soporte_admin.php?buscar=<?= urlencode($s['asunto']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- PEDIDOS RECIENTES -->
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h5 class="mb-0"><i class="bi bi-list-check"></i> Pedidos recientes</h5>
      <a href="reportes.php" class="btn btn-sm btn-outline-primary">Ver más</a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm fs-table">
        <thead class="table-borderless">
          <tr><th>ID</th><th>Cliente</th><th>Fecha</th><th>Estado</th><th class="text-center">Acciones</th></tr>
        </thead>
        <tbody>
        <?php if (!$pedidos): ?>
          <tr><td colspan="5" class="text-center text-secondary py-4">No hay pedidos registrados.</td></tr>
        <?php else: foreach ($pedidos as $p): ?>
          <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= h($p['cliente']) ?></td>
            <td><?= h($p['fecha_pedido']) ?></td>
            <td>
              <?php
                $estado = strtolower($p['estado']);
                $badge = 'secondary';
                if ($estado === 'pendiente') $badge = 'warning';
                if ($estado === 'enviado')   $badge = 'info';
                if ($estado === 'entregado') $badge = 'success';
                if ($estado === 'cancelado') $badge = 'danger';
              ?>
              <span class="badge text-bg-<?= $badge ?>"><?= h($p['estado']) ?></span>
            </td>
            <td class="text-center"><a href="ver_pedido.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<!-- OFFCANVAS PERFIL -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offPerfil" aria-labelledby="offPerfilLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offPerfilLabel"><i class="bi bi-person-circle"></i> Mi perfil</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body">
    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="action" value="save_profile">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">

      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="avatar" style="width:64px;height:64px;border-width:3px;">
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

      <?php if ($hasNombre): ?>
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input type="text" class="form-control" name="nombre" required value="<?= h($profile['nombre'] ?? $nombre) ?>">
        </div>
      <?php endif; ?>

      <?php if ($emailCol): ?>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" value="<?= h($profile[$emailCol] ?? '') ?>">
        </div>
      <?php endif; ?>

      <?php if ($hasTel): ?>
        <div class="mb-3">
          <label class="form-label">Teléfono</label>
          <input type="text" class="form-control" name="telefono" value="<?= h($profile['telefono'] ?? '') ?>">
        </div>
      <?php endif; ?>

      <?php if ($hasDir): ?>
        <div class="mb-3">
          <label class="form-label">Dirección</label>
          <input type="text" class="form-control" name="direccion" value="<?= h($profile['direccion'] ?? '') ?>">
        </div>
      <?php endif; ?>

      <div class="d-grid gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="offcanvas">Cerrar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ctx = document.getElementById('chartPedidos');
const labels = <?= json_encode($labels) ?>;
const data    = <?= json_encode(array_values($values)) ?>;

const gradient = (() => {
  const g = ctx.getContext('2d').createLinearGradient(0, 0, 0, ctx.parentElement.clientHeight);
  g.addColorStop(0, 'rgba(37,99,235,0.35)');
  g.addColorStop(1, 'rgba(37,99,235,0.02)');
  return g;
})();

new Chart(ctx, {
  type: 'line',
  data: { labels, datasets: [{ label: 'Pedidos', data, tension: .35, fill: true, backgroundColor: gradient, borderColor: 'rgba(37,99,235,1)', borderWidth: 2, pointRadius: 3, pointHoverRadius: 5 }]},
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
    interaction: { mode: 'index', intersect: false },
    scales: { x: { grid: { display:false } }, y: { beginAtZero:true, grid: { color:'rgba(2,6,23,.06)' } } }
  }
});

// Preview del avatar
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
      document.querySelector('.offcanvas .avatar').appendChild(prev);
    }
    prev.src = url;
  });
}
</script>
</body>
</html>









