<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

$result = $conn->query("
    SELECT lr.*, u.full_name, lt.name AS leave_name, lt.reduces_balance
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    ORDER BY lr.created_at DESC
");
$rows = $result->fetch_all(MYSQLI_ASSOC);

$pendingCount = count(array_filter($rows, fn($r) => $r["status"] === "pending"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests</title>
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
            <div class="px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
                <h1 class="text-lg sm:text-xl font-bold">Leave Requests</h1>
                <?php if ($pendingCount > 0): ?>
                <span class="shrink-0 bg-yellow-600/30 text-yellow-400 border border-yellow-600
                             px-2.5 py-1 rounded-full text-xs font-medium">
                    <?= $pendingCount ?> pending
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 p-4 sm:p-6">

            <?php if (empty($rows)): ?>

            <!-- Empty state -->
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="text-5xl mb-4">🏖️</div>
                <p class="text-gray-300 font-semibold text-lg">No leave requests yet.</p>
                <p class="text-gray-500 text-sm mt-1">They'll appear here once tellers apply.</p>
            </div>

            <?php else: ?>

            <!-- Desktop table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full bg-gray-800 rounded-xl text-sm">
                    <thead class="bg-gray-700 text-gray-300 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="p-3 text-left">Teller</th>
                            <th class="p-3 text-left">Type</th>
                            <th class="p-3 text-left">Start</th>
                            <th class="p-3 text-left">End</th>
                            <th class="p-3 text-center">Days</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $statusClass = match($row["status"]) {
                                "approved" => "bg-green-600/30 text-green-400 border-green-600",
                                "rejected" => "bg-red-600/30 text-red-400 border-red-600",
                                default    => "bg-yellow-600/30 text-yellow-400 border-yellow-600",
                            };
                        ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700/40 transition-colors">
                            <td class="p-3 font-medium"><?= htmlspecialchars($row["full_name"]) ?></td>
                            <td class="p-3 text-gray-300"><?= htmlspecialchars($row["leave_name"]) ?></td>
                            <td class="p-3 whitespace-nowrap text-gray-300"><?= $row["start_date"] ?></td>
                            <td class="p-3 whitespace-nowrap text-gray-300"><?= $row["end_date"] ?></td>
                            <td class="p-3 text-center"><?= $row["total_days"] ?></td>
                            <td class="p-3 text-center">
                                <span class="<?= $statusClass ?> border px-2.5 py-1 rounded-full text-xs font-medium">
                                    <?= ucfirst($row["status"]) ?>
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <?php if ($row["status"] === "pending"): ?>
                                <div class="flex items-center justify-center gap-2">
                                    <a href="approve_leave.php?id=<?= $row["id"] ?>"
                                       class="bg-green-600 hover:bg-green-700 active:bg-green-800
                                              px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                        ✓ Approve
                                    </a>
                                    <a href="reject_leave.php?id=<?= $row["id"] ?>"
                                       class="bg-red-600 hover:bg-red-700 active:bg-red-800
                                              px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                        ✕ Reject
                                    </a>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-600">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="md:hidden space-y-3">
                <?php foreach ($rows as $row):
                    $statusClass = match($row["status"]) {
                        "approved" => "bg-green-600/30 text-green-400 border-green-600",
                        "rejected" => "bg-red-600/30 text-red-400 border-red-600",
                        default    => "bg-yellow-600/30 text-yellow-400 border-yellow-600",
                    };
                ?>
                <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 space-y-3">

                    <!-- Name + status -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold"><?= htmlspecialchars($row["full_name"]) ?></p>
                            <p class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($row["leave_name"]) ?></p>
                        </div>
                        <span class="<?= $statusClass ?> border px-2.5 py-1 rounded-full text-xs font-medium shrink-0">
                            <?= ucfirst($row["status"]) ?>
                        </span>
                    </div>

                    <!-- Date + days -->
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

                    <!-- Actions -->
                    <?php if ($row["status"] === "pending"): ?>
                    <div class="flex gap-2 pt-1">
                        <a href="approve_leave.php?id=<?= $row["id"] ?>"
                           class="flex-1 bg-green-600 hover:bg-green-700 active:bg-green-800
                                  text-center py-2.5 rounded-lg text-sm font-semibold transition-colors">
                            ✓ Approve
                        </a>
                        <a href="reject_leave.php?id=<?= $row["id"] ?>"
                           class="flex-1 bg-red-600 hover:bg-red-700 active:bg-red-800
                                  text-center py-2.5 rounded-lg text-sm font-semibold transition-colors">
                            ✕ Reject
                        </a>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>

        </div><!-- /content -->
    </div><!-- /flex-1 -->
</div><!-- /flex h-screen -->

</body>
</html>