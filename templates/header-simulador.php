<?php
require_once '../includes/session.php';
require_once '../config/db.php';

verificaLogin();

$user = $_SESSION['usuario'];
$id = $_SESSION['usuario']['id'];
?>



<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal PCM CCR</title>

    <!-- Metatags SEO -->
    <meta name="description" content="Simulador de Manutenção Preventiva - CCR">
    <meta name="author" content="CCR Metrô Bahia">

    <!-- Performance: Pré-conexão para CDNs -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">

    <!-- CSS Externos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.css" rel="stylesheet">

    <!-- Seu CSS Customizado -->
    <link href="../public/assets/css/style-simulador.css" rel="stylesheet">

    <!-- JavaScript: Bibliotecas principais (ordem crítica) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Use Chart.js v3 (required for timeline plugin) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <!-- date-fns -->
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.30.0/dist/date-fns.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>

    
    <!-- Chart.js date adapter -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.min.js"></script>
    
    <!-- Timeline plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-timeline@1.0.0/dist/chartjs-chart-timeline.min.js"></script>

    <!-- Other plugins -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.1/dist/chartjs-plugin-zoom.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.5.0/dist/chartjs-plugin-annotation.min.js"></script>

    <!-- noUiSlider -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.js"></script>

    <!-- Bootstrap Bundle (JS + Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <!-- pdfMake -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/vfs_fonts.js"></script>

    <style>
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        .modal-grafico {
            min-height: 500px;
        }
    </style>
</head>





<body>
    <header>
        <section class="logotipo">
            <img src="assets/img/ccr_metro_bahia.png" alt="CCR Metrô Bahia">
            <h1>Portal PCM - CCR Metrô Bahia</h1>
        </section>
        <section class="header-items">
            <div class="menu">
                <i class="bi bi-house-door-fill"></i>
                <a href="portal.php">Home</a>
            </div>
            <div class="menu">
                <i class="bi bi-bar-chart-fill"></i>
                <a href="simulador.php">Simulador</a>
            </div>
            <div class="menu">
                <i class="bi bi-calendar-check"></i>
                <a href="programacao.php">Programação</a>
            </div>
            <div class="menu">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <a href="falhas.php">Falhas</a>
            </div>
            <?php if ($_SESSION['usuario']['tipo'] === 'admin' || $_SESSION['usuario']['tipo'] === 'gerente'): ?>
                <div class="menu">
                    <i class="bi bi-clipboard-data-fill"></i>
                    <a href="dados.php">Dados</a>
                </div>
            <?php endif; ?>
            <div class="menu">
                <i class="bi bi-book-half"></i>
                <a href="instrucoes.php">Instruções</a>
            </div>
            <div class="menu">
                <i class="bi bi-bell-fill"></i>
                <a href="notificacoes.php">Notificações</a>
            </div>
            <?php if ($_SESSION['usuario']['tipo'] === 'admin'): ?>
                <div class="menu">
                    <i class="bi bi-people-fill"></i>
                    <a href="admin.php">Admin</a>
                </div>
            <?php endif; ?>
            <div class="menu">
                <i class="bi bi-person-circle"></i>
                <a href="conta.php?id=<?= $id ?>">Conta</a>
            </div>
            <div class="menu">
                <i class="bi bi-forward-fill"></i>
                <a href="logout.php">Sair</a>
            </div>
        </section>
    </header>