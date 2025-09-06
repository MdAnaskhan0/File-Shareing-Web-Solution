<?php
// Database config
$host = "localhost";
$user = "root"; // change if needed
$pass = "";
$dbname = "file_sharing";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

session_start();
?>
