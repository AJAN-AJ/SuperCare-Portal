<?php
session_start();
require_once "../config/db.php";


ini_set("display_errors", 1);
error_reporting(E_ALL);




if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $branch_id = intval($_POST['branch_id']);

    if (!$username || !$password || !$branch_id) {
        $error = "All fields are required.";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            // Insert teller
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("
                INSERT INTO users (username, password, role, branch_id, profile_completed, approved)
                VALUES (?, ?, 'teller', ?, 0, 0)
            ");
            $insert->bind_param("ssi", $username, $hashed, $branch_id);
            if ($insert->execute()) {
                $success = "Teller account created successfully.";
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

// Fetch branches for dropdown
$branches = $conn->query("SELECT id, name FROM branches");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Teller</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex">
<?php include "../includes/admin_sidebar.php"; ?>

<div class="flex-1 overflow-y-auto p-3 sm:p-4 md:p-6 w-full max-w-md mx-auto">

    <h2 class="text-2xl font-bold mb-6 text-center">Create Teller Account</h2>

    <?php if ($error): ?>
        <div class="bg-red-600 p-3 rounded mb-4"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-600 p-3 rounded mb-4"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block mb-1">Username</label>
            <input type="text" name="username" class="w-full p-3 rounded bg-gray-700 border border-gray-600" required>
        </div>

        <div>
            <label class="block mb-1">Temporary Password</label>
            <input type="password" name="password" class="w-full p-3 rounded bg-gray-700 border border-gray-600" required>
        </div>

        <div>
            <label class="block mb-1">Branch</label>
            <select name="branch_id" class="w-full p-3 rounded bg-gray-700 border border-gray-600" required>
                <option value="">Select Branch</option>
                <?php while($b = $branches->fetch_assoc()): ?>
                    <option value="<?= $b['id']; ?>"><?= htmlspecialchars($b['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 p-3 rounded font-semibold">Create Teller</button>
    </form>

</div>
</body>
</html>
