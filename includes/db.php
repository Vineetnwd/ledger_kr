<?php
session_start();

define('BASE_URL', 'https://ledger.krnursingcollege.com');

$host     = 'localhost';
$dbname   = 'u673864504_kr_ledger';
$username = 'u673864504_kr_ledger';
$password = '@Kr_Ledger_2001';

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

require_once __DIR__ . '/functions.php';
?>