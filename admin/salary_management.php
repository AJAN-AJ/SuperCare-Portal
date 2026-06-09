<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

$admin_id   = $_SESSION["user_id"];
$month_year = $_GET["month"] ?? date("Y-m");
$month_name = date("F Y", strtotime($month_year . "-01"));

/* ── Handle actions ── */
$msg = $msgType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* Approve / reject advance */
    if (isset($_POST["action"]) && isset($_POST["advance_id"])) {
        $advance_id = intval($_POST["advance_id"]);
        $action     = $_POST["action"];
        $notes      = trim($_POST["notes"] ?? "");

        if ($action === "approve") {
            /* Get advance details */
            $aStmt = $conn->prepare("SELECT * FROM salary_advances WHERE id=?");
            $aStmt->bind_param("i", $advance_id);
            $aStmt->execute();
            $adv = $aStmt->get_result()->fetch_assoc();

            /* Update advance status */
            $upd = $conn->prepare("UPDATE salary_advances SET status='approved', approved_by=?, approved_at=NOW(), notes=? WHERE id=?");
            $upd->bind_param("isi", $admin_id, $notes, $advance_id);
            $upd->execute();

            /* Update salary record */
            $totStmt = $conn->prepare("
                SELECT COALESCE(SUM(amount),0) total
                FROM salary_advances
                WHERE user_id=? AND month_year=? AND status='approved'
            ");
            $totStmt->bind_param("is", $adv["user_id"], $adv["month_year"]);
            $totStmt->execute();
            $total_adv = floatval($totStmt->get_result()->fetch_assoc()["total"]);

            /* Get base salary */
            $bStmt = $conn->prepare("SELECT salary FROM users WHERE id=?");
            $bStmt->bind_param("i", $adv["user_id"]);
            $bStmt->execute();
            $base = floatval($bStmt->get_result()->fetch_assoc()["salary"]);
            $final = max(0, $base - $total_adv);

            /* Upsert salary record */
            $upsert = $conn->prepare("
                INSERT INTO salary_records (user_id, month_year, base_salary, total_advances, final_salary)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE total_advances=VALUES(total_advances), final_salary=VALUES(final_salary)
            ");
            $upsert->bind_param("isddd", $adv["user_id"], $adv["month_year"], $base, $total_adv, $final);
            $upsert->execute();

            $msg = "Advance approved successfully.";
            $msgType = "success";

        } elseif ($action === "reject") {
            $upd = $conn->prepare("UPDATE salary_advances SET status='rejected', approved_by=?, approved_at=NOW(), notes=? WHERE id=?");
            $upd->bind_param("isi", $admin_id, $notes, $advance_id);
            $upd->execute();
            $msg = "Advance rejected.";
            $msgType = "error";
        }
    }

    /* Mark salary as paid */
    if (isset($_POST["mark_paid"])) {
        $sr_id = intval($_POST["salary_record_id"]);
        $upd = $conn->prepare("UPDATE salary_records SET paid=1, paid_at=NOW() WHERE id=?");
        $upd->bind_param("i", $sr_id);
        $upd->execute();
        $msg = "Salary marked as paid.";
        $msgType = "success";
    }

    /* Month reset — initialize salaries for selected month */
    if (isset($_POST["init_month"])) {
        $init_month = $_POST["init_month_year"] ?? date("Y-m");
        $tList = $conn->query("SELECT id, salary FROM users WHERE role='teller' AND is_active=1");
        $created = 0;
        while ($t = $tList->fetch_assoc()) {
            $chk = $conn->prepare("SELECT id FROM salary_records WHERE user_id=? AND month_year=?");
            $chk->bind_param("is", $t["id"], $init_month);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO salary_records (user_id, month_year, base_salary, total_advances, final_salary) VALUES (?, ?, ?, 0, ?)");
                $ins->bind_param("isdd", $t["id"], $init_month, $t["salary"], $t["salary"]);
                $ins->execute();
                $created++;
            }
        }
        $msg = "Initialised $created salary records for " . date("F Y", strtotime($init_month . "-01")) . ".";
        $msgType = "success";
    }

    /* Update base salary */
    if (isset($_POST["update_salary"])) {
        $uid        = intval($_POST["teller_id"]);
        $new_salary = floatval(str_replace(",", "", $_POST["new_salary"]));
        $upd = $conn->prepare("UPDATE users SET salary=? WHERE id=?");
        $upd->bind_param("di", $new_salary, $uid);
        $upd->execute();
        $msg = "Salary updated successfully.";
        $msgType = "success";
    }
}

