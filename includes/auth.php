<?php
require_once '../config/db.php';
require_once 'session.php';

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usuario = trim($_POST['usuario'] ?? '');
  $senha   = $_POST['senha'] ?? '';

  if ($usuario === '' || $senha === '') {
    $erros[] = 'Preencha todos os campos.';
  } else {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
      // Login válido
      $_SESSION['usuario'] = [
        'id' => $user['id'],
        'nome' => $user['nome'],
        'usuario' => $user['usuario'],
        'tipo' => $user['tipo']
      ];
      header('Location: ../public/portal.php');
      exit();
    }  else {
        $_SESSION['login_error'] = "Usuário ou senha inválidos.";
        header("Location: ../public/login.php");
    }
  }
}

