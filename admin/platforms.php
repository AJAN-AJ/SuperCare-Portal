<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: ../login.php");
    exit();
}

/* ── Add platform ── */
if (isset($_POST["add_platform"])) {
    $name = trim($_POST["name"]);
    if ($name !== "") {
        $stmt = $conn->prepare("INSERT INTO platforms (name, active) VALUES (?, 1)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
    }
    header("Location: platforms.php");
    exit();
}

/* ── Toggle active/inactive ── */
if (isset($_GET["toggle"])) {
    $id = intval($_GET["toggle"]);
    $stmt = $conn->prepare("UPDATE platforms SET active = 1 - active WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: platforms.php");
    exit();
}

/* ── Fetch platforms ── */
$rows = $conn->query("SELECT * FROM platforms ORDER BY active DESC, name ASC")->fetch_all(MYSQLI_ASSOC);
$activeCount   = count(array_filter($rows, fn($r) => $r["active"] == 1));
$inactiveCount = count($rows) - $activeCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Platforms</title>
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
            <div class="pl-14 sm:pl-6 pr-4 sm:pr-6 py-3 flex items-center justify-between gap-3">
                <h1 class="text-lg sm:text-xl font-bold">Platform Management</h1>
                <!-- Summary badges -->
                <div class="flex items-center gap-2 shrink-0">
                    <span class="bg-green-600/30 text-green-400 border border-green-600
                                 px-2.5 py-1 rounded-full text-xs font-medium">
                        <?= $activeCount ?> active
                    </span>
                    <?php if ($inactiveCount > 0): ?>
                    <span class="bg-red-600/30 text-red-400 border border-red-600
                                 px-2.5 py-1 rounded-full text-xs font-medium">
                        <?= $inactiveCount ?> off
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 p-4 sm:p-6 space-y-5 max-w-2xl mx-auto w-full">

            <!-- Add platform form -->
            <form method="POST" class="flex gap-2">
                <input type="text" name="name" required
                       placeholder="Platform name (e.g. Airtel Money)"
                       class="flex-1 p-3 rounded-lg bg-gray-800 border border-gray-600
                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                              outline-none text-white placeholder-gray-400 transition-colors">
                <button type="submit" name="add_platform"
                        class="bg-green-600 hover:bg-green-700 active:bg-green-800
                               px-4 sm:px-6 py-3 rounded-lg font-medium text-sm transition-colors shrink-0">
                    + Add
                </button>
            </form>

            <!-- Platform list -->
            <?php if (empty($rows)): ?>
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="text-5xl mb-4">🏦</div>
                <p class="text-gray-300 font-semibold text-lg">No platforms yet.</p>
                <p class="text-gray-500 text-sm mt-1">Add your first platform above.</p>
            </div>
            <?php else: ?>

            <!-- Desktop table -->
            <div class="hidden sm:block bg-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-700 text-gray-300 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="p-3 text-left">Platform</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $p): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700/40 transition-colors">
                            <td class="p-3 font-medium"><?= htmlspecialchars($p["name"]) ?></td>
                            <td class="p-3 text-center">
                                <?php if ($p["active"] == 1): ?>
                                    <span class="bg-green-600/30 text-green-400 border border-green-600
                                                 px-2.5 py-1 rounded-full text-xs font-medium">Active</span>
                                <?php else: ?>
                                    <span class="bg-red-600/30 text-red-400 border border-red-600
                                                 px-2.5 py-1 rounded-full text-xs font-medium">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <a href="?toggle=<?= $p["id"] ?>"
                                   class="<?= $p["active"] == 1
                                       ? 'bg-red-600/30 text-red-400 border border-red-600 hover:bg-red-600 hover:text-white'
                                       : 'bg-green-600/30 text-green-400 border border-green-600 hover:bg-green-600 hover:text-white' ?>
                                          px-3 py-1.5 rounded-lg text-xs font-medium transition-colors inline-block">
                                    <?= $p["active"] == 1 ? 'Deactivate' : 'Activate' ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="sm:hidden space-y-3">
                <?php foreach ($rows as $p): ?>
                <div class="bg-gray-800 rounded-xl px-4 py-3 border border-gray-700
                            flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Active indicator dot -->
                        <span class="w-2.5 h-2.5 rounded-full shrink-0
                                     <?= $p["active"] == 1 ? 'bg-green-400' : 'bg-red-400' ?>"></span>
                        <span class="font-medium truncate"><?= htmlspecialchars($p["name"]) ?></span>
                    </div>
                    <a href="?toggle=<?= $p["id"] ?>"
                       class="<?= $p["active"] == 1
                           ? 'bg-red-600/30 text-red-400 border border-red-600 hover:bg-red-600 hover:text-white'
                           : 'bg-green-600/30 text-green-400 border border-green-600 hover:bg-green-600 hover:text-white' ?>
                              px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shrink-0">
                        <?= $p["active"] == 1 ? 'Deactivate' : 'Activate' ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>

        </div><!-- /content -->
    </div><!-- /flex-1 -->
</div><!-- /flex h-screen -->

</body>
</html>

