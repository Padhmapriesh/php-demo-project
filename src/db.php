<?php
// Database connection settings — pulled from environment variables.
// Set these as env vars when running the Docker container (see README).
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'appdb';
$DB_USER = getenv('DB_USER') ?: 'admin';
$DB_PASS = getenv('DB_PASS') ?: 'password';
$DB_PORT = getenv('DB_PORT') ?: '3306';

function getConnection() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_PORT;
    try {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
        return $conn;
    } catch (Exception $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Auto-create the table if it doesn't exist yet (handy for first run / demo)
function ensureTable() {
    $conn = getConnection();
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    $conn->close();
}