/* ── Fetch all tellers with salary info for selected month ── */
$tellers = $conn->prepare("
    SELECT
        u.id, u.full_name, u.username, u.salary AS base_salary,
        sr.id AS record_id,
        COALESCE(sr.total_advances, 0) AS total_advances,
        CASE WHEN sr.id IS NULL THEN u.salary ELSE sr.final_salary END AS final_salary,
        COALESCE(sr.paid, 0) AS paid,
        sr.paid_at,
        COALESCE(
            (SELECT COUNT(*) FROM salary_advances sa
             WHERE sa.user_id=u.id AND sa.month_year=? AND sa.status='pending'),
            0
        ) AS pending_advances
    FROM users u
    LEFT JOIN salary_records sr ON sr.id = (
        SELECT id FROM salary_records
        WHERE user_id = u.id AND month_year = ?
        ORDER BY id DESC LIMIT 1
    )
    WHERE u.role='teller' AND u.is_active=1
    ORDER BY u.full_name
");
$tellers->bind_param("ss", $month_year, $month_year);
$tellers->execute();
$tellerRows = $tellers->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Pending advances ── */
$pendingAdv = $conn->prepare("
    SELECT sa.*, u.full_name
    FROM salary_advances sa
    JOIN users u ON sa.user_id=u.id
    WHERE sa.month_year=? AND sa.status='pending'
    ORDER BY sa.requested_at ASC
");
$pendingAdv->bind_param("s", $month_year);
$pendingAdv->execute();
$pendingRows = $pendingAdv->get_result()->fetch_all(MYSQLI_ASSOC);

$pendingCount = count($pendingRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) { input, select, textarea { font-size: 16px !important; } }
    </style>
</head>
<body class="bg-gray-900 text-white">

<div class="flex h-screen overflow-hidden">
    <?php include "../includes/admin_sidebar.php"; ?>

    <div class="flex-1 flex flex-col overflow-y-auto">

        <!-- Sticky header -->
        <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
            <div class="pl-14 sm:pl-6 pr-4 sm:pr-6 py-3 flex items-center justify-between gap-3">
                <h1 class="text-lg sm:text-xl font-bold">Salary Management</h1>
                <?php if ($pendingCount > 0): ?>
                <span class="bg-yellow-600/30 text-yellow-400 border border-yellow-600
                             px-2.5 py-1 rounded-full text-xs font-medium shrink-0">
                    <?= $pendingCount ?> pending
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex-1 p-4 sm:p-6 space-y-6">

            <!-- Alert -->
            <?php if ($msg): ?>
            <div class="flex gap-3 p-4 rounded-xl text-sm
                <?= $msgType === 'success' ? 'bg-green-700/40 border border-green-600 text-green-300' : 'bg-red-700/40 border border-red-600 text-red-300' ?>">
                <span><?= $msgType === 'success' ? '✓' : '⚠️' ?></span>
                <p><?= htmlspecialchars($msg) ?></p>
            </div>
            <?php endif; ?>

            <!-- Month selector + Init button -->
            <div class="flex flex-col sm:flex-row gap-3">
                <form method="GET" class="flex items-center gap-2 flex-1">
                    <input type="month" name="month" value="<?= $month_year ?>"
                           class="bg-gray-800 border border-gray-600 p-2.5 rounded-lg text-white
                                  focus:border-blue-500 outline-none text-sm">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">
                        View
                    </button>
                    <span class="text-gray-400 text-sm hidden sm:block">— <?= $month_name ?></span>
                </form>
                <!-- Initialise month button -->
                <form method="POST">
                    <input type="hidden" name="init_month" value="1">
                    <input type="hidden" name="init_month_year" value="<?= $month_year ?>">
                    <button type="submit"
                            onclick="return confirm('Initialise salary records for <?= $month_name ?>? This will create fresh records for all active tellers who do not have one yet.')"
                            class="w-full sm:w-auto bg-gray-700 hover:bg-gray-600 active:bg-gray-500
                                   border border-gray-600 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">
                        🔄 Initialise <?= $month_name ?>
                    </button>
                </form>
            </div>

            <!-- ── Pending Advance Requests ── -->
            <?php if (!empty($pendingRows)): ?>
            <div class="bg-gray-800 rounded-2xl p-4 sm:p-5 space-y-4">
                <h2 class="font-bold text-base text-yellow-400">⏳ Pending Advance Requests</h2>
                <?php foreach ($pendingRows as $adv): ?>
                <div class="bg-gray-700/50 rounded-xl p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold"><?= htmlspecialchars($adv["full_name"]) ?></p>
                            <p class="text-gray-400 text-xs mt-0.5">
                                <?= date("d M Y H:i", strtotime($adv["requested_at"])) ?>
                            </p>
                        </div>
                        <p class="font-bold text-lg text-white shrink-0">
                            MK <?= number_format($adv["amount"], 2) ?>
                        </p>
                    </div>
                    <?php if ($adv["reason"]): ?>
                    <p class="text-sm text-gray-300 bg-gray-800 rounded-lg px-3 py-2">
                        "<?= htmlspecialchars($adv["reason"]) ?>"
                    </p>
                    <?php endif; ?>
                    <!-- Approve / Reject forms -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="advance_id" value="<?= $adv["id"] ?>">
                            <input type="hidden" name="action" value="approve">
                            <textarea name="notes" rows="2" placeholder="Optional note..."
                                      class="w-full p-2 rounded-lg bg-gray-700 border border-gray-600
                                             text-sm outline-none focus:border-green-500 resize-none text-white placeholder-gray-500"></textarea>
                            <button type="submit"
                                    onclick="return confirm('Approve this advance?')"
                                    class="w-full bg-green-600 hover:bg-green-700 py-2 rounded-lg text-sm font-semibold transition-colors">
                                ✓ Approve
                            </button>
                        </form>
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="advance_id" value="<?= $adv["id"] ?>">
                            <input type="hidden" name="action" value="reject">
                            <textarea name="notes" rows="2" placeholder="Reason for rejection..."
                                      class="w-full p-2 rounded-lg bg-gray-700 border border-gray-600
                                             text-sm outline-none focus:border-red-500 resize-none text-white placeholder-gray-500"></textarea>
                            <button type="submit"
                                    onclick="return confirm('Reject this advance?')"
                                    class="w-full bg-red-600 hover:bg-red-700 py-2 rounded-lg text-sm font-semibold transition-colors">
                                ✕ Reject
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ── Teller Salary Overview ── -->
            <div class="space-y-3">
                <h2 class="font-bold text-base">Salary Overview — <?= $month_name ?></h2>

                <!-- Desktop table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full bg-gray-800 rounded-xl text-sm">
                        <thead class="bg-gray-700 text-gray-300 uppercase text-xs tracking-wide">
                            <tr>
                                <th class="p-3 text-left">Teller</th>
                                <th class="p-3 text-right">Base Salary</th>
                                <th class="p-3 text-right">Advances</th>
                                <th class="p-3 text-right">Final Salary</th>
                                <th class="p-3 text-center">Status</th>
                                <th class="p-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tellerRows as $t): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700/40 transition-colors">
                                <td class="p-3">
                                    <p class="font-medium"><?= htmlspecialchars($t["full_name"]) ?></p>
                                    <p class="text-gray-400 text-xs">@<?= htmlspecialchars($t["username"]) ?></p>
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    MK <?= number_format($t["base_salary"], 2) ?>
                                </td>
                                <td class="p-3 text-right text-red-400 tabular-nums">
                                    <?= $t["total_advances"] ? 'MK ' . number_format($t["total_advances"], 2) : '—' ?>
                                </td>
                                <td class="p-3 text-right font-bold text-green-400 tabular-nums">
                                    MK <?= number_format($t["final_salary"] ?? $t["base_salary"], 2) ?>
                                </td>
                                <td class="p-3 text-center">
                                    <?php if ($t["paid"]): ?>
                                    <span class="bg-green-600/30 text-green-400 border border-green-600 px-2 py-0.5 rounded-full text-xs">
                                        ✓ Paid <?= date("d M", strtotime($t["paid_at"])) ?>
                                    </span>
                                    <?php elseif ($t["pending_advances"] > 0): ?>
                                    <span class="bg-yellow-600/30 text-yellow-400 border border-yellow-600 px-2 py-0.5 rounded-full text-xs">
                                        <?= $t["pending_advances"] ?> pending
                                    </span>
                                    <?php else: ?>
                                    <span class="bg-gray-600/30 text-gray-400 border border-gray-600 px-2 py-0.5 rounded-full text-xs">
                                        Unpaid
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- Mark paid -->
                                        <?php if (!$t["paid"] && $t["record_id"]): ?>
                                        <form method="POST">
                                            <input type="hidden" name="mark_paid" value="1">
                                            <input type="hidden" name="salary_record_id" value="<?= $t["record_id"] ?>">
                                            <button type="submit"
                                                    onclick="return confirm('Mark salary as paid?')"
                                                    class="bg-green-600 hover:bg-green-700 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                Mark Paid
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <!-- Edit salary -->
                                        <button onclick="openEditSalary(<?= $t['id'] ?>, '<?= htmlspecialchars($t['full_name']) ?>', <?= $t['base_salary'] ?>)"
                                                class="bg-blue-600 hover:bg-blue-700 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                            Edit Salary
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div class="md:hidden space-y-3">
                    <?php foreach ($tellerRows as $t): ?>
                    <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold"><?= htmlspecialchars($t["full_name"]) ?></p>
                                <p class="text-gray-400 text-xs">@<?= htmlspecialchars($t["username"]) ?></p>
                            </div>
                            <?php if ($t["paid"]): ?>
                            <span class="bg-green-600/30 text-green-400 border border-green-600 px-2 py-0.5 rounded-full text-xs shrink-0">✓ Paid</span>
                            <?php elseif ($t["pending_advances"] > 0): ?>
                            <span class="bg-yellow-600/30 text-yellow-400 border border-yellow-600 px-2 py-0.5 rounded-full text-xs shrink-0"><?= $t["pending_advances"] ?> pending</span>
                            <?php else: ?>
                            <span class="bg-gray-600/30 text-gray-400 border border-gray-600 px-2 py-0.5 rounded-full text-xs shrink-0">Unpaid</span>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <div class="bg-gray-700/50 rounded-lg p-2.5 text-center">
                                <p class="text-gray-400 text-xs mb-0.5">Base</p>
                                <p class="font-medium text-xs"><?= number_format($t["base_salary"], 2) ?></p>
                            </div>
                            <div class="bg-red-900/30 rounded-lg p-2.5 text-center">
                                <p class="text-gray-400 text-xs mb-0.5">Advances</p>
                                <p class="font-medium text-xs text-red-400"><?= number_format($t["total_advances"] ?? 0, 2) ?></p>
                            </div>
                            <div class="bg-green-900/30 rounded-lg p-2.5 text-center">
                                <p class="text-gray-400 text-xs mb-0.5">Final</p>
                                <p class="font-bold text-xs text-green-400"><?= number_format($t["final_salary"] ?? $t["base_salary"], 2) ?></p>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <?php if (!$t["paid"] && $t["record_id"]): ?>
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="mark_paid" value="1">
                                <input type="hidden" name="salary_record_id" value="<?= $t["record_id"] ?>">
                                <button type="submit"
                                        onclick="return confirm('Mark salary as paid?')"
                                        class="w-full bg-green-600 hover:bg-green-700 py-2 rounded-lg text-sm font-medium transition-colors">
                                    Mark Paid
                                </button>
                            </form>
                            <?php endif; ?>
                            <button onclick="openEditSalary(<?= $t['id'] ?>, '<?= htmlspecialchars($t['full_name']) ?>', <?= $t['base_salary'] ?>)"
                                    class="flex-1 bg-blue-600 hover:bg-blue-700 py-2 rounded-lg text-sm font-medium transition-colors">
                                Edit Salary
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Edit Salary Modal -->
<div id="salaryModal" class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center p-4">
    <div class="bg-gray-800 rounded-2xl p-6 w-full max-w-sm space-y-4 border border-gray-700">
        <h3 class="font-bold text-lg" id="modalTitle">Edit Salary</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="update_salary" value="1">
            <input type="hidden" name="teller_id" id="modalTellerId">
            <div class="space-y-1.5">
                <label class="text-sm font-medium text-gray-300">New Base Salary (MK)</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium pointer-events-none">MK</span>
                    <input type="text" inputmode="numeric" name="new_salary" id="modalSalaryInput"
                           required
                           class="w-full bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                  rounded-xl pl-10 pr-4 py-3 text-lg text-right outline-none transition-colors">
                </div>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeModal()"
                        class="flex-1 bg-gray-700 hover:bg-gray-600 py-3 rounded-xl font-medium transition-colors">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 py-3 rounded-xl font-bold transition-colors">
                    Update
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditSalary(id, name, salary) {
    document.getElementById('modalTellerId').value = id;
    document.getElementById('modalTitle').textContent = 'Edit Salary — ' + name;
    var input = document.getElementById('modalSalaryInput');
    input.value = parseFloat(salary).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('salaryModal').classList.remove('hidden');
    document.getElementById('salaryModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('salaryModal').classList.add('hidden');
    document.getElementById('salaryModal').classList.remove('flex');
}

// Close on backdrop click
document.getElementById('salaryModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Money input for modal
var msi = document.getElementById('modalSalaryInput');
msi.addEventListener('focus', function() {
    var raw = this.value.replace(/,/g, '');
    this.value = (raw === '0.00' || raw === '') ? '' : raw;
    var len = this.value.length;
    this.setSelectionRange(len, len);
});
msi.addEventListener('input', function() {
    var cleaned = this.value.replace(/[^0-9.]/g, '');
    var parts = cleaned.split('.');
    if (parts.length > 2) cleaned = parts[0] + '.' + parts.slice(1).join('');
    if (parts.length === 2 && parts[1].length > 2) cleaned = parts[0] + '.' + parts[1].substring(0, 2);
    this.value = cleaned;
});
msi.addEventListener('blur', function() {
    var n = parseFloat(this.value.replace(/,/g, ''));
    this.value = (!isNaN(n) && this.value !== '') ? n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
});
msi.closest('form').addEventListener('submit', function() {
    msi.value = msi.value.replace(/,/g, '');
});
</script>

</body>
</html>