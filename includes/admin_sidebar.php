<!-- MOBILE TOP BAR -->
<div class="lg:hidden fixed top-0 left-0 right-0 bg-gray-800 z-50 h-16 flex items-center px-4 shadow-lg">

    <button
        onclick="toggleSidebar()"
        class="text-white text-3xl">

        ☰

    </button>

    <h2 class="ml-4 text-lg font-bold">
        Admin Panel
    </h2>

</div>


<!-- OVERLAY -->
<div
id="sidebarOverlay"
onclick="closeSidebar()"
class="hidden fixed inset-0 bg-black/60 z-40 lg:hidden">
</div>


<!-- SIDEBAR -->
<div
id="adminSidebar"

class="
fixed
top-0
left-0
z-50
w-72
h-screen
bg-gray-800
transform
-transition
duration-300
ease-in-out
-translate-x-full
lg:translate-x-0
overflow-y-auto
shadow-2xl
flex
flex-col
">

    <!-- HEADER -->

    <div class="p-6 border-b border-gray-700">

        <h2 class="text-2xl font-bold text-center">
            Admin Menu
        </h2>

    </div>


    <!-- NAV -->

    <nav class="flex flex-col gap-2 p-4 flex-1">

        <a href="dashboard.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Dashboard
        </a>

        <a href="manage_tellers.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Manage Tellers
        </a>

        <a href="approve_profiles.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Approve Profiles
        </a>

        <a href="opening_approvals.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Opening Balances
        </a>

        <a href="approve_closing.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Closing Balances
        </a>

        <a href="leave_requests.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Leave Requests
        </a>

        <a href="attendance_dashboard.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Attendance
        </a>

        <a href="attendance_report.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Attendance Report
        </a>

        <a href="platforms.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Platform Management
        </a>

        <a href="audit_logs.php"
           onclick="closeSidebar()"
           class="p-4 rounded-xl hover:bg-gray-700 transition">
            Audit Logs
        </a>

    </nav>


    <!-- FOOTER -->

    <div class="p-4 border-t border-gray-700">

        <a
        href="../logout.php"

        class="
        block
        text-center
        p-4
        rounded-xl
        bg-red-700
        hover:bg-red-800
        transition
        ">

            Logout

        </a>

    </div>

</div>


<!-- CONTENT SPACING -->
<div class="lg:ml-72 pt-16 lg:pt-0"></div>


<script>

function toggleSidebar(){

let sidebar =
document.getElementById(
"adminSidebar"
);

let overlay =
document.getElementById(
"sidebarOverlay"
);

sidebar.classList.toggle(
"-translate-x-full"
);

overlay.classList.toggle(
"hidden"
);

}


function closeSidebar(){

document
.getElementById(
"adminSidebar"
)
.classList.add(
"-translate-x-full"
);

document
.getElementById(
"sidebarOverlay"
)
.classList.add(
"hidden"
);

}


window.addEventListener(
"resize",
function(){

if(window.innerWidth>=1024){

document
.getElementById(
"adminSidebar"
)
.classList.remove(
"-translate-x-full"
);

document
.getElementById(
"sidebarOverlay"
)
.classList.add(
"hidden"
);

}

}
);

</script>