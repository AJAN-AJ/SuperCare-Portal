<?php
session_start();

require_once "../config/db.php";

$id=$_GET["id"];

$stmt=
$conn->prepare("
UPDATE leave_requests
SET
status='rejected',
approved_by=?,
approved_at=NOW()
WHERE id=?
");

$stmt->bind_param(
"ii",
$_SESSION["user_id"],
$id
);

$stmt->execute();

header(
"Location: leave_requests.php"
);