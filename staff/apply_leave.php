<?php
session_start();
require_once "../config/db.php";
//require_once "../includes/session_guard.php";
//$session_id = ensureDailySession($conn, $user_id);
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller") {
    header("Location: ../login.php");
    exit();
}
$user_id = $_SESSION["user_id"];

/* Fetch teller */
$userStmt = $conn->prepare("
    SELECT annual_leave_days, annual_leave_used
    FROM users WHERE id=?
");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$remaining_leave = $user["annual_leave_days"] - $user["annual_leave_used"];

/* Leave types */
$leaveTypes = $conn->query("SELECT * FROM leave_types ORDER BY name");

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $leave_type_id = intval($_POST["leave_type_id"]);
    $start_date    = $_POST["start_date"];
    $end_date      = $_POST["end_date"];
    $reason        = trim($_POST["reason"]);

    if (strtotime($start_date) < strtotime(date("Y-m-d"))) {
        $message     = "Leave cannot start in the past.";
        $messageType = "error";
    } else {
        $days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
        if ($days <= 0) {
            $message     = "End date must be after start date.";
            $messageType = "error";
        } else {
            $check = $conn->prepare("
                SELECT id FROM leave_requests
                WHERE user_id=? AND status='pending'
            ");
            $check->bind_param("i", $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message     = "You already have a pending leave request.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO leave_requests
                        (user_id, leave_type_id, start_date, end_date, total_days, reason)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iissis", $user_id, $leave_type_id, $start_date, $end_date, $days, $reason);
                if ($stmt->execute()) {
                    $message     = "Leave request submitted successfully.";
                    $messageType = "success";
                } else {
                    $message     = "Submission failed. Please try again.";
                    $messageType = "error";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Leave</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Ensure date inputs look consistent on mobile */
        input[type="date"] {
            -webkit-appearance: none;
            appearance: none;
        }
        /* Prevent zoom on input focus on iOS */
        @media (max-width: 640px) {
            select, input, textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Top bar / header -->
    <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
        <div class="max-w-xl mx-auto px-4 py-3 flex items-center gap-3">
            <a href="dashboard.php"
               class="flex items-center gap-1 bg-gray-700 hover:bg-gray-600 active:bg-gray-500
                      px-3 py-2 rounded-lg text-sm font-medium transition-colors shrink-0">
                <span>←</span>
                <span class="hidden sm:inline">Dashboard</span>
            </a>
            <h1 class="text-lg sm:text-xl font-bold truncate">Apply For Leave</h1>
        </div>
    </div>

    <!-- Main content -->
    <div class="max-w-xl mx-auto px-4 py-5 space-y-4">

        <!-- Leave balance card -->
        <div class="bg-gray-800 rounded-xl p-4 flex items-center justify-between">
            <span class="text-gray-400 text-sm sm:text-base">Annual Leave Remaining</span>
            <span class="text-green-400 font-bold text-lg sm:text-xl">
                <?= $remaining_leave ?> <span class="text-sm font-normal text-green-300">days</span>
            </span>
        </div>

        <!-- Alert message -->
        <?php if ($message): ?>
        <div class="flex items-start gap-3 p-4 rounded-xl
                    <?= $messageType === "success" ? "bg-green-700/50 border border-green-500" : "bg-red-700/50 border border-red-500" ?>">
            <span class="text-xl leading-none mt-0.5">
                <?= $messageType === "success" ? "✓" : "✕" ?>
            </span>
            <p class="text-sm sm:text-base"><?= htmlspecialchars($message) ?></p>
        </div>
        <?php endif; ?>

        <!-- Form card -->
        <div class="bg-gray-800 p-4 sm:p-6 rounded-xl">
            <form method="POST" class="space-y-5">

                <!-- Leave Type -->
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-gray-300" for="leave_type_id">
                        Leave Type <span class="text-red-400">*</span>
                    </label>
                    <select id="leave_type_id" name="leave_type_id" required
                            class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                   focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500
                                   text-white transition-colors">
                        <option value="">Select Leave Type</option>
                        <?php while ($type = $leaveTypes->fetch_assoc()): ?>
                            <option value="<?= $type["id"] ?>">
                                <?= htmlspecialchars($type["name"]) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Date row — side by side on mobile too -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-300" for="start_date">
                            Start Date <span class="text-red-400">*</span>
                        </label>
                        <input type="date" id="start_date" name="start_date" required
                               min="<?= date('Y-m-d') ?>"
                               class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                      focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500
                                      text-white transition-colors">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-300" for="end_date">
                            End Date <span class="text-red-400">*</span>
                        </label>
                        <input type="date" id="end_date" name="end_date" required
                               min="<?= date('Y-m-d') ?>"
                               class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                      focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500
                                      text-white transition-colors">
                    </div>
                </div>

                <!-- Reason -->
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-gray-300" for="reason">
                        Reason
                    </label>
                    <textarea id="reason" name="reason" rows="4"
                              placeholder="Briefly describe the reason for your leave..."
                              class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                     focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500
                                     text-white placeholder-gray-500 transition-colors resize-none"></textarea>
                </div>

                <!-- Submit -->
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                               p-3.5 rounded-lg font-semibold text-base
                               transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800">
                    Submit Leave Request
                </button>

            </form>
        </div>

    </div><!-- /max-w-xl -->
</body>
</html>