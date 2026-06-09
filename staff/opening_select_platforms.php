<?php
session_start();
require_once "../config/db.php";
require_once "../includes/session_guard.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller") {
    header("Location: ../login.php");
    exit();
}

$user_id   = $_SESSION["user_id"];
$branch_id = $_SESSION["branch_id"];
$today     = date("Y-m-d");

/* ── Check if today's session exists ── */
$stmt = $conn->prepare("SELECT id, status FROM balance_sessions WHERE user_id=? AND balance_date=?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $session_id = $existing["id"];
    $status     = $existing["status"];

    /* Fix #5: If already approved redirect straight to entry (locked view) */
    /* If draft or pending, allow re-selecting platforms */
    if ($status == "approved_opening" || $status == "approved_closing" ||
        $status == "pending_approval_closing") {
        header("Location: opening_entry.php");
        exit();
    }
} else {
    /* Create new draft session */
    $ins = $conn->prepare("
        INSERT INTO balance_sessions (user_id, branch_id, balance_date, status)
        VALUES (?, ?, ?, 'draft')
    ");
    $ins->bind_param("iis", $user_id, $branch_id, $today);
    $ins->execute();
    $session_id = $ins->insert_id;
    $status     = "draft";
}

/* ── Get already selected platforms for this session ── */
$selectedStmt = $conn->prepare("SELECT platform_id FROM balance_session_platforms WHERE session_id=?");
$selectedStmt->bind_param("i", $session_id);
$selectedStmt->execute();
$selectedRes  = $selectedStmt->get_result();
$selectedIds  = [];
while ($r = $selectedRes->fetch_assoc()) {
    $selectedIds[] = $r["platform_id"];
}

/* ── Handle form submission ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!empty($_POST["platforms"])) {
        /* Remove old selections */
        $del = $conn->prepare("DELETE FROM balance_session_platforms WHERE session_id=?");
        $del->bind_param("i", $session_id);
        $del->execute();

        /* Insert new selections */
        foreach ($_POST["platforms"] as $platform_id) {
            $ins2 = $conn->prepare("INSERT INTO balance_session_platforms (session_id, platform_id) VALUES (?,?)");
            $ins2->bind_param("ii", $session_id, $platform_id);
            $ins2->execute();
        }

        header("Location: opening_entry.php");
        exit();
    } else {
        $error = "Please select at least one platform.";
    }
}

/* ── Fetch active platforms ── */
$platforms = $conn->query("SELECT * FROM platforms WHERE active=1 ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Platforms</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) { input, select { font-size: 16px !important; } }
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
            <h1 class="text-lg sm:text-xl font-bold">Select Platforms</h1>
        </div>
    </div>

    <div class="flex-1 p-4 sm:p-6">
        <div class="max-w-xl mx-auto space-y-4">

            <p class="text-gray-400 text-sm">
                Select the platforms you are working with today. You can change this selection before submitting.
            </p>

            <?php if (!empty($error)): ?>
            <div class="flex gap-3 p-4 bg-red-700/40 border border-red-600 rounded-xl text-red-300 text-sm">
                <span>⚠️</span><p><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-3" id="platformForm">

                <?php while ($platform = $platforms->fetch_assoc()): ?>
                <label class="flex items-center gap-4 bg-gray-800 border border-gray-700
                              hover:border-blue-500 rounded-xl p-4 cursor-pointer
                              transition-colors has-[:checked]:border-blue-500
                              has-[:checked]:bg-blue-900/20">
                    <input
                        type="checkbox"
                        name="platforms[]"
                        value="<?= $platform['id'] ?>"
                        <?= in_array($platform['id'], $selectedIds) ? 'checked' : '' ?>
                        class="w-5 h-5 rounded accent-blue-500 cursor-pointer shrink-0"
                    >
                    <span class="text-base font-medium"><?= htmlspecialchars($platform['name']) ?></span>
                </label>
                <?php endwhile; ?>

                <!-- Select all / none -->
                <div class="flex gap-2 pt-1">
                    <button type="button" onclick="selectAll(true)"
                            class="flex-1 bg-gray-700 hover:bg-gray-600 py-2 rounded-lg text-sm transition-colors">
                        Select All
                    </button>
                    <button type="button" onclick="selectAll(false)"
                            class="flex-1 bg-gray-700 hover:bg-gray-600 py-2 rounded-lg text-sm transition-colors">
                        Clear All
                    </button>
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                               p-4 rounded-xl font-bold text-base transition-colors mt-2">
                    Continue to Opening Entry →
                </button>
            </form>

        </div>
    </div>
</div>

<script>
function selectAll(val) {
    document.querySelectorAll('input[name="platforms[]"]').forEach(function(cb) {
        cb.checked = val;
    });
}
</script>
</body>
</html>