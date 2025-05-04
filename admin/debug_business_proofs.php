<?php
session_start();
include "../backend/db_connection.php"; 
include "../backend/check_role.php"; 

// Ensure only admins can access this
checkRole('Admin');

// Get specific account ID if provided
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Query to get business proofs
$sql = "SELECT id, username, business_proof FROM clients_accounts";
$params = [];
$types = "";

if ($id) {
    $sql .= " WHERE id = ?";
    $params[] = $id;
    $types = "i";
}

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Proof Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #333; }
        .account { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .json { background: #f5f5f5; padding: 10px; font-family: monospace; overflow-x: auto; }
        .images { margin-top: 10px; }
        .images img { margin: 5px; border: 1px solid #ccc; padding: 3px; max-width: 100px; height: auto; }
        .path-test { margin-top: 10px; border-top: 1px dotted #ccc; padding-top: 10px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Business Proof Debug Tool</h1>
    
    <?php while ($row = $result->fetch_assoc()): ?>
    <div class="account">
        <h2>Account #<?= $row['id'] ?>: <?= htmlspecialchars($row['username']) ?></h2>
        
        <h3>Stored JSON:</h3>
        <div class="json"><?= htmlspecialchars($row['business_proof'] ?? '[]') ?></div>
        
        <h3>Parsed Data:</h3>
        <?php
        $proofs = json_decode($row['business_proof'] ?? '[]', true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($proofs)): ?>
            <ul>
                <?php foreach ($proofs as $index => $path): ?>
                <li><?= $index ?>: <?= htmlspecialchars($path) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="error">Error parsing JSON: <?= json_last_error_msg() ?></p>
        <?php endif; ?>
        
        <h3>Images Preview:</h3>
        <div class="images">
            <?php
            if (json_last_error() === JSON_ERROR_NONE && is_array($proofs) && !empty($proofs)):
                foreach ($proofs as $proof):
                    // Try different path combinations to see what works
                    $pathVariations = [
                        'original' => $proof,
                        'admin_prefixed' => '/admin' . $proof,
                        'absolute_admin' => '/admin/uploads/' . $row['username'] . '/' . basename($proof),
                        'relative' => str_replace('/admin/', '../../', $proof),
                        'no_leading_slash' => ltrim($proof, '/')
                    ];
                    
                    foreach ($pathVariations as $type => $path):
                        echo "<div class='path-test'>";
                        echo "<strong>{$type}:</strong> " . htmlspecialchars($path);
                        echo "<br><img src='" . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . 
                             "' alt='Test {$type}' onload=\"this.style.border='2px solid green'\" " .
                             "onerror=\"this.style.border='2px solid red'; this.style.opacity=0.3; " .
                             "this.parentNode.innerHTML += '<span class=\"error\">Failed to load</span>'\">";
                        echo "</div>";
                    endforeach;
                endforeach;
            else:
                echo "<p>No images available</p>";
            endif;
            ?>
        </div>
        
        <h3>Fix Options:</h3>
        <form action="fix_clients_accounts.php" method="get">
            <input type="hidden" name="action" value="fix_single">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <button type="submit">Fix This Account</button>
        </form>
    </div>
    <?php endwhile; ?>
    
    <?php if ($result->num_rows === 0): ?>
        <p>No accounts found.</p>
    <?php endif; ?>
    
    <div style="margin-top: 30px;">
        <h3>Fix All Accounts:</h3>
        <form action="fix_clients_accounts.php" method="get">
            <input type="hidden" name="action" value="fix_proofs">
            <button type="submit">Fix All Business Proofs</button>
        </form>
    </div>
</body>
</html>