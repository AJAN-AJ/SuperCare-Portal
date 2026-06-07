<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "../config/db.php";
require_once "../includes/balance_engine.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

/* ── Statistics ── */
$totalTellers    = $conn->query("SELECT COUNT(*) total FROM users WHERE role='teller'")->fetch_assoc()['total'];
$activeTellers   = $conn->query("SELECT COUNT(*) total FROM users WHERE role='teller' AND is_active=1")->fetch_assoc()['total'];
$pendingProfiles = $conn->query("SELECT COUNT(*) total FROM users WHERE role='teller' AND profile_completed=1 AND approved=0")->fetch_assoc()['total'];
$pendingOpening  = $conn->query("SELECT COUNT(*) total FROM balance_sessions WHERE status='pending_approval_opening'")->fetch_assoc()['total'];
$pendingClosing  = $conn->query("SELECT COUNT(*) total FROM balance_sessions WHERE status='pending_approval_closing'")->fetch_assoc()['total'];
$todayAttendance = $conn->query("SELECT COUNT(*) total FROM attendance WHERE DATE(check_in_time)=CURDATE()")->fetch_assoc()['total'];
$pendingLeave    = $conn->query("SELECT COUNT(*) total FROM leave_requests WHERE status='pending'")->fetch_assoc()['total'];
$commissionEntries = $conn->query("SELECT COUNT(*) total FROM commission_entries")->fetch_assoc()['total'];

