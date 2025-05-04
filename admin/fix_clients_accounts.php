<?php
session_start();
include "../backend/db_connection.php"; 
include "../backend/check_role.php"; 

// Ensure only admins can access this
checkRole('Admin');

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'No action specified'];

// Function to fix all business proofs
if (isset($_GET['action']) && $_GET['action'] === 'fix_proofs') {
    try {
        // Get all accounts with business proofs
        $stmt = $conn->prepare("SELECT id, username, business_proof FROM clients_accounts");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $fixed = 0;
        $errors = 0;
        
        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $username = $row['username'];
            $current_proofs = $row['business_proof'];
            
            // Skip if empty
            if (empty($current_proofs)) {
                continue;
            }
            
            $fixed_proofs = [];
            $needs_fixing = false;
            
            // Try to decode existing proofs
            try {
                $proofs_array = json_decode($current_proofs, true);
                
                // Check if it's a valid array
                if (!is_array($proofs_array)) {
                    $needs_fixing = true;
                    // Try to extract paths
                    preg_match_all('/(?:\\"|\')([^\\"\']+\.(?:jpg|jpeg|png|gif))(?:\\"|\')/i', $current_proofs, $matches);
                    if (!empty($matches[1])) {
                        $fixed_proofs = $matches[1];
                    }
                } else {
                    // Fix any malformed paths in the array
                    foreach ($proofs_array as $proof) {
                        if (is_string($proof)) {
                            // Normalize path format
                            if (strpos($proof, '\\') !== false) {
                                $needs_fixing = true;
                                $proof = str_replace('\\', '/', $proof);
                            }
                            
                            if (strpos($proof, '../..') === 0) {
                                $needs_fixing = true;
                                $proof = str_replace('../../', '/admin/', $proof);
                            }
                            
                            $fixed_proofs[] = $proof;
                        }
                    }
                }
                
                // If no paths were extracted and we need fixing, set to empty array
                if ($needs_fixing && empty($fixed_proofs)) {
                    $fixed_proofs = [];
                }
                
                // If we don't need fixing, just use the original array
                if (!$needs_fixing) {
                    $fixed_proofs = $proofs_array;
                }
                
            } catch (Exception $e) {
                $needs_fixing = true;
                $fixed_proofs = []; // Empty array as fallback
                $errors++;
            }
            
            // Only update if needed
            if ($needs_fixing) {
                $encoded_proofs = json_encode($fixed_proofs);
                
                // Update the record
                $update_stmt = $conn->prepare("UPDATE clients_accounts SET business_proof = ? WHERE id = ?");
                $update_stmt->bind_param("si", $encoded_proofs, $id);
                
                if ($update_stmt->execute()) {
                    $fixed++;
                } else {
                    $errors++;
                }
                $update_stmt->close();
            }
        }
        
        $stmt->close();
        
        $response = [
            'success' => true,
            'message' => "Fixed $fixed accounts with business proof issues. Errors: $errors",
            'fixed' => $fixed,
            'errors' => $errors
        ];
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

echo json_encode($response);
exit;