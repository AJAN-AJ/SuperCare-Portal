<?php
session_start();
require_once "../config/db.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller") {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$today   = date("Y-m-d");

/* ── Today's session ── */
$stmt = $conn->prepare("SELECT * FROM balance_sessions WHERE user_id=? AND balance_date=?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: opening_select_platforms.php");
    exit();
}

$session    = $result->fetch_assoc();
$session_id = $session["id"];
$status     = $session["status"];

/* ── Lock ── */
$locked = ($status == "pending_approval_opening" || $status == "approved_opening");

/* ── Existing entries ── */
$existing = [];
$entries  = $conn->prepare("SELECT platform_id, opening_amount FROM balance_platform_entries WHERE session_id=?");
$entries->bind_param("i", $session_id);
$entries->execute();
$res = $entries->get_result();
while ($row = $res->fetch_assoc()) {
    $existing[$row["platform_id"]] = $row["opening_amount"];
}

/* ── Platforms ── */
$platformQuery = $conn->prepare("
    SELECT p.id, p.name
    FROM balance_session_platforms bsp
    JOIN platforms p ON p.id = bsp.platform_id
    WHERE bsp.session_id=?
");
$platformQuery->bind_param("i", $session_id);
$platformQuery->execute();
$platforms = $platformQuery->get_result();

/* ── Save ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$locked) {
    $total = 0;
    $conn->query("DELETE FROM balance_platform_entries WHERE session_id=$session_id");

    foreach ($_POST["amount"] as $platform_id => $amount) {
        $amount  = floatval(str_replace(",", "", $amount));
        $total  += $amount;
        $insert  = $conn->prepare("INSERT INTO balance_platform_entries (session_id, platform_id, opening_amount) VALUES (?,?,?)");
        $insert->bind_param("iid", $session_id, $platform_id, $amount);
        $insert->execute();
    }

    $update = $conn->prepare("UPDATE balance_sessions SET opening_total=?, status='pending_approval_opening' WHERE id=?");
    $update->bind_param("di", $total, $session_id);
    $update->execute();

    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opening Balances</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) {
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex overflow-hidden">

<?php include "../includes/sidebar.php"; ?>

<div class="flex-1 flex flex-col overflow-y-auto">

    <!-- Sticky header -->
    <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
        <div class="px-4 sm:px-6 py-3 flex items-center gap-3">
            <a href="dashboard.php"
               class="flex items-center gap-1 bg-gray-700 hover:bg-gray-600 active:bg-gray-500
                      px-3 py-2 rounded-lg text-sm font-medium transition-colors shrink-0">
                <span>←</span>
                <span class="hidden sm:inline">Dashboard</span>
            </a>
            <h1 class="text-lg sm:text-xl font-bold truncate">Opening Balances</h1>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 p-4 sm:p-6">
        <div class="max-w-2xl mx-auto space-y-4">

            <!-- Locked notice -->
            <?php if ($locked): ?>
            <div class="flex items-center gap-3 bg-yellow-700/40 border border-yellow-600 rounded-xl p-4 text-yellow-300 text-sm">
                <span class="text-xl">🔒</span>
                <span>Opening balances already submitted and are awaiting approval.</span>
            </div>
            <?php endif; ?>

            <!-- Form card -->
            <div class="bg-gray-800 rounded-2xl p-4 sm:p-6 shadow">
                <form method="POST" class="space-y-4">

                    <?php while ($platform = $platforms->fetch_assoc()): ?>
                    <div class="bg-gray-700/50 rounded-xl p-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <?= htmlspecialchars($platform["name"]) ?>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium pointer-events-none">$</span>
                            <input
                                type="text"
                                inputmode="decimal"
                                name="amount[<?= $platform["id"] ?>]"
                                class="money-input w-full bg-gray-700 border border-gray-600
                                       focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                       rounded-xl pl-7 pr-4 py-3 text-lg sm:text-xl
                                       text-right transition-colors outline-none
                                       <?= $locked ? 'opacity-60 cursor-not-allowed' : '' ?>"
                                value="<?= isset($existing[$platform["id"]])
                                    ? number_format($existing[$platform["id"]], 2)
                                    : '0.00' ?>"
                                <?= $locked ? "disabled" : "" ?>
                                oninput="formatMoney(this)"
                            />
                        </div>
                    </div>
                    <?php endwhile; ?>

                    <!-- Total -->
                    <div class="bg-blue-900/60 border border-blue-700 rounded-xl p-5 text-center">
                        <p class="text-blue-300 text-sm font-medium uppercase tracking-wide mb-1">Opening Total</p>
                        <p id="totalDisplay" class="text-3xl sm:text-4xl font-bold text-white">0.00</p>
                    </div>

                    <!-- Actions -->
                    <?php if (!$locked): ?>
                    <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                                   rounded-xl p-4 font-bold text-base sm:text-lg
                                   transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800">
                        Submit Opening
                    </button>
                    <?php endif; ?>

                </form>
            </div>

        </div>
    </div><!-- /content -->
</div><!-- /flex-1 -->

<script>
function clean(v) {
    return v.replace(/,/g, '');
}

function money(v) {
    const n = parseFloat(v);
    if (isNaN(n)) return '';
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatMoney(input) {
    const pos = input.selectionStart;
    input.value = money(clean(input.value));
    updateTotal();
}

function updateTotal() {
    let t = 0;
    document.querySelectorAll('.money-input').forEach(i => {
        t += parseFloat(clean(i.value)) || 0;
    });
    document.getElementById('totalDisplay').textContent =
        t.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Strip commas before submit so PHP gets raw numbers
document.querySelector('form').addEventListener('submit', function () {
    document.querySelectorAll('.money-input').forEach(i => {
        i.value = clean(i.value);
    });
});

// Init total on page load
updateTotal();
</script>
</body>
</html>