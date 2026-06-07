<?php
session_start();
require_once "../config/db.php";
require_once "../includes/session_guard.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teller') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Redirect if profile already fully completed
$chk = $conn->prepare("SELECT profile_completed FROM users WHERE id=?");
$chk->bind_param("i", $user_id);
$chk->execute();
$chkUser = $chk->get_result()->fetch_assoc();
if ($chkUser['profile_completed'] == 1) {
    header("Location: dashboard.php");
    exit();
}

// Ensure upload directories exist
foreach (['../uploads/profiles', '../uploads/ids'] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// Load or create employee_profiles row
$epStmt = $conn->prepare("SELECT * FROM employee_profiles WHERE user_id=?");
$epStmt->bind_param("i", $user_id);
$epStmt->execute();
$ep = $epStmt->get_result()->fetch_assoc();

if (!$ep) {
    $ins = $conn->prepare("INSERT INTO employee_profiles (user_id, current_step) VALUES (?, 1)");
    $ins->bind_param("i", $user_id);
    $ins->execute();
    $epStmt->execute();
    $ep = $epStmt->get_result()->fetch_assoc();
}

$currentStep = $ep['current_step'] ?? 1;
$error   = '';
$success = '';

// ─── Helper: move uploaded image ───────────────────────────────────────────
function handleUpload($fileKey, $folder, $user_id) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
    $file    = $_FILES[$fileKey];
    $allowed = ['image/jpeg','image/png','image/jpg','image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null; // 5MB max
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $user_id . '_' . $fileKey . '_' . time() . '.' . $ext;
    $dest     = "../uploads/{$folder}/{$filename}";
    if (move_uploaded_file($file['tmp_name'], $dest)) return "uploads/{$folder}/{$filename}";
    return null;
}

// ─── POST HANDLING ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = intval($_POST['step'] ?? 1);

    // ── STEP 1: Personal Details ──
    if ($step === 1) {
        $first_name       = trim($_POST['first_name']);
        $middle_name      = trim($_POST['middle_name']);
        $surname          = trim($_POST['surname']);
        $date_of_birth    = $_POST['date_of_birth'];
        $physical_address = trim($_POST['physical_address']);
        $national_id      = trim($_POST['national_id']);
        $passport_no      = trim($_POST['passport_no']);
        $original_village = trim($_POST['original_village']);
        $phone_1          = trim($_POST['phone_1']);
        $phone_2          = trim($_POST['phone_2']);

        if (!$first_name || !$surname || !$date_of_birth || !$physical_address || !$national_id || !$phone_1) {
            $error = "First name, surname, date of birth, address, national ID and primary phone are required.";
        } else {
            // Handle image uploads
            $profile_photo    = handleUpload('profile_photo',    'profiles', $user_id) ?? $ep['profile_photo'];
            $national_id_image = handleUpload('national_id_image', 'ids',     $user_id) ?? $ep['national_id_image'];

            // Build full_name for users table
            $full_name = trim("$first_name $middle_name $surname");

            $upd = $conn->prepare("
                UPDATE employee_profiles SET
                    first_name=?, middle_name=?, surname=?,
                    date_of_birth=?, physical_address=?, national_id=?,
                    passport_no=?, original_village=?, phone_1=?, phone_2=?,
                    profile_photo=?, national_id_image=?, current_step=2
                WHERE user_id=?
            ");
            $upd->bind_param(
                "ssssssssssssi",
                $first_name, $middle_name, $surname,
                $date_of_birth, $physical_address, $national_id,
                $passport_no, $original_village, $phone_1, $phone_2,
                $profile_photo, $national_id_image, $user_id
            );

            if ($upd->execute()) {
                // Also sync key fields to users table
                $syncU = $conn->prepare("UPDATE users SET full_name=?, date_of_birth=?, phone=?, address=? WHERE id=?");
                $syncU->bind_param("ssssi", $full_name, $date_of_birth, $phone_1, $physical_address, $user_id);
                $syncU->execute();

                $epStmt->execute();
                $ep          = $epStmt->get_result()->fetch_assoc();
                $currentStep = 2;
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }

    // ── STEP 2: Referee Details ──
    elseif ($step === 2) {
        $ref_first_name = trim($_POST['ref_first_name']);
        $ref_middle_name = trim($_POST['ref_middle_name']);
        $ref_surname    = trim($_POST['ref_surname']);
        $ref_address    = trim($_POST['ref_address']);
        $ref_national_id = trim($_POST['ref_national_id']);
        $ref_passport_no = trim($_POST['ref_passport_no']);
        $ref_village    = trim($_POST['ref_village']);
        $ref_phone      = trim($_POST['ref_phone']);

        if (!$ref_first_name || !$ref_surname || !$ref_phone) {
            $error = "Referee first name, surname and phone are required.";
        } else {
            $upd = $conn->prepare("
                UPDATE employee_profiles SET
                    ref_first_name=?, ref_middle_name=?, ref_surname=?,
                    ref_address=?, ref_national_id=?, ref_passport_no=?,
                    ref_village=?, ref_phone=?, current_step=3
                WHERE user_id=?
            ");
            $upd->bind_param(
                "ssssssssi",
                $ref_first_name, $ref_middle_name, $ref_surname,
                $ref_address, $ref_national_id, $ref_passport_no,
                $ref_village, $ref_phone, $user_id
            );
            if ($upd->execute()) {
                $epStmt->execute();
                $ep          = $epStmt->get_result()->fetch_assoc();
                $currentStep = 3;
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }

    // ── STEP 3: Oath ──
    elseif ($step === 3) {
        $oath_name    = trim($_POST['oath_name']);
        $oath_agreed  = isset($_POST['oath_agreed']) ? 1 : 0;
        $oath_date    = $_POST['oath_date'];

        if (!$oath_name || !$oath_agreed) {
            $error = "You must enter your full name and agree to the oath to proceed.";
        } else {
            $oath_at = date('Y-m-d H:i:s');
            $upd = $conn->prepare("
                UPDATE employee_profiles SET
                    oath_signed=1, oath_signed_at=?, oath_signed_name=?,
                    submitted_at=?, current_step=3
                WHERE user_id=?
            ");
            $upd->bind_param("sssi", $oath_at, $oath_name, $oath_at, $user_id);

            if ($upd->execute()) {
                // Mark profile as completed in users table
                $done = $conn->prepare("UPDATE users SET profile_completed=1 WHERE id=?");
                $done->bind_param("i", $user_id);
                $done->execute();
                header("Location: profile_pending.php");
                exit();
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

// Go back a step
if (isset($_GET['back'])) {
    $backTo = max(1, intval($_GET['back']));
    $upd = $conn->prepare("UPDATE employee_profiles SET current_step=? WHERE user_id=?");
    $upd->bind_param("ii", $backTo, $user_id);
    $upd->execute();
    header("Location: profile_setup.php");
    exit();
}

// Re-fetch latest ep
$epStmt->execute();
$ep          = $epStmt->get_result()->fetch_assoc();
$currentStep = $ep['current_step'] ?? 1;

$stepTitles = [
    1 => "Personal Details",
    2 => "Referee Details",
    3 => "Oath of Confidentiality",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Setup — Step <?= $currentStep ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 640px) { input, select, textarea { font-size: 16px !important; } }
        .step-done  { background:#16a34a; color:#fff; }
        .step-active{ background:#3b82f6; color:#fff; }
        .step-todo  { background:#374151; color:#9ca3af; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<!-- Top bar -->
<div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-10">
    <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="../logoicon.png" alt="SCS" class="w-8 h-8 object-contain">
            <span class="font-bold text-sm sm:text-base">SuperCare Solutions</span>
        </div>
        <span class="text-gray-400 text-sm">Step <?= $currentStep ?> of 3</span>
    </div>
</div>

<div class="max-w-2xl mx-auto px-4 py-6 space-y-6">

    <!-- Progress steps -->
    <div class="flex items-center gap-2">
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="flex items-center <?= $i < 3 ? 'flex-1' : '' ?>">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0
                    <?= $i < $currentStep ? 'step-done' : ($i == $currentStep ? 'step-active' : 'step-todo') ?>">
                    <?= $i < $currentStep ? '✓' : $i ?>
                </div>
                <span class="text-xs hidden sm:inline
                    <?= $i == $currentStep ? 'text-white font-medium' : 'text-gray-500' ?>">
                    <?= $stepTitles[$i] ?>
                </span>
            </div>
            <?php if ($i < 3): ?>
            <div class="flex-1 h-0.5 mx-2 <?= $i < $currentStep ? 'bg-green-600' : 'bg-gray-700' ?>"></div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Step heading -->
    <div>
        <h1 class="text-2xl font-bold"><?= $stepTitles[$currentStep] ?></h1>
        <p class="text-gray-400 text-sm mt-1">
            <?php if ($currentStep == 1): ?>
                Fill in your personal information exactly as it appears on your ID documents.
            <?php elseif ($currentStep == 2): ?>
                Provide details of a referee (parent, guardian or trusted person).
            <?php else: ?>
                Read the oath carefully, then sign to complete your profile.
            <?php endif; ?>
        </p>
    </div>

    <!-- Error -->
    <?php if ($error): ?>
    <div class="flex gap-3 p-4 bg-red-700/40 border border-red-600 rounded-xl text-red-300 text-sm">
        <span>⚠️</span><p><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════
         STEP 1: Personal Details
    ════════════════════════════ -->
    <?php if ($currentStep == 1): ?>
    <form method="POST" enctype="multipart/form-data" class="space-y-5">
        <input type="hidden" name="step" value="1">

        <!-- Profile photo -->
        <div class="bg-gray-800 rounded-2xl p-5 space-y-4">
            <h2 class="font-semibold text-blue-400 text-sm uppercase tracking-wide">Profile Photo & ID Image</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-300">Profile Photo</label>
                    <?php if ($ep['profile_photo']): ?>
                    <img src="../<?= htmlspecialchars($ep['profile_photo']) ?>" class="w-20 h-20 rounded-xl object-cover mb-2">
                    <?php endif; ?>
                    <input type="file" name="profile_photo" accept="image/*"
                           class="w-full text-sm text-gray-400 file:mr-3 file:py-2 file:px-3
                                  file:rounded-lg file:border-0 file:bg-blue-600 file:text-white
                                  file:text-sm file:cursor-pointer hover:file:bg-blue-700">
                    <p class="text-gray-500 text-xs">JPG/PNG, max 5MB</p>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-300">National ID Image</label>
                    <?php if ($ep['national_id_image']): ?>
                    <img src="../<?= htmlspecialchars($ep['national_id_image']) ?>" class="w-20 h-20 rounded-xl object-cover mb-2">
                    <?php endif; ?>
                    <input type="file" name="national_id_image" accept="image/*"
                           class="w-full text-sm text-gray-400 file:mr-3 file:py-2 file:px-3
                                  file:rounded-lg file:border-0 file:bg-blue-600 file:text-white
                                  file:text-sm file:cursor-pointer hover:file:bg-blue-700">
                    <p class="text-gray-500 text-xs">JPG/PNG, max 5MB</p>
                </div>
            </div>
        </div>

        <!-- Personal details -->
        <div class="bg-gray-800 rounded-2xl p-5 space-y-4">
            <h2 class="font-semibold text-blue-400 text-sm uppercase tracking-wide">Part A — Personal Details</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <?php
                $fields1 = [
                    ['first_name',       'First Name',       'text', true],
                    ['middle_name',      'Middle Name',      'text', false],
                    ['surname',          'Surname',          'text', true],
                ];
                foreach ($fields1 as [$name, $label, $type, $req]): ?>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">
                        <?= $label ?> <?= $req ? '<span class="text-red-400">*</span>' : '' ?>
                    </label>
                    <input type="<?= $type ?>" name="<?= $name ?>" <?= $req ? 'required' : '' ?>
                           value="<?= htmlspecialchars($ep[$name] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                  outline-none text-white transition-colors">
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">Date of Birth <span class="text-red-400">*</span></label>
                    <input type="date" name="date_of_birth" required
                           value="<?= htmlspecialchars($ep['date_of_birth'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">National ID Number <span class="text-red-400">*</span></label>
                    <input type="text" name="national_id" required
                           value="<?= htmlspecialchars($ep['national_id'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">Passport No <span class="text-gray-500 font-normal">(if applicable)</span></label>
                    <input type="text" name="passport_no"
                           value="<?= htmlspecialchars($ep['passport_no'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">Original Village</label>
                    <input type="text" name="original_village"
                           value="<?= htmlspecialchars($ep['original_village'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">Contact Phone 1 <span class="text-red-400">*</span></label>
                    <input type="tel" name="phone_1" required
                           value="<?= htmlspecialchars($ep['phone_1'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">Contact Phone 2</label>
                    <input type="tel" name="phone_2"
                           value="<?= htmlspecialchars($ep['phone_2'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-medium text-gray-300">Physical Address <span class="text-red-400">*</span></label>
                <input type="text" name="physical_address" required
                       value="<?= htmlspecialchars($ep['physical_address'] ?? '') ?>"
                       class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
            </div>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                       py-3.5 rounded-xl font-bold transition-colors">
            Save & Continue →
        </button>
    </form>

    <!-- ═══════════════════════════
         STEP 2: Referee Details
    ════════════════════════════ -->
    <?php elseif ($currentStep == 2): ?>
    <form method="POST" class="space-y-5">
        <input type="hidden" name="step" value="2">

        <div class="bg-gray-800 rounded-2xl p-5 space-y-4">
            <h2 class="font-semibold text-blue-400 text-sm uppercase tracking-wide">Part B — Referee Details</h2>
            <p class="text-gray-400 text-xs">Could be a parent, guardian or trusted person who can vouch for you.</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <?php
                $fields2 = [
                    ['ref_first_name',  'First Name',  true],
                    ['ref_middle_name', 'Middle Name', false],
                    ['ref_surname',     'Surname',     true],
                ];
                foreach ($fields2 as [$name, $label, $req]): ?>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">
                        <?= $label ?> <?= $req ? '<span class="text-red-400">*</span>' : '' ?>
                    </label>
                    <input type="text" name="<?= $name ?>" <?= $req ? 'required' : '' ?>
                           value="<?= htmlspecialchars($ep[$name] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                  outline-none text-white transition-colors">
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">National ID Number</label>
                    <input type="text" name="ref_national_id"
                           value="<?= htmlspecialchars($ep['ref_national_id'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">Passport No <span class="text-gray-500 font-normal">(if applicable)</span></label>
                    <input type="text" name="ref_passport_no"
                           value="<?= htmlspecialchars($ep['ref_passport_no'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">Original Village</label>
                    <input type="text" name="ref_village"
                           value="<?= htmlspecialchars($ep['ref_village'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-gray-300">Contact Phone(s) <span class="text-red-400">*</span></label>
                    <input type="text" name="ref_phone" required
                           placeholder="e.g. 0771234567 / 0991234567"
                           value="<?= htmlspecialchars($ep['ref_phone'] ?? '') ?>"
                           class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-medium text-gray-300">Physical Address</label>
                <input type="text" name="ref_address"
                       value="<?= htmlspecialchars($ep['ref_address'] ?? '') ?>"
                       class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
            </div>
        </div>

        <div class="flex gap-3">
            <a href="profile_setup.php?back=1"
               class="flex-1 text-center bg-gray-700 hover:bg-gray-600 py-3.5 rounded-xl font-medium transition-colors">
                ← Back
            </a>
            <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                           py-3.5 rounded-xl font-bold transition-colors">
                Save & Continue →
            </button>
        </div>
    </form>

    <!-- ═══════════════════════════
         STEP 3: Oath
    ════════════════════════════ -->
    <?php elseif ($currentStep == 3): ?>
    <form method="POST" class="space-y-5">
        <input type="hidden" name="step" value="3">

        <!-- Oath text -->
        <div class="bg-gray-800 rounded-2xl p-5 space-y-4">
            <h2 class="font-semibold text-blue-400 text-sm uppercase tracking-wide">Oath of Confidentiality</h2>

            <div class="bg-gray-900 rounded-xl p-4 text-sm text-gray-300 leading-relaxed space-y-3 max-h-72 overflow-y-auto border border-gray-700">
                <p>I, <span class="text-blue-400 font-medium"><?= htmlspecialchars(trim(($ep['first_name'] ?? '') . ' ' . ($ep['surname'] ?? ''))) ?></span>,
                agree that I will faithfully discharge my duties as an employee for Super Care Solutions (SCS) and will observe and comply with all policies and procedures of SCS with respect to privacy, confidentiality and security of SCS business information not in the public domain, confidential information and personal information, which I understand as any information that could reasonably be retraced to a specific individual. I will take all reasonable precautions to prevent any unauthorized collection, use, disclosure and destruction of this information while I am employed by, affiliated with or in a contractual relationship with the SCS.</p>

                <p>Unless legally authorized to do so, I will not use or disclose any of the information, as listed above, that comes to my knowledge or possession by reason of my role with the SCS, including after I cease to be employed by, affiliated with or in a contractual relationship with the SCS and for a period of 2 years thereafter.</p>

                <p>Upon termination of my engagement with SCS, or upon request at any time by SCS, all documents and other material that contain any of the information, as listed above, that I have in my possession and/or control will be promptly delivered by me to SCS.</p>

                <p>At SCS's written direction, I will erase all of the information, as listed above, that is stored electronically in all devices, including but not limited to computers, laptops, Smartphones, USB and other storage devices or media and mobile phones.</p>

                <p>I understand and agree that a breach of this oath is just cause for termination of my employment, affiliation or contractual relationship with the SCS.</p>

                <p>I am aware that the SCS has policies and procedures regarding privacy, confidentially, and security of information and I understand and agree that it is my responsibility to be familiar with the requirements outlined in these policies and procedures.</p>

                <p>I agree that my use of the SCS's electronic or paper files, email, other electronic applications, computers, cellular phones or other electronic devices may be monitored by the SCS or its designate at any time to ensure appropriate usage, confidentiality and security.</p>

                <p>I agree to refer to the SCS's for the details of the Policies and Procedures and any other information required for me to understand and fulfill my obligations as set out herein.</p>
            </div>
        </div>

        <!-- Sign -->
        <div class="bg-gray-800 rounded-2xl p-5 space-y-4">
            <h2 class="font-semibold text-blue-400 text-sm uppercase tracking-wide">Your Signature</h2>

            <div class="space-y-1.5">
                <label class="text-sm font-medium text-gray-300">
                    Full Name (as signature) <span class="text-red-400">*</span>
                </label>
                <input type="text" name="oath_name" required
                       placeholder="Type your full legal name"
                       value="<?= htmlspecialchars($ep['oath_signed_name'] ?? trim(($ep['first_name'] ?? '') . ' ' . ($ep['middle_name'] ?? '') . ' ' . ($ep['surname'] ?? ''))) ?>"
                       class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-white">
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-medium text-gray-300">Date</label>
                <input type="date" name="oath_date" value="<?= date('Y-m-d') ?>" readonly
                       class="w-full p-3 rounded-lg bg-gray-700 border border-gray-600
                              outline-none text-gray-400 cursor-not-allowed">
            </div>

            <!-- Agreement checkbox -->
            <label class="flex items-start gap-3 bg-gray-700/50 border border-gray-600 rounded-xl p-4 cursor-pointer hover:bg-gray-700 transition-colors">
                <input type="checkbox" name="oath_agreed" value="1" required
                       class="w-5 h-5 mt-0.5 rounded accent-blue-500 shrink-0 cursor-pointer">
                <div>
                    <p class="font-medium text-sm">I have read and agree to the Oath of Confidentiality</p>
                    <p class="text-gray-400 text-xs mt-0.5">By checking this box you are digitally signing this agreement. This is legally binding.</p>
                </div>
            </label>
        </div>

        <div class="flex gap-3">
            <a href="profile_setup.php?back=2"
               class="flex-1 text-center bg-gray-700 hover:bg-gray-600 py-3.5 rounded-xl font-medium transition-colors">
                ← Back
            </a>
            <button type="submit"
                    class="flex-1 bg-green-600 hover:bg-green-700 active:bg-green-800
                           py-3.5 rounded-xl font-bold transition-colors">
                ✓ Submit Profile
            </button>
        </div>
    </form>
    <?php endif; ?>

</div><!-- /container -->
</body>
</html>