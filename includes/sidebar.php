<?php
/* Fetch profile photo for sidebar */
$_sidebarStmt = $conn->prepare("SELECT ep.profile_photo, u.full_name FROM users u LEFT JOIN employee_profiles ep ON ep.user_id = u.id WHERE u.id = ?");
$_sidebarStmt->bind_param("i", $_SESSION["user_id"]);
$_sidebarStmt->execute();
$_sidebarUser = $_sidebarStmt->get_result()->fetch_assoc();
$_sidebarPhoto = $_sidebarUser["profile_photo"] ?? null;
$_sidebarName  = $_sidebarUser["full_name"] ?? "Staff";
?>

<!-- MOBILE HEADER -->
<div class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-gray-900 flex items-center px-4 shadow-lg z-50 border-b border-gray-800">
    <button onclick="toggleStaffSidebar()" class="text-2xl text-gray-300 hover:text-white transition-colors">☰</button>
    <h1 class="ml-4 text-lg font-bold text-blue-400">SuperCare</h1>
    <!-- Profile photo in mobile header -->
    <div class="ml-auto">
        <?php if ($_sidebarPhoto): ?>
        <img src="../<?= htmlspecialchars($_sidebarPhoto) ?>"
             class="w-9 h-9 rounded-full object-cover border-2 border-blue-500 cursor-pointer"
             onclick="toggleStaffSidebar()">
        <?php else: ?>
        <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center font-bold text-sm cursor-pointer"
             onclick="toggleStaffSidebar()">
            <?= strtoupper(substr($_sidebarName, 0, 1)) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- OVERLAY -->
<div id="staffOverlay" onclick="closeStaffSidebar()"
     class="hidden fixed inset-0 bg-black/60 z-40 lg:hidden"></div>

<!-- SIDEBAR -->
<div id="staffSidebar"
     class="fixed top-0 left-0 h-screen w-72 bg-gray-900 text-white shadow-2xl z-50
            transform transition-transform duration-300 ease-in-out -translate-x-full
            lg:translate-x-0 overflow-y-auto flex flex-col border-r border-gray-800">

    <!-- Sidebar header with profile -->
    <div class="p-5 border-b border-gray-800">
        <div class="flex items-center gap-3 mb-1">
            <?php if ($_sidebarPhoto): ?>
            <img src="../<?= htmlspecialchars($_sidebarPhoto) ?>"
                 class="w-12 h-12 rounded-xl object-cover border-2 border-blue-500 shrink-0">
            <?php else: ?>
            <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center font-bold text-lg shrink-0">
                <?= strtoupper(substr($_sidebarName, 0, 1)) ?>
            </div>
            <?php endif; ?>
            <div class="min-w-0">
                <h1 class="text-base font-bold text-white truncate"><?= htmlspecialchars($_sidebarName) ?></h1>
                <p class="text-blue-400 text-xs font-semibold">SuperCare Solutions</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 p-4 space-y-1">
        <a href="dashboard.php" onclick="closeStaffSidebar()"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-800 transition-colors text-sm font-medium">
            <span class="text-lg">🏠</span> Dashboard
        </a>
        <a href="opening_entry.php" onclick="closeStaffSidebar()"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-800 transition-colors text-sm font-medium">
            <span class="text-lg">📂</span> Opening Balances
        </a>
        <a href="closing_entry.php" onclick="closeStaffSidebar()"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-800 transition-colors text-sm font-medium">
            <span class="text-lg">📁</span> Closing Balances
        </a>
        <a href="add_adjustment.php" onclick="closeStaffSidebar()"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-800 transition-colors text-sm font-medium">
            <span class="text-lg">📝</span> Statements
        </a>
      <!--  <a href="commissions.php" onclick="closeStaffSidebar()"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-800 transition-colors text-sm font-medium">
            <span class="text-lg">💰</span> Commissions
        </a>-->
        <a href="salary.php" onclick="closeStaffSidebar()"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-800 transition-colors text-sm font-medium">
            <span class="text-lg">💵</span> Salary
        </a>
        <a href="apply_leave.php" onclick="closeStaffSidebar()"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-800 transition-colors text-sm font-medium">
            <span class="text-lg">🏖️</span> Apply Leave
        </a>
        <a href="my_leave_requests.php" onclick="closeStaffSidebar()"
           class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-800 transition-colors text-sm font-medium">
            <span class="text-lg">📋</span> My Leave Requests
        </a>
    </nav>

    <div class="p-4 border-t border-gray-800">
        <a href="../logout.php"
           class="flex items-center justify-center gap-2 p-3 bg-red-600 hover:bg-red-700
                  active:bg-red-800 rounded-xl transition-colors font-medium text-sm">
            <span>🚪</span> Logout
        </a>
    </div>
</div>

<!-- PAGE OFFSET -->
<div class="lg:ml-72 pt-16 lg:pt-0"></div>

<script>
function toggleStaffSidebar() {
    document.getElementById("staffSidebar").classList.toggle("-translate-x-full");
    document.getElementById("staffOverlay").classList.toggle("hidden");
}
function closeStaffSidebar() {
    document.getElementById("staffSidebar").classList.add("-translate-x-full");
    document.getElementById("staffOverlay").classList.add("hidden");
}
window.addEventListener("resize", function() {
    if (window.innerWidth >= 1024) {
        document.getElementById("staffSidebar").classList.remove("-translate-x-full");
        document.getElementById("staffOverlay").classList.add("hidden");
    }
});
</script>