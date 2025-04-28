<?php
require_once '../config/db.php';

$local = $_GET['local'] ?? '';

if (empty($local)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$sql = "SELECT data, valmed_postotcontador 
        FROM hodometro 
        WHERE local_instalacao = ? 
        ORDER BY data ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$local]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($result);