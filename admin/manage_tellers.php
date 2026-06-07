<?php
session_start();
require_once "../config/db.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$sql    = "SELECT * FROM users WHERE role = 'teller' AND is_active = 1";
$params = [];
$types  = '';

if ($search) {
    $sql   .= " AND (username LIKE ? OR full_name LIKE ?)";
    $like   = "%$search%";
    $params = [$like, $like];
    $types  = "ss";
}

$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$tellers    = $stmt->get_result();
$tellerRows = $tellers->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tellers</title>
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
                <h1 class="text-lg sm:text-xl font-bold truncate">Manage Tellers</h1>
                <a href="admin_create_teller.php"
                   class="shrink-0 bg-green-600 hover:bg-green-700 active:bg-green-800
                          px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    + <span class="hidden sm:inline">Create New Teller</span><span class="sm:hidden">New</span>
                </a>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 p-4 sm:p-6 space-y-4">

            <!-- Search bar -->
            <form method="GET" class="flex gap-2">
                <input type="text" name="search"
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search by username or name"
                       class="flex-1 p-3 rounded-lg bg-gray-700 border border-gray-600
                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                              outline-none transition-colors text-white placeholder-gray-400">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                               px-4 py-2 rounded-lg font-medium text-sm transition-colors shrink-0">
                    Search
                </button>
                <?php if ($search): ?>
                <a href="manage_tellers.php"
                   class="bg-gray-700 hover:bg-gray-600 px-3 py-2 rounded-lg text-sm transition-colors flex items-center">
                    ✕
                </a>
                <?php endif; ?>
            </form>

            <!-- Result count -->
            <?php if ($search): ?>
            <p class="text-sm text-gray-400">
                <?= count($tellerRows) ?> result<?= count($tellerRows) != 1 ? 's' : '' ?> for
                "<span class="text-white"><?= htmlspecialchars($search) ?></span>"
            </p>
            <?php endif; ?>

            <!-- Desktop table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full bg-gray-800 rounded-xl text-sm">
                    <thead class="bg-gray-700 text-gray-300 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="p-3 text-left">Username</th>
                            <th class="p-3 text-left">Full Name</th>
                            <th class="p-3 text-center">Profile</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tellerRows)): ?>
                            <?php foreach ($tellerRows as $teller): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700/40 transition-colors">
                                <td class="p-3 font-medium"><?= htmlspecialchars($teller['username']) ?></td>
                                <td class="p-3 text-gray-300"><?= htmlspecialchars($teller['full_name'] ?? '—') ?></td>
                                <td class="p-3 text-center">
                                    <?php if ($teller['profile_completed']): ?>
                                        <span class="bg-green-600/30 text-green-400 border border-green-600 px-2.5 py-1 rounded-full text-xs font-medium">Yes</span>
                                    <?php else: ?>
                                        <span class="bg-yellow-600/30 text-yellow-400 border border-yellow-600 px-2.5 py-1 rounded-full text-xs font-medium">No</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="admin_edit_teller.php?id=<?= $teller['id'] ?>"
                                           class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                                                  px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                            Edit
                                        </a>
                                        <a href="admin_delete_teller.php?id=<?= $teller['id'] ?>"
                                           onclick="return confirm('Are you sure you want to delete this teller?');"
                                           class="bg-red-600 hover:bg-red-700 active:bg-red-800
                                                  px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                            Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center p-8 text-gray-400">No tellers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="md:hidden space-y-3">
                <?php if (!empty($tellerRows)): ?>
                    <?php foreach ($tellerRows as $teller): ?>
                    <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 space-y-3">
                        <!-- Name row -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold"><?= htmlspecialchars($teller['username']) ?></p>
                                <p class="text-gray-400 text-sm mt-0.5"><?= htmlspecialchars($teller['full_name'] ?? '—') ?></p>
                            </div>
                            <?php if ($teller['profile_completed']): ?>
                                <span class="bg-green-600/30 text-green-400 border border-green-600 px-2.5 py-1 rounded-full text-xs font-medium shrink-0">Profile Done</span>
                            <?php else: ?>
                                <span class="bg-yellow-600/30 text-yellow-400 border border-yellow-600 px-2.5 py-1 rounded-full text-xs font-medium shrink-0">Incomplete</span>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <a href="admin_edit_teller.php?id=<?= $teller['id'] ?>"
                               class="flex-1 bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                                      text-center py-2 rounded-lg text-sm font-medium transition-colors">
                                Edit
                            </a>
                            <a href="admin_delete_teller.php?id=<?= $teller['id'] ?>"
                               onclick="return confirm('Are you sure you want to delete this teller?');"
                               class="flex-1 bg-red-600 hover:bg-red-700 active:bg-red-800
                                      text-center py-2 rounded-lg text-sm font-medium transition-colors">
                                Delete
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-gray-800 rounded-xl p-8 text-center text-gray-400 border border-gray-700">
                        No tellers found.
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /content -->
    </div><!-- /flex-1 -->
</div><!-- /flex h-screen -->

</body>
</html>