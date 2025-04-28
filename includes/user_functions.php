<?php
require_once __DIR__ . '/../config/db.php';

function listarUsuarios() {
  global $pdo;
  $stmt = $pdo->query("SELECT id, nome, usuario, tipo FROM usuarios");
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarUsuarioPorId($id) {
  global $pdo;
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
  $stmt->execute([$id]);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function criarUsuario($nome, $usuario, $senha, $tipo) {
  global $pdo;
  
  try {
      // Validação básica
      if (empty($nome) || empty($usuario) || empty($senha) || empty($tipo)) {
          return ['success' => false, 'message' => 'Todos os campos são obrigatórios'];
      }

      // Verificar se usuário já existe
      $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
      $stmt->execute([$usuario]);
      
      if ($stmt->fetch()) {
          return ['success' => false, 'message' => 'Nome de usuário já existe'];
      }

      // Criar hash da senha
      $hash = password_hash($senha, PASSWORD_DEFAULT);
      
      // Modificação principal aqui - especifique as colunas sem incluir o ID
      $stmt = $pdo->prepare("INSERT INTO usuarios (nome, usuario, senha, tipo) VALUES (?, ?, ?, ?)");
      $success = $stmt->execute([$nome, $usuario, $hash, $tipo]);
      
      return [
          'success' => $success,
          'message' => $success ? 'Usuário criado com sucesso' : 'Falha ao criar usuário',
          'id' => $success ? $pdo->lastInsertId() : null
      ];
      
  } catch (PDOException $e) {
      error_log("Erro ao criar usuário: " . $e->getMessage());
      return ['success' => false, 'message' => 'Erro no sistema ao criar usuário: ' . $e->getMessage()];
  }
}


function atualizarUsuario($id, $nome, $usuario, $tipo) {
  global $pdo;
  $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, usuario = ?, tipo = ? WHERE id = ?");
  return $stmt->execute([$nome, $usuario, $tipo, $id]);
}

function atualizarSenha($id, $novaSenha) {
  global $pdo;
  $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
  return $stmt->execute([$hash, $id]);
}

function excluirUsuario($id) {
  global $pdo;
  $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
  return $stmt->execute([$id]);
}

function atualizarContaUsuario($id, $nome, $usuario, $senha = null) {
  global $pdo;

  // Primeiro buscamos o tipo atual para garantir que ele não será alterado
  $usuarioAtual = buscarUsuarioPorId($id);
  $tipo = $usuarioAtual['tipo'];  // Mantemos o tipo atual

  // Se a senha for fornecida, atualizamos ela
  if ($senha) {
      $hash = password_hash($senha, PASSWORD_DEFAULT);
      $query = "UPDATE usuarios SET nome = ?, usuario = ?, senha = ?, tipo = ? WHERE id = ?";
      $stmt = $pdo->prepare($query);
      return $stmt->execute([$nome, $usuario, $hash, $tipo, $id]);
  } else {
      // Se não houver senha, apenas atualizamos nome e usuário
      $query = "UPDATE usuarios SET nome = ?, usuario = ?, tipo = ? WHERE id = ?";
      $stmt = $pdo->prepare($query);
      return $stmt->execute([$nome, $usuario, $tipo, $id]);
  }
}


?>

