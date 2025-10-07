<?php
// api/auth.php - Authentication API
session_start();
require_once '../config.php';

$conn = getDBConnection();
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'login':
        login($conn);
        break;
    case 'register':
        register($conn);
        break;
    case 'logout':
        logout();
        break;
    case 'getCurrentUser':
        getCurrentUser($conn);
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function login($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = sanitize($data['email'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendResponse(false, 'Email and password are required');
    }
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'Invalid email or password');
    }
    
    $user = $result->fetch_assoc();
    
    // For demo purposes, using plain text comparison
    // In production, use password_verify($password, $user['password'])
    if ($password !== $user['password']) {
        sendResponse(false, 'Invalid email or password');
    }
    
    // Store user in session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    
    // Remove password from response
    unset($user['password']);
    
    sendResponse(true, 'Login successful', $user);
}

function register($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitize($data['name'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $phone = sanitize($data['phone'] ?? '');
    $dob = sanitize($data['dob'] ?? '');
    $address = sanitize($data['address'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        sendResponse(false, 'Name, email and password are required');
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email format');
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, 'Email already registered');
    }
    
    // Insert new user
    // In production, use password_hash($password, PASSWORD_DEFAULT)
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, date_of_birth, address, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $phone, $dob, $address, $password);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Registration successful', ['id' => $conn->insert_id]);
    } else {
        sendResponse(false, 'Registration failed: ' . $conn->error);
    }
}

function logout() {
    session_destroy();
    sendResponse(true, 'Logged out successfully');
}

function getCurrentUser($conn) {
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT id, name, email, phone, date_of_birth as dob, address, created_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            sendResponse(true, 'User found', $result->fetch_assoc());
        }
    }
    sendResponse(false, 'No user logged in', null);
}

$conn->close();
?>