/* ── Recent activity ── */
$recentLeaves = $conn->query("
    SELECT u.full_name, lt.name leave_type, lr.status
    FROM leave_requests lr
    JOIN users u ON lr.user_id=u.id
    JOIN leave_types lt ON lr.leave_type_id=lt.id
    ORDER BY lr.created_at DESC LIMIT 5
");

$recentAttendance = $conn->query("
    SELECT u.full_name, a.status, a.check_in_time
    FROM attendance a
    JOIN users u ON a.user_id=u.id
    ORDER BY a.check_in_time DESC LIMIT 5
");

/* ── Balance sessions ── */
$stmt = $conn->prepare("
    SELECT bs.*, u.full_name, b.name branch_name
    FROM balance_sessions bs
    JOIN users u ON bs.user_id=u.id
    JOIN branches b ON bs.branch_id=b.id
    ORDER BY bs.balance_date DESC, bs.created_at DESC
");
$stmt->execute();
$sessions     = $stmt->get_result();
$sessionRows  = $sessions->fetch_all(MYSQLI_ASSOC); // fetch once, reuse for both layouts
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) {
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white">

<div class="flex h-screen overflow-hidden">

<?php include "../includes/admin_sidebar.php"; ?>

<div class="flex-1 flex flex-col overflow-y-auto">

    <!-- Sticky page header -->
    <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
        <div class="px-4 sm:px-6 py-3">
            <h1 class="text-xl sm:text-2xl font-bold">CEO Dashboard</h1>
        </div>
    </div>

    <div class="flex-1 p-4 sm:p-6 space-y-8">

        <!-- ── Stat cards ── -->
        <?php
        $cards = [
            ["Total Tellers",      $totalTellers,      "bg-blue-700",   "👥"],
            ["Active Tellers",     $activeTellers,     "bg-green-700",  "✅"],
            ["Pending Profiles",   $pendingProfiles,   "bg-yellow-600", "📋"],
            ["Today's Attendance", $todayAttendance,   "bg-purple-700", "🕐"],
            ["Pending Opening",    $pendingOpening,    "bg-orange-600", "🔓"],
            ["Pending Closing",    $pendingClosing,    "bg-red-700",    "🔒"],
            ["Pending Leave",      $pendingLeave,      "bg-indigo-700", "🏖️"],
            ["Commission Records", $commissionEntries, "bg-pink-700",   "💰"],
        ];
        ?>
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3 sm:gap-5">
            <?php foreach ($cards as $card): ?>
            <div class="<?= $card[2] ?> p-4 sm:p-5 rounded-xl flex flex-col gap-1">
                <div class="flex items-center justify-between">
                    <span class="text-xs sm:text-sm font-medium opacity-90 leading-tight"><?= $card[0] ?></span>
                    <span class="text-lg sm:text-xl opacity-75"><?= $card[3] ?></span>
                </div>
                <div class="text-3xl sm:text-4xl font-bold"><?= $card[1] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Quick Actions ── -->
        <div>
            <h2 class="text-lg sm:text-xl font-bold mb-3">Quick Actions</h2>
            <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-3">
                <a href="admin_create_teller.php"  class="bg-blue-600   hover:bg-blue-700   active:bg-blue-800   px-4 py-3 rounded-lg text-sm font-medium text-center transition-colors">Create Teller</a>
                <a href="leave_requests.php"        class="bg-yellow-600 hover:bg-yellow-700 active:bg-yellow-800 px-4 py-3 rounded-lg text-sm font-medium text-center transition-colors">Approve Leaves</a>
                <a href="attendance_report.php"     class="bg-green-600  hover:bg-green-700  active:bg-green-800  px-4 py-3 rounded-lg text-sm font-medium text-center transition-colors">Attendance Report</a>
                <a href="manage_tellers.php"        class="bg-purple-600 hover:bg-purple-700 active:bg-purple-800 px-4 py-3 rounded-lg text-sm font-medium text-center transition-colors">Manage Staff</a>
            </div>
        </div>

        <!-- ── Recent Activity ── -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">

            <!-- Recent Leaves -->
            <div class="bg-gray-800 p-4 sm:p-5 rounded-xl">
                <h3 class="text-lg font-bold mb-4">Recent Leave Requests</h3>
                <div class="divide-y divide-gray-700">
                    <?php while ($row = $recentLeaves->fetch_assoc()): ?>
                    <div class="py-3 flex items-center justify-between gap-2">
                        <div>
                            <p class="font-medium text-sm"><?= htmlspecialchars($row["full_name"]) ?></p>
                            <p class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($row["leave_type"]) ?></p>
                        </div>
                        <?php
                        $lsColor = match($row["status"]) {
                            "approved" => "bg-green-600/30 text-green-400 border-green-600",
                            "rejected" => "bg-red-600/30 text-red-400 border-red-600",
                            default    => "bg-yellow-600/30 text-yellow-400 border-yellow-600",
                        };
                        ?>
                        <span class="<?= $lsColor ?> border px-2 py-0.5 rounded-full text-xs font-medium shrink-0">
                            <?= ucfirst($row["status"]) ?>
                        </span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="bg-gray-800 p-4 sm:p-5 rounded-xl">
                <h3 class="text-lg font-bold mb-4">Recent Attendance</h3>
                <div class="divide-y divide-gray-700">
                    <?php while ($row = $recentAttendance->fetch_assoc()): ?>
                    <div class="py-3 flex items-center justify-between gap-2">
                        <div>
                            <p class="font-medium text-sm"><?= htmlspecialchars($row["full_name"]) ?></p>
                            <p class="text-gray-400 text-xs mt-0.5"><?= date("d M H:i", strtotime($row["check_in_time"])) ?></p>
                        </div>
                        <?php
                        $asColor = match(strtolower($row["status"])) {
                            "present" => "bg-green-600/30 text-green-400 border-green-600",
                            "absent"  => "bg-red-600/30 text-red-400 border-red-600",
                            "late"    => "bg-yellow-600/30 text-yellow-400 border-yellow-600",
                            default   => "bg-gray-600/30 text-gray-400 border-gray-600",
                        };
                        ?>
                        <span class="<?= $asColor ?> border px-2 py-0.5 rounded-full text-xs font-medium shrink-0">
                            <?= ucfirst($row["status"]) ?>
                        </span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

        </div>

        <!-- ── Balance Sessions ── -->
        <div>
            <h2 class="text-lg sm:text-xl font-bold mb-3">Balance Sessions</h2>

            <!-- Desktop table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full bg-gray-800 rounded-xl text-sm">
                    <thead class="bg-gray-700 text-gray-300 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="p-3 text-left">Teller</th>
                            <th class="p-3 text-left">Branch</th>
                            <th class="p-3 text-right">Opening</th>
                            <th class="p-3 text-right">Closing</th>
                            <th class="p-3 text-right">Expected</th>
                            <th class="p-3 text-right">Difference</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sessionRows)): ?>
                            <?php foreach ($sessionRows as $row):
                                $engine = getBalanceEngine($conn, $row["id"]);
                            ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-750 transition-colors">
                                <td class="p-3 font-medium"><?= htmlspecialchars($row["full_name"]) ?></td>
                                <td class="p-3 text-gray-300"><?= htmlspecialchars($row["branch_name"]) ?></td>
                                <td class="p-3 text-right"><?= number_format($row["opening_total"], 2) ?></td>
                                <td class="p-3 text-right"><?= number_format($row["closing_total"], 2) ?></td>
                                <td class="p-3 text-right"><?= number_format($engine["expected"], 2) ?></td>
                                <td class="p-3 text-right font-semibold <?= $engine["difference"] < 0 ? "text-red-400" : "text-green-400" ?>">
                                    <?= number_format($engine["difference"], 2) ?>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="text-xs px-2 py-1 rounded-full border
                                        <?= str_contains($row["status"], 'approved') ? 'bg-green-600/30 text-green-400 border-green-600'
                                          : (str_contains($row["status"], 'pending') ? 'bg-yellow-600/30 text-yellow-400 border-yellow-600'
                                          : 'bg-gray-600/30 text-gray-400 border-gray-600') ?>">
                                        <?= htmlspecialchars($row["status"]) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="view_balance_session.php?id=<?= $row["id"] ?>"
                                           class="bg-gray-600 hover:bg-gray-500 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                            View
                                        </a>
                                        <?php if ($row["status"] == "pending_approval_closing"): ?>
                                        <a href="approve_closing.php?id=<?= $row["id"] ?>"
                                           class="bg-blue-600 hover:bg-blue-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                            Approve
                                        </a>
                                        <?php else: ?>
                                        <span class="text-gray-600">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="p-8 text-center text-gray-400">No balance sessions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="md:hidden space-y-3">
                <?php if (!empty($sessionRows)): ?>
                    <?php foreach ($sessionRows as $row):
                        $engine = getBalanceEngine($conn, $row["id"]);
                        $diffColor = $engine["difference"] < 0 ? "text-red-400" : "text-green-400";
                        $statusClass = str_contains($row["status"], 'approved')
                            ? 'bg-green-600/30 text-green-400 border-green-600'
                            : (str_contains($row["status"], 'pending')
                            ? 'bg-yellow-600/30 text-yellow-400 border-yellow-600'
                            : 'bg-gray-600/30 text-gray-400 border-gray-600');
                    ?>
                    <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 space-y-3">
                        <!-- Header row -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold"><?= htmlspecialchars($row["full_name"]) ?></p>
                                <p class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($row["branch_name"]) ?></p>
                            </div>
                            <span class="<?= $statusClass ?> border px-2 py-0.5 rounded-full text-xs font-medium shrink-0">
                                <?= htmlspecialchars($row["status"]) ?>
                            </span>
                        </div>

                        <!-- Amounts grid -->
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="bg-gray-700/50 rounded-lg p-2.5">
                                <p class="text-gray-400 text-xs mb-0.5">Opening</p>
                                <p class="font-medium"><?= number_format($row["opening_total"], 2) ?></p>
                            </div>
                            <div class="bg-gray-700/50 rounded-lg p-2.5">
                                <p class="text-gray-400 text-xs mb-0.5">Closing</p>
                                <p class="font-medium"><?= number_format($row["closing_total"], 2) ?></p>
                            </div>
                            <div class="bg-gray-700/50 rounded-lg p-2.5">
                                <p class="text-gray-400 text-xs mb-0.5">Expected</p>
                                <p class="font-medium"><?= number_format($engine["expected"], 2) ?></p>
                            </div>
                            <div class="bg-gray-700/50 rounded-lg p-2.5">
                                <p class="text-gray-400 text-xs mb-0.5">Difference</p>
                                <p class="font-semibold <?= $diffColor ?>"><?= number_format($engine["difference"], 2) ?></p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2 pt-1">
                            <a href="view_balance_session.php?id=<?= $row["id"] ?>"
                               class="flex-1 bg-gray-700 hover:bg-gray-600 active:bg-gray-500
                                      text-center px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                                View
                            </a>
                            <?php if ($row["status"] == "pending_approval_closing"): ?>
                            <a href="approve_closing.php?id=<?= $row["id"] ?>"
                               class="flex-1 bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                                      text-center px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                                Approve
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-gray-800 rounded-xl p-8 text-center text-gray-400 border border-gray-700">
                        No balance sessions found.
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div><!-- /content -->
</div><!-- /flex-1 -->
</div><!-- /flex h-screen -->

</body>
</html>