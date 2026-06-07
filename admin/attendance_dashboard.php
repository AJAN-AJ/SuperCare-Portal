<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

$date   = $_GET["date"] ?? date("Y-m-d");
$search = trim($_GET["search"] ?? "");

/* Statistics */
$result = $conn->query("
    SELECT COUNT(*) total, SUM(status='present') present, SUM(status='late') late
    FROM attendance WHERE DATE(check_in_time)='$date'
");
$stats = $result->fetch_assoc();

/* Attendance records */
$sql    = "
    SELECT a.*, u.full_name, b.name branch_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    JOIN branches b ON a.branch_id = b.id
    WHERE DATE(a.check_in_time) = ?
";
$params = [$date];
$types  = "s";

if ($search) {
    $sql   .= " AND (u.full_name LIKE ? OR u.username LIKE ?)";
    $like   = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}
$sql .= " ORDER BY a.check_in_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard</title>
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
            <div class="pl-14 sm:pl-6 pr-4 sm:pr-6 py-3 flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg sm:text-xl font-bold leading-tight">Attendance Dashboard</h1>
                    <p class="text-xs text-gray-400 hidden sm:block"><?= date("l, d F Y", strtotime($date)) ?></p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 p-4 sm:p-6 space-y-5">

            <!-- Stat cards -->
            <div class="grid grid-cols-3 gap-3 sm:gap-4">
                <div class="bg-gray-800 border border-gray-700 p-4 sm:p-5 rounded-xl">
                    <p class="text-gray-400 text-xs sm:text-sm">Total</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1"><?= $stats["total"] ?? 0 ?></p>
                </div>
                <div class="bg-green-700/40 border border-green-700 p-4 sm:p-5 rounded-xl">
                    <p class="text-green-300 text-xs sm:text-sm">Present</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1 text-green-400"><?= $stats["present"] ?? 0 ?></p>
                </div>
                <div class="bg-yellow-600/30 border border-yellow-600 p-4 sm:p-5 rounded-xl">
                    <p class="text-yellow-300 text-xs sm:text-sm">Late</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1 text-yellow-400"><?= $stats["late"] ?? 0 ?></p>
                </div>
            </div>

            <!-- Filter form -->
            <form method="GET" class="flex flex-col sm:flex-row gap-2">
                <input type="date" name="date" value="<?= $date ?>"
                       class="bg-gray-800 border border-gray-600 p-3 rounded-lg
                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                              outline-none text-white transition-colors sm:w-44">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search teller..."
                       class="flex-1 bg-gray-800 border border-gray-600 p-3 rounded-lg
                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                              outline-none text-white placeholder-gray-400 transition-colors">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                               px-5 py-3 rounded-lg font-medium text-sm transition-colors">
                    Filter
                </button>
                <?php if ($search): ?>
                <a href="attendance_dashboard.php?date=<?= $date ?>"
                   class="bg-gray-700 hover:bg-gray-600 px-4 py-3 rounded-lg text-sm
                          transition-colors flex items-center justify-center">
                    ✕
                </a>
                <?php endif; ?>
            </form>

            <!-- Result info -->
            <p class="text-sm text-gray-400">
                <?= count($rows) ?> record<?= count($rows) != 1 ? 's' : '' ?>
                for <span class="text-white font-medium"><?= date("d M Y", strtotime($date)) ?></span>
                <?= $search ? '— "<span class="text-white">'.htmlspecialchars($search).'</span>"' : '' ?>
            </p>

            <?php if (empty($rows)): ?>

            <!-- Empty state -->
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="text-5xl mb-4">🕐</div>
                <p class="text-gray-300 font-semibold text-lg">No attendance records.</p>
                <p class="text-gray-500 text-sm mt-1">Try a different date or search term.</p>
            </div>

            <?php else: ?>

            <!-- Desktop table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full bg-gray-800 rounded-xl text-sm">
                    <thead class="bg-gray-700 text-gray-300 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="p-3 text-left">Teller</th>
                            <th class="p-3 text-left">Branch</th>
                            <th class="p-3 text-center">Check In</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-left">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700/40 transition-colors">
                            <td class="p-3 font-medium"><?= htmlspecialchars($row["full_name"]) ?></td>
                            <td class="p-3 text-gray-300"><?= htmlspecialchars($row["branch_name"]) ?></td>
                            <td class="p-3 text-center font-mono"><?= date("H:i", strtotime($row["check_in_time"])) ?></td>
                            <td class="p-3 text-center">
                                <?php if ($row["status"] == "late"): ?>
                                    <span class="bg-yellow-600/30 text-yellow-400 border border-yellow-600 px-2.5 py-1 rounded-full text-xs font-medium">Late</span>
                                <?php else: ?>
                                    <span class="bg-green-600/30 text-green-400 border border-green-600 px-2.5 py-1 rounded-full text-xs font-medium">Present</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-gray-400 font-mono text-xs"><?= htmlspecialchars($row["recorded_ip"]) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="md:hidden space-y-3">
                <?php foreach ($rows as $row): ?>
                <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 space-y-2.5">

                    <!-- Name + status -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold"><?= htmlspecialchars($row["full_name"]) ?></p>
                            <p class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($row["branch_name"]) ?></p>
                        </div>
                        <?php if ($row["status"] == "late"): ?>
                            <span class="bg-yellow-600/30 text-yellow-400 border border-yellow-600 px-2.5 py-1 rounded-full text-xs font-medium shrink-0">Late</span>
                        <?php else: ?>
                            <span class="bg-green-600/30 text-green-400 border border-green-600 px-2.5 py-1 rounded-full text-xs font-medium shrink-0">Present</span>
                        <?php endif; ?>
                    </div>

                    <!-- Check-in time + IP -->
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-1.5 text-gray-300">
                            <span class="text-gray-500">🕐</span>
                            <span class="font-mono font-medium"><?= date("H:i", strtotime($row["check_in_time"])) ?></span>
                        </div>
                        <span class="text-gray-500 font-mono text-xs"><?= htmlspecialchars($row["recorded_ip"]) ?></span>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>

        </div><!-- /content -->
    </div><!-- /flex-1 -->
</div><!-- /flex h-screen -->

</body>
</html>