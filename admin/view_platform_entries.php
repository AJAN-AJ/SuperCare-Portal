<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get session_id from query string
$session_id = $_GET['session_id'] ?? null;

if (!$session_id) {
    die("Invalid session.");
}

// Fetch session details
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
    die("Balance session not found.");
}

// Fetch platform-level entries
$platformStmt = $conn->prepare("
    SELECT p.name AS platform_name, bpe.opening_amount, bpe.closing_amount
    FROM balance_platform_entries bpe
    JOIN platforms p ON bpe.platform_id = p.id
    WHERE bpe.session_id = ?
");
$platformStmt->bind_param("i", $session_id);
$platformStmt->execute();
$entries = $platformStmt->get_result();
?>

<?php include "../includes/admin_sidebar.php"; ?>

<div class="flex-1 p-6 max-w-4xl mx-auto">

    <h2 class="text-2xl font-bold mb-6">Platform-Level Balances</h2>

    <div class="mb-4">
        <p><strong>Teller:</strong> <?= htmlspecialchars($session['full_name']); ?></p>
        <p><strong>Branch:</strong> <?= htmlspecialchars($session['branch_name']); ?></p>
        <p><strong>Date:</strong> <?= htmlspecialchars($session['balance_date']); ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($session['status']); ?></p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full bg-gray-800 rounded-xl overflow-hidden">
            <thead class="bg-gray-700">
                <tr>
                    <th class="p-3 text-left">Platform</th>
                    <th class="p-3 text-center">Opening Amount</th>
                    <th class="p-3 text-center">Closing Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php while($entry = $entries->fetch_assoc()): ?>
                    <tr class="border-b border-gray-700 hover:bg-gray-750">
                        <td class="p-3"><?= htmlspecialchars($entry['platform_name']); ?></td>
                        <td class="p-3 text-center"><?= number_format($entry['opening_amount'], 2); ?></td>
                        <td class="p-3 text-center"><?= number_format($entry['closing_amount'] ?? 0, 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <a href="view_teller_balances.php?teller_id=<?= $session['user_id']; ?>" 
           class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
           ← Back to Teller Balances
        </a>
    </div>

</div>
