<?php
// Detect environment
$isLocal = ($_SERVER['SERVER_NAME'] === 'localhost');

// LOCAL (XAMPP)
if ($isLocal) {
    $host = "localhost";
    $user = "root";
    $password = "";
    $dbname = "akwaaba";
} else {
    // LIVE HOST (InfinityFree)
    $host = "sql100.infinityfree.com";
    $user = "if0_41688761";
    $password = "JxDEEfoeIpxbm"; // placeholder
    $dbname = "if0_41688761_akwaaba";
}

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>