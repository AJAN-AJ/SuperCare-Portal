<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

$session_id = $_GET["id"] ?? null;
if (!$session_id) {
    die("Invalid session ID");
}

/* =========================
   GET SESSION INFO
========================= */
$stmt = $conn->prepare("
    SELECT bs.*, u.full_name, b.name AS branch_name
    FROM balance_sessions bs
    JOIN users u ON bs.user_id = u.id
    JOIN branches b ON bs.branch_id = b.id
    WHERE bs.id = ?
");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    die("Session not found");
}

/* =========================
   OPENING BREAKDOWN
========================= */
$openingStmt = $conn->prepare("
    SELECT p.name, bpe.opening_amount
    FROM balance_platform_entries bpe
    JOIN platforms p ON bpe.platform_id = p.id
    WHERE bpe.session_id = ?
");
$openingStmt->bind_param("i", $session_id);
$openingStmt->execute();
$opening = $openingStmt->get_result();

/* =========================
   CLOSING BREAKDOWN
========================= */
$closingStmt = $conn->prepare("
    SELECT p.name, bpe.closing_amount
    FROM balance_platform_entries bpe
    JOIN platforms p ON bpe.platform_id = p.id
    WHERE bpe.session_id = ?
");
$closingStmt->bind_param("i", $session_id);
$closingStmt->execute();
$closing = $closingStmt->get_result();

/* =========================
   STATEMENTS
========================= */
$adjStmt = $conn->prepare("
    SELECT ba.*, p.name AS platform_name
    FROM balance_adjustments ba
    JOIN platforms p ON ba.platform_id = p.id
    WHERE ba.balance_session_id = ?
    ORDER BY ba.created_at ASC
");
$adjStmt->bind_param("i", $session_id);
$adjStmt->execute();
$adjustments = $adjStmt->get_result();

/* =========================
   ENGINE (EXPECTED VALUE)
========================= */
$expected = $session["opening_total"];

while ($a = $adjustments->fetch_assoc()) {
    if ($a["type"] == "incoming") {
        $expected += $a["amount"];
    } else {
        $expected -= $a["amount"];
    }
}

/* reset pointer */
$adjStmt->execute();
$adjustments = $adjStmt->get_result();

$difference = $session["closing_total"] - $expected;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Balance Viewer</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 text-white p-6">

<div class="max-w-4xl mx-auto">

    <!-- HEADER -->
    <div class="bg-gray-800 p-5 rounded-xl mb-4">
        <h2 class="text-2xl font-bold">
            <?= htmlspecialchars($session["full_name"]); ?>
        </h2>

        <p class="text-gray-400">
            <?= $session["balance_date"]; ?> • <?= htmlspecialchars($session["branch_name"]); ?>
        </p>

        <div class="mt-2">
            Status:
            <span class="px-3 py-1 rounded bg-blue-600 text-sm">
                <?= htmlspecialchars($session["status"]); ?>
            </span>
        </div>
    </div>

    <!-- OPENING -->
    <div class="bg-gray-800 p-5 rounded-xl mb-4">
        <h3 class="text-xl font-bold mb-3">Opening Balances</h3>

        <?php while ($o = $opening->fetch_assoc()): ?>
            <div class="flex justify-between border-b border-gray-700 py-1">
                <span><?= htmlspecialchars($o["name"]); ?></span>
                <span><?= number_format($o["opening_amount"], 2); ?></span>
            </div>
        <?php endwhile; ?>

        <div class="mt-3 font-bold">
            Total: <?= number_format($session["opening_total"], 2); ?>
        </div>
    </div>

    <!-- STATEMENTS -->
    <div class="bg-gray-800 p-5 rounded-xl mb-4">
        <h3 class="text-xl font-bold mb-3">Statements</h3>

        <?php if ($adjustments->num_rows > 0): ?>
            <?php while ($a = $adjustments->fetch_assoc()): ?>
                <div class="p-3 bg-gray-700 rounded mb-2">
                    <div class="flex justify-between">
                        <span><?= htmlspecialchars($a["platform_name"]); ?></span>
                        <span class="<?= $a["type"] == "incoming" ? "text-green-400" : "text-red-400"; ?>">
                            <?= ucfirst($a["type"]); ?>
                        </span>
                    </div>

                    <div class="text-sm text-gray-300">
                        <?= htmlspecialchars($a["description"]); ?>
                    </div>

                    <div class="text-right font-bold">
                        <?= number_format($a["amount"], 2); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-gray-400">No statements.</p>
        <?php endif; ?>
    </div>

    <!-- CLOSING -->
    <div class="bg-gray-800 p-5 rounded-xl mb-4">
        <h3 class="text-xl font-bold mb-3">Closing Balances</h3>

        <?php while ($c = $closing->fetch_assoc()): ?>
            <div class="flex justify-between border-b border-gray-700 py-1">
                <span><?= htmlspecialchars($c["name"]); ?></span>
                <span><?= number_format($c["closing_amount"], 2); ?></span>
            </div>
        <?php endwhile; ?>

        <div class="mt-3 font-bold">
            Total: <?= number_format($session["closing_total"], 2); ?>
        </div>
    </div>

    <!-- ENGINE SUMMARY -->
    <div class="bg-gray-800 p-5 rounded-xl">
        <h3 class="text-xl font-bold mb-3">Analysis</h3>

        <p>Expected: <?= number_format($expected, 2); ?></p>
        <p>Actual: <?= number_format($session["closing_total"], 2); ?></p>

        <p class="mt-3 text-lg font-bold 
            <?= $difference == 0 ? 'text-green-400' : ($difference > 0 ? 'text-yellow-400' : 'text-red-400'); ?>">
            
            Difference: <?= number_format($difference, 2); ?>
        </p>

        <p class="mt-2">
            <?php
                if ($difference == 0) echo "Balanced";
                elseif ($difference > 0) echo "Overage";
                else echo "Shortage";
            ?>
        </p>
    </div>

    <!-- BACK -->
    <div class="mt-6">
        <a href="dashboard.php" class="text-gray-400 hover:text-white">
            ← Back to Dashboard
        </a>
    </div>

</div>

</body>
</html>