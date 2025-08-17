<?php
// includes/layout.php
// Minimal DRY layout for FarmaSalud
function fs_header(string $title='FarmaSalud', bool $solidNav=true): void { ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/public/css/fs.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head><body>
<nav class="navbar <?= $solidNav ? 'bg-white shadow-sm' : 'bg-body-tertiary bg-opacity-75 backdrop-blur'; ?> navbar-expand-lg border-bottom" style="--bs-border-color: var(--fs-border);">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="/"><i class="bi bi-capsule"></i> FarmaSalud</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/modulos/cliente.php">Cliente</a></li>
        <li class="nav-item"><a class="nav-link" href="/modulos/admin.php">Admin</a></li>
        <li class="nav-item"><a class="nav-link" href="/modulos/farmaceutico.php">Farmacéutico</a></li>
        <li class="nav-item"><a class="nav-link" href="/modulos/distribuidor.php">Distribuidor</a></li>
      </ul>
    </div>
  </div>
</nav>
<main class="min-vh-100" style="background:var(--fs-bg)">
<?php }

function fs_footer(): void { ?>
</main>
<footer class="border-top py-4 bg-white" style="--bs-border-color: var(--fs-border);">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
    <div class="text-muted small">© <?= date('Y') ?> FarmaSalud</div>
    <a class="btn btn-sm btn-fs-ghost" target="_blank"
       href="https://wa.me/573170613008?text=<?= rawurlencode('Hola, tengo una consulta sobre FarmaSalud') ?>">
      <i class="bi bi-whatsapp"></i> WhatsApp
    </a>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
<?php }
