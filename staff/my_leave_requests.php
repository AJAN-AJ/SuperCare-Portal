<?php
session_start();
require_once "../config/db.php";
require_once "../includes/session_guard.php";
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller") {
    header("Location: ../login.php");
    exit();
}
$user_id = $_SESSION["user_id"];

/* Fetch leave requests */
$stmt = $conn->prepare("
    SELECT lr.*, lt.name AS leave_name
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.user_id = ?
    ORDER BY lr.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests = $stmt->get_result();

/* Leave balance */
$user = $conn->query("
    SELECT annual_leave_days, annual_leave_used
    FROM users WHERE id=$user_id
")->fetch_assoc();
$remaining = $user["annual_leave_days"] - $user["annual_leave_used"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leave Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) {
            select, input, textarea { font-size: 16px !important; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex overflow-hidden">

<?php include "../includes/sidebar.php"; ?>

<div class="flex-1 flex flex-col overflow-y-auto">

    <!-- Sticky header -->
    <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
        <div class="px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="dashboard.php"
                   class="flex items-center gap-1 bg-gray-700 hover:bg-gray-600 active:bg-gray-500
                          px-3 py-2 rounded-lg text-sm font-medium transition-colors shrink-0">
                    <span>←</span>
                    <span class="hidden sm:inline">Dashboard</span>
                </a>
                <h1 class="text-lg sm:text-xl font-bold truncate">My Leave Requests</h1>
            </div>
            <!-- Balance badge -->
            <div class="shrink-0 bg-gray-700 px-3 py-1.5 rounded-lg text-sm text-center">
                <span class="text-gray-400 hidden sm:inline">Remaining: </span>
                <span class="text-green-400 font-bold"><?= $remaining ?></span>
                <span class="text-green-300 text-xs"> days</span>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 p-4 sm:p-6 space-y-5">

        <!-- ── DESKTOP TABLE (hidden on mobile) ── -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full bg-gray-800 rounded-xl text-sm">
                <thead class="bg-gray-700 text-gray-300 uppercase text-xs tracking-wide">
                    <tr>
                        <th class="p-3 text-left">Leave Type</th>
                        <th class="p-3 text-left">Start</th>
                        <th class="p-3 text-left">End</th>
                        <th class="p-3 text-center">Days</th>
                        <th class="p-3 text-left">Reason</th>
                        <th class="p-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests->num_rows): ?>
                        <?php
                        $rows = $requests->fetch_all(MYSQLI_ASSOC);
                        foreach ($rows as $row):
                        ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-750 transition-colors">
                            <td class="p-3"><?= htmlspecialchars($row["leave_name"]) ?></td>
                            <td class="p-3 whitespace-nowrap"><?= $row["start_date"] ?></td>
                            <td class="p-3 whitespace-nowrap"><?= $row["end_date"] ?></td>
                            <td class="p-3 text-center"><?= $row["total_days"] ?></td>
                            <td class="p-3 text-gray-300 max-w-xs truncate"><?= htmlspecialchars($row["reason"]) ?></td>
                            <td class="p-3 text-center">
                                <?php if ($row["status"] == "approved"): ?>
                                    <span class="bg-green-600/30 text-green-400 border border-green-600 px-2.5 py-1 rounded-full text-xs font-medium">Approved</span>
                                <?php elseif ($row["status"] == "rejected"): ?>
                                    <span class="bg-red-600/30 text-red-400 border border-red-600 px-2.5 py-1 rounded-full text-xs font-medium">Rejected</span>
                                <?php else: ?>
                                    <span class="bg-yellow-600/30 text-yellow-400 border border-yellow-600 px-2.5 py-1 rounded-full text-xs font-medium">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center p-10 text-gray-400">No leave requests yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── MOBILE CARDS (hidden on desktop) ── -->
        <div class="sm:hidden space-y-3">
            <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $row): ?>
                <div class="bg-gray-800 rounded-xl p-4 space-y-3 border border-gray-700">
                    <!-- Top row: leave type + status -->
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-semibold text-white">
                            <?= htmlspecialchars($row["leave_name"]) ?>
                        </span>
                        <?php if ($row["status"] == "approved"): ?>
                            <span class="bg-green-600/30 text-green-400 border border-green-600 px-2.5 py-1 rounded-full text-xs font-medium shrink-0">Approved</span>
                        <?php elseif ($row["status"] == "rejected"): ?>
                            <span class="bg-red-600/30 text-red-400 border border-red-600 px-2.5 py-1 rounded-full text-xs font-medium shrink-0">Rejected</span>
                        <?php else: ?>
                            <span class="bg-yellow-600/30 text-yellow-400 border border-yellow-600 px-2.5 py-1 rounded-full text-xs font-medium shrink-0">Pending</span>
                        <?php endif; ?>
                    </div>

                    <!-- Date + days row -->
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <div>
                            <p class="text-gray-400 text-xs mb-0.5">Start</p>
                            <p class="text-white"><?= $row["start_date"] ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs mb-0.5">End</p>
                            <p class="text-white"><?= $row["end_date"] ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs mb-0.5">Days</p>
                            <p class="text-white font-semibold"><?= $row["total_days"] ?></p>
                        </div>
                    </div>

                    <!-- Reason -->
                    <?php if (!empty($row["reason"])): ?>
                    <div class="text-sm">
                        <p class="text-gray-400 text-xs mb-0.5">Reason</p>
                        <p class="text-gray-300"><?= htmlspecialchars($row["reason"]) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-gray-800 rounded-xl p-10 text-center text-gray-400 border border-gray-700">
                    No leave requests yet.
                </div>
            <?php endif; ?>
        </div>

        <!-- Apply button -->
        <div>
            <a href="apply_leave.php"
               class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                      px-5 py-3 rounded-lg font-semibold transition-colors text-sm sm:text-base">
                <span>+</span> Apply New Leave
            </a>
        </div>

    </div><!-- /content -->
</div><!-- /flex-1 -->

</body>
</html>