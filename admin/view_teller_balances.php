<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch tellers for the dropdown
$tellersResult = $conn->query("SELECT id, full_name, username FROM users WHERE role='teller' ORDER BY full_name ASC");

$selected_teller_id = $_GET['teller_id'] ?? null;
$sessions = [];

if ($selected_teller_id) {
    $stmt = $conn->prepare("
        SELECT bs.*, b.name AS branch_name
        FROM balance_sessions bs
        JOIN branches b ON bs.branch_id = b.id
        WHERE bs.user_id = ?
        ORDER BY bs.balance_date DESC, bs.created_at DESC
    ");
    $stmt->bind_param("i", $selected_teller_id);
    $stmt->execute();
    $sessions = $stmt->get_result();
}
?>

<?php include "../includes/admin_sidebar.php"; ?>

<div class="flex-1 p-6 max-w-4xl mx-auto">

    <h2 class="text-2xl font-bold mb-6">Teller Balances</h2>

    <form method="GET" class="mb-6 flex gap-3 items-center">
        <select name="teller_id" class="p-3 rounded bg-gray-700 border border-gray-600">
            <option value="">-- Select Teller --</option>
            <?php while ($teller = $tellersResult->fetch_assoc()): ?>
                <option value="<?= $teller['id']; ?>" <?= ($selected_teller_id == $teller['id']) ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($teller['full_name']) . " ({$teller['username']})"; ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">View</button>
    </form>

    <?php if ($selected_teller_id && $sessions->num_rows > 0): ?>

        <div class="overflow-x-auto">
            <table class="w-full bg-gray-800 rounded-xl overflow-hidden">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="p-3 text-left">Date</th>
                        <th class="p-3">Opening</th>
                        <th class="p-3">Closing</th>
                        <th class="p-3">Difference</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $sessions->fetch_assoc()): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-750">
                            <td class="p-3"><?= htmlspecialchars($row['balance_date']); ?></td>
                            <td class="p-3 text-center"><?= number_format($row['opening_total'],2); ?></td>
                            <td class="p-3 text-center"><?= number_format($row['closing_total'],2); ?></td>
                            <td class="p-3 text-center <?= $row['difference'] < 0 ? 'text-red-400' : 'text-green-400'; ?>">
                                <?= number_format($row['difference'],2); ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php if($row['status'] == 'approved_closing'): ?>
                                    <span class="bg-green-600 px-3 py-1 rounded-full text-sm">Approved Closing</span>
                                <?php elseif($row['status'] == 'pending_approval_closing'): ?>
                                    <span class="bg-yellow-600 px-3 py-1 rounded-full text-sm">Pending Closing</span>
                                <?php elseif($row['status'] == 'approved_opening'): ?>
                                    <span class="bg-blue-600 px-3 py-1 rounded-full text-sm">Approved Opening</span>
                                <?php else: ?>
                                    <span class="bg-gray-600 px-3 py-1 rounded-full text-sm"><?= htmlspecialchars($row['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <a href="view_platform_entries.php?session_id=<?= $row['id']; ?>"
                                   class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded text-sm">
                                    View Entries
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif($selected_teller_id): ?>
        <p class="text-gray-400 mt-4">No balance sessions found for this teller.</p>
    <?php endif; ?>

</div>
