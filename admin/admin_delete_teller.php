<?php
session_start();
require_once "../config/db.php";

ini_set("display_errors",1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$teller_id = $_GET['id'] ?? null;
if (!$teller_id) die("Invalid teller ID.");

// Optional: Check if teller has any sessions before deleting
$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM balance_sessions WHERE user_id=?");
$stmt->bind_param("i", $teller_id);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['count'];

if ($count > 0) {
    die("Cannot delete teller with existing balance sessions.");
}

// Delete teller
$del = $conn->prepare("UPDATE users SET is_active = 0 WHERE id=?");
$del->bind_param("i", $teller_id);
$del->execute();

header("Location: manage_tellers.php");
exit();
