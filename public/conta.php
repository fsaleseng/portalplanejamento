<?php
require_once '../includes/session.php';
require_once '../includes/user_functions.php';

verificaPermissao(['admin', 'gerente', 'visualizador']);

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ID inválido.";
    exit();
}

$usuario = buscarUsuarioPorId($id);
if (!$usuario) {
    echo "Usuário não encontrado.";
    exit();
}

// Verifica se é o próprio usuário editando ou se tem permissão
if ($_SESSION['usuario']['id'] != $id && $_SESSION['usuario']['tipo'] != 'admin') {
    echo "Acesso negado.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $usuarioNome = $_POST['usuario'];
    $senha = $_POST['senha'] ?? null;

    if (atualizarContaUsuario($id, $nome, $usuarioNome, $senha)) {
        // Atualiza sessão se for o próprio usuário alterando
        if ($_SESSION['usuario']['id'] == $id) {
            $_SESSION['usuario']['nome'] = $nome;
            $_SESSION['usuario']['usuario'] = $usuarioNome;
        }
        header('Location: portal.php');
        exit();
    } else {
        echo '<p>Erro ao atualizar conta.</p>';
    }
}
?>


<?php include '../templates/header.php'; ?>


<main>
    <section class="dashboard">
        <h2>Editar Usuário</h2>
        <form method="POST">
        <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
            <input type="text" name="usuario" value="<?= htmlspecialchars($usuario['usuario']) ?>" required>
            <input type="password" name="senha" placeholder="Nova senha (deixe em branco para manter)" autocomplete="new-password">
            <button type="submit">Salvar alterações</button>
    </section>
    <style>
        .dashboard h2 {
            color: var(--secondary-color);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
            display: inline-block;
        }

        .dashboard form {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            max-width: 600px;
        }

        .dashboard input {
            padding: 0.8rem 1rem;
            border: 1px solid var(--medium-gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.9);
        }

        .dashboard input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            background-color: var(--white);
        }

        .dashboard input::placeholder {
            color: #aaa;
            font-style: italic;
        }

        .dashboard button[type="submit"] {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            align-self: flex-start;
        }

        .dashboard button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .dashboard button[type="submit"]:active {
            transform: translateY(0);
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .dashboard {
                padding: 1.5rem;
            }

            .dashboard form {
                gap: 1rem;
            }

            .dashboard input {
                padding: 0.7rem 1rem;
            }
        }
    </style>
</main>
<footer>Desenvolvido por <strong>Fernanda Sales</strong>. CCR Metrô Bahia.</footer>
</body>

</html>