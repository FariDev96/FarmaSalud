<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* DATA */
$stmt = $conn->query("SELECT id, nombre, descripcion, precio, stock, fecha_caducidad, imagen FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$usuario = $_SESSION['nombre'] ?? null;
$rol     = $_SESSION['rol_id'] ?? null;

/* rutas robustas */
$enModulos = file_exists(__DIR__ . '/login.php');
$baseHref  = $enModulos ? '' : 'modulos/';
$imgBaseA  = $enModulos ? '../imagenes/' : 'imagenes/';
$imgBaseB  = $enModulos ? 'imagenes/'   : '../imagenes/';

/* destacados (8) */
$destacados = array_slice($productos, 0, 8);

function fecha_legible(?string $ymd): string {
  if (!$ymd || $ymd === '0000-00-00') return '—';
  $ts = strtotime($ymd);
  return $ts ? date('d/m/Y', $ts) : htmlspecialchars($ymd);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>FarmaSalud — Tu farmacia en línea</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Compra tus medicamentos en minutos. Catálogo verificado, entrega rápida y pagos seguros.">
  <meta name="theme-color" content="#2563eb">

  <!-- Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      /* paleta login: blanco + azul + verde acento */
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
    html, body { height: 100%; }
    body{
      font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background:
        radial-gradient(1000px 600px at 12% -18%, rgba(37,99,235,.06), transparent 60%),
        radial-gradient(900px 550px at 100% 0%, rgba(16,185,129,.06), transparent 60%),
        linear-gradient(180deg, var(--fs-bg) 0%, #fff 100%);
      color: var(--fs-text);
      letter-spacing: .2px;
    }
    a { text-decoration: none; }

    /* NAVBAR */
    .fs-navbar { backdrop-filter: saturate(1.15) blur(10px); background: rgba(255,255,255,.9); border-bottom: 1px solid var(--fs-border); }
    .fs-brand { display:inline-flex; align-items:center; gap:.6rem; font-weight:700; color:var(--fs-text); }
    .fs-badge { width:40px; height:40px; border-radius:12px; display:grid; place-items:center; font-weight:900; color:#052e1a; background:linear-gradient(135deg,#93c5fd,var(--fs-primary)); box-shadow:0 6px 14px rgba(37,99,235,.22); }
    .fs-link { color:rgba(15,23,42,.72)!important; border-radius:10px; padding:.45rem .75rem; }
    .fs-link:hover, .fs-link.active { color:var(--fs-text)!important; background:rgba(2,6,23,.04); }

    /* HERO minimal */
    .fs-hero { padding: 48px 0 18px; }
    .fs-hero-card { border-radius:var(--fs-radius); background:var(--fs-surface); border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }
    .fs-hero-body { padding:24px; }
    .fs-title { font-size: clamp(26px, 2.1vw + 14px, 36px); margin:0 0 6px; }
    .fs-lead  { color: var(--fs-dim); margin: 0 0 16px; }
    .fs-search .input-group-text, .fs-search .form-control { background:#fff; border-color:var(--fs-border); color:var(--fs-text); }
    .fs-search .form-control{ padding:.9rem 1rem; }
    .fs-cta .btn { padding:.7rem 1rem; }

    /* Beneficios */
    .fs-benefits .card{ border-radius:14px; border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }
    .fs-benefits .bi{ font-size:1.25rem; color:var(--fs-primary); }

    /* Tarjetas de producto */
    .fs-card{
      height:100%;
      border-radius:14px;
      background:var(--fs-surface);
      border:1px solid var(--fs-border);
      box-shadow:var(--fs-shadow);
      overflow:hidden;
      transition: transform .12s ease, box-shadow .22s ease, border-color .22s ease;
    }
    .producto-img{ height:170px; object-fit:contain; background:#f8fafc; transition: filter .25s ease; }
    .fs-badges{ position:absolute; top:10px; left:10px; display:flex; gap:6px; }
    .fs-badge-pill{ padding:.28rem .55rem; font-size:.75rem; border-radius:999px; border:1px solid var(--fs-border); background:rgba(2,6,23,.04); color:#0f172a; }
    .fs-pill-warn{ background:rgba(245,158,11,.12); border-color:rgba(245,158,11,.35); color:#9a3412; }
    .fs-pill-danger{ background:rgba(220,38,38,.12); border-color:rgba(220,38,38,.35); color:#7f1d1d; }
    .fs-price{ font-weight:700; color:var(--fs-primary-600); }
    .fs-meta{ color:var(--fs-dim); font-size:.9rem; }
    .fs-desc{ color:var(--fs-dim); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:44px; }

    /* Overlay para hover oscuro (sin tocar HTML) */
    .fs-card .position-relative::after{
      content:""; position:absolute; inset:0; pointer-events:none;
      background: rgba(2,6,23,0); transition: background .25s ease;
    }
    .fs-card:hover{
      transform: translateY(-3px);
      box-shadow: 0 16px 36px rgba(2,6,23,.12);
      border-color: #dbeafe;
    }
    .fs-card:hover .producto-img{ filter: brightness(.88); }
    .fs-card:hover .position-relative::after{
      background: linear-gradient(to bottom, rgba(2,6,23,.14), rgba(2,6,23,0) 65%);
    }

    /* Botones */
    .btn-fs-primary{ --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary); --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600); --bs-btn-color:#fff; font-weight:600; border-radius:12px; box-shadow:0 8px 22px rgba(37,99,235,.25); }
    .btn-fs-ghost{ background:#fff; color:var(--fs-text); border:1px solid var(--fs-border); border-radius:12px; font-weight:600; }
    .btn-fs-ghost:hover{ background:rgba(2,6,23,.04); }

    /* Footer */
    .fs-footer{ color:var(--fs-dim); border-top:1px solid var(--fs-border); margin-top:36px; padding:26px 0; background:#fff; }

    /* ===== Afinado móvil ===== */
    @media (hover: none) and (pointer: coarse) {
      .fs-card:active { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(2,6,23,.10); }
      .fs-card:active .producto-img { filter: brightness(.88); }
    }
    @media (max-width: 480px) {
      .fs-hero { padding: 28px 0 8px; }
      .fs-hero-body { padding: 16px; }
      .fs-title { font-size: 22px; }
      .producto-img { height: 140px; }
      .fs-desc { -webkit-line-clamp: 3; }
      .fs-cta .btn { width: 100%; }
      .fs-badge-pill { font-size: .65rem; padding: .2rem .45rem; }
    }
    @media (prefers-reduced-motion: reduce) {
      .fs-card, .producto-img, .fs-card .position-relative::after { transition: none !important; }
    }
  </style>
</head>
<body>

<header>
  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg navbar-light fs-navbar py-3">
    <div class="container">
      <a class="navbar-brand fs-brand" href="<?= $enModulos ? 'index.php' : 'index.php' ?>">
        <span class="fs-badge" aria-hidden="true">FS</span><span>FarmaSalud</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#fsNav" aria-label="Abrir menú">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="fsNav">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link fs-link active" aria-current="page" href="#"><i class="bi bi-house-door"></i> Inicio</a></li>
          <li class="nav-item"><a class="nav-link fs-link" href="#destacados"><i class="bi bi-box-seam"></i> Destacados</a></li>
          <li class="nav-item"><a class="nav-link fs-link" href="<?= $baseHref ?>soporte.php"><i class="bi bi-life-preserver"></i> Soporte</a></li>
          <?php if ((int)$rol === 1): ?>
            <li class="nav-item"><a class="nav-link fs-link" href="<?= $baseHref ?>admin.php"><i class="bi bi-gear"></i> Admin</a></li>
          <?php endif; ?>
        </ul>
        <div class="d-flex align-items-center gap-2">
          <?php if ($usuario): ?>
            <?php if ((int)$rol === 2): ?><a href="<?= $baseHref ?>cliente.php" class="btn btn-fs-ghost btn-sm"><i class="bi bi-person"></i> Mi cuenta</a><?php endif; ?>
            <a href="<?= $baseHref ?>logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
          <?php else: ?>
            <a class="btn btn-fs-ghost btn-sm" href="<?= $baseHref ?>login.php"><i class="bi bi-door-open"></i> Iniciar sesión</a>
            <a class="btn btn-fs-primary btn-sm" href="<?= $baseHref ?>registro.php"><i class="bi bi-pencil-square"></i> Crear cuenta</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
</header>

<main>
  <!-- HERO: minimal -->
  <section class="fs-hero">
    <div class="container">
      <div class="fs-hero-card">
        <div class="fs-hero-body">
          <div class="row g-4 align-items-center">
            <div class="col-lg-8">
              <h1 class="fs-title">Encuentra tus medicamentos en minutos</h1>
              <p class="fs-lead">Busca por nombre o laboratorio y compra sin fricción.</p>
              <form class="fs-search mb-3" role="search" aria-label="Buscar productos destacados" onsubmit="event.preventDefault();">
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                  <input id="buscar" type="text" class="form-control" placeholder="Buscar en destacados..." aria-label="Buscar en destacados">
                </div>
              </form>
              <div class="fs-cta d-flex gap-2 flex-wrap">
                <?php if (!$usuario): ?>
                  <a href="<?= $baseHref ?>registro.php" class="btn btn-fs-primary"><i class="bi bi-person-plus"></i> Crear cuenta</a>
                  <a href="<?= $baseHref ?>productos.php" class="btn btn-fs-ghost"><i class="bi bi-cart3"></i> Ver catálogo</a>
                <?php else: ?>
                  <a href="<?= $baseHref ?>productos.php" class="btn btn-fs-primary"><i class="bi bi-cart3"></i> Ir al catálogo</a>
                  <?php if ((int)$rol === 2): ?><a href="<?= $baseHref ?>cliente.php" class="btn btn-fs-ghost"><i class="bi bi-person"></i> Mi cuenta</a><?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-lg-4"><!-- espacio para respirar o futura ilustración --></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- BENEFICIOS (confiable va aquí) -->
  <section class="py-3">
    <div class="container fs-benefits">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="card h-100 p-3">
            <div class="d-flex align-items-start gap-2"><i class="bi bi-shield-check" aria-hidden="true"></i>
              <div><h6 class="mb-1">Catálogo verificado</h6><p class="mb-0 text-secondary">Proveedores autorizados y lotes certificados.</p></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card h-100 p-3">
            <div class="d-flex align-items-start gap-2"><i class="bi bi-truck" aria-hidden="true"></i>
              <div><h6 class="mb-1">Entrega rápida</h6><p class="mb-0 text-secondary">Seguimiento y notificaciones de tu pedido.</p></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card h-100 p-3">
            <div class="d-flex align-items-start gap-2"><i class="bi bi-credit-card" aria-hidden="true"></i>
              <div><h6 class="mb-1">Pagos seguros</h6><p class="mb-0 text-secondary">Múltiples métodos con protección.</p></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card h-100 p-3">
            <div class="d-flex align-items-start gap-2"><i class="bi bi-headset" aria-hidden="true"></i>
              <div><h6 class="mb-1">Soporte humano</h6><p class="mb-0 text-secondary">Te acompañamos cuando lo necesites.</p></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- DESTACADOS -->
  <section id="destacados" class="py-2">
    <div class="container">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="h5 text-secondary mb-0">🧪 Productos destacados</h2>
        <a href="<?= $baseHref ?>productos.php" class="btn btn-sm btn-fs-ghost">Ver todo el catálogo</a>
      </div>

      <?php if (empty($destacados)): ?>
        <div class="alert alert-warning">Estamos preparando el catálogo. Vuelve pronto.</div>
      <?php else: ?>
        <div id="gridProductos" class="row g-4">
          <?php foreach ($destacados as $p): ?>
            <?php
              $imgPath = null;
              if (!empty($p['imagen'])) {
                $candA = ($enModulos ? __DIR__ . '/../imagenes/' : __DIR__ . '/imagenes/') . $p['imagen'];
                $candB = ($enModulos ? __DIR__ . '/imagenes/'   : __DIR__ . '/../imagenes/') . $p['imagen'];
                if (file_exists($candA)) $imgPath = $imgBaseA . $p['imagen'];
                elseif (file_exists($candB)) $imgPath = $imgBaseB . $p['imagen'];
              }
              if (!$imgPath) {
                $imgPath = file_exists(($enModulos ? __DIR__.'/../imagenes/generico.png' : __DIR__.'/imagenes/generico.png'))
                          ? ($enModulos ? '../imagenes/generico.png' : 'imagenes/generico.png')
                          : 'https://via.placeholder.com/300x170?text=FarmaSalud';
              }
              $stock = (int)$p['stock'];
              $agotado = $stock <= 0;
              $bajo    = ($stock > 0 && $stock <= 5);
            ?>
            <div class="col-sm-6 col-md-4 col-lg-3 producto"
                 data-nombre="<?= htmlspecialchars(mb_strtolower($p['nombre'])) ?>"
                 data-precio="<?= (float)$p['precio'] ?>"
                 data-stock="<?= $stock ?>">
              <div class="fs-card position-relative h-100 d-flex flex-column">
                <div class="position-relative">
                  <img src="<?= $imgPath ?>" class="producto-img w-100"
                       alt="Producto: <?= htmlspecialchars($p['nombre']) ?>"
                       loading="lazy" decoding="async">
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
                  <div class="d-flex align-items-center justify-content-between mt-1">
                    <span class="fs-price">$<?= number_format((float)$p['precio'], 0, ',', '.') ?></span>
                    <span class="fs-meta">Stock: <?= $stock ?> · Caduca: <?= fecha_legible($p['fecha_caducidad']) ?></span>
                  </div>
                  <div class="mt-auto pt-2">
                    <?php if ($usuario && (int)$rol === 2): ?>
                      <?php if ($agotado): ?>
                        <button class="btn btn-secondary w-100" disabled><i class="bi bi-cart-x"></i> No disponible</button>
                      <?php else: ?>
                        <a href="<?= $baseHref ?>carrito.php?agregar=<?= (int)$p['id'] ?>" class="btn btn-fs-primary w-100"><i class="bi bi-cart-plus"></i> Agregar</a>
                      <?php endif; ?>
                    <?php else: ?>
                      <a href="<?= $baseHref ?>login.php" class="btn btn-fs-ghost w-100"><i class="bi bi-lock"></i> Inicia sesión para comprar</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<footer class="fs-footer">
  <div class="container small d-flex flex-wrap justify-content-between gap-2">
    <div>© <?= date('Y') ?> FarmaSalud — Tu farmacia en línea.</div>
    <div class="d-flex gap-3">
      <a class="link-secondary" href="<?= $baseHref ?>soporte.php">Soporte</a>
      <a class="link-secondary" href="#">Términos</a>
      <a class="link-secondary" href="#">Privacidad</a>
    </div>
  </div>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Búsqueda en destacados (cliente)
  const buscar = document.getElementById('buscar');
  const grid   = document.getElementById('gridProductos');
  function filtrar(){
    if(!buscar || !grid) return;
    const q = (buscar.value || '').trim().toLowerCase();
    Array.from(grid.querySelectorAll('.producto')).forEach(card => {
      const name = (card.dataset.nombre || '').toLowerCase();
      card.style.display = name.includes(q) ? '' : 'none';
    });
  }
  buscar && buscar.addEventListener('input', filtrar);
  filtrar();

  // Simular hover en táctiles para feedback
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






