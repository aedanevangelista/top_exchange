<?php
// Start the session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    error_log("User not logged in - session username missing");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if password was provided
if (!isset($_POST['password']) || empty($_POST['password'])) {
    error_log("Password not provided in POST request");
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit();
}

// Log session data for debugging (remove in production)
error_log("Session data: user_id=" . ($_SESSION['user_id'] ?? 'not set') .
          ", username=" . $_SESSION['username'] .
          ", role=" . ($_SESSION['role'] ?? 'not set'));

// Connect to database
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$username = $_SESSION['username'];
$password = $_POST['password'];

// Get the user's stored password from the database
error_log("Looking up password for username: " . $username);

// First check clients_accounts table by username
$stmt = $conn->prepare("SELECT id, username, password, email FROM clients_accounts WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// If not found by username, try checking by email (in case email is used as username)
if ($result->num_rows === 0) {
    error_log("User not found by username in clients_accounts, trying email field");
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, username, password, email FROM clients_accounts WHERE email = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        error_log("User found by email in clients_accounts table");
    }
}

// If not found in clients_accounts, check accounts table
if ($result->num_rows === 0) {
    error_log("User not found in clients_accounts table, checking accounts table");
    $stmt->close();

    // Try accounts table
    $stmt = $conn->prepare("SELECT id, username, password, role FROM accounts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("User not found in any account table: " . $username);
        echo json_encode(['success' => false, 'message' => 'User not found in any account table']);
        exit();
    }
    error_log("User found in accounts table");
} else {
    error_log("User found in clients_accounts table");
}

$user = $result->fetch_assoc();
$storedPassword = $user['password'];

// Log user details for debugging (remove in production)
error_log("User details - ID: " . $user['id'] . ", Username: " . $user['username']);
error_log("Password hash type: " . (password_get_info($storedPassword)['algo'] ? 'PHP password_hash' : 'Not PHP password_hash'));

// Add rate limiting to prevent brute force attacks
$maxAttempts = 5; // Maximum number of attempts allowed
$timeWindow = 15 * 60; // 15 minutes in seconds

// Check if we need to initialize the attempts counter
if (!isset($_SESSION['password_verify_attempts'])) {
    $_SESSION['password_verify_attempts'] = 0;
    $_SESSION['password_verify_time'] = time();
}

// Check if we need to reset the counter (time window expired)
if (time() - $_SESSION['password_verify_time'] > $timeWindow) {
    $_SESSION['password_verify_attempts'] = 0;
    $_SESSION['password_verify_time'] = time();
}

// Check if too many attempts
if ($_SESSION['password_verify_attempts'] >= $maxAttempts) {
    // Calculate time remaining until reset
    $timeRemaining = $timeWindow - (time() - $_SESSION['password_verify_time']);
    $minutesRemaining = ceil($timeRemaining / 60);

    echo json_encode([
        'success' => false,
        'message' => "Too many failed attempts. Please try again in {$minutesRemaining} minutes."
    ]);
    exit();
}

// Increment attempt counter
$_SESSION['password_verify_attempts']++;

// Verify the password
$passwordMatches = false;

// Debug: Log for troubleshooting
error_log("Verifying password for user: " . $username);

try {
    // Primary method: Check if password is hashed with password_hash (recommended)
    if (password_verify($password, $storedPassword)) {
        $passwordMatches = true;
        error_log("Password matched with password_verify");
    }
    // Fallback method: Direct database query as a last resort
    else {
        // This is a special case for this specific system
        $fallbackQuery = "SELECT * FROM clients_accounts WHERE username = ? AND password = ?";
        $fallbackStmt = $conn->prepare($fallbackQuery);
        if (!$fallbackStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $bindResult = $fallbackStmt->bind_param("ss", $username, $password);
        if (!$bindResult) {
            throw new Exception("Bind failed: " . $fallbackStmt->error);
        }

        $executeResult = $fallbackStmt->execute();
        if (!$executeResult) {
            throw new Exception("Execute failed: " . $fallbackStmt->error);
        }

        $fallbackResult = $fallbackStmt->get_result();

        if ($fallbackResult && $fallbackResult->num_rows > 0) {
            // Fallback method worked
            $passwordMatches = true;
            error_log("Password matched with direct database query");
        } else {
            error_log("Password verification failed with direct database query");

            // Try one more fallback with email as username
            $emailFallbackQuery = "SELECT * FROM clients_accounts WHERE email = ? AND password = ?";
            $emailFallbackStmt = $conn->prepare($emailFallbackQuery);
            if ($emailFallbackStmt) {
                $emailFallbackStmt->bind_param("ss", $username, $password);
                $emailFallbackStmt->execute();
                $emailFallbackResult = $emailFallbackStmt->get_result();

                if ($emailFallbackResult && $emailFallbackResult->num_rows > 0) {
                    $passwordMatches = true;
                    error_log("Password matched with email fallback query");
                } else {
                    error_log("Password verification failed with all methods");
                }

                $emailFallbackStmt->close();
            }
        }

        if ($fallbackStmt) {
            $fallbackStmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Error during password verification: " . $e->getMessage());
}

if ($passwordMatches) {
    // Reset attempts on successful verification
    $_SESSION['password_verify_attempts'] = 0;
    echo json_encode(['success' => true, 'message' => 'Password verified successfully']);
} else {
    // Calculate attempts remaining
    $attemptsRemaining = $maxAttempts - $_SESSION['password_verify_attempts'];

    // Log the failed attempt for debugging
    error_log("Password verification failed for user: " . $username);

    echo json_encode([
        'success' => false,
        'message' => "Incorrect password. Please try again. {$attemptsRemaining} attempts remaining."
    ]);
}

$stmt->close();
$conn->close();
?>
