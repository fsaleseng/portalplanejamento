<?php
require_once '../includes/session.php';
require_once '../includes/user_functions.php';

// Verifica se o usuário tem permissão de admin
verificaPermissao(['admin']);

// Listar todos os usuários
$usuarios = listarUsuarios();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['excluir'])) {
        $id = $_POST['excluir'];
        if (excluirUsuario($id)) {
            header('Location: admin.php');  // Atualiza a página para refletir a exclusão
            exit();
        } else {
            echo "<p>Erro ao excluir o usuário.</p>";
        }
    }

    if (isset($_POST['editar'])) {
        $id = $_POST['id'];
        $nome = $_POST['nome'];
        $usuarioNome = $_POST['usuario'];
        $tipo = $_POST['tipo']; // Agora pega o tipo também
        $senha = $_POST['senha']; // A senha pode ser deixada em branco

        // Atualizar nome, usuario e tipo
        atualizarUsuario($id, $nome, $usuarioNome, $tipo);

        // Se enviou senha nova, atualiza a senha também
        if (!empty($senha)) {
            atualizarSenha($id, $senha);
        }

        header('Location: admin.php');
        exit();
    }

    if (isset($_POST['criar'])) {
        $nome = $_POST['nome'];
        $usuarioNome = $_POST['usuario'];
        $senha = $_POST['senha'];
        $tipo = $_POST['tipo'];
    
        $resultado = criarUsuario($nome, $usuarioNome, $senha, $tipo);
    
        if ($resultado['success']) {
            header('Location: admin.php');
            exit();
        } else {
            echo "<p>Erro ao criar o usuário: " . htmlspecialchars($resultado['message']) . "</p>";
        }
    }
    
}

// Verificar se o ID do usuário foi passado e pegar os dados do usuário
$id = $_GET['id'] ?? null;
if ($id) {
    $usuario = buscarUsuarioPorId($id);
}


// Listar todos os usuários
$usuarios = listarUsuarios();

?>


<?php include '../templates/header.php'; ?>

<main>
    <section class="dashboard">
        <h2>Dashboard Admin</h2>

        <!-- Formulário para criar novo usuário -->
        <div class="form-container">
            <h3>Criar Novo Usuário</h3>
            <form method="POST">
                <input type="text" name="nome" placeholder="Nome" required>
                <input type="text" name="usuario" placeholder="Usuário" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <select name="tipo" required>
                    <option value="admin">Admin</option>
                    <option value="gerente">Gerente</option>
                    <option value="visualizador">Visualizador</option>
                </select>
                <button type="submit" name="criar">Criar Usuário</button>
            </form>
        </div>

        <!-- Listar usuários e opções para editar e excluir -->
        <div class="user-list">
            <h3>Usuários Cadastrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr id="usuario-<?= $usuario['id'] ?>">
                            <td><?= htmlspecialchars($usuario['nome']) ?></td>
                            <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                            <td><?= htmlspecialchars($usuario['tipo']) ?></td>
                            <td>
                                <!-- Botão de editar -->
                                <button onclick="toggleEditForm(<?= $usuario['id'] ?>)">Editar</button>
                                <!-- Formulário de exclusão -->
                                <form method="POST" style="display:inline;">
                                    <button type="submit" name="excluir" value="<?= $usuario['id'] ?>">Excluir</button>
                                </form>
                            </td>
                        </tr>
                        <!-- Formulário de edição, escondido inicialmente -->
                        <tr id="edit-form-<?= $usuario['id'] ?>" style="display:none;">
                            <td colspan="4">
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?= $usuario['id'] ?>" />
                                    <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>"
                                        required />
                                    <input type="text" name="usuario" value="<?= htmlspecialchars($usuario['usuario']) ?>"
                                        required />
                                    <input type="password" name="senha"
                                        placeholder="Nova senha (deixe em branco para manter)" />
                                    <select name="tipo" required>
                                        <option value="admin" <?= $usuario['tipo'] === 'admin' ? 'selected' : '' ?>>Admin
                                        </option>
                                        <option value="gerente" <?= $usuario['tipo'] === 'gerente' ? 'selected' : '' ?>>Gerente
                                        </option>
                                        <option value="visualizador" <?= $usuario['tipo'] === 'visualizador' ? 'selected' : '' ?>>Visualizador</option>
                                    </select>
                                    <button type="submit" name="editar">Salvar</button>
                                    <button type="button" onclick="toggleEditForm(<?= $usuario['id'] ?>)">Cancelar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
                function toggleEditForm(userId) {
                    var editForm = document.getElementById('edit-form-' + userId);
                    var userRow = document.getElementById('usuario-' + userId);

                    // Alternar a exibição do formulário de edição e a linha do usuário
                    if (editForm.style.display === 'none' || editForm.style.display === '') {
                        editForm.style.display = 'table-row';  // Exibe o formulário de edição
                        userRow.style.display = 'none';  // Oculta a linha de visualização do usuário
                    } else {
                        editForm.style.display = 'none';  // Oculta o formulário de edição
                        userRow.style.display = 'table-row';  // Exibe a linha de visualização do usuário
                    }
                }
            </script>
        </div>
    </section>
</main>
<footer>Desenvolvido por <strong>Fernanda Sales</strong>. CCR Metrô Bahia.</footer>
</body>

</html>