<?php
session_start();
require_once "../config/db.php";
require_once "../includes/audit.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Join with employee_profiles to get richer data
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.full_name,
           ep.first_name, ep.surname, ep.phone_1, ep.physical_address,
           ep.national_id, ep.date_of_birth, ep.profile_photo,
           ep.oath_signed, ep.current_step
    FROM users u
    LEFT JOIN employee_profiles ep ON ep.user_id = u.id
    WHERE u.role = 'teller' AND u.profile_completed = 1 AND u.approved = 0
    ORDER BY u.id DESC
");
$stmt->execute();
$profileRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Profiles</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) { input, select { font-size: 16px !important; } }
    </style>
</head>
<body class="bg-gray-900 text-white">

<div class="flex h-screen overflow-hidden">
    <?php include "../includes/admin_sidebar.php"; ?>

    <div class="flex-1 flex flex-col overflow-y-auto">

        <!-- Sticky header -->
        <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
            <div class="pl-14 sm:pl-6 pr-4 sm:pr-6 py-3 flex items-center justify-between gap-3">
                <h1 class="text-lg sm:text-xl font-bold">Pending Teller Profiles</h1>
                <?php if (!empty($profileRows)): ?>
                <span class="shrink-0 bg-yellow-600/30 text-yellow-400 border border-yellow-600
                             px-2.5 py-1 rounded-full text-xs font-medium">
                    <?= count($profileRows) ?> pending
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex-1 p-4 sm:p-6">

            <?php if (empty($profileRows)): ?>
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="text-5xl mb-4">✅</div>
                <p class="text-gray-300 font-semibold text-lg">All caught up!</p>
                <p class="text-gray-500 text-sm mt-1">No pending profiles to approve.</p>
            </div>
            <?php else: ?>

            <!-- Desktop table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full bg-gray-800 rounded-xl text-sm">
                    <thead class="bg-gray-700 text-gray-300 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="p-3 text-left">Teller</th>
                            <th class="p-3 text-left">National ID</th>
                            <th class="p-3 text-left">Phone</th>
                            <th class="p-3 text-left">Address</th>
                            <th class="p-3 text-center">Oath</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profileRows as $row): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700/40 transition-colors">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <?php if ($row['profile_photo']): ?>
                                    <img src="../<?= htmlspecialchars($row['profile_photo']) ?>"
                                         class="w-9 h-9 rounded-lg object-cover shrink-0">
                                    <?php else: ?>
                                    <div class="w-9 h-9 rounded-lg bg-blue-700 flex items-center justify-center font-bold text-sm shrink-0">
                                        <?= strtoupper(substr($row['full_name'], 0, 1)) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($row['full_name']) ?></p>
                                        <p class="text-gray-400 text-xs">@<?= htmlspecialchars($row['username']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 text-gray-300"><?= htmlspecialchars($row['national_id'] ?? '—') ?></td>
                            <td class="p-3 text-gray-300 whitespace-nowrap"><?= htmlspecialchars($row['phone_1'] ?? '—') ?></td>
                            <td class="p-3 text-gray-300 max-w-[140px] truncate"><?= htmlspecialchars($row['physical_address'] ?? '—') ?></td>
                            <td class="p-3 text-center">
                                <?php if ($row['oath_signed']): ?>
                                <span class="bg-green-600/30 text-green-400 border border-green-600 px-2 py-0.5 rounded-full text-xs">Signed</span>
                                <?php else: ?>
                                <span class="bg-red-600/30 text-red-400 border border-red-600 px-2 py-0.5 rounded-full text-xs">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="view_teller.php?id=<?= $row['id'] ?>"
                                       class="bg-gray-600 hover:bg-gray-500 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                        View
                                    </a>
                                    <a href="approve_profile_action.php?id=<?= $row['id'] ?>"
                                       onclick="return confirm('Approve this teller profile?')"
                                       class="bg-green-600 hover:bg-green-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                        ✓ Approve
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="md:hidden space-y-3">
                <?php foreach ($profileRows as $row): ?>
                <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 space-y-3">

                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <?php if ($row['profile_photo']): ?>
                            <img src="../<?= htmlspecialchars($row['profile_photo']) ?>"
                                 class="w-12 h-12 rounded-xl object-cover shrink-0">
                            <?php else: ?>
                            <div class="w-12 h-12 rounded-xl bg-blue-700 flex items-center justify-center font-bold text-lg shrink-0">
                                <?= strtoupper(substr($row['full_name'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="font-semibold"><?= htmlspecialchars($row['full_name']) ?></p>
                                <p class="text-gray-400 text-xs">@<?= htmlspecialchars($row['username']) ?></p>
                            </div>
                        </div>
                        <?php if ($row['oath_signed']): ?>
                        <span class="bg-green-600/30 text-green-400 border border-green-600 px-2 py-0.5 rounded-full text-xs shrink-0">Oath ✓</span>
                        <?php else: ?>
                        <span class="bg-red-600/30 text-red-400 border border-red-600 px-2 py-0.5 rounded-full text-xs shrink-0">No Oath</span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <p class="text-gray-400 text-xs mb-0.5">National ID</p>
                            <p><?= htmlspecialchars($row['national_id'] ?? '—') ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs mb-0.5">Phone</p>
                            <p><?= htmlspecialchars($row['phone_1'] ?? '—') ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-gray-400 text-xs mb-0.5">Address</p>
                            <p><?= htmlspecialchars($row['physical_address'] ?? '—') ?></p>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <a href="view_teller.php?id=<?= $row['id'] ?>"
                           class="flex-1 text-center bg-gray-700 hover:bg-gray-600 py-2.5 rounded-lg text-sm font-medium transition-colors">
                            View Profile
                        </a>
                        <a href="approve_profile_action.php?id=<?= $row['id'] ?>"
                           onclick="return confirm('Approve this teller profile?')"
                           class="flex-1 text-center bg-green-600 hover:bg-green-700 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                            ✓ Approve
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>