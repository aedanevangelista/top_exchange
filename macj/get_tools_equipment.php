<?php
/**
 * Get tools and equipment data for technician checklist
 */
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Get all tools and equipment
    $query = "SELECT id, name, category, description FROM tools_equipment ORDER BY category, name";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $tools = [];
    
    // Group tools by category
    while ($row = $result->fetch_assoc()) {
        $category = $row['category'];
        
        if (!isset($tools[$category])) {
            $tools[$category] = [];
        }
        
        $tools[$category][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $tools]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
