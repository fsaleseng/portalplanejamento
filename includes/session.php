<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['usuario']);
}

function is_admin() {
    return isset($_SESSION['usuario']['tipo']) && $_SESSION['usuario']['tipo'] === 'admin';
}

function is_gerente() {
    return isset($_SESSION['usuario']['tipo']) && $_SESSION['usuario']['tipo'] === 'gerente';
}

function verificaLogin() {
    if (!is_logged_in()) {
        header('Location: ../public/login.php');
        exit();
    }
}


function verificaPermissao(array $tiposPermitidos) {
  verificaLogin();
  $tipoUsuario = $_SESSION['usuario']['tipo'] ?? '';
  if (!in_array($tipoUsuario, $tiposPermitidos)) {
    http_response_code(403);
    echo "<h2>Acesso negado.</h2><p>Você não tem permissão para acessar esta página.</p>";
    exit();
  }
}