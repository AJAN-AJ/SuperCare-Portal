<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$teller_id = intval($_GET['id'] ?? 0);
if (!$teller_id) die("Invalid ID.");

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE id=? AND role='teller'");
$stmt->bind_param("i", $teller_id);
$stmt->execute();
$teller = $stmt->get_result()->fetch_assoc();
if (!$teller) die("Teller not found.");

// Fetch employee profile
$epStmt = $conn->prepare("SELECT * FROM employee_profiles WHERE user_id=?");
$epStmt->bind_param("i", $teller_id);
$epStmt->execute();
$ep = $epStmt->get_result()->fetch_assoc();

// Handle Part C admin verification POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_verification'])) {
    $ver_address    = isset($_POST['verified_address'])   ? 1 : 0;
    $ver_contact    = isset($_POST['verified_contact'])   ? 1 : 0;
    $ver_referee    = isset($_POST['verified_referee'])   ? 1 : 0;
    $traceable      = isset($_POST['employee_traceable']) ? 1 : 0;
    $ids_attached   = isset($_POST['ids_attached'])       ? 1 : 0;
    $date_of_emp    = $_POST['date_of_employment'];

    $upd = $conn->prepare("
        UPDATE employee_profiles SET
            verified_address=?, verified_contact=?, verified_referee=?,
            employee_traceable=?, ids_attached=?, date_of_employment=?
        WHERE user_id=?
    ");
    $upd->bind_param("iiiiisi",
        $ver_address, $ver_contact, $ver_referee,
        $traceable, $ids_attached, $date_of_emp, $teller_id
    );
    $upd->execute();

    // Re-fetch
    $epStmt->execute();
    $ep = $epStmt->get_result()->fetch_assoc();
    $saved = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Teller — <?= htmlspecialchars($teller['full_name']) ?></title>
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
            <div class="pl-14 sm:pl-6 pr-4 sm:pr-6 py-3 flex items-center gap-3">
                <a href="manage_tellers.php"
                   class="flex items-center gap-1 bg-gray-700 hover:bg-gray-600 px-3 py-2 rounded-lg text-sm transition-colors shrink-0">
                    ← <span class="hidden sm:inline">Tellers</span>
                </a>
                <div>
                    <h1 class="text-lg sm:text-xl font-bold"><?= htmlspecialchars($teller['full_name']) ?></h1>
                    <p class="text-xs text-gray-400">@<?= htmlspecialchars($teller['username']) ?></p>
                </div>
                <div class="ml-auto">
                    <a href="admin_edit_teller.php?id=<?= $teller_id ?>"
                       class="bg-blue-600 hover:bg-blue-700 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        Edit
                    </a>
                </div>
            </div>
        </div>

        <div class="flex-1 p-4 sm:p-6 space-y-5 max-w-3xl mx-auto w-full">

            <?php if (isset($saved)): ?>
            <div class="flex gap-3 p-4 bg-green-700/40 border border-green-600 rounded-xl text-green-300 text-sm">
                ✓ Verification details saved.
            </div>
            <?php endif; ?>

            <?php if (!$ep): ?>
            <div class="bg-gray-800 rounded-2xl p-8 text-center border border-gray-700">
                <p class="text-5xl mb-3">📋</p>
                <p class="text-gray-300 font-semibold">No profile form submitted yet.</p>
                <p class="text-gray-500 text-sm mt-1">The teller has not completed their profile setup.</p>
            </div>
            <?php else: ?>

            <!-- ── Profile photo + summary ── -->
            <div class="bg-gray-800 rounded-2xl p-5 flex flex-col sm:flex-row gap-5 items-start">
                <div class="shrink-0">
                    <?php if ($ep['profile_photo']): ?>
                    <img src="../<?= htmlspecialchars($ep['profile_photo']) ?>"
                         class="w-24 h-24 rounded-2xl object-cover border-2 border-gray-600">
                    <?php else: ?>
                    <div class="w-24 h-24 rounded-2xl bg-blue-700 flex items-center justify-center text-3xl font-bold">
                        <?= strtoupper(substr($teller['full_name'], 0, 1)) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="space-y-1">
                    <p class="text-xl font-bold"><?= htmlspecialchars($teller['full_name']) ?></p>
                    <p class="text-gray-400 text-sm">@<?= htmlspecialchars($teller['username']) ?></p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="<?= $teller['approved'] ? 'bg-green-600/30 text-green-400 border-green-600' : 'bg-yellow-600/30 text-yellow-400 border-yellow-600' ?> border px-2.5 py-0.5 rounded-full text-xs font-medium">
                            <?= $teller['approved'] ? 'Approved' : 'Pending Approval' ?>
                        </span>
                        <span class="<?= $ep['oath_signed'] ? 'bg-blue-600/30 text-blue-400 border-blue-600' : 'bg-gray-600/30 text-gray-400 border-gray-600' ?> border px-2.5 py-0.5 rounded-full text-xs font-medium">
                            <?= $ep['oath_signed'] ? '✓ Oath Signed' : 'Oath Pending' ?>
                        </span>
                    </div>
                    <?php if ($ep['oath_signed_at']): ?>
                    <p class="text-gray-500 text-xs mt-1">Submitted: <?= date("d M Y H:i", strtotime($ep['submitted_at'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Part A: Personal Details ── -->
            <div class="bg-gray-800 rounded-2xl p-5 space-y-4">
                <h2 class="font-bold text-blue-400 text-sm uppercase tracking-wide">Part A — Personal Details</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                    <?php
                    $partA = [
                        ['First Name',      $ep['first_name']],
                        ['Middle Name',     $ep['middle_name']],
                        ['Surname',         $ep['surname']],
                        ['Date of Birth',   $ep['date_of_birth'] ? date("d M Y", strtotime($ep['date_of_birth'])) : '—'],
                        ['National ID',     $ep['national_id']],
                        ['Passport No',     $ep['passport_no'] ?: '—'],
                        ['Original Village',$ep['original_village'] ?: '—'],
                        ['Phone 1',         $ep['phone_1']],
                        ['Phone 2',         $ep['phone_2'] ?: '—'],
                        ['Physical Address', $ep['physical_address']],
                    ];
                    foreach ($partA as [$label, $value]):
                        $span = in_array($label, ['Physical Address']) ? 'col-span-2 sm:col-span-3' : '';
                    ?>
                    <div class="bg-gray-700/50 rounded-xl p-3 <?= $span ?>">
                        <p class="text-gray-400 text-xs mb-0.5"><?= $label ?></p>
                        <p class="font-medium"><?= htmlspecialchars($value ?: '—') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- National ID image -->
                <?php if ($ep['national_id_image']): ?>
                <div>
                    <p class="text-gray-400 text-xs mb-2">National ID Image</p>
                    <a href="../<?= htmlspecialchars($ep['national_id_image']) ?>" target="_blank">
                        <img src="../<?= htmlspecialchars($ep['national_id_image']) ?>"
                             class="h-32 rounded-xl object-cover border border-gray-600 hover:opacity-80 transition-opacity">
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Part B: Referee ── -->
            <div class="bg-gray-800 rounded-2xl p-5 space-y-4">
                <h2 class="font-bold text-blue-400 text-sm uppercase tracking-wide">Part B — Referee Details</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                    <?php
                    $partB = [
                        ['First Name',      $ep['ref_first_name']],
                        ['Middle Name',     $ep['ref_middle_name']],
                        ['Surname',         $ep['ref_surname']],
                        ['National ID',     $ep['ref_national_id']],
                        ['Passport No',     $ep['ref_passport_no'] ?: '—'],
                        ['Village',         $ep['ref_village'] ?: '—'],
                        ['Phone(s)',        $ep['ref_phone']],
                        ['Address',         $ep['ref_address']],
                    ];
                    foreach ($partB as [$label, $value]):
                        $span = in_array($label, ['Address']) ? 'col-span-2 sm:col-span-3' : '';
                    ?>
                    <div class="bg-gray-700/50 rounded-xl p-3 <?= $span ?>">
                        <p class="text-gray-400 text-xs mb-0.5"><?= $label ?></p>
                        <p class="font-medium"><?= htmlspecialchars($value ?: '—') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Part C: Admin Verification ── -->
            <div class="bg-gray-800 rounded-2xl p-5 space-y-4">
                <h2 class="font-bold text-blue-400 text-sm uppercase tracking-wide">Part C — For Official Use</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="save_verification" value="1">

                    <div class="space-y-3">
                        <?php
                        $checks = [
                            ['verified_address',   'Verified Physical Address',   $ep['verified_address']],
                            ['verified_contact',   'Verified Contact Details',    $ep['verified_contact']],
                            ['verified_referee',   'Verified Identity of Referee',$ep['verified_referee']],
                            ['employee_traceable', 'Employee Traceable',          $ep['employee_traceable']],
                            ['ids_attached',       'Employee has attached copies of IDs', $ep['ids_attached']],
                        ];
                        foreach ($checks as [$name, $label, $checked]):
                        ?>
                        <label class="flex items-center justify-between bg-gray-700/50 border border-gray-600
                                      rounded-xl px-4 py-3 cursor-pointer hover:bg-gray-700 transition-colors">
                            <span class="text-sm font-medium"><?= $label ?></span>
                            <div class="flex items-center gap-3">
                                <span class="text-xs <?= $checked ? 'text-green-400' : 'text-gray-500' ?>">
                                    <?= $checked ? 'YES' : 'NO' ?>
                                </span>
                                <input type="checkbox" name="<?= $name ?>" value="1"
                                       <?= $checked ? 'checked' : '' ?>
                                       class="w-5 h-5 rounded accent-blue-500 cursor-pointer">
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-gray-300">Date of Employment</label>
                        <input type="date" name="date_of_employment"
                               value="<?= htmlspecialchars($ep['date_of_employment'] ?? '') ?>"
                               class="w-full sm:w-56 p-3 rounded-lg bg-gray-700 border border-gray-600
                                      focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                    </div>

                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                                   px-6 py-2.5 rounded-xl text-sm font-semibold transition-colors">
                        Save Verification
                    </button>
                </form>
            </div>

            <!-- ── Oath ── -->
            <?php if ($ep['oath_signed']): ?>
            <div class="bg-gray-800 rounded-2xl p-5 space-y-2 border border-blue-800">
                <h2 class="font-bold text-blue-400 text-sm uppercase tracking-wide">Oath of Confidentiality</h2>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-gray-700/50 rounded-xl p-3">
                        <p class="text-gray-400 text-xs mb-0.5">Signed By</p>
                        <p class="font-medium"><?= htmlspecialchars($ep['oath_signed_name']) ?></p>
                    </div>
                    <div class="bg-gray-700/50 rounded-xl p-3">
                        <p class="text-gray-400 text-xs mb-0.5">Signed At</p>
                        <p class="font-medium"><?= date("d M Y H:i", strtotime($ep['oath_signed_at'])) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; // ep exists ?>

        </div>
    </div>
</div>

</body>
</html>