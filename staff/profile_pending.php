<?php
session_start();
require_once "../config/db.php";
require_once "../includes/session_guard.php";

//$session_id = ensureDailySession($conn, $user_id);

// Ensure only logged-in tellers can access
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller") {
    header("Location: ../login.php");
    exit();
}

// Fetch user info
$user_id = $_SESSION["user_id"];
$stmt = $conn->prepare("SELECT full_name, profile_completed, approved FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userResult = $stmt->get_result();
$user = $userResult->fetch_assoc();

// Redirect if profile not completed (they should fill profile first)
if ($user['profile_completed'] == 0) {
    header("Location: profile_setup.php");
    exit();
}

// Redirect if already approved → dashboard
if ($user['approved'] == 1) {
    header("Location: dashboard.php");
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile Pending Approval</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">

<div class="bg-gray-800 p-8 rounded-2xl shadow-lg max-w-md text-center">
    <h2 class="text-2xl font-bold mb-4">Profile Pending Approval</h2>
    <p class="mb-4 text-gray-300">
        Hello <?= htmlspecialchars($user['full_name'] ?: "Teller"); ?>, your profile has been submitted successfully.
    </p>
    <p class="mb-6 text-yellow-400">
        ⚠ Your profile is awaiting admin approval. You will be able to access your dashboard once approved.
    </p>

    <a href="../logout.php" 
       class="bg-red-600 hover:bg-red-700 px-6 py-3 rounded-lg font-semibold transition">
        Logout
    </a>
</div>

</body>
</html>
