<?php
$host = "sql111.infinityfree.com";
$user = "if0_42122075";
$password = "sucaso78com";
$database = "if0_42122075_supercareData";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error){
	die("Connection failed: ".$conn->connect_error);
}
?>
