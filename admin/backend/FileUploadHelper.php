<?php
/**
 * File Upload Helper
 * Consistently handles file uploads and paths
 */
class FileUploadHelper {
    private $baseUploadDir; // Server filesystem path
    private $baseUrlPath;   // URL path
    
    /**
     * Initialize with base paths
     */
    public function __construct($baseUploadDir = null, $baseUrlPath = null) {
        // Default paths
        $this->baseUploadDir = $baseUploadDir ?: rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . 
                               DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $this->baseUrlPath = $baseUrlPath ?: '/admin/uploads/';
    }
    
    /**
     * Generate a safe filename
     */
    public function generateSafeFilename($username, $originalFilename) {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalFilename, PATHINFO_FILENAME));
        $safeBaseName = trim(substr($safeBaseName, 0, 50), '_');
        
        if (empty($safeBaseName)) {
            $safeBaseName = 'file';
        }
        
        $uniqueId = bin2hex(random_bytes(5));
        return $username . '_' . $safeBaseName . '_' . $uniqueId . '.' . $extension;
    }
    
    /**
     * Create user directory
     */
    public function createUserDirectory($username) {
        $userDir = $this->baseUploadDir . $username . DIRECTORY_SEPARATOR;
        
        if (!is_dir($userDir) && !mkdir($userDir, 0775, true) && !is_dir($userDir)) {
            throw new Exception('Failed to create upload directory: ' . $userDir);
        }
        
        return $userDir;
    }
    
    /**
     * Upload a file
     */
    public function uploadFile($username, $tmpName, $originalFilename, $allowedTypes = ['image/jpeg', 'image/png'], $maxSize = 20971520) {
        // Validate file
        $fileType = mime_content_type($tmpName);
        $fileSize = filesize($tmpName);
        
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid file type for ' . $originalFilename);
        }
        
        if ($fileSize > $maxSize) {
            throw new Exception('File ' . $originalFilename . ' exceeds size limit');
        }
        
        // Create directory
        $userDir = $this->createUserDirectory($username);
        
        // Generate safe filename
        $safeFilename = $this->generateSafeFilename($username, $originalFilename);
        
        // File paths
        $filePath = $userDir . $safeFilename;
        $urlPath = $this->baseUrlPath . $username . '/' . $safeFilename;
        
        // Upload file
        if (!move_uploaded_file($tmpName, $filePath)) {
            throw new Exception('Failed to upload file: ' . $originalFilename);
        }
        
        return [
            'filePath' => $filePath,     // Server path
            'urlPath' => $urlPath,       // URL path always starting with /admin/uploads/
            'filename' => $safeFilename  // Just the filename
        ];
    }
    
    /**
     * Normalize a path for storage
     */
    public static function normalizePath($path, $username = null) {
        if (empty($path)) return '';
        
        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);
        
        // Convert relative paths
        if (strpos($path, '../..') === 0) {
            $path = str_replace('../../', '/admin/', $path);
        }
        
        // Ensure path starts with /admin/
        if (!preg_match('#^/admin/#', $path)) {
            // If it's just a filename and we know the username
            if ($username && strpos($path, '/') === false) {
                $path = '/admin/uploads/' . $username . '/' . $path;
            } else if (strpos($path, '/') === 0) {
                // If it starts with / but not /admin/
                $path = '/admin' . $path;
            } else if ($username) {
                // Otherwise, use the filename part
                $path = '/admin/uploads/' . $username . '/' . basename($path);
            }
        }
        
        return $path;
    }
}