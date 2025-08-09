<?php
session_start();
session_unset();
session_destroy();

// Redirigir al login en el mismo subdirectorio
header("Location: login.php");
exit;
