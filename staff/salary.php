<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller") {
    header("Location: ../login.php");
    exit();
}

$user_id    = $_SESSION["user_id"];
$month_year = date("Y-m");
$month_name = date("F Y");

/* ── Fetch teller base salary ── */
$uStmt = $conn->prepare("SELECT full_name, salary FROM users WHERE id=?");
$uStmt->bind_param("i", $user_id);
$uStmt->execute();
$teller = $uStmt->get_result()->fetch_assoc();
$base_salary = floatval($teller["salary"]);

/* ── Get or create salary record for this month ── */
$srStmt = $conn->prepare("SELECT * FROM salary_records WHERE user_id=? AND month_year=?");
$srStmt->bind_param("is", $user_id, $month_year);
$srStmt->execute();
$salary_record = $srStmt->get_result()->fetch_assoc();

if (!$salary_record) {
    /* Create record for this month */
    $ins = $conn->prepare("
        INSERT INTO salary_records (user_id, month_year, base_salary, total_advances, final_salary)
        VALUES (?, ?, ?, 0, ?)
    ");
    $ins->bind_param("isdd", $user_id, $month_year, $base_salary, $base_salary);
    $ins->execute();
    $srStmt->execute();
    $salary_record = $srStmt->get_result()->fetch_assoc();
}

/* ── Total approved advances this month ── */
$advStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) total
    FROM salary_advances
    WHERE user_id=? AND month_year=? AND status='approved'
");
$advStmt->bind_param("is", $user_id, $month_year);
$advStmt->execute();
$total_advances = floatval($advStmt->get_result()->fetch_assoc()["total"]);
$remaining      = max(0, $base_salary - $total_advances);

