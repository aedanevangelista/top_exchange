<?php
// Base URL configuration
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$script_name = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
$base_url = $protocol . $host . $script_name;
$base_url = rtrim($base_url, '/'); // Remove trailing slash if present

// Define constants for asset paths
define('BASE_URL', $base_url);
define('ASSETS_URL', $base_url . '/assets');
?> 