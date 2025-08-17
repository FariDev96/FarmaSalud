<?php
// includes/csrf.php
// CSRF helper: token generation, field helper and validation.
// Uso:
//   require_once __DIR__.'/../includes/csrf.php';
//   if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_validate(); }
//   <form method="post"><?= csrf_field() ?></form>

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="'.$t.'">';
}

function csrf_validate() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent  = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        $valid = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
        if (!$sent || !$valid || !hash_equals($valid, $sent)) {
            http_response_code(419); // Authentication Timeout
            exit('CSRF token inválido o ausente.');
        }
        // Rotar token en cada POST
        unset($_SESSION['csrf_token']);
    }
}

