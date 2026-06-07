<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

$month  = $_GET["month"] ?? date("Y-m");
$search = trim($_GET["search"] ?? "");

$sql    = "
    SELECT
        u.id, u.full_name,
        COUNT(a.id) total,
        SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) present,
        SUM(CASE WHEN a.status='late'    THEN 1 ELSE 0 END) late
    FROM users u
    LEFT JOIN attendance a
        ON u.id = a.user_id
        AND DATE_FORMAT(a.check_in_time, '%Y-%m') = ?
    WHERE u.role = 'teller'
";
$params = [$month];
$types  = "s";

if ($search) {
    $sql   .= " AND (u.full_name LIKE ? OR u.username LIKE ?)";
    $like   = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}

$sql .= " GROUP BY u.id ORDER BY u.full_name";

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
    <title>Attendance Report</title>
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

        <!-- Sticky header — pl-14 on mobile clears the hamburger button -->
        <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
            <div class="pl-14 sm:pl-6 pr-4 sm:pr-6 py-3">
                <h1 class="text-lg sm:text-xl font-bold">Attendance Report</h1>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 p-4 sm:p-6 space-y-5">

            <!-- Export button -->
            <div class="flex justify-start">
                <a href="export_attendance_csv.php?month=<?= $month ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 active:bg-green-800
                          px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">
                    ↓ Export CSV
                </a>
            </div>

            <!-- Filter form -->
            <form method="GET" class="flex flex-col sm:flex-row gap-2">
                <input type="month" name="month" value="<?= $month ?>"
                       class="bg-gray-800 border border-gray-600 p-3 rounded-lg
                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none
                              text-white transition-colors sm:w-44">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search teller..."
                       class="flex-1 bg-gray-800 border border-gray-600 p-3 rounded-lg
                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none
                              text-white placeholder-gray-400 transition-colors">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                               px-5 py-3 rounded-lg font-medium text-sm transition-colors">
                    Filter
                </button>
                <?php if ($search): ?>
                <a href="attendance_report.php?month=<?= $month ?>"
                   class="bg-gray-700 hover:bg-gray-600 px-4 py-3 rounded-lg text-sm
                          transition-colors flex items-center justify-center">
                    ✕
                </a>
                <?php endif; ?>
            </form>

            <!-- Result info -->
            <p class="text-sm text-gray-400">
                Showing <span class="text-white font-medium"><?= count($rows) ?></span> teller<?= count($rows) != 1 ? 's' : '' ?>
                for <span class="text-white font-medium"><?= date("F Y", strtotime($month."-01")) ?></span>
                <?= $search ? '— filtered by "<span class="text-white">'.htmlspecialchars($search).'</span>"' : '' ?>
            </p>

            <?php if (empty($rows)): ?>

            <!-- Empty state -->
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="text-5xl mb-4">📋</div>
                <p class="text-gray-300 font-semibold text-lg">No attendance data.</p>
                <p class="text-gray-500 text-sm mt-1">Try a different month or search term.</p>
            </div>

            <?php else: ?>

            <!-- Desktop table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full bg-gray-800 rounded-xl text-sm">
                    <thead class="bg-gray-700 text-gray-300 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="p-3 text-left">Teller</th>
                            <th class="p-3 text-center">Present</th>
                            <th class="p-3 text-center">Late</th>
                            <th class="p-3 text-center">Total</th>
                            <th class="p-3 text-center">Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $percent  = round((($row["present"] + $row["late"]) / 22) * 100);
                            $barColor = $percent >= 80 ? 'bg-green-500' : ($percent >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                        ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700/40 transition-colors">
                            <td class="p-3 font-medium"><?= htmlspecialchars($row["full_name"]) ?></td>
                            <td class="p-3 text-center text-green-400 font-semibold"><?= $row["present"] ?></td>
                            <td class="p-3 text-center text-yellow-400 font-semibold"><?= $row["late"] ?></td>
                            <td class="p-3 text-center"><?= $row["total"] ?></td>
                            <td class="p-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-20 bg-gray-700 rounded-full h-2 overflow-hidden">
                                        <div class="<?= $barColor ?> h-2 rounded-full" style="width:<?= min($percent,100) ?>%"></div>
                                    </div>
                                    <span class="text-xs font-medium w-9 text-right"><?= $percent ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="md:hidden space-y-3">
                <?php foreach ($rows as $row):
                    $percent  = round((($row["present"] + $row["late"]) / 22) * 100);
                    $barColor = $percent >= 80 ? 'bg-green-500' : ($percent >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                    $pctText  = $percent >= 80 ? 'text-green-400' : ($percent >= 50 ? 'text-yellow-400' : 'text-red-400');
                ?>
                <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 space-y-3">

                    <!-- Name + percentage -->
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold"><?= htmlspecialchars($row["full_name"]) ?></p>
                        <span class="<?= $pctText ?> font-bold text-lg shrink-0"><?= $percent ?>%</span>
                    </div>

                    <!-- Progress bar -->
                    <div class="w-full bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div class="<?= $barColor ?> h-2 rounded-full" style="width:<?= min($percent,100) ?>%"></div>
                    </div>

                    <!-- Stats row -->
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <div class="bg-gray-700/50 rounded-lg p-2.5 text-center">
                            <p class="text-gray-400 text-xs mb-0.5">Present</p>
                            <p class="text-green-400 font-semibold"><?= $row["present"] ?></p>
                        </div>
                        <div class="bg-gray-700/50 rounded-lg p-2.5 text-center">
                            <p class="text-gray-400 text-xs mb-0.5">Late</p>
                            <p class="text-yellow-400 font-semibold"><?= $row["late"] ?></p>
                        </div>
                        <div class="bg-gray-700/50 rounded-lg p-2.5 text-center">
                            <p class="text-gray-400 text-xs mb-0.5">Total</p>
                            <p class="font-semibold"><?= $row["total"] ?></p>
                        </div>
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