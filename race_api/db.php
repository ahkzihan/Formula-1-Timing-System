<?php
$host = "localhost";
$user = "assignment2";
$password = "4euvBEKgaNCoFbJ5";
$dbname = "assignment2";  // Your DB name

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
