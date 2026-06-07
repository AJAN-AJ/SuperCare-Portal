<?php
session_start();
require_once "../config/db.php";
//require_once "../includes/session_guard.php";

//$session_id = ensureDailySession($conn, $user_id);

ini_set('display_errors', 1);
error_reporting(E_ALL);

if(!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller" ){
		header("Location: ../login.php");
		exit();
	}
$user_id = $_SESSION["user_id"];
$branch_id = $_SESSION["branch_id"];

$current_datetime = date("Y-m-d  H:i:s");
$current_time = date("H:i:s");

$cutoff_time = "07:30:00";

$status =  ($current_time <= $cutoff_time) ? "present" : "absent"; //determining statuss

$ip = $_SERVER['REMOTE_ADDR'];


// check if aalredy checked in today
$stmt_check = $conn->prepare(
"SELECT id FROM attendance WHERE user_id = ? AND DATE(check_in_time) = CURDATE() "
);
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0){
		header("Location: dashboard.php");
		exit();
	}


/*$allowed_network = "192.168.1."
if (strpos($ip, $allowed_network) !== 0){
	die("Check-in is only allowed within the shop network.");
}*/




$stmt = $conn->prepare("INSERT INTO attendance (user_id, branch_id, check_in_time, status, recorded_ip) VALUES ( ?, ?, ?, ?, ?)");
$stmt->bind_param("iisss", $user_id, $branch_id, $current_datetime, $status, $ip);



if($stmt->execute()){
	header("Location: opening_select_platforms.php");
	exit();
}else{
	echo "Error: " . $stmt->error;
}
?>
