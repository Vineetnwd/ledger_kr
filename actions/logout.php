<?php
session_start();
require_once '../includes/db.php';
session_destroy();
header("Location: " . BASE_URL . "/login.php");
exit();
