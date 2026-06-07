<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$teller_id = $_GET['id'] ?? null;
if (!$teller_id || !is_numeric($teller_id)) die("Invalid teller ID.");

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $teller_id);
$stmt->execute();
$teller = $stmt->get_result()->fetch_assoc();
if (!$teller) die("Teller not found.");

$error   = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username          = trim($_POST["username"]);
    $full_name         = trim($_POST["full_name"]);
    $profile_completed = isset($_POST["profile_completed"]) ? 1 : 0;
    $annual_leave_days = intval($_POST["annual_leave_days"]);
    $password          = trim($_POST["password"]);

    if (empty($username) || empty($full_name)) {
        $error = "Username and Full Name are required.";
    } else {
        if ($password !== "") {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET username=?, full_name=?, profile_completed=?, annual_leave_days=?, password=? WHERE id=?");
            $update->bind_param("ssiisi", $username, $full_name, $profile_completed, $annual_leave_days, $password_hash, $teller_id);
        } else {
            $update = $conn->prepare("UPDATE users SET username=?, full_name=?, profile_completed=?, annual_leave_days=? WHERE id=?");
            $update->bind_param("ssiii", $username, $full_name, $profile_completed, $annual_leave_days, $teller_id);
        }

        if ($update->execute()) {
            $success = "Teller updated successfully.";
            $stmt->execute();
            $teller = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

$remaining = max(0, $teller["annual_leave_days"] - $teller["annual_leave_used"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) {
            input, select { font-size: 16px !important; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white">

<div class="flex h-screen overflow-hidden">

    <?php include "../includes/admin_sidebar.php"; ?>

    <div class="flex-1 flex flex-col overflow-y-auto">

        <!-- Sticky header -->
        <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
            <div class="px-4 sm:px-6 py-3 flex items-center gap-3">
                <a href="manage_tellers.php"
                   class="flex items-center gap-1 bg-gray-700 hover:bg-gray-600 active:bg-gray-500
                          px-3 py-2 rounded-lg text-sm font-medium transition-colors shrink-0">
                    <span>←</span>
                    <span class="hidden sm:inline">Tellers</span>
                </a>
                <div>
                    <h1 class="text-lg sm:text-xl font-bold leading-tight">Edit Teller</h1>
                    <p class="text-xs text-gray-400 hidden sm:block"><?= htmlspecialchars($teller["username"]) ?></p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 p-4 sm:p-6">
            <div class="max-w-lg mx-auto space-y-4">

                <!-- Alerts -->
                <?php if ($error): ?>
                <div class="flex items-start gap-3 p-4 rounded-xl bg-red-700/40 border border-red-600 text-red-300 text-sm">
                    <span class="text-lg leading-none">✕</span>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="flex items-start gap-3 p-4 rounded-xl bg-green-700/40 border border-green-600 text-green-300 text-sm">
                    <span class="text-lg leading-none">✓</span>
                    <p><?= htmlspecialchars($success) ?></p>
                </div>
                <?php endif; ?>

                <!-- Form card -->
                <div class="bg-gray-800 rounded-2xl p-4 sm:p-6 shadow">
                    <form method="POST" class="space-y-5">

                        <!-- Username -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-gray-300" for="username">
                                Username <span class="text-red-400">*</span>
                            </label>
                            <input type="text" id="username" name="username" required
                                   value="<?= htmlspecialchars($teller["username"]) ?>"
                                   class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                          focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                          outline-none transition-colors text-white">
                        </div>

                        <!-- Full Name -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-gray-300" for="full_name">
                                Full Name <span class="text-red-400">*</span>
                            </label>
                            <input type="text" id="full_name" name="full_name" required
                                   value="<?= htmlspecialchars($teller["full_name"]) ?>"
                                   class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                          focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                          outline-none transition-colors text-white">
                        </div>

                        <!-- Leave allocation -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-gray-300" for="annual_leave_days">
                                Annual Leave Allocation (Days)
                            </label>
                            <input type="number" id="annual_leave_days" name="annual_leave_days" min="0"
                                   value="<?= $teller["annual_leave_days"] ?>"
                                   class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                          focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                          outline-none transition-colors text-white">
                        </div>

                        <!-- Leave summary -->
                        <div class="bg-gray-700/50 border border-gray-600 rounded-xl p-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-gray-400 text-xs mb-1">Used Leave</p>
                                <p class="text-white font-semibold text-lg"><?= $teller["annual_leave_used"] ?> <span class="text-xs font-normal text-gray-400">days</span></p>
                            </div>
                            <div>
                                <p class="text-gray-400 text-xs mb-1">Remaining</p>
                                <p class="<?= $remaining <= 3 ? 'text-red-400' : 'text-green-400' ?> font-semibold text-lg">
                                    <?= $remaining ?> <span class="text-xs font-normal text-gray-400">days</span>
                                </p>
                            </div>
                        </div>

                        <!-- Profile completed toggle -->
                        <label class="flex items-center gap-3 bg-gray-700/50 border border-gray-600 rounded-xl p-4 cursor-pointer hover:bg-gray-700 transition-colors">
                            <input type="checkbox" name="profile_completed" value="1"
                                   <?= $teller["profile_completed"] ? "checked" : "" ?>
                                   class="w-5 h-5 rounded accent-blue-500 cursor-pointer">
                            <div>
                                <p class="font-medium text-sm">Profile Completed</p>
                                <p class="text-gray-400 text-xs mt-0.5">Mark if this teller has finished their profile setup</p>
                            </div>
                        </label>

                        <!-- Reset password -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-gray-300" for="password">
                                Reset Password <span class="text-gray-500 font-normal">(optional)</span>
                            </label>
                            <input type="password" id="password" name="password"
                                   placeholder="Leave blank to keep current password"
                                   class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                          focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                          outline-none transition-colors text-white placeholder-gray-500">
                        </div>

                        <!-- Submit -->
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                                       p-3.5 rounded-xl font-bold text-base transition-colors
                                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800">
                            Update Teller
                        </button>

                    </form>
                </div>

            </div>
        </div><!-- /content -->
    </div><!-- /flex-1 -->
</div><!-- /flex h-screen -->

</body>
</html>