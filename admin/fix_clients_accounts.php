<?php
session_start();
include "../backend/db_connection.php"; 
include "../backend/check_role.php"; 

// Ensure only admins can access this
checkRole('Admin');

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'No action specified'];

// Function to fix business proof paths
function fixBusinessProofs($conn, $id = null) {
    $fixed = 0;
    $errors = 0;
    $details = [];
    
    try {
        // Prepare the query - either for one account or all
        $sql = "SELECT id, username, business_proof FROM clients_accounts";
        $params = [];
        $types = "";
        
        if ($id !== null) {
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
        
        while ($row = $result->fetch_assoc()) {
            $account_id = $row['id'];
            $username = $row['username'];
            $current_proofs = $row['business_proof'];
            
            // Skip if empty
            if (empty($current_proofs)) {
                continue;
            }
            
            $fixed_proofs = [];
            $needs_fixing = false;
            $account_details = [
                'id' => $account_id,
                'username' => $username,
                'original' => $current_proofs,
                'fixed' => null,
                'status' => 'unchanged'
            ];
            
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
                            $fixed_path = $proof;
                            
                            // Fix backslashes
                            if (strpos($proof, '\\') !== false) {
                                $needs_fixing = true;
                                $fixed_path = str_replace('\\', '/', $fixed_path);
                            }
                            
                            // Convert relative paths to absolute
                            if (strpos($fixed_path, '../..') === 0) {
                                $needs_fixing = true;
                                $fixed_path = str_replace('../../', '/admin/', $fixed_path);
                            }
                            
                            // Ensure path starts with /admin/ for consistency
                            if (!preg_match('#^/admin/#', $fixed_path) && basename($fixed_path) != $fixed_path) {
                                $needs_fixing = true;
                                
                                // If it's just a filename, prepend the correct path
                                if (strpos($fixed_path, '/') === false) {
                                    $fixed_path = '/admin/uploads/' . $username . '/' . $fixed_path;
                                } else if (strpos($fixed_path, '/') === 0) {
                                    // If it starts with / but not /admin/, add /admin
                                    $fixed_path = '/admin' . $fixed_path;
                                } else {
                                    // Otherwise, make it a standard path
                                    $fixed_path = '/admin/uploads/' . $username . '/' . basename($fixed_path);
                                }
                            }
                            
                            $fixed_proofs[] = $fixed_path;
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
                $account_details['error'] = $e->getMessage();
            }
            
            // Only update if needed
            if ($needs_fixing) {
                $encoded_proofs = json_encode($fixed_proofs);
                $account_details['fixed'] = $encoded_proofs;
                
                // Update the record
                $update_stmt = $conn->prepare("UPDATE clients_accounts SET business_proof = ? WHERE id = ?");
                $update_stmt->bind_param("si", $encoded_proofs, $account_id);
                
                if ($update_stmt->execute()) {
                    $fixed++;
                    $account_details['status'] = 'fixed';
                } else {
                    $errors++;
                    $account_details['status'] = 'error';
                    $account_details['error'] = $update_stmt->error;
                }
                $update_stmt->close();
            }
            
            $details[] = $account_details;
        }
        
        $stmt->close();
        
        return [
            'success' => true,
            'message' => "Fixed $fixed accounts with business proof issues. Errors: $errors",
            'fixed' => $fixed,
            'errors' => $errors,
            'details' => $details
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

// --- Handle actions ---

// Fix all proofs
if (isset($_GET['action']) && $_GET['action'] === 'fix_proofs') {
    $response = fixBusinessProofs($conn);
}

// Fix a single account
if (isset($_GET['action']) && $_GET['action'] === 'fix_single' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $response = fixBusinessProofs($conn, $id);
}

echo json_encode($response);
exit;