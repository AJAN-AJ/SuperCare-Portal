<?php
session_start();
require_once "../config/db.php";

if ($_SESSION["role"]!="admin") {
exit();
}

$id=$_GET["id"];

$sql="
SELECT
lr.*,
lt.reduces_balance,
u.annual_leave_days,
u.annual_leave_used
FROM leave_requests lr
JOIN leave_types lt
ON lr.leave_type_id=lt.id
JOIN users u
ON lr.user_id=u.id
WHERE lr.id=?
";

$stmt=$conn->prepare($sql);

$stmt->bind_param("i",$id);

$stmt->execute();

$row=
$stmt
->get_result()
->fetch_assoc();

if(!$row){
die("Request not found");
}

$conn->begin_transaction();

try{

if($row["reduces_balance"]){

$remaining=
$row["annual_leave_days"]
-
$row["annual_leave_used"];

if(
$row["total_days"]
>
$remaining
){

throw new Exception(
"Not enough leave balance."
);

}

$update=$conn->prepare("
UPDATE users
SET annual_leave_used=
annual_leave_used+?
WHERE id=?
");

$update->bind_param(
"ii",
$row["total_days"],
$row["user_id"]
);

$update->execute();

}

$approve=$conn->prepare("
UPDATE leave_requests
SET
status='approved',
approved_by=?,
approved_at=NOW()
WHERE id=?
");

$approve->bind_param(
"ii",
$_SESSION["user_id"],
$id
);

$approve->execute();

$conn->commit();

header(
"Location: leave_requests.php"
);

}catch(Exception $e){

$conn->rollback();

die(
$e->getMessage()
);

}