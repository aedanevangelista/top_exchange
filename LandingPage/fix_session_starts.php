<?php
// This script will fix all session_start() calls in PHP files
// to prevent "session already started" notices

// Directory to scan
$directory = __DIR__;

// Get all PHP files in the directory and subdirectories
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory)
);

$phpFiles = [];
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}

echo "Found " . count($phpFiles) . " PHP files to check.\n";

// Counter for modified files
$modifiedFiles = 0;

// Process each file
foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    
    // Skip files that already have the correct session check
    if (strpos($content, 'session_status()') !== false) {
        echo "Skipping (already fixed): " . basename($file) . "\n";
        continue;
    }
    
    // Check if the file contains a direct session_start() call
    if (preg_match('/^\s*session_start\(\);/m', $content)) {
        // Replace the direct session_start() call with the conditional version
        $newContent = preg_replace(
            '/^\s*session_start\(\);/m',
            "// Start the session if it hasn't been started already\nif (session_status() == PHP_SESSION_NONE) {\n    session_start();\n}",
            $content
        );
        
        // Write the modified content back to the file
        file_put_contents($file, $newContent);
        
        echo "Fixed: " . basename($file) . "\n";
        $modifiedFiles++;
    } else {
        echo "Skipping (no direct session_start call): " . basename($file) . "\n";
    }
}

echo "Completed! Modified $modifiedFiles files.\n";
?>