/* ── All advances this month ── */
$myAdv = $conn->prepare("
    SELECT sa.*, u.full_name AS approved_by_name
    FROM salary_advances sa
    LEFT JOIN users u ON sa.approved_by = u.id
    WHERE sa.user_id=? AND sa.month_year=?
    ORDER BY sa.requested_at DESC
");
$myAdv->bind_param("is", $user_id, $month_year);
$myAdv->execute();
$advances = $myAdv->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Salary history (past months) ── */
$histStmt = $conn->prepare("
    SELECT * FROM salary_records
    WHERE user_id=? AND month_year != ?
    ORDER BY month_year DESC
    LIMIT 6
");
$histStmt->bind_param("is", $user_id, $month_year);
$histStmt->execute();
$history = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Handle advance request ── */
$error = $success = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["request_advance"])) {
    $amount = floatval(str_replace(",", "", $_POST["amount"]));
    $reason = trim($_POST["reason"]);

    /* Check pending request already exists */
    $pendChk = $conn->prepare("
        SELECT id FROM salary_advances
        WHERE user_id=? AND month_year=? AND status='pending'
    ");
    $pendChk->bind_param("is", $user_id, $month_year);
    $pendChk->execute();
    $hasPending = $pendChk->get_result()->num_rows > 0;

    if ($hasPending) {
        $error = "You already have a pending advance request. Wait for it to be reviewed.";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } elseif ($amount > $remaining) {
        $error = "Amount exceeds your remaining salary of MK " . number_format($remaining, 2);
    } elseif (empty($reason)) {
        $error = "Please provide a reason for the advance.";
    } else {
        $ins = $conn->prepare("
            INSERT INTO salary_advances (user_id, month_year, amount, reason)
            VALUES (?, ?, ?, ?)
        ");
        $ins->bind_param("isds", $user_id, $month_year, $amount, $reason);
        if ($ins->execute()) {
            $success = "Advance request submitted. Awaiting admin approval.";
            /* Refresh advances */
            $myAdv->execute();
            $advances = $myAdv->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Salary</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) { input, select, textarea { font-size: 16px !important; } }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<?php include "../includes/sidebar.php"; ?>

<div class="flex flex-col min-h-screen lg:ml-72">

    <!-- Sticky header -->
    <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
        <div class="px-4 sm:px-6 py-3 flex items-center gap-3">
            <a href="dashboard.php"
               class="flex items-center gap-1 bg-gray-700 hover:bg-gray-600 active:bg-gray-500
                      px-3 py-2 rounded-lg text-sm font-medium transition-colors shrink-0">
                ← <span class="hidden sm:inline">Dashboard</span>
            </a>
            <h1 class="text-lg sm:text-xl font-bold">My Salary</h1>
            <span class="ml-auto text-xs text-gray-400"><?= $month_name ?></span>
        </div>
    </div>

    <div class="flex-1 p-4 sm:p-6 space-y-5 max-w-2xl mx-auto w-full">

        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="flex gap-3 p-4 bg-red-700/40 border border-red-600 rounded-xl text-red-300 text-sm">
            <span>⚠️</span><p><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="flex gap-3 p-4 bg-green-700/40 border border-green-600 rounded-xl text-green-300 text-sm">
            <span>✓</span><p><?= htmlspecialchars($success) ?></p>
        </div>
        <?php endif; ?>

        <!-- Salary summary cards -->
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 text-center">
                <p class="text-gray-400 text-xs mb-1">Base Salary</p>
                <p class="font-bold text-lg text-white">
                    <?= number_format($base_salary, 2) ?>
                </p>
            </div>
            <div class="bg-red-900/40 border border-red-700 rounded-xl p-4 text-center">
                <p class="text-red-300 text-xs mb-1">Advances Taken</p>
                <p class="font-bold text-lg text-red-400">
                    <?= number_format($total_advances, 2) ?>
                </p>
            </div>
            <div class="bg-green-900/40 border border-green-700 rounded-xl p-4 text-center">
                <p class="text-green-300 text-xs mb-1">End of Month</p>
                <p class="font-bold text-lg text-green-400">
                    <?= number_format($remaining, 2) ?>
                </p>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="bg-gray-800 rounded-xl p-4">
            <div class="flex justify-between text-xs text-gray-400 mb-2">
                <span>Advances used</span>
                <span><?= $base_salary > 0 ? round(($total_advances / $base_salary) * 100) : 0 ?>%</span>
            </div>
            <div class="w-full bg-gray-700 rounded-full h-3 overflow-hidden">
                <?php $pct = $base_salary > 0 ? min(100, ($total_advances / $base_salary) * 100) : 0; ?>
                <div class="h-3 rounded-full transition-all
                    <?= $pct >= 80 ? 'bg-red-500' : ($pct >= 50 ? 'bg-yellow-500' : 'bg-green-500') ?>"
                     style="width:<?= $pct ?>%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                MK <?= number_format($total_advances, 2) ?> taken of MK <?= number_format($base_salary, 2) ?> total
            </p>
        </div>

        <!-- Request advance form -->
        <?php
        $hasPendingAdvance = !empty(array_filter($advances, fn($a) => $a["status"] === "pending"));
        ?>
        <?php if ($remaining > 0 && !$hasPendingAdvance): ?>
        <div class="bg-gray-800 rounded-2xl p-4 sm:p-5 space-y-4">
            <h2 class="font-bold text-base">Request Salary Advance</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="request_advance" value="1">

                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">
                        Amount <span class="text-red-400">*</span>
                        <span class="text-gray-500 font-normal ml-1">
                            (max MK <?= number_format($remaining, 2) ?>)
                        </span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium pointer-events-none">MK</span>
                        <input type="text" inputmode="numeric" name="amount" id="advAmount"
                               required placeholder="0.00"
                               class="w-full bg-gray-700 border border-gray-600
                                      focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                      rounded-xl pl-10 pr-4 py-3 text-lg text-right outline-none transition-colors">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">
                        Reason <span class="text-red-400">*</span>
                    </label>
                    <textarea name="reason" rows="3" required
                              placeholder="Briefly explain why you need this advance..."
                              class="w-full p-3 rounded-xl bg-gray-700 border border-gray-600
                                     focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                     outline-none text-white placeholder-gray-500 resize-none transition-colors"></textarea>
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                               py-3 rounded-xl font-bold transition-colors">
                    Submit Advance Request
                </button>
            </form>
        </div>
        <?php elseif ($hasPendingAdvance): ?>
        <div class="flex gap-3 p-4 bg-yellow-700/40 border border-yellow-600 rounded-xl text-yellow-300 text-sm">
            <span>🕐</span>
            <p>You have a pending advance request. You can submit another once it is reviewed.</p>
        </div>
        <?php elseif ($remaining <= 0): ?>
        <div class="flex gap-3 p-4 bg-red-700/40 border border-red-600 rounded-xl text-red-300 text-sm">
            <span>⚠️</span>
            <p>Your full salary has been taken as advances this month. No further requests possible.</p>
        </div>
        <?php endif; ?>

        <!-- This month's advances -->
        <?php if (!empty($advances)): ?>
        <div class="bg-gray-800 rounded-2xl p-4 sm:p-5 space-y-3">
            <h2 class="font-bold text-base">This Month's Advances</h2>
            <?php foreach ($advances as $adv):
                $statusClass = match($adv["status"]) {
                    "approved" => "bg-green-600/30 text-green-400 border-green-600",
                    "rejected" => "bg-red-600/30 text-red-400 border-red-600",
                    default    => "bg-yellow-600/30 text-yellow-400 border-yellow-600",
                };
            ?>
            <div class="bg-gray-700/50 rounded-xl p-4 space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-bold text-lg">MK <?= number_format($adv["amount"], 2) ?></p>
                        <p class="text-gray-400 text-xs mt-0.5"><?= date("d M Y H:i", strtotime($adv["requested_at"])) ?></p>
                    </div>
                    <span class="<?= $statusClass ?> border px-2.5 py-1 rounded-full text-xs font-medium shrink-0">
                        <?= ucfirst($adv["status"]) ?>
                    </span>
                </div>
                <?php if ($adv["reason"]): ?>
                <p class="text-sm text-gray-300"><?= htmlspecialchars($adv["reason"]) ?></p>
                <?php endif; ?>
                <?php if ($adv["status"] === "approved" && $adv["approved_by_name"]): ?>
                <p class="text-xs text-gray-500">Approved by <?= htmlspecialchars($adv["approved_by_name"]) ?>
                    on <?= date("d M Y", strtotime($adv["approved_at"])) ?></p>
                <?php endif; ?>
                <?php if ($adv["status"] === "rejected" && $adv["notes"]): ?>
                <p class="text-xs text-red-400">Reason: <?= htmlspecialchars($adv["notes"]) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Salary history -->
        <?php if (!empty($history)): ?>
        <div class="bg-gray-800 rounded-2xl p-4 sm:p-5 space-y-3">
            <h2 class="font-bold text-base">Past Months</h2>
            <?php foreach ($history as $rec): ?>
            <div class="flex items-center justify-between bg-gray-700/50 rounded-xl px-4 py-3 text-sm">
                <div>
                    <p class="font-medium"><?= date("F Y", strtotime($rec["month_year"] . "-01")) ?></p>
                    <p class="text-gray-400 text-xs mt-0.5">
                        Base: MK <?= number_format($rec["base_salary"], 2) ?>
                        · Advances: MK <?= number_format($rec["total_advances"], 2) ?>
                    </p>
                </div>
                <div class="text-right">
                    <p class="font-bold text-green-400">MK <?= number_format($rec["final_salary"], 2) ?></p>
                    <span class="text-xs <?= $rec["paid"] ? 'text-green-400' : 'text-yellow-400' ?>">
                        <?= $rec["paid"] ? "✓ Paid" : "Pending" ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
var advAmount = document.getElementById('advAmount');
if (advAmount) {
    advAmount.addEventListener('focus', function() {
        var raw = this.value.replace(/,/g, '');
        this.value = (raw === '0.00' || raw === '') ? '' : raw;
        var len = this.value.length;
        this.setSelectionRange(len, len);
    });
    advAmount.addEventListener('input', function() {
        var cleaned = this.value.replace(/[^0-9.]/g, '');
        var parts = cleaned.split('.');
        if (parts.length > 2) cleaned = parts[0] + '.' + parts.slice(1).join('');
        if (parts.length === 2 && parts[1].length > 2) cleaned = parts[0] + '.' + parts[1].substring(0, 2);
        this.value = cleaned;
    });
    advAmount.addEventListener('blur', function() {
        var n = parseFloat(this.value.replace(/,/g, ''));
        this.value = (!isNaN(n) && this.value !== '') ? n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
    });
    advAmount.closest('form').addEventListener('submit', function() {
        advAmount.value = advAmount.value.replace(/,/g, '');
    });
}
</script>
</body>
</html>