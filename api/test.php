<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode([
    'step' => 1,
    'php_working' => true,
    'php_version' => phpversion()
]);

try {
    $conn = new mysqli('localhost', 'root', 'Annaramos14', 'sunset_resort');
    
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }
    
    echo json_encode([
        'step' => 2,
        'database_connected' => true,
        'database_name' => 'sunset_resort'
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'step' => 'ERROR',
        'error_message' => $e->getMessage(),
        'error_trace' => $e->getTraceAsString()
    ]);
}
?>