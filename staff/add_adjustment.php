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
$message = "";

/* ── Session ── */
$stmt = $conn->prepare("SELECT id FROM balance_sessions WHERE user_id=? AND balance_date=?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    header("Location: opening_select_platforms.php");
    exit();
}
$session_id = $res->fetch_assoc()["id"];

/* ── Platforms ── */
$platforms = $conn->query("SELECT id, name FROM platforms ORDER BY name");

/* ── Save ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $platform_id = intval($_POST["platform_id"]);
    $type        = $_POST["type"];
    $amount      = floatval(str_replace(",", "", $_POST["amount"]));
    $description = trim($_POST["description"]);

    if ($amount <= 0) {
        $message = "Amount must be greater than zero.";
    } elseif (!in_array($type, ["incoming", "outgoing"])) {
        $message = "Invalid transaction type.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO balance_adjustments
                (balance_session_id, platform_id, type, amount, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisds", $session_id, $platform_id, $type, $amount, $description);
        if ($stmt->execute()) {
            header("Location: dashboard.php");
            exit();
        } else {
            $message = $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Statement</title>
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
            <h1 class="text-lg sm:text-xl font-bold">Add Statement</h1>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 p-4 sm:p-6">
        <div class="max-w-xl mx-auto space-y-4">

            <!-- Error -->
            <?php if ($message): ?>
            <div class="flex gap-3 p-4 bg-red-700/40 border border-red-600 rounded-xl text-red-300 text-sm">
                <span>⚠️</span><p><?= htmlspecialchars($message) ?></p>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="bg-gray-800 rounded-2xl p-4 sm:p-6 shadow">
                <form method="POST" class="space-y-5">

                    <!-- Platform -->
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-300">
                            Platform <span class="text-red-400">*</span>
                        </label>
                        <select name="platform_id" required
                                class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                       focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                       outline-none text-white transition-colors">
                            <?php while ($p = $platforms->fetch_assoc()): ?>
                            <option value="<?= $p["id"] ?>"><?= htmlspecialchars($p["name"]) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Type -->
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-300">
                            Transaction Type <span class="text-red-400">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-3 bg-gray-700/50 border border-gray-600
                                          rounded-xl p-3 cursor-pointer hover:border-red-500
                                          has-[:checked]:border-red-500 has-[:checked]:bg-red-900/20 transition-colors">
                                <input type="radio" name="type" value="outgoing" required
                                       class="accent-red-500 w-4 h-4 shrink-0">
                                <div>
                                    <p class="font-medium text-sm">Sent</p>
                                    <p class="text-gray-400 text-xs">Outgoing</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 bg-gray-700/50 border border-gray-600
                                          rounded-xl p-3 cursor-pointer hover:border-green-500
                                          has-[:checked]:border-green-500 has-[:checked]:bg-green-900/20 transition-colors">
                                <input type="radio" name="type" value="incoming"
                                       class="accent-green-500 w-4 h-4 shrink-0">
                                <div>
                                    <p class="font-medium text-sm">Received</p>
                                    <p class="text-gray-400 text-xs">Incoming</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-300">
                            Amount <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium pointer-events-none">MK</span>
                            <input
                                type="text"
                                inputmode="numeric"
                                name="amount"
                                id="amountInput"
                                required
                                placeholder="0.00"
                                class="w-full bg-gray-700 border border-gray-600
                                       focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                       rounded-xl pl-10 pr-4 py-3 text-lg text-right
                                       outline-none transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-300">
                            Description <span class="text-red-400">*</span>
                        </label>
                        <textarea name="description" rows="3"
                                  placeholder="e.g. Sent to John Banda"
                                  required
                                  class="w-full p-3 rounded-xl bg-gray-700 border border-gray-600
                                         focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                         outline-none text-white placeholder-gray-500
                                         transition-colors resize-none"></textarea>
                    </div>

                    <button type="submit"
                            class="w-full bg-green-600 hover:bg-green-700 active:bg-green-800
                                   p-4 rounded-xl font-bold text-base transition-colors">
                        Save Statement
                    </button>

                </form>
            </div>

        </div>
    </div>
</div>

<script>
var amountInput = document.getElementById('amountInput');

// Focus: clear formatting, cursor to end
amountInput.addEventListener('focus', function() {
    var raw = this.value.replace(/,/g, '');
    if (raw === '0.00' || raw === '') {
        this.value = '';
    } else {
        this.value = raw;
    }
    var len = this.value.length;
    this.setSelectionRange(len, len);
});

// Input: only allow digits and one decimal point, max 2 decimal places
amountInput.addEventListener('input', function() {
    var cleaned = this.value.replace(/[^0-9.]/g, '');
    var parts   = cleaned.split('.');
    if (parts.length > 2) {
        cleaned = parts[0] + '.' + parts.slice(1).join('');
    }
    if (parts.length === 2 && parts[1].length > 2) {
        cleaned = parts[0] + '.' + parts[1].substring(0, 2);
    }
    this.value = cleaned;
});

// Blur: format with commas
amountInput.addEventListener('blur', function() {
    var raw = this.value.replace(/,/g, '');
    var n   = parseFloat(raw);
    if (!isNaN(n) && raw !== '') {
        this.value = n.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        this.value = '';
    }
});

// Strip commas before submit
amountInput.closest('form').addEventListener('submit', function() {
    amountInput.value = amountInput.value.replace(/,/g, '');
});
</script>

</body>
</html>