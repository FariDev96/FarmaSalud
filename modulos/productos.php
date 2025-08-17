<?php
session_start();

if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 2) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->query("SELECT id, nombre, descripcion, precio, stock, fecha_caducidad, imagen FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nombre = $_SESSION['nombre'] ?? 'Cliente';

/* utilidades */
function fecha_legible(?string $ymd): string {
  if (!$ymd || $ymd === '0000-00-00') return '—';
  $ts = strtotime($ymd);
  return $ts ? date('d/m/Y', $ts) : htmlspecialchars($ymd);
}

/* como estamos en /modulos, preparamos rutas de imágenes robustas */
$imgBaseA = '../imagenes/';   // lo normal desde /modulos
$imgBaseB = 'imagenes/';      // por si movieron carpetas sin actualizar rutas
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Catálogo — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">

  <!-- Tipografía + Bootstrap + Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --fs-bg: #f6f9fc;
      --fs-surface: #ffffff;
      --fs-border: #e8edf3;
      --fs-text: #0f172a;
      --fs-dim: #64748b;
      --fs-primary: #2563eb;
      --fs-primary-600:#1d4ed8;
      --fs-accent:#10b981;
      --fs-warn:#f59e0b;
      --fs-danger:#dc2626;
      --fs-radius: 16px;
      --fs-shadow: 0 12px 28px rgba(2,6,23,.06);
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
    a { text-decoration:none; }

    /* NAV */
    .fs-navbar{ backdrop-filter:saturate(1.15) blur(10px); background:rgba(255,255,255,.9); border-bottom:1px solid var(--fs-border); }
    .fs-brand{ display:inline-flex; align-items:center; gap:.6rem; font-weight:700; color:var(--fs-text); }
    .fs-badge{ width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-weight:900;color:#052e1a;background:linear-gradient(135deg,#93c5fd,var(--fs-primary)); box-shadow:0 6px 14px rgba(37,99,235,.22); }
    .fs-link{ color:rgba(15,23,42,.72)!important; border-radius:10px; padding:.45rem .75rem; }
    .fs-link:hover,.fs-link.active{ color:var(--fs-text)!important; background:rgba(2,6,23,.04); }

    /* Toolbar */
    .fs-toolbar{ position:sticky; top:0; z-index: 1020; padding:12px 0; background:linear-gradient(180deg,rgba(246,249,252,.9),rgba(255,255,255,.9)); backdrop-filter: blur(8px); border-bottom:1px solid var(--fs-border); }
    .fs-toolbar .card{ border-radius:14px; border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }

    /* Cards de producto */
    .fs-card{
      height:100%;
      border-radius:14px;
      background:var(--fs-surface);
      border:1px solid var(--fs-border);
      box-shadow:var(--fs-shadow);
      overflow:hidden;
      transition: transform .12s ease, box-shadow .22s ease, border-color .22s ease;
    }
    .producto-img{ height:180px; object-fit:contain; background:#f8fafc; transition: filter .25s ease; }
    .fs-badges{ position:absolute; top:10px; left:10px; display:flex; gap:6px; }
    .fs-badge-pill{ padding:.28rem .55rem; font-size:.75rem; border-radius:999px; border:1px solid var(--fs-border); background:rgba(2,6,23,.04); color:#0f172a; }
    .fs-pill-warn{ background:rgba(245,158,11,.12); border-color:rgba(245,158,11,.35); color:#9a3412; }
    .fs-pill-danger{ background:rgba(220,38,38,.12); border-color:rgba(220,38,38,.35); color:#7f1d1d; }
    .fs-price{ font-weight:700; color:var(--fs-primary-600); }
    .fs-meta{ color:var(--fs-dim); font-size:.9rem; }
    .fs-desc{ color:var(--fs-dim); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:44px; }

    .fs-card .position-relative::after{ content:""; position:absolute; inset:0; pointer-events:none; background:rgba(2,6,23,0); transition:background .25s ease; }
    .fs-card:hover{ transform:translateY(-3px); box-shadow:0 16px 36px rgba(2,6,23,.12); border-color:#dbeafe; }
    .fs-card:hover .producto-img{ filter:brightness(.88); }
    .fs-card:hover .position-relative::after{ background:linear-gradient(to bottom, rgba(2,6,23,.14), rgba(2,6,23,0) 65%); }

    .btn-fs-primary{ --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary); --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600); --bs-btn-color:#fff; font-weight:600; border-radius:12px; box-shadow:0 8px 22px rgba(37,99,235,.25); }
    .btn-fs-ghost{ background:#fff; color:var(--fs-text); border:1px solid var(--fs-border); border-radius:12px; font-weight:600; }
    .btn-fs-ghost:hover{ background:rgba(2,6,23,.04); }

    @media (hover:none) and (pointer:coarse){
      .fs-card:active{ transform:translateY(-2px); box-shadow:0 12px 28px rgba(2,6,23,.10); border-color:#dbeafe; }
      .fs-card:active .producto-img{ filter:brightness(.88); }
    }
    @media (max-width:480px){
      .producto-img{ height:150px; }
      .fs-desc{ -webkit-line-clamp:3; }
      .fs-toolbar .form-select, .fs-toolbar .form-control{ font-size:.95rem; }
    }
    @media (prefers-reduced-motion:reduce){
      .fs-card, .producto-img, .fs-card .position-relative::after{ transition:none !important; }
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
        <li class="nav-item"><a class="nav-link fs-link" href="cliente.php"><i class="bi bi-house-door"></i> Inicio</a></li>
        <li class="nav-item"><a class="nav-link fs-link active" aria-current="page" href="productos.php"><i class="bi bi-box-seam"></i> Catálogo</a></li>
        <li class="nav-item"><a class="nav-link fs-link" href="soporte.php"><i class="bi bi-life-preserver"></i> Soporte</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <span class="text-secondary small d-none d-md-inline">Hola, <strong><?= htmlspecialchars($nombre) ?></strong></span>
        <a href="carrito.php" class="btn btn-fs-ghost btn-sm"><i class="bi bi-cart3"></i> Carrito</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
      </div>
    </div>
  </div>
</nav>

<!-- TOOLBAR filtros -->
<div class="fs-toolbar">
  <div class="container">
    <div class="card p-3">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-6">
          <div class="input-group">
            <span class="input-group-text bg-white border-secondary-subtle"><i class="bi bi-search"></i></span>
            <input id="buscar" type="text" class="form-control bg-white border-secondary-subtle" placeholder="Buscar productos por nombre...">
          </div>
        </div>
        <div class="col-6 col-md-3">
          <select id="orden" class="form-select bg-white border-secondary-subtle">
            <option value="nombre-asc">Nombre (A–Z)</option>
            <option value="nombre-desc">Nombre (Z–A)</option>
            <option value="precio-asc">Precio (↑)</option>
            <option value="precio-desc">Precio (↓)</option>
            <option value="stock-asc">Stock (↑)</option>
            <option value="stock-desc">Stock (↓)</option>
          </select>
        </div>
        <div class="col-6 col-md-3 d-flex align-items-center justify-content-between">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="soloDisp">
            <label class="form-check-label" for="soloDisp">Solo disponibles</label>
          </div>
          <button id="limpiar" class="btn btn-fs-ghost"><i class="bi bi-arrow-counterclockwise"></i></button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- CONTENIDO -->
<main class="py-4">
  <div class="container">
    <h1 class="h4 text-secondary mb-3">Catálogo de productos</h1>

    <?php if (empty($productos)): ?>
      <div class="alert alert-warning">No hay productos disponibles.</div>
    <?php else: ?>
      <div id="gridProductos" class="row g-4">
        <?php foreach ($productos as $p): ?>
          <?php
            // Imagen robusta
            $imgPath = null;
            if (!empty($p['imagen'])) {
              $candA = __DIR__ . '/../imagenes/' . $p['imagen']; // ../imagenes/ desde /modulos
              $candB = __DIR__ . '/imagenes/'   . $p['imagen'];   // fallback
              if (file_exists($candA)) $imgPath = $imgBaseA . $p['imagen'];
              elseif (file_exists($candB)) $imgPath = $imgBaseB . $p['imagen'];
            }
            if (!$imgPath) {
              $imgPath = file_exists(__DIR__.'/../imagenes/generico.png') ? '../imagenes/generico.png'
                      : (file_exists(__DIR__.'/imagenes/generico.png') ? 'imagenes/generico.png'
                      : 'https://via.placeholder.com/300x170?text=FarmaSalud');
            }

            $stock  = (int)$p['stock'];
            $agotado = $stock <= 0;
            $bajo    = ($stock > 0 && $stock <= 5);
          ?>
          <div class="col-12 col-sm-6 col-md-4 col-lg-3 producto"
               data-nombre="<?= htmlspecialchars(mb_strtolower($p['nombre'])) ?>"
               data-precio="<?= (float)$p['precio'] ?>"
               data-stock="<?= $stock ?>">
            <div class="fs-card position-relative h-100 d-flex flex-column">
              <div class="position-relative">
                <img src="<?= $imgPath ?>" class="producto-img w-100"
                     alt="Producto: <?= htmlspecialchars($p['nombre']) ?>" loading="lazy" decoding="async">
                <div class="fs-badges">
                  <?php if ($agotado): ?>
                    <span class="fs-badge-pill fs-pill-danger"><i class="bi bi-x-octagon"></i> Agotado</span>
                  <?php elseif ($bajo): ?>
                    <span class="fs-badge-pill fs-pill-warn"><i class="bi bi-exclamation-triangle"></i> Bajo stock</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="p-3 d-flex flex-column" style="gap:.35rem;">
                <h5 class="mb-0"><?= htmlspecialchars($p['nombre']) ?></h5>
                <div class="fs-desc"><?= htmlspecialchars($p['descripcion']) ?></div>

                <!-- SOLO PRECIO PARA CLIENTE -->
                <div class="d-flex align-items-center mt-1">
                  <span class="fs-price">$<?= number_format((float)$p['precio'], 0, ',', '.') ?></span>
                </div>

                <div class="mt-auto pt-2">
                  <?php if ($agotado): ?>
                    <button class="btn btn-secondary w-100" disabled><i class="bi bi-cart-x"></i> No disponible</button>
                  <?php else: ?>
                    <a href="carrito.php?agregar=<?= (int)$p['id'] ?>" class="btn btn-fs-primary w-100">
                      <i class="bi bi-cart-plus"></i> Agregar
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<footer class="py-4 mt-4" style="border-top:1px solid var(--fs-border); background:#fff;">
  <div class="container small d-flex flex-wrap justify-content-between gap-2 text-secondary">
    <div>© <?= date('Y') ?> FarmaSalud — Catálogo.</div>
    <div class="d-flex gap-3">
      <a class="link-secondary" href="soporte.php">Soporte</a>
      <a class="link-secondary" href="#">Términos</a>
      <a class="link-secondary" href="#">Privacidad</a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Filtros en cliente
  const buscar  = document.getElementById('buscar');
  const orden   = document.getElementById('orden');
  const soloDisp= document.getElementById('soloDisp');
  const btnClr  = document.getElementById('limpiar');
  const grid    = document.getElementById('gridProductos');

  function applyFilters(){
    if(!grid) return;
    const q = (buscar.value || '').trim().toLowerCase();
    const onlyAvail = !!soloDisp.checked;

    const items = Array.from(grid.querySelectorAll('.producto'));

    // filtro por texto y disponibilidad
    items.forEach(card => {
      const name  = (card.dataset.nombre || '').toLowerCase();
      const stock = parseFloat(card.dataset.stock || '0');
      const visible = name.includes(q) && (!onlyAvail || stock > 0);
      card.style.display = visible ? '' : 'none';
    });

    // ordenar visibles
    const visibles = items.filter(el => el.style.display !== 'none');
    const [key, dir] = (orden.value || 'nombre-asc').split('-');

    visibles.sort((a,b)=>{
      let va, vb;
      if(key==='nombre'){ va=a.dataset.nombre||''; vb=b.dataset.nombre||''; return dir==='asc'? va.localeCompare(vb): vb.localeCompare(va); }
      if(key==='precio'){ va=parseFloat(a.dataset.precio||'0'); vb=parseFloat(b.dataset.precio||'0'); }
      else{ va=parseFloat(a.dataset.stock||'0'); vb=parseFloat(b.dataset.stock||'0'); }
      return dir==='asc'? va-vb : vb-va;
    });

    visibles.forEach(el => grid.appendChild(el));
  }

  buscar && buscar.addEventListener('input', applyFilters);
  orden && orden.addEventListener('change', applyFilters);
  soloDisp && soloDisp.addEventListener('change', applyFilters);
  btnClr && btnClr.addEventListener('click', () => {
    buscar.value=''; orden.value='nombre-asc'; soloDisp.checked=false; applyFilters();
  });

  applyFilters();

  // Feedback táctil: simula hover
  document.querySelectorAll('.fs-card').forEach(card => {
    card.addEventListener('touchstart', () => card.classList.add('is-touch'), {passive:true});
    const off = () => card.classList.remove('is-touch');
    card.addEventListener('touchend', off);
    card.addEventListener('touchcancel', off);
  });
  (function(){
    const style = document.createElement('style');
    style.textContent = `
      .fs-card.is-touch { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(2,6,23,.10); border-color:#dbeafe; }
      .fs-card.is-touch .producto-img { filter: brightness(.88); }
    `;
    document.head.appendChild(style);
  })();
</script>
</body>
</html>







