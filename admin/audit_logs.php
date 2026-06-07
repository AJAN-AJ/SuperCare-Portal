<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$sql = "
    SELECT
        al.*,
        u.full_name,
        u.username
    FROM audit_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>

<html>
<head>
    <title>Audit Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 text-white min-h-screen flex">

<?php include "../includes/admin_sidebar.php"; ?>

<div class="flex-1 overflow-y-auto p-3 sm:p-4 md:p-6 w-full ml-64">

```
<h2 class="text-2xl font-bold mb-6">
    Audit Logs
</h2>

<div class="overflow-x-auto">

    <table class="w-full bg-gray-800 rounded-xl overflow-hidden">

        <thead class="bg-gray-700">
            <tr>
                <th class="p-3 text-left">User</th>
                <th class="p-3 text-left">Action</th>
                <th class="p-3 text-left">Description</th>
                <th class="p-3 text-left">IP Address</th>
                <th class="p-3 text-left">Date</th>
            </tr>
        </thead>

        <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while($row = $result->fetch_assoc()): ?>

                    <tr class="border-b border-gray-700 hover:bg-gray-700">

                        <td class="p-3">
                            <?= htmlspecialchars($row['full_name'] ?: $row['username']); ?>
                            <br>
                            <span class="text-xs text-gray-400">
                                <?= htmlspecialchars($row['username']); ?>
                            </span>
                        </td>

                        <td class="p-3">
                            <?= htmlspecialchars($row['action']); ?>
                        </td>

                        <td class="p-3">
                            <?= htmlspecialchars($row['description']); ?>
                        </td>

                        <td class="p-3">
                            <?= htmlspecialchars($row['ip_address']); ?>
                        </td>

                        <td class="p-3">
                            <?= htmlspecialchars($row['created_at']); ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5" class="text-center p-6 text-gray-400">
                        No audit logs found.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>
```

</div>

</body>
</html>
