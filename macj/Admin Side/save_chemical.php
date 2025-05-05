<?php
header('Content-Type: application/json');

$db = new PDO('mysql:host=localhost;dbname=u701062148_top_exchange', 'u701062148_top_exchange', 'Aedanpogi123');

$data = [
    ':name' => $_POST['chemical_name'],
    ':type' => $_POST['type'],
    ':qty' => $_POST['quantity'],
    ':unit' => $_POST['unit'],
    ':manufacturer' => $_POST['manufacturer'],
    ':supplier' => $_POST['supplier'],
    ':desc' => $_POST['description'],
    ':safety' => $_POST['safety_info']
];

$sql = "INSERT INTO chemical_inventory
        (chemical_name, type, quantity, unit, manufacturer, supplier, description, safety_info)
        VALUES (:name, :type, :qty, :unit, :manufacturer, :supplier, :desc, :safety)";

$stmt = $db->prepare($sql);
$success = $stmt->execute($data);

echo json_encode(['success' => $success]);