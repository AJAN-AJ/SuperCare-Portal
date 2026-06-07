<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION["user_id"];

/* ── Handle Approve / Reject ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $session_id = intval($_POST["session_id"]);
    $action     = $_POST["action"];
    $status     = $action === "approve" ? "approved_opening" : "draft";

    $stmt = $conn->prepare("
        UPDATE balance_sessions
        SET status=?, approved_by=?, approved_at=NOW()
        WHERE id=?
    ");
    $stmt->bind_param("sii", $status, $admin_id, $session_id);
    $stmt->execute();
}

/* ── Fetch pending opening approvals ── */
$result = $conn->query("
    SELECT bs.*, u.full_name, b.name AS branch_name
    FROM balance_sessions bs
    JOIN users u ON bs.user_id = u.id
    JOIN branches b ON bs.branch_id = b.id
    WHERE bs.status = 'pending_approval_opening'
    ORDER BY bs.balance_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opening Approvals</title>
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

        <!-- Sticky header -->
        <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
            <div class="px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
                <h1 class="text-lg sm:text-xl font-bold">Opening Approvals</h1>
                <?php if ($result->num_rows > 0): ?>
                <span class="shrink-0 bg-orange-600/30 text-orange-400 border border-orange-600
                             px-2.5 py-1 rounded-full text-xs font-medium">
                    <?= $result->num_rows ?> pending
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 p-4 sm:p-6">

            <?php if ($result->num_rows == 0): ?>

            <!-- Empty state -->
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="text-5xl mb-4">✅</div>
                <p class="text-gray-300 font-semibold text-lg">All caught up!</p>
                <p class="text-gray-500 text-sm mt-1">No pending opening approvals.</p>
            </div>

            <?php else: ?>

            <div class="space-y-4 max-w-2xl mx-auto">
                <?php while ($row = $result->fetch_assoc()):
                    $session_id = $row["id"];

                    /* Platform breakdown */
                    $details = $conn->prepare("
                        SELECT p.name, bpe.opening_amount
                        FROM balance_platform_entries bpe
                        JOIN platforms p ON bpe.platform_id = p.id
                        WHERE bpe.session_id = ?
                    ");
                    $details->bind_param("i", $session_id);
                    $details->execute();
                    $detailRows = $details->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                <div class="bg-gray-800 rounded-2xl shadow-lg overflow-hidden">

                    <!-- Card header -->
                    <div class="px-4 sm:px-6 pt-5 pb-4 border-b border-gray-700">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-bold text-base sm:text-lg"><?= htmlspecialchars($row["full_name"]) ?></p>
                                <p class="text-gray-400 text-sm mt-0.5"><?= htmlspecialchars($row["branch_name"]) ?></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs text-gray-400">Date</p>
                                <p class="text-sm font-medium"><?= $row["balance_date"] ?></p>
                            </div>
                        </div>

                        <!-- Opening total -->
                        <div class="mt-3 bg-blue-900/40 border border-blue-700 rounded-xl px-4 py-3 flex items-center justify-between">
                            <span class="text-blue-300 text-sm font-medium">Opening Total</span>
                            <span class="text-white text-xl font-bold"><?= number_format($row["opening_total"], 2) ?></span>
                        </div>
                    </div>

                    <!-- Platform breakdown -->
                    <?php if (!empty($detailRows)): ?>
                    <div class="px-4 sm:px-6 py-4 border-b border-gray-700">
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Platform Breakdown</h4>
                        <div class="space-y-2">
                            <?php foreach ($detailRows as $detail): ?>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-300"><?= htmlspecialchars($detail["name"]) ?></span>
                                <span class="font-medium tabular-nums"><?= number_format($detail["opening_amount"], 2) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="px-4 sm:px-6 py-4">
                        <form method="POST" class="flex gap-3">
                            <input type="hidden" name="session_id" value="<?= $row["id"] ?>">
                            <button type="submit" name="action" value="approve"
                                    onclick="return confirm('Approve this opening balance?')"
                                    class="flex-1 bg-green-600 hover:bg-green-700 active:bg-green-800
                                           py-3 rounded-xl font-semibold text-sm transition-colors">
                                ✓ Approve
                            </button>
                            <button type="submit" name="action" value="reject"
                                    onclick="return confirm('Send this back for correction?')"
                                    class="flex-1 bg-red-600 hover:bg-red-700 active:bg-red-800
                                           py-3 rounded-xl font-semibold text-sm transition-colors">
                                ✕ Reject
                            </button>
                        </form>
                    </div>

                </div>
                <?php endwhile; ?>
            </div>

            <?php endif; ?>

        </div><!-- /content -->
    </div><!-- /flex-1 -->
</div><!-- /flex h-screen -->

</body>
</html>