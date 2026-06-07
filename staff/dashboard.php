<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "../config/db.php";
require_once "../includes/session_guard.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller") {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$today   = date("Y-m-d");

/* Fetch teller profile */
$userStmt = $conn->prepare("
    SELECT full_name, username, date_of_birth, phone, address, next_of_kin,
           annual_leave_days, annual_leave_used, profile_completed
    FROM users WHERE id = ?
");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$teller = $userStmt->get_result()->fetch_assoc();
$remaining_leave = $teller["annual_leave_days"] - $teller["annual_leave_used"];

/* Today's balance session */
$stmt = $conn->prepare("SELECT * FROM balance_sessions WHERE user_id = ? AND balance_date = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$sessionResult = $stmt->get_result();
$session       = null;
$sessionExists = false;

if ($sessionResult->num_rows > 0) {
    $session       = $sessionResult->fetch_assoc();
    $sessionExists = true;
}

/* Attendance check */
$stmt = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND DATE(check_in_time) = CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$already_checked = $stmt->get_result()->num_rows > 0;

/* Statements */
$statements = null;
if ($sessionExists) {
    $session_id = $session["id"];
    $stmt = $conn->prepare("
        SELECT ba.*, p.name AS platform_name
        FROM balance_adjustments ba
        JOIN platforms p ON ba.platform_id = p.id
        WHERE ba.balance_session_id = ?
        ORDER BY ba.created_at DESC
    ");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $statements = $stmt->get_result();
}

/* Balance engine */
$expected = $incoming = $outgoing = 0;
if ($sessionExists) {
    $sid = $session["id"];
    foreach (["outgoing", "incoming"] as $type) {
        $s = $conn->prepare("SELECT COALESCE(SUM(amount),0) total FROM balance_adjustments WHERE balance_session_id=? AND type=?");
        $s->bind_param("is", $sid, $type);
        $s->execute();
        $$type = $s->get_result()->fetch_assoc()["total"];
    }
    $expected = floatval($session["opening_total"]) - floatval($outgoing) + floatval($incoming);
}

$analysisDifference = floatval($session["closing_total"] ?? 0) - floatval($expected);

/* Active tab from URL */
$tab = $_GET["tab"] ?? "dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= htmlspecialchars($teller["full_name"]) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) {
            input, select { font-size: 16px !important; }
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<?php include "../includes/header.php"; ?>
<?php include "../includes/sidebar.php"; ?>

<div class="ml-0 lg:ml-72 p-3 sm:p-6 transition-all duration-300">
<div class="max-w-5xl mx-auto space-y-5">

    <!-- ── Welcome bar ── -->
    <div class="bg-gray-800 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-gray-400 text-sm">Welcome back,</p>
            <h1 class="text-2xl sm:text-3xl font-bold"><?= htmlspecialchars($teller["full_name"]) ?></h1>
            <p class="text-gray-500 text-xs mt-0.5">@<?= htmlspecialchars($teller["username"]) ?> · <?= date("l, d F Y") ?></p>
        </div>
        <div class="flex items-center gap-3">
            <!-- Profile button -->
            <button onclick="switchTab('profile')"
                    class="flex items-center gap-2 bg-gray-700 hover:bg-gray-600 active:bg-gray-500
                           px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="opacity-70">
                    <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                </svg>
                My Profile
            </button>
            <!-- Check-in badge -->
            <?php if ($already_checked): ?>
            <span class="bg-green-600/30 text-green-400 border border-green-600 px-3 py-2 rounded-xl text-xs font-medium">
                ✅ Checked In
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Tab nav ── -->
    <div class="flex gap-2 bg-gray-800 p-1.5 rounded-xl w-fit">
        <button onclick="switchTab('dashboard')" id="tab-btn-dashboard"
                class="tab-btn px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-blue-600 text-white">
            Dashboard
        </button>
        <button onclick="switchTab('profile')" id="tab-btn-profile"
                class="tab-btn px-4 py-2 rounded-lg text-sm font-medium transition-colors text-gray-400 hover:text-white">
            My Profile
        </button>
    </div>

    <!-- ══════════════════════════════
         TAB: DASHBOARD
    ══════════════════════════════ -->
    <div id="tab-dashboard" class="tab-content active space-y-5">

        <?php if (!$already_checked): ?>
        <!-- Check in prompt -->
        <div class="bg-gray-800 rounded-2xl p-6 text-center space-y-4">
            <p class="text-gray-400">You haven't checked in yet today.</p>
            <form method="POST" action="checkin.php">
                <button class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                               px-8 py-3.5 rounded-xl font-bold text-base transition-colors">
                    Check In Now
                </button>
            </form>
        </div>

        <?php else: ?>

        <!-- Session status banner -->
        <?php if ($sessionExists): ?>
        <?php
            $bannerClass = match($session["status"]) {
                "draft"                    => "bg-gray-700/60 border-gray-600 text-gray-300",
                "pending_approval_opening" => "bg-yellow-700/40 border-yellow-600 text-yellow-300",
                "approved_opening"         => "bg-green-700/40 border-green-600 text-green-300",
                default                    => "bg-blue-700/40 border-blue-600 text-blue-300",
            };
            $bannerMsg = match($session["status"]) {
                "draft"                    => "⏳ Opening balance not submitted yet.",
                "pending_approval_opening" => "🕐 Opening balance awaiting admin approval.",
                "approved_opening"         => "✅ Opening approved — you may submit closing when ready.",
                default                    => ucfirst($session["status"]),
            };
        ?>
        <div class="<?= $bannerClass ?> border rounded-xl px-4 py-3 text-sm font-medium">
            <?= $bannerMsg ?>
        </div>
        <?php endif; ?>

        <!-- Opening + Closing grid -->
        <?php if ($sessionExists): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            <!-- Opening -->
            <div class="bg-gray-800 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg">Opening Balances</h3>
                    <span class="text-blue-400 font-bold text-lg"><?= number_format($session["opening_total"], 2) ?></span>
                </div>
                <?php
                $open = $conn->prepare("SELECT p.name, bpe.opening_amount FROM balance_platform_entries bpe JOIN platforms p ON p.id=bpe.platform_id WHERE session_id=?");
                $open->bind_param("i", $session["id"]);
                $open->execute();
                $opening = $open->get_result();
                ?>
                <div class="space-y-2">
                <?php while ($row = $opening->fetch_assoc()): ?>
                    <div class="flex justify-between text-sm border-b border-gray-700 py-2">
                        <span class="text-gray-300"><?= htmlspecialchars($row["name"]) ?></span>
                        <span class="font-medium tabular-nums"><?= number_format($row["opening_amount"], 2) ?></span>
                    </div>
                <?php endwhile; ?>
                </div>
            </div>

            <!-- Closing -->
            <div class="bg-gray-800 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg">Closing Balances</h3>
                    <span class="text-red-400 font-bold text-lg"><?= number_format($session["closing_total"], 2) ?></span>
                </div>
                <?php
                $close = $conn->prepare("SELECT p.name, bpe.closing_amount FROM balance_platform_entries bpe JOIN platforms p ON p.id=bpe.platform_id WHERE session_id=?");
                $close->bind_param("i", $session["id"]);
                $close->execute();
                $closing = $close->get_result();
                ?>
                <div class="space-y-2">
                <?php while ($row = $closing->fetch_assoc()): ?>
                    <div class="flex justify-between text-sm border-b border-gray-700 py-2">
                        <span class="text-gray-300"><?= htmlspecialchars($row["name"]) ?></span>
                        <span class="font-medium tabular-nums"><?= number_format($row["closing_amount"], 2) ?></span>
                    </div>
                <?php endwhile; ?>
                </div>
            </div>

        </div>

        <!-- Balance Analysis -->
        <div class="bg-gray-800 rounded-2xl p-5">
            <h3 class="font-bold text-lg mb-4">Balance Analysis</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                    <p class="text-gray-400 text-xs mb-1">Expected</p>
                    <p class="text-blue-400 font-bold"><?= number_format($expected, 2) ?></p>
                </div>
                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                    <p class="text-gray-400 text-xs mb-1">Actual</p>
                    <p class="font-bold"><?= number_format($session["closing_total"] ?? 0, 2) ?></p>
                </div>
                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                    <p class="text-gray-400 text-xs mb-1">Incoming</p>
                    <p class="text-green-400 font-bold"><?= number_format($incoming, 2) ?></p>
                </div>
                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                    <p class="text-gray-400 text-xs mb-1">Outgoing</p>
                    <p class="text-red-400 font-bold"><?= number_format($outgoing, 2) ?></p>
                </div>
            </div>
            <div class="rounded-xl p-3 text-center font-bold text-lg
                <?= $analysisDifference < 0 ? 'bg-red-700/30 border border-red-700 text-red-400'
                  : ($analysisDifference > 0 ? 'bg-yellow-700/30 border border-yellow-700 text-yellow-400'
                  : 'bg-green-700/30 border border-green-700 text-green-400') ?>">
                <?php if ($analysisDifference < 0): ?>
                    ⚠️ Shortage: <?= number_format(abs($analysisDifference), 2) ?>
                <?php elseif ($analysisDifference > 0): ?>
                    ⚠️ Overage: <?= number_format($analysisDifference, 2) ?>
                <?php else: ?>
                    ✅ Balanced
                <?php endif; ?>
            </div>
        </div>

        <!-- Statements -->
        <div class="bg-gray-800 rounded-2xl p-5">
            <h3 class="font-bold text-lg mb-4">Today's Statements</h3>
            <?php if ($statements && $statements->num_rows): ?>
            <div class="space-y-3">
                <?php while ($s = $statements->fetch_assoc()): ?>
                <div class="bg-gray-700/50 rounded-xl p-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-sm"><?= htmlspecialchars($s["platform_name"]) ?></p>
                        <p class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($s["description"]) ?></p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="font-bold tabular-nums"><?= number_format($s["amount"], 2) ?></p>
                        <span class="text-xs font-medium <?= $s["type"] == "incoming" ? "text-green-400" : "text-red-400" ?>">
                            <?= strtoupper($s["type"]) ?>
                        </span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-400 text-sm">No statements today.</p>
            <?php endif; ?>
        </div>

        <!-- Action buttons -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="add_adjustment.php"
               class="bg-orange-600 hover:bg-orange-700 active:bg-orange-800
                      text-center rounded-xl p-4 font-semibold transition-colors">
                + Add Statement
            </a>
            <?php if (in_array($session["status"], ["approved_opening", "pending_approval_closing", "approved_closing"])): ?>
            <a href="closing_entry.php"
               class="bg-red-600 hover:bg-red-700 active:bg-red-800
                      text-center rounded-xl p-4 font-semibold transition-colors">
                Closing Balances
            </a>
            <?php endif; ?>
        </div>

        <?php endif; // sessionExists ?>
        <?php endif; // already_checked ?>

    </div><!-- /tab-dashboard -->


    <!-- ══════════════════════════════
         TAB: PROFILE
    ══════════════════════════════ -->
    <div id="tab-profile" class="tab-content space-y-4">

        <!-- Profile card -->
        <div class="bg-gray-800 rounded-2xl p-5 sm:p-6">

            <!-- Avatar + name -->
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 mb-6 pb-6 border-b border-gray-700">
                <div class="w-20 h-20 rounded-2xl bg-blue-600 flex items-center justify-center text-3xl font-bold shrink-0">
                    <?= strtoupper(substr($teller["full_name"], 0, 1)) ?>
                </div>
                <div class="text-center sm:text-left">
                    <h2 class="text-2xl font-bold"><?= htmlspecialchars($teller["full_name"]) ?></h2>
                    <p class="text-gray-400 text-sm mt-0.5">@<?= htmlspecialchars($teller["username"]) ?></p>
                    <span class="inline-block mt-2 <?= $teller["profile_completed"] ? 'bg-green-600/30 text-green-400 border-green-600' : 'bg-yellow-600/30 text-yellow-400 border-yellow-600' ?> border px-2.5 py-0.5 rounded-full text-xs font-medium">
                        <?= $teller["profile_completed"] ? "Profile Complete" : "Profile Incomplete" ?>
                    </span>
                </div>
            </div>

            <!-- Details grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <?php
                $fields = [
                    ["Date of Birth",  $teller["date_of_birth"] ? date("d F Y", strtotime($teller["date_of_birth"])) : "—", "🎂"],
                    ["Phone",          $teller["phone"] ?: "—",       "📞"],
                    ["Address",        $teller["address"] ?: "—",     "📍"],
                    ["Next of Kin",    $teller["next_of_kin"] ?: "—", "👤"],
                ];
                foreach ($fields as $f):
                ?>
                <div class="bg-gray-700/50 rounded-xl p-4">
                    <p class="text-gray-400 text-xs mb-1 flex items-center gap-1.5">
                        <span><?= $f[2] ?></span><?= $f[0] ?>
                    </p>
                    <p class="font-medium text-sm"><?= htmlspecialchars($f[1]) ?></p>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- Leave balance card -->
        <div class="bg-gray-800 rounded-2xl p-5">
            <h3 class="font-bold text-lg mb-4">Leave Balance</h3>
            <div class="grid grid-cols-3 gap-3 mb-3">
                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                    <p class="text-gray-400 text-xs mb-1">Allocated</p>
                    <p class="font-bold text-lg"><?= $teller["annual_leave_days"] ?></p>
                </div>
                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                    <p class="text-gray-400 text-xs mb-1">Used</p>
                    <p class="font-bold text-lg text-yellow-400"><?= $teller["annual_leave_used"] ?></p>
                </div>
                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                    <p class="text-gray-400 text-xs mb-1">Remaining</p>
                    <p class="font-bold text-lg <?= $remaining_leave <= 3 ? 'text-red-400' : 'text-green-400' ?>"><?= $remaining_leave ?></p>
                </div>
            </div>
            <!-- Leave bar -->
            <div class="w-full bg-gray-700 rounded-full h-2 overflow-hidden">
                <?php $usedPct = $teller["annual_leave_days"] > 0 ? ($teller["annual_leave_used"] / $teller["annual_leave_days"]) * 100 : 0; ?>
                <div class="h-2 rounded-full <?= $usedPct >= 80 ? 'bg-red-500' : ($usedPct >= 50 ? 'bg-yellow-500' : 'bg-green-500') ?>"
                     style="width:<?= min($usedPct,100) ?>%"></div>
            </div>
            <div class="mt-4">
                <a href="apply_leave.php"
                   class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                          px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    + Apply for Leave
                </a>
            </div>
        </div>

        <!-- Quick links -->
        <div class="grid grid-cols-2 gap-3">
            <a href="my_leave_requests.php"
               class="bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl p-4 text-center text-sm font-medium transition-colors">
                🏖️ My Leave Requests
            </a>
            <button onclick="switchTab('dashboard')"
                    class="bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl p-4 text-center text-sm font-medium transition-colors">
                ← Back to Dashboard
            </button>
        </div>

    </div><!-- /tab-profile -->

</div><!-- /max-w -->
</div><!-- /ml -->

<?php include "../includes/footer.php"; ?>

<script>
function switchTab(name) {
    // Hide all content panels
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    // Reset all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('bg-blue-600', 'text-white');
        btn.classList.add('text-gray-400', 'hover:text-white');
    });
    // Activate selected
    document.getElementById('tab-' + name).classList.add('active');
    const activeBtn = document.getElementById('tab-btn-' + name);
    activeBtn.classList.add('bg-blue-600', 'text-white');
    activeBtn.classList.remove('text-gray-400', 'hover:text-white');
    // Update URL without reload
    history.replaceState(null, '', '?tab=' + name);
    // Scroll to top of content
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Init from URL param
const urlTab = new URLSearchParams(window.location.search).get('tab');
if (urlTab) switchTab(urlTab);
</script>

</body>
</html>