<?php
/**
 * Archive Functions
 *
 * This file contains functions for archiving, restoring, and managing archived data.
 */

/**
 * Archive a chemical inventory item
 *
 * @param PDO|mysqli $db Database connection
 * @param int $id ID of the chemical to archive
 * @return array Result of the operation
 */
function archiveChemical($db, $id) {
    // Calculate scheduled deletion date (30 days from now)
    $scheduledDeletionDate = date('Y-m-d', strtotime('+30 days'));

    try {
        // For PDO connection
        if ($db instanceof PDO) {
            // Begin transaction
            $db->beginTransaction();

            // Get the chemical data
            $stmt = $db->prepare("SELECT * FROM chemical_inventory WHERE id = ?");
            $stmt->execute([$id]);
            $chemical = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$chemical) {
                return ['success' => false, 'error' => 'Chemical not found'];
            }

            // Insert into archive table
            $stmt = $db->prepare("INSERT INTO archived_chemical_inventory
                (id, chemical_name, type, quantity, unit, manufacturer, supplier, description,
                safety_info, expiration_date, created_at, target_pest, scheduled_deletion_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $chemical['id'],
                $chemical['chemical_name'],
                $chemical['type'],
                $chemical['quantity'],
                $chemical['unit'],
                $chemical['manufacturer'],
                $chemical['supplier'],
                $chemical['description'],
                $chemical['safety_info'],
                $chemical['expiration_date'],
                $chemical['created_at'],
                $chemical['target_pest'],
                $scheduledDeletionDate
            ]);

            // Delete from original table
            $stmt = $db->prepare("DELETE FROM chemical_inventory WHERE id = ?");
            $stmt->execute([$id]);

            // Commit transaction
            $db->commit();

            return ['success' => true];
        }
        // For mysqli connection
        else if ($db instanceof mysqli) {
            // Begin transaction
            $db->begin_transaction();

            // Get the chemical data
            $stmt = $db->prepare("SELECT * FROM chemical_inventory WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $chemical = $result->fetch_assoc();

            if (!$chemical) {
                return ['success' => false, 'error' => 'Chemical not found'];
            }

            // Insert into archive table
            $stmt = $db->prepare("INSERT INTO archived_chemical_inventory
                (id, chemical_name, type, quantity, unit, manufacturer, supplier, description,
                safety_info, expiration_date, created_at, target_pest, scheduled_deletion_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "issdssssssss",
                $chemical['id'],
                $chemical['chemical_name'],
                $chemical['type'],
                $chemical['quantity'],
                $chemical['unit'],
                $chemical['manufacturer'],
                $chemical['supplier'],
                $chemical['description'],
                $chemical['safety_info'],
                $chemical['expiration_date'],
                $chemical['created_at'],
                $chemical['target_pest'],
                $scheduledDeletionDate
            );
            $stmt->execute();

            // Delete from original table
            $stmt = $db->prepare("DELETE FROM chemical_inventory WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            // Commit transaction
            $db->commit();

            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Unsupported database connection type'];
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($db instanceof PDO) {
            $db->rollBack();
        } else if ($db instanceof mysqli) {
            $db->rollback();
        }

        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Restore a chemical inventory item from archive
 *
 * @param PDO|mysqli $db Database connection
 * @param int $archiveId Archive ID of the chemical to restore
 * @return array Result of the operation
 */
function restoreChemical($db, $archiveId) {
    try {
        // For PDO connection
        if ($db instanceof PDO) {
            // Begin transaction
            $db->beginTransaction();

            // Get the archived chemical data
            $stmt = $db->prepare("SELECT * FROM archived_chemical_inventory WHERE archive_id = ?");
            $stmt->execute([$archiveId]);
            $chemical = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$chemical) {
                return ['success' => false, 'error' => 'Archived chemical not found'];
            }

            // Check if the original ID already exists in the main table
            $stmt = $db->prepare("SELECT COUNT(*) FROM chemical_inventory WHERE id = ?");
            $stmt->execute([$chemical['id']]);
            $exists = $stmt->fetchColumn() > 0;

            // If the ID exists, we'll need to insert without specifying the ID
            if ($exists) {
                $stmt = $db->prepare("INSERT INTO chemical_inventory
                    (chemical_name, type, quantity, unit, manufacturer, supplier, description,
                    safety_info, expiration_date, created_at, target_pest)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $chemical['chemical_name'],
                    $chemical['type'],
                    $chemical['quantity'],
                    $chemical['unit'],
                    $chemical['manufacturer'],
                    $chemical['supplier'],
                    $chemical['description'],
                    $chemical['safety_info'],
                    $chemical['expiration_date'],
                    $chemical['created_at'],
                    $chemical['target_pest']
                ]);
            } else {
                // Insert with the original ID
                $stmt = $db->prepare("INSERT INTO chemical_inventory
                    (id, chemical_name, type, quantity, unit, manufacturer, supplier, description,
                    safety_info, expiration_date, created_at, target_pest)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $chemical['id'],
                    $chemical['chemical_name'],
                    $chemical['type'],
                    $chemical['quantity'],
                    $chemical['unit'],
                    $chemical['manufacturer'],
                    $chemical['supplier'],
                    $chemical['description'],
                    $chemical['safety_info'],
                    $chemical['expiration_date'],
                    $chemical['created_at'],
                    $chemical['target_pest']
                ]);
            }

            // Delete from archive table
            $stmt = $db->prepare("DELETE FROM archived_chemical_inventory WHERE archive_id = ?");
            $stmt->execute([$archiveId]);

            // Commit transaction
            $db->commit();

            return ['success' => true];
        }
        // For mysqli connection
        else if ($db instanceof mysqli) {
            // Begin transaction
            $db->begin_transaction();

            // Get the archived chemical data
            $stmt = $db->prepare("SELECT * FROM archived_chemical_inventory WHERE archive_id = ?");
            $stmt->bind_param("i", $archiveId);
            $stmt->execute();
            $result = $stmt->get_result();
            $chemical = $result->fetch_assoc();

            if (!$chemical) {
                return ['success' => false, 'error' => 'Archived chemical not found'];
            }

            // Check if the original ID already exists in the main table
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM chemical_inventory WHERE id = ?");
            $stmt->bind_param("i", $chemical['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $exists = $row['count'] > 0;

            // If the ID exists, we'll need to insert without specifying the ID
            if ($exists) {
                $stmt = $db->prepare("INSERT INTO chemical_inventory
                    (chemical_name, type, quantity, unit, manufacturer, supplier, description,
                    safety_info, expiration_date, created_at, target_pest)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param(
                    "ssdssssssss",
                    $chemical['chemical_name'],
                    $chemical['type'],
                    $chemical['quantity'],
                    $chemical['unit'],
                    $chemical['manufacturer'],
                    $chemical['supplier'],
                    $chemical['description'],
                    $chemical['safety_info'],
                    $chemical['expiration_date'],
                    $chemical['created_at'],
                    $chemical['target_pest']
                );
            } else {
                // Insert with the original ID
                $stmt = $db->prepare("INSERT INTO chemical_inventory
                    (id, chemical_name, type, quantity, unit, manufacturer, supplier, description,
                    safety_info, expiration_date, created_at, target_pest)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param(
                    "issdssssssss",
                    $chemical['id'],
                    $chemical['chemical_name'],
                    $chemical['type'],
                    $chemical['quantity'],
                    $chemical['unit'],
                    $chemical['manufacturer'],
                    $chemical['supplier'],
                    $chemical['description'],
                    $chemical['safety_info'],
                    $chemical['expiration_date'],
                    $chemical['created_at'],
                    $chemical['target_pest']
                );
            }
            $stmt->execute();

            // Delete from archive table
            $stmt = $db->prepare("DELETE FROM archived_chemical_inventory WHERE archive_id = ?");
            $stmt->bind_param("i", $archiveId);
            $stmt->execute();

            // Commit transaction
            $db->commit();

            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Unsupported database connection type'];
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($db instanceof PDO) {
            $db->rollBack();
        } else if ($db instanceof mysqli) {
            $db->rollback();
        }

        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Archive a client
 *
 * @param mysqli $db Database connection
 * @param int $clientId ID of the client to archive
 * @return array Result of the operation
 */
function archiveClient($db, $clientId) {
    // Calculate scheduled deletion date (30 days from now)
    $scheduledDeletionDate = date('Y-m-d', strtotime('+30 days'));

    try {
        // Begin transaction
        $db->begin_transaction();

        // Get the client data
        $stmt = $db->prepare("SELECT * FROM clients WHERE client_id = ?");
        $stmt->bind_param("i", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();
        $client = $result->fetch_assoc();

        if (!$client) {
            return ['success' => false, 'error' => 'Client not found'];
        }

        // Insert into archive table
        $stmt = $db->prepare("INSERT INTO archived_clients
            (client_id, first_name, last_name, email, contact_number, password, registered_at,
            location_address, type_of_place, location_lat, location_lng, scheduled_deletion_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "isssssssssss",
            $client['client_id'],
            $client['first_name'],
            $client['last_name'],
            $client['email'],
            $client['contact_number'],
            $client['password'],
            $client['registered_at'],
            $client['location_address'],
            $client['type_of_place'],
            $client['location_lat'],
            $client['location_lng'],
            $scheduledDeletionDate
        );
        $stmt->execute();

        // Delete from original table
        $stmt = $db->prepare("DELETE FROM clients WHERE client_id = ?");
        $stmt->bind_param("i", $clientId);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        return ['success' => true];
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Restore a client from archive
 *
 * @param mysqli $db Database connection
 * @param int $archiveId Archive ID of the client to restore
 * @return array Result of the operation
 */
function restoreClient($db, $archiveId) {
    try {
        // Begin transaction
        $db->begin_transaction();

        // Get the archived client data
        $stmt = $db->prepare("SELECT * FROM archived_clients WHERE archive_id = ?");
        $stmt->bind_param("i", $archiveId);
        $stmt->execute();
        $result = $stmt->get_result();
        $client = $result->fetch_assoc();

        if (!$client) {
            return ['success' => false, 'error' => 'Archived client not found'];
        }

        // Check if the original ID already exists in the main table
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM clients WHERE client_id = ?");
        $stmt->bind_param("i", $client['client_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $exists = $row['count'] > 0;

        // If the ID exists, we'll need to insert without specifying the ID
        if ($exists) {
            $stmt = $db->prepare("INSERT INTO clients
                (first_name, last_name, email, contact_number, password, registered_at,
                location_address, type_of_place, location_lat, location_lng)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "ssssssssss",
                $client['first_name'],
                $client['last_name'],
                $client['email'],
                $client['contact_number'],
                $client['password'],
                $client['registered_at'],
                $client['location_address'],
                $client['type_of_place'],
                $client['location_lat'],
                $client['location_lng']
            );
        } else {
            // Insert with the original ID
            $stmt = $db->prepare("INSERT INTO clients
                (client_id, first_name, last_name, email, contact_number, password, registered_at,
                location_address, type_of_place, location_lat, location_lng)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "issssssssss",
                $client['client_id'],
                $client['first_name'],
                $client['last_name'],
                $client['email'],
                $client['contact_number'],
                $client['password'],
                $client['registered_at'],
                $client['location_address'],
                $client['type_of_place'],
                $client['location_lat'],
                $client['location_lng']
            );
        }
        $stmt->execute();

        // Delete from archive table
        $stmt = $db->prepare("DELETE FROM archived_clients WHERE archive_id = ?");
        $stmt->bind_param("i", $archiveId);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        return ['success' => true];
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Archive a technician
 *
 * @param mysqli $db Database connection
 * @param int $technicianId ID of the technician to archive
 * @return array Result of the operation
 */
function archiveTechnician($db, $technicianId) {
    // Calculate scheduled deletion date (30 days from now)
    $scheduledDeletionDate = date('Y-m-d', strtotime('+30 days'));

    try {
        // Begin transaction
        $db->begin_transaction();

        // Get the technician data
        $stmt = $db->prepare("SELECT * FROM technicians WHERE technician_id = ?");
        $stmt->bind_param("i", $technicianId);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();

        if (!$technician) {
            return ['success' => false, 'error' => 'Technician not found'];
        }

        // Check if there are related records in technician_checklist_logs
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM technician_checklist_logs WHERE technician_id = ?");
        $stmt->bind_param("i", $technicianId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $hasChecklistLogs = $row['count'] > 0;

        // If there are checklist logs, create an archived_technician_checklist_logs table if it doesn't exist
        if ($hasChecklistLogs) {
            // Check if the archived_technician_checklist_logs table exists
            $tableExists = $db->query("SHOW TABLES LIKE 'archived_technician_checklist_logs'")->num_rows > 0;

            if (!$tableExists) {
                // Create the archived_technician_checklist_logs table
                $createTableSql = "CREATE TABLE archived_technician_checklist_logs (
                    archive_id INT AUTO_INCREMENT PRIMARY KEY,
                    log_id INT,
                    technician_id INT,
                    checklist_date DATETIME,
                    checked_items TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    scheduled_deletion_date DATE
                )";
                $db->query($createTableSql);
            }

            // Get the structure of the technician_checklist_logs table
            $result = $db->query("DESCRIBE technician_checklist_logs");
            $columns = [];
            $primaryKeyColumn = null;

            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
                if ($row['Key'] == 'PRI') {
                    $primaryKeyColumn = $row['Field'];
                }
            }

            if (!$primaryKeyColumn) {
                // If we can't find the primary key, let's check common names
                $commonPrimaryKeys = ['id', 'log_id', 'checklist_id'];
                foreach ($commonPrimaryKeys as $key) {
                    if (in_array($key, $columns)) {
                        $primaryKeyColumn = $key;
                        break;
                    }
                }

                // If still not found, use the first column
                if (!$primaryKeyColumn && !empty($columns)) {
                    $primaryKeyColumn = $columns[0];
                } else if (!$primaryKeyColumn) {
                    // Last resort
                    $primaryKeyColumn = 'id';
                }
            }

            // Archive the checklist logs
            $stmt = $db->prepare("INSERT INTO archived_technician_checklist_logs
                (log_id, technician_id, checklist_date, checked_items, scheduled_deletion_date)
                SELECT $primaryKeyColumn, technician_id, checklist_date, checked_items, ?
                FROM technician_checklist_logs
                WHERE technician_id = ?");
            $stmt->bind_param("si", $scheduledDeletionDate, $technicianId);
            $stmt->execute();

            // Delete the checklist logs
            $stmt = $db->prepare("DELETE FROM technician_checklist_logs WHERE technician_id = ?");
            $stmt->bind_param("i", $technicianId);
            $stmt->execute();
        }

        // Insert into archive table
        $stmt = $db->prepare("INSERT INTO archived_technicians
            (technician_id, username, password, tech_contact_number, tech_fname, tech_lname,
            technician_picture, scheduled_deletion_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "isssssss",
            $technician['technician_id'],
            $technician['username'],
            $technician['password'],
            $technician['tech_contact_number'],
            $technician['tech_fname'],
            $technician['tech_lname'],
            $technician['technician_picture'],
            $scheduledDeletionDate
        );
        $stmt->execute();

        // Delete from original table
        $stmt = $db->prepare("DELETE FROM technicians WHERE technician_id = ?");
        $stmt->bind_param("i", $technicianId);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        return ['success' => true];
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Restore a technician from archive
 *
 * @param mysqli $db Database connection
 * @param int $archiveId Archive ID of the technician to restore
 * @return array Result of the operation
 */
function restoreTechnician($db, $archiveId) {
    try {
        // Begin transaction
        $db->begin_transaction();

        // Get the archived technician data
        $stmt = $db->prepare("SELECT * FROM archived_technicians WHERE archive_id = ?");
        $stmt->bind_param("i", $archiveId);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();

        if (!$technician) {
            return ['success' => false, 'error' => 'Archived technician not found'];
        }

        // Check if the original ID already exists in the main table
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM technicians WHERE technician_id = ?");
        $stmt->bind_param("i", $technician['technician_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $exists = $row['count'] > 0;

        // Get the technician ID to use (original or new)
        $technicianId = $technician['technician_id'];

        // If the ID exists, we'll need to insert without specifying the ID
        if ($exists) {
            $stmt = $db->prepare("INSERT INTO technicians
                (username, password, tech_contact_number, tech_fname, tech_lname, technician_picture)
                VALUES (?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "ssssss",
                $technician['username'],
                $technician['password'],
                $technician['tech_contact_number'],
                $technician['tech_fname'],
                $technician['tech_lname'],
                $technician['technician_picture']
            );
            $stmt->execute();

            // Get the new technician ID
            $technicianId = $db->insert_id;
        } else {
            // Insert with the original ID
            $stmt = $db->prepare("INSERT INTO technicians
                (technician_id, username, password, tech_contact_number, tech_fname, tech_lname, technician_picture)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "issssss",
                $technician['technician_id'],
                $technician['username'],
                $technician['password'],
                $technician['tech_contact_number'],
                $technician['tech_fname'],
                $technician['tech_lname'],
                $technician['technician_picture']
            );
            $stmt->execute();
        }

        // Check if there are archived checklist logs for this technician
        $tableExists = $db->query("SHOW TABLES LIKE 'archived_technician_checklist_logs'")->num_rows > 0;

        if ($tableExists) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM archived_technician_checklist_logs WHERE technician_id = ?");
            $stmt->bind_param("i", $technician['technician_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $hasArchivedLogs = $row['count'] > 0;

            if ($hasArchivedLogs) {
                // Restore the checklist logs
                $stmt = $db->prepare("INSERT INTO technician_checklist_logs
                    (technician_id, checklist_date, checked_items)
                    SELECT ?, checklist_date, checked_items
                    FROM archived_technician_checklist_logs
                    WHERE technician_id = ?");
                $stmt->bind_param("ii", $technicianId, $technician['technician_id']);
                $stmt->execute();

                // Delete the archived logs
                $stmt = $db->prepare("DELETE FROM archived_technician_checklist_logs WHERE technician_id = ?");
                $stmt->bind_param("i", $technician['technician_id']);
                $stmt->execute();
            }
        }

        // Delete from archive table
        $stmt = $db->prepare("DELETE FROM archived_technicians WHERE archive_id = ?");
        $stmt->bind_param("i", $archiveId);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        return ['success' => true];
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Archive a tool/equipment
 *
 * @param mysqli $db Database connection
 * @param int $toolId ID of the tool to archive
 * @return array Result of the operation
 */
function archiveTool($db, $toolId) {
    // Calculate scheduled deletion date (30 days from now)
    $scheduledDeletionDate = date('Y-m-d', strtotime('+30 days'));

    try {
        // Begin transaction
        $db->begin_transaction();

        // Get the tool data
        $stmt = $db->prepare("SELECT * FROM tools_equipment WHERE id = ?");
        $stmt->bind_param("i", $toolId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tool = $result->fetch_assoc();

        if (!$tool) {
            return ['success' => false, 'error' => 'Tool not found'];
        }

        // Insert into archive table
        $stmt = $db->prepare("INSERT INTO archived_tools_equipment
            (id, name, category, quantity, description, created_at, updated_at, scheduled_deletion_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "issisiss",
            $tool['id'],
            $tool['name'],
            $tool['category'],
            $tool['quantity'],
            $tool['description'],
            $tool['created_at'],
            $tool['updated_at'],
            $scheduledDeletionDate
        );
        $stmt->execute();

        // Delete from original table
        $stmt = $db->prepare("DELETE FROM tools_equipment WHERE id = ?");
        $stmt->bind_param("i", $toolId);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        return ['success' => true];
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Restore a tool/equipment from archive
 *
 * @param mysqli $db Database connection
 * @param int $archiveId Archive ID of the tool to restore
 * @return array Result of the operation
 */
function restoreTool($db, $archiveId) {
    try {
        // Begin transaction
        $db->begin_transaction();

        // Get the archived tool data
        $stmt = $db->prepare("SELECT * FROM archived_tools_equipment WHERE archive_id = ?");
        $stmt->bind_param("i", $archiveId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tool = $result->fetch_assoc();

        if (!$tool) {
            return ['success' => false, 'error' => 'Archived tool not found'];
        }

        // Check if the original ID already exists in the main table
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM tools_equipment WHERE id = ?");
        $stmt->bind_param("i", $tool['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $exists = $row['count'] > 0;

        // If the ID exists, we'll need to insert without specifying the ID
        if ($exists) {
            $stmt = $db->prepare("INSERT INTO tools_equipment
                (name, category, quantity, description, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "ssisss",
                $tool['name'],
                $tool['category'],
                $tool['quantity'],
                $tool['description'],
                $tool['created_at'],
                $tool['updated_at']
            );
        } else {
            // Insert with the original ID
            $stmt = $db->prepare("INSERT INTO tools_equipment
                (id, name, category, quantity, description, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "ississs",
                $tool['id'],
                $tool['name'],
                $tool['category'],
                $tool['quantity'],
                $tool['description'],
                $tool['created_at'],
                $tool['updated_at']
            );
        }
        $stmt->execute();

        // Delete from archive table
        $stmt = $db->prepare("DELETE FROM archived_tools_equipment WHERE archive_id = ?");
        $stmt->bind_param("i", $archiveId);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        return ['success' => true];
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Clean up expired archived items
 *
 * @param mysqli $db Database connection
 * @return array Result of the operation with counts of deleted items
 */
function cleanupExpiredArchives($db) {
    $today = date('Y-m-d');
    $deletedCounts = [
        'chemicals' => 0,
        'clients' => 0,
        'technicians' => 0,
        'tools' => 0,
        'checklist_logs' => 0
    ];

    try {
        // Delete expired chemicals
        $stmt = $db->prepare("DELETE FROM archived_chemical_inventory WHERE scheduled_deletion_date <= ?");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $deletedCounts['chemicals'] = $stmt->affected_rows;

        // Delete expired clients
        $stmt = $db->prepare("DELETE FROM archived_clients WHERE scheduled_deletion_date <= ?");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $deletedCounts['clients'] = $stmt->affected_rows;

        // Delete expired technicians
        $stmt = $db->prepare("DELETE FROM archived_technicians WHERE scheduled_deletion_date <= ?");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $deletedCounts['technicians'] = $stmt->affected_rows;

        // Delete expired tools
        $stmt = $db->prepare("DELETE FROM archived_tools_equipment WHERE scheduled_deletion_date <= ?");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $deletedCounts['tools'] = $stmt->affected_rows;

        // Check if the archived_technician_checklist_logs table exists
        $tableExists = $db->query("SHOW TABLES LIKE 'archived_technician_checklist_logs'")->num_rows > 0;

        if ($tableExists) {
            // Delete expired checklist logs
            $stmt = $db->prepare("DELETE FROM archived_technician_checklist_logs WHERE scheduled_deletion_date <= ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $deletedCounts['checklist_logs'] = $stmt->affected_rows;
        }

        return [
            'success' => true,
            'deleted_counts' => $deletedCounts
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
