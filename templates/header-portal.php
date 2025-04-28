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
    <link rel="stylesheet" href="../public/assets/css/style-portal.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Portal PCM CCR</title>
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