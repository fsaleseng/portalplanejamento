<?php
require_once '../includes/session.php';
require_once '../config/db.php';

verificaLogin();

if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['usuario'];
$id = $_SESSION['usuario']['id'];
?>

<?php include '../templates/header-portal.php'; ?>


<main>
    <section class="portal-section1">
        <div class="portal-card conta">
            <div class="user-initial">
                <?= strtoupper(substr($_SESSION['usuario']['nome'], 0, 1)) ?>
            </div>
            <?php
            date_default_timezone_set('America/Bahia'); // ou 'America/Sao_Paulo' dependendo da sua região
            $hora = date('H');
            if ($hora < 12) {
                $saudacao = "Bom dia";
            } elseif ($hora < 18) {
                $saudacao = "Boa tarde";
            } else {
                $saudacao = "Boa noite";
            }
            ?>
            <p><strong><?= $saudacao ?>,</strong> <?= htmlspecialchars($_SESSION['usuario']['nome']) ?>!</p>
            <p><strong>Permissão:</strong> <?= htmlspecialchars($_SESSION['usuario']['tipo']) ?></p>

            <p>Deseja atualizar suas informações?</p>
            <a href="conta.php?id=<?= $id ?>">Gerenciar Conta</a>
        </div>
    </section>
    <section class="portal-section2">
        <div class="portal-card portal-menu">
            <div class="menu-card">
                <img src="assets/img/trem.png" alt="">
                <h1>Simulador</h1>
                <a href="simulador.php">Acessar</a>
            </div>
            <div class="menu-card">
                <img src="assets/img/trem.png" alt="">
                <h1>Programação</h1>
                <a href="programacao.php">Acessar</a>
            </div>
            <div class="menu-card">
                <img src="assets/img/trem.png" alt="">
                <h1>Falhas</h1>
                <a href="falhas.php">Acessar</a>
            </div>
        </div>
    </section>
    <section class="portal-section3">
        <div class="portal-card instrucoes">
            <div class="program-instructions">
                <p><strong>Bem-vindo ao Portal PCM CCR</strong> — o sistema integrado para gestão da manutenção
                    metroviária da CCR Metrô Bahia.</p> <br>

                <p>Utilize o menu superior para navegar rapidamente entre as funcionalidades e recursos disponíveis.</p>
            </div>
        </div>
    </section>
</main>
<footer>Desenvolvido por <strong>Fernanda Sales</strong>. CCR Metrô Bahia.</footer>
</body>

</html>