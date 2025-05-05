<?php
require_once '../db_config.php';

try {
    $chemicalId = $_POST['id'];
    $quantity = (float)$_POST['quantity'];
    $target_pest = $_POST['target_pest'] ?? null;

    $stmt = $pdo->prepare("UPDATE chemical_inventory
                          SET quantity = ?, target_pest = ?
                          WHERE id = ?");
    $stmt->execute([$quantity, $target_pest, $chemicalId]);

    echo json_encode(['success' => true]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}