<?php
session_start();

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style-index.css">
    <title>Portal PCM CCR</title>
</head>
<body>
<main class="login-wrapper">
  <form class="login-card" method="POST" action="../includes/auth.php">
  <img src="assets/img/ccr_metro_bahia.png" alt="">
  <br><br>
    <h2>Login</h2>

    <?php if (isset($_SESSION['login_error'])): ?>
      <p class="login-error"><?= $_SESSION['login_error']; unset($_SESSION['login_error']); ?></p>
    <?php endif; ?>

    <label for="usuario">Usuário</label>
    <input type="text" id="usuario" name="usuario" required>

    <label for="senha">Senha</label>
    <input type="password" id="senha" name="senha" required>

    <button type="submit" class="btn-login">Entrar</button>
    <a href="../index.php" class="back-link">← Voltar</a>
  </form>
</main>
</body>
</html>