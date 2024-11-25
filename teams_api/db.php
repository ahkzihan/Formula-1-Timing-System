<?php
// Connection details
$servername = "localhost";
$mysql_username = "assignment2";
$mysql_password = "hIRU4PWGyccT6jYk";
$database = "assignment2";

// Create connection
$conn = new mysqli($servername, $mysql_username, $mysql_password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optionally, set character set to UTF-8
if (!$conn->set_charset("utf8mb4")) {
    die("Error loading character set utf8mb4: " . $conn->error);
}
?>
