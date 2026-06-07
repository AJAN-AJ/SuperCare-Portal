<?php
session_start();
require_once "../config/db.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] != "admin"
) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET["id"])) {
    header("Location: dashboard.php");
    exit();
}

$session_id = (int) $_GET["id"];
$admin_id = $_SESSION["user_id"];


/*
|-------------------------------------------------
| Approve Closing
|-------------------------------------------------
*/
$stmt = $conn->prepare("
    UPDATE balance_sessions
    SET
        status='approved_closing',
        approved_by=?,
        approved_at=NOW()
    WHERE id=?
");

if (!$stmt) {
    die("Prepare Error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $admin_id,
    $session_id
);

if (!$stmt->execute()) {
    die("Execute Error: " . $stmt->error);
}


/*
|-------------------------------------------------
| Optional Audit (skip if audit file unavailable)
|-------------------------------------------------
*/

if (
    file_exists("../includes/audit.php")
) {

    require_once "../includes/audit.php";

    if (function_exists("logAction")) {

        logAction(
            $conn,
            $admin_id,
            "Approve Closing",
            "Approved closing session #" . $session_id
        );

    }
}

header("Location: dashboard.php");
exit();