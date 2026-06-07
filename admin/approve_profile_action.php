<?php
session_start();
require_once "../config/db.php";
//require_once "../includes/audit.php";
ini_set("display_errors", 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: approve_profiles.php");
    exit();
}

$teller_id = intval($_GET['id']);

// Approve teller profile
$stmt = $conn->prepare("UPDATE users SET approved = 1 WHERE id = ?");
$stmt->bind_param("i", $teller_id);
$stmt->execute();

/*logAction(
    $conn,
    $_SESSION['user_id'],
    "Approve Profile",
    "Approved profile for user ID $user_id"
);*/

header("Location: approve_profiles.php");
exit();
?>
