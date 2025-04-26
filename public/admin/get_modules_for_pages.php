<?php
include("../includes/config.php");

header('Content-Type: application/json');

if (isset($_POST['pages']) && is_array($_POST['pages'])) {
    $pages = $_POST['pages'];
    $pages_list = "'" . implode("','", $pages) . "'";
    
    $query = "SELECT DISTINCT module_id FROM pages WHERE page_name IN ($pages_list)";
    $result = $conn->query($query);
    
    $modules = [];
    while ($row = $result->fetch_assoc()) {
        $modules[] = $row['module_id'];
    }
    
    echo json_encode(['status' => 'success', 'modules' => $modules]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
}
?>