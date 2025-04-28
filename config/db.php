<?php
$host = 'hopper.proxy.rlwy.net';
$port = 42264;
$db   = 'railway';
$user = 'root';
$pass = 'lQvHyYlZzNASUzxhRFJfPoHrEYXDpigh';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Em vez de lançar exceção, retorne JSON em caso de erro
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erro de conexão com o banco de dados',
        'error' => $e->getMessage()
    ]);
    exit();
}