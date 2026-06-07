<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$search = "%" . ($_GET['search'] ?? '') . "%";

$sql = "
    SELECT u.*, b.name AS branch_name 
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.role = 'teller' 
      AND (u.username LIKE ? OR u.full_name LIKE ? OR b.name LIKE ?)
    ORDER BY u.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $search, $search, $search);
$stmt->execute();
$tellers = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Tellers</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex">

<?php include "../includes/admin_sidebar.php"; ?>

<div class="flex-1 overflow-y-auto p-3 sm:p-4 md:p-6 w-full ml-64">

    <h2 class="text-2xl font-bold mb-6">Manage Tellers</h2>

    <form method="GET" class="mb-6 flex gap-2">
        <input type="text" name="search" placeholder="Search by username, name or branch"
               class="flex-1 p-2 rounded bg-gray-700 border border-gray-600 text-white">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 rounded">Search</button>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full bg-gray-800 rounded-xl overflow-hidden">
            <thead class="bg-gray-700">
                <tr>
                    <th class="p-3">Username</th>
                    <th class="p-3">Full Name</th>
                    <th class="p-3">Branch</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $tellers->fetch_assoc()): ?>
                <tr class="border-b border-gray-700 hover:bg-gray-750">
                    <td class="p-3"><?= htmlspecialchars($row['username']); ?></td>
                    <td class="p-3"><?= htmlspecialchars($row['full_name']); ?></td>
                    <td class="p-3"><?= htmlspecialchars($row['branch_name']); ?></td>
                    <td class="p-3">
                        <?= $row['approved'] ? 
                            '<span class="bg-green-600 px-3 py-1 rounded-full text-sm">Approved</span>' :
                            '<span class="bg-yellow-600 px-3 py-1 rounded-full text-sm">Pending</span>'; ?>
                    </td>
                    <td class="p-3 flex gap-2">
                        <a href="edit_teller.php?id=<?= $row['id']; ?>" class="bg-blue-600 px-3 py-1 rounded text-sm">Edit</a>
                        <a href="delete_teller.php?id=<?= $row['id']; ?>" class="bg-red-600 px-3 py-1 rounded text-sm"
                           onclick="return confirm('Are you sure you want to delete this teller?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
