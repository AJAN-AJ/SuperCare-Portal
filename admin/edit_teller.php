<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get teller ID
if (!isset($_GET['id'])) {
    header("Location: manage_tellers.php");
    exit();
}

$teller_id = intval($_GET['id']);

// Fetch teller info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $teller_id);
$stmt->execute();
$teller = $stmt->get_result()->fetch_assoc();

if (!$teller) {
    die("Teller not found.");
}

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $branch_id = $_POST['branch_id'];
    $profile_completed = isset($_POST['profile_completed']) ? 1 : 0;
    $password = trim($_POST['password']);

    if (!$full_name || !$username) {
        $error = "Full name and username are required.";
    } else {
        if ($password) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("
                UPDATE users SET full_name=?, username=?, phone=?, branch_id=?, profile_completed=?, password=?
                WHERE id=?
            ");
            $update->bind_param("sssiisi", $full_name, $username, $phone, $branch_id, $profile_completed, $password_hash, $teller_id);
        } else {
            $update = $conn->prepare("
                UPDATE users SET full_name=?, username=?, phone=?, branch_id=?, profile_completed=?
                WHERE id=?
            ");
            $update->bind_param("sssiii", $full_name, $username, $phone, $branch_id, $profile_completed, $teller_id);
        }

        if ($update->execute()) {
            $success = "Teller updated successfully.";
            // Refresh teller data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $teller_id);
            $stmt->execute();
            $teller = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

// Fetch branches for dropdown
$branches = $conn->query("SELECT * FROM branches ORDER BY name ASC");
?>

<?php include "../includes/admin_sidebar.php"; ?>

<div class="flex-1 p-6 max-w-md mx-auto">
    <h2 class="text-2xl font-bold mb-6 text-center">Edit Teller</h2>

    <?php if ($error): ?>
        <div class="bg-red-600 p-3 rounded mb-4"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-600 p-3 rounded mb-4"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block mb-1">Full Name</label>
            <input type="text" name="full_name" class="w-full p-3 rounded bg-gray-700 border border-gray-600"
                   value="<?= htmlspecialchars($teller['full_name']); ?>" required>
        </div>

        <div>
            <label class="block mb-1">Username</label>
            <input type="text" name="username" class="w-full p-3 rounded bg-gray-700 border border-gray-600"
                   value="<?= htmlspecialchars($teller['username']); ?>" required>
        </div>

        <div>
            <label class="block mb-1">Phone</label>
            <input type="text" name="phone" class="w-full p-3 rounded bg-gray-700 border border-gray-600"
                   value="<?= htmlspecialchars($teller['phone']); ?>">
        </div>

        <div>
            <label class="block mb-1">Branch</label>
            <select name="branch_id" class="w-full p-3 rounded bg-gray-700 border border-gray-600">
                <option value="">-- Select Branch --</option>
                <?php while ($branch = $branches->fetch_assoc()): ?>
                    <option value="<?= $branch['id']; ?>" <?= $branch['id'] == $teller['branch_id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($branch['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="profile_completed" id="profile_completed"
                   value="1" <?= $teller['profile_completed'] ? 'checked' : ''; ?>>
            <label for="profile_completed">Profile Completed / Approved</label>
        </div>

        <div>
            <label class="block mb-1">Reset Password (leave blank to keep current)</label>
            <input type="password" name="password" class="w-full p-3 rounded bg-gray-700 border border-gray-600">
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 p-3 rounded font-semibold">
            Update Teller
        </button>
    </form>
</div>
