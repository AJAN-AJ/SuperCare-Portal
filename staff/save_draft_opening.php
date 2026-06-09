<?php
// staff/save_draft_opening.php
// Called via AJAX to save individual platform amounts as draft
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller") {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$user_id    = $_SESSION["user_id"];
$today      = date("Y-m-d");

// Get today's session
$stmt = $conn->prepare("SELECT id, status FROM balance_sessions WHERE user_id=? AND balance_date=?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    echo json_encode(["success" => false, "message" => "No session found"]);
    exit();
}

// Only allow saving if not yet approved
if ($session["status"] == "approved_opening") {
    echo json_encode(["success" => false, "message" => "Session locked"]);
    exit();
}

$session_id  = $session["id"];
$platform_id = intval($_POST["platform_id"] ?? 0);
$amount      = floatval(str_replace(",", "", $_POST["amount"] ?? 0));

if (!$platform_id) {
    echo json_encode(["success" => false, "message" => "Invalid platform"]);
    exit();
}

// Check if entry exists
$chk = $conn->prepare("SELECT id FROM balance_platform_entries WHERE session_id=? AND platform_id=?");
$chk->bind_param("ii", $session_id, $platform_id);
$chk->execute();
$exists = $chk->get_result()->num_rows > 0;

if ($exists) {
    $upd = $conn->prepare("UPDATE balance_platform_entries SET opening_amount=? WHERE session_id=? AND platform_id=?");
    $upd->bind_param("dii", $amount, $session_id, $platform_id);
    $upd->execute();
} else {
    $ins = $conn->prepare("INSERT INTO balance_platform_entries (session_id, platform_id, opening_amount) VALUES (?,?,?)");
    $ins->bind_param("iid", $session_id, $platform_id, $amount);
    $ins->execute();
}

// Update the draft total
$totStmt = $conn->prepare("SELECT COALESCE(SUM(opening_amount),0) total FROM balance_platform_entries WHERE session_id=?");
$totStmt->bind_param("i", $session_id);
$totStmt->execute();
$total = $totStmt->get_result()->fetch_assoc()["total"];

$updTotal = $conn->prepare("UPDATE balance_sessions SET opening_total=? WHERE id=?");
$updTotal->bind_param("di", $total, $session_id);
$updTotal->execute();

echo json_encode(["success" => true, "total" => $total]);
exit();