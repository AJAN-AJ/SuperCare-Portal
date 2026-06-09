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

/* ── Fix #5: Only lock when approved, not just pending ── */
/* Teller can still edit while status is draft OR pending_approval_opening */
/* Once admin approves (approved_opening) it locks */
$locked = ($status == "approved_opening");

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

/* ── Auto-save draft (AJAX) ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["autosave"]) && !$locked) {
    header("Content-Type: application/json");
    $total = 0;
    $conn->query("DELETE FROM balance_platform_entries WHERE session_id=$session_id");
    foreach ($_POST["amount"] as $platform_id => $amount) {
        $amount = floatval(str_replace(",", "", $amount));
        $total += $amount;
        $ins = $conn->prepare("INSERT INTO balance_platform_entries (session_id, platform_id, opening_amount) VALUES (?,?,?)");
        $ins->bind_param("iid", $session_id, $platform_id, $amount);
        $ins->execute();
    }
    $upd = $conn->prepare("UPDATE balance_sessions SET opening_total=? WHERE id=?");
    $upd->bind_param("di", $total, $session_id);
    $upd->execute();
    echo json_encode(["ok" => true]);
    exit();
}

/* ── Full submit ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$locked && !isset($_POST["autosave"])) {
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
<?php
/* ── Auto-save endpoint ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["autosave"]) && !$locked) {
    header("Content-Type: application/json");
    $saved = 0;
    $total = 0;

    // Delete existing entries first
    $conn->query("DELETE FROM balance_platform_entries WHERE session_id=$session_id");

    foreach ($_POST["amount"] as $platform_id => $amount) {
        $amount = floatval(str_replace(",", "", $amount));
        $total += $amount;
        $ins = $conn->prepare("INSERT INTO balance_platform_entries (session_id, platform_id, opening_amount) VALUES (?,?,?)");
        $ins->bind_param("iid", $session_id, $platform_id, $amount);
        if ($ins->execute()) $saved++;
    }

    // Update total but keep status as draft
    $upd = $conn->prepare("UPDATE balance_sessions SET opening_total=? WHERE id=?");
    $upd->bind_param("di", $total, $session_id);
    $upd->execute();

    echo json_encode(["ok" => true, "saved" => $saved, "total" => $total]);
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
<body class="bg-gray-900 text-white min-h-screen">

<?php include "../includes/sidebar.php"; ?>

<div class="flex flex-col min-h-screen lg:ml-72">

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
            <span id="saveStatus" class="ml-auto text-xs text-gray-500 shrink-0"></span>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 p-4 sm:p-6">
        <div class="max-w-2xl mx-auto space-y-4">

            <!-- Status notice -->
            <?php if ($locked): ?>
            <div class="flex items-center gap-3 bg-yellow-700/40 border border-yellow-600 rounded-xl p-4 text-yellow-300 text-sm">
                <span class="text-xl">🔒</span>
                <span>Opening balances have been approved and are now locked.</span>
            </div>
            <?php elseif ($status == "pending_approval_opening"): ?>
            <div class="flex items-center gap-3 bg-blue-700/40 border border-blue-600 rounded-xl p-4 text-blue-300 text-sm">
                <span class="text-xl">🕐</span>
                <span>Awaiting admin approval — you can still edit and resubmit.</span>
            </div>
            <?php endif; ?>

            <!-- Form card -->
            <div class="bg-gray-800 rounded-2xl p-4 sm:p-6 shadow">
                <form method="POST" class="space-y-4" id="openingForm">

                    <?php while ($platform = $platforms->fetch_assoc()): ?>
                    <div class="bg-gray-700/50 rounded-xl p-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <?= htmlspecialchars($platform["name"]) ?>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium pointer-events-none">MK</span>
                            <input
                                type="text"
                                inputmode="numeric"
                                name="amount[<?= $platform["id"] ?>]"
                                class="money-input w-full bg-gray-700 border border-gray-600
                                       focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                       rounded-xl pl-10 pr-4 py-3 text-lg sm:text-xl
                                       text-right transition-colors outline-none
                                       <?= $locked ? 'opacity-60 cursor-not-allowed' : '' ?>"
                                value="<?= isset($existing[$platform["id"]])
                                    ? number_format($existing[$platform["id"]], 2, '.', ',')
                                    : '' ?>"
                                placeholder="0.00"
                                <?= $locked ? "disabled" : "" ?>
                                data-platform="<?= $platform["id"] ?>"
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
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="opening_select_platforms.php"
                           class="flex-1 text-center bg-gray-700 hover:bg-gray-600 active:bg-gray-500
                                  rounded-xl p-4 font-bold text-base sm:text-lg
                                  transition-colors border border-gray-600">
                            ✏️ Edit Platforms
                        </a>
                        <button type="submit"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                                       rounded-xl p-4 font-bold text-base sm:text-lg
                                       transition-colors focus:outline-none focus:ring-2
                                       focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800">
                            <?= $status == "pending_approval_opening" ? "Resubmit Opening" : "Submit Opening" ?>
                        </button>
                    </div>
                    <?php endif; ?>

                </form>
            </div>

        </div>
    </div>
</div>

<script>
// ─── Auto-save draft values to DB ──────────────────────────────────────────
var saveTimer = null;
var saveStatus = document.getElementById('saveStatus');

function scheduleSave() {
    clearTimeout(saveTimer);
    saveStatus.textContent = 'Saving...';
    saveStatus.className = 'ml-auto text-xs text-gray-400 shrink-0';
    saveTimer = setTimeout(autoSave, 800); // save 800ms after last keystroke
}

function autoSave() {
    var formData = new FormData();
    formData.append('autosave', '1');

    document.querySelectorAll('.money-input').forEach(function(i) {
        var raw = i.value.replace(/,/g, '');
        var platformId = i.getAttribute('data-platform');
        formData.append('amount[' + platformId + ']', raw || '0');
    });

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            var now = new Date();
            var time = now.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            saveStatus.textContent = 'Draft saved ' + time;
            saveStatus.className = 'ml-auto text-xs text-green-500 shrink-0';
        }
    })
    .catch(function() {
        saveStatus.textContent = 'Save failed';
        saveStatus.className = 'ml-auto text-xs text-red-400 shrink-0';
    });
}

// ─── Fix #6: Proper money input that doesn't jump cursor ───────────────────
// Strategy: store raw digits only, format only on display
// User types digits, we keep track of integer + decimal parts separately

document.querySelectorAll('.money-input').forEach(function(input) {

    // On focus: strip formatting so user types raw
    input.addEventListener('focus', function() {
        var raw = this.value.replace(/,/g, '');
        if (raw === '0.00' || raw === '') {
            this.value = '';
        } else {
            this.value = raw;
        }
        // Move cursor to end
        var len = this.value.length;
        this.setSelectionRange(len, len);
    });

    // On input: only allow digits and one decimal point
    input.addEventListener('input', function() {
        var pos    = this.selectionStart;
        var before = this.value.substring(0, pos);
        var after  = this.value.substring(pos);

        // Strip anything that isn't a digit or decimal point
        var cleaned = this.value.replace(/[^0-9.]/g, '');

        // Only allow one decimal point
        var parts = cleaned.split('.');
        if (parts.length > 2) {
            cleaned = parts[0] + '.' + parts.slice(1).join('');
        }

        // Limit to 2 decimal places
        if (parts.length === 2 && parts[1].length > 2) {
            cleaned = parts[0] + '.' + parts[1].substring(0, 2);
        }

        this.value = cleaned;
        updateTotal();
    });

    // On blur: format with commas and 2 decimal places
    input.addEventListener('blur', function() {
        scheduleSave();
        var raw = this.value.replace(/,/g, '');
        var n   = parseFloat(raw);
        if (isNaN(n) || raw === '') {
            this.value = '';
        } else {
            this.value = n.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        updateTotal();
    });
});

function updateTotal() {
    var t = 0;
    document.querySelectorAll('.money-input').forEach(function(i) {
        var raw = i.value.replace(/,/g, '');
        t += parseFloat(raw) || 0;
    });
    document.getElementById('totalDisplay').textContent =
        t.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Strip commas before submit so PHP gets raw numbers
document.getElementById('openingForm').addEventListener('submit', function() {
    document.querySelectorAll('.money-input').forEach(function(i) {
        i.value = i.value.replace(/,/g, '');
    });
});

// Init total on page load
updateTotal();
</script>
</body>
</html>