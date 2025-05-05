<?php
function ConnectDB() {
    // Production database credentials
    $hostname = "151.106.122.5";
    $dbuser = "u701062148_macj";
    $dbname = "u701062148_macj";
    $dbpassword = "Macjpestcontrol123";

    try {
        $dsn = "mysql:host=$hostname;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $dbuser, $dbpassword, $options);
        return $pdo;
    } catch (\PDOException $e) {
        error_log("Database connection failed in " . __FILE__ . " with error: " . $e->getMessage());
        error_log("Connection attempted with user: $dbuser on host: $hostname");
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}
?>
