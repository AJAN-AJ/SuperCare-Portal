<?php
session_start();
require_once "../config/db.php";
require_once "../includes/session_guard.php";

//$session_id = ensureDailySession($conn, $user_id);

ini_set('display_errors', 1);
error_reporting(E_ALL);



if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teller") {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$branch_id = $_SESSION["branch_id"]; // make sure branch_id is stored at login
$today = date("Y-m-d");

/* -------------------------------------------------
   1️⃣ Check if today's session exists
---------------------------------------------------*/
$stmt = $conn->prepare("SELECT id FROM balance_sessions WHERE user_id = ? AND balance_date = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $session_id = $row["id"];
} else {
    // Create new session for today
    $insertSession = $conn->prepare("
        INSERT INTO balance_sessions (user_id, branch_id, balance_date, status)
        VALUES (?, ?, ?, 'draft')
    ");
    $insertSession->bind_param("iis", $user_id, $branch_id, $today);
    $insertSession->execute();
    $session_id = $insertSession->insert_id;
}

/* -------------------------------------------------
   2️⃣ Handle Form Submission
---------------------------------------------------*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!empty($_POST["platforms"])) {

        // First delete old selections (in case of update)
        $delete = $conn->prepare("DELETE FROM balance_session_platforms WHERE session_id = ?");
        $delete->bind_param("i", $session_id);
        $delete->execute();

        // Insert selected platforms
        foreach ($_POST["platforms"] as $platform_id) {
            $insertPlatform = $conn->prepare("
                INSERT INTO balance_session_platforms (session_id, platform_id)
                VALUES (?, ?)
            ");
            $insertPlatform->bind_param("ii", $session_id, $platform_id);
            $insertPlatform->execute();
        }

        header("Location: opening_entry.php");
        exit();
    }
}

/* -------------------------------------------------
   3️⃣ Fetch All Platforms
---------------------------------------------------*/
$platforms = $conn->query("SELECT * FROM platforms WHERE active = 1 ORDER BY name ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Select Platforms</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex overflow-hidden">

<?php include "../includes/sidebar.php"; ?>

<div class="flex-1 p-3 sm:p-6 overflow-y-auto">
    <h2 class="text-xl font-bold mb-4 text-center">
        Select Platforms for Today
    </h2>

    <form method="POST" class="space-y-3">

        <?php while ($platform = $platforms->fetch_assoc()): ?>
            <label class="flex items-center bg-gray-700 p-3 rounded-lg cursor-pointer hover:bg-gray-600 transition">
                <input 
                    type="checkbox" 
                    name="platforms[]" 
                    value="<?= $platform['id']; ?>" 
                    class="mr-3 w-5 h-5"
                >
                <span class="text-lg"><?= htmlspecialchars($platform['name']); ?></span>
            </label>
        <?php endwhile; ?>

        <button 
            type="submit" 
            class="w-full bg-blue-600 hover:bg-blue-700 p-3 rounded-xl font-semibold mt-4 transition"
        >
            Continue to Opening Entry
        </button>

    </form>

    <div class="text-center mt-4">
        <a href="dashboard.php" class="text-sm text-gray-400 hover:text-gray-200">
            ← Back to Dashboard
        </a>
    </div>

</div>

</body>
</html>

