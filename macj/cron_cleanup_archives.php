<?php
/**
 * Cron Job Script for Cleaning Up Expired Archives
 * 
 * This script should be run daily to delete archived items that have reached their scheduled deletion date.
 * 
 * Usage:
 * - Set up a cron job to run this script daily
 * - Example cron job: 0 0 * * * php /path/to/cron_cleanup_archives.php
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once 'db_connect.php';
require_once 'Admin Side/archive_functions.php';

// Log file
$logFile = 'archive_cleanup_log.txt';

// Function to log messages
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Log start of cleanup
logMessage("Starting archive cleanup process");

try {
    // Clean up expired archives
    $result = cleanupExpiredArchives($conn);
    
    if ($result['success']) {
        $deletedCounts = $result['deleted_counts'];
        $totalDeleted = array_sum($deletedCounts);
        
        logMessage("Cleanup completed successfully");
        logMessage("Deleted items: " . $totalDeleted);
        logMessage("- Chemicals: " . $deletedCounts['chemicals']);
        logMessage("- Clients: " . $deletedCounts['clients']);
        logMessage("- Technicians: " . $deletedCounts['technicians']);
        logMessage("- Tools: " . $deletedCounts['tools']);
    } else {
        logMessage("Cleanup failed: " . $result['error']);
    }
} catch (Exception $e) {
    logMessage("Error during cleanup: " . $e->getMessage());
}

// Log end of cleanup
logMessage("Archive cleanup process completed");
