<!-- MOBILE HEADER -->
<div
class="
lg:hidden
fixed
top-0
left-0
right-0
h-16
bg-gray-900
flex
items-center
px-4
shadow-lg
z-50
">

<button
onclick="toggleStaffSidebar()"
class="text-3xl">

☰

</button>

<h1 class="ml-4 text-lg font-bold text-blue-400">
SuperCare
</h1>

</div>



<!-- OVERLAY -->

<div
id="staffOverlay"
onclick="closeStaffSidebar()"

class="
hidden
fixed
inset-0
bg-black/60
z-40
lg:hidden
">
</div>



<!-- SIDEBAR -->

<div

id="staffSidebar"

class="
fixed
top-0
left-0
h-screen
w-72
bg-gray-900
text-white
shadow-2xl
z-50
transform
transition-transform
duration-300
ease-in-out
-translate-x-full
lg:translate-x-0
overflow-y-auto
flex
flex-col
"

>

<div class="p-6 border-b border-gray-700">

<h1 class="text-2xl font-bold text-blue-400">

SuperCare

</h1>

</div>



<nav class="flex-1 p-4 space-y-2">

<a
href="dashboard.php"
onclick="closeStaffSidebar()"
class="
block
p-4
rounded-xl
hover:bg-gray-800
transition
">

Dashboard

</a>


<!--<a
href="attendance.php"
onclick="closeStaffSidebar()"
class="
block
p-4
rounded-xl
hover:bg-gray-800
transition
">

Attendance

</a>-->


<a
href="opening_entry.php"
onclick="closeStaffSidebar()"
class="
block
p-4
rounded-xl
hover:bg-gray-800
transition
">

Opening Balances

</a>


<a
href="closing_entry.php"
onclick="closeStaffSidebar()"
class="
block
p-4
rounded-xl
hover:bg-gray-800
transition
">

Closing Balances

</a>


<a
href="add_adjustment.php"
onclick="closeStaffSidebar()"
class="
block
p-4
rounded-xl
hover:bg-gray-800
transition
">

Statements

</a>


<a
href="commissions.php"
onclick="closeStaffSidebar()"
class="
block
p-4
rounded-xl
hover:bg-gray-800
transition
">

Commissions

</a>


<a
href="apply_leave.php"
onclick="closeStaffSidebar()"
class="
block
p-4
rounded-xl
hover:bg-gray-800
transition
">

Apply Leave

</a>


<a
href="my_leave_requests.php"
onclick="closeStaffSidebar()"
class="
block
p-4
rounded-xl
hover:bg-gray-800
transition
">

My Leave Requests

</a>

</nav>



<div class="p-4 border-t border-gray-700">

<a
href="../logout.php"

class="
block
text-center
p-4
bg-red-600
hover:bg-red-700
rounded-xl
transition
">

Logout

</a>

</div>

</div>



<!-- PAGE OFFSET -->

<div class="lg:ml-72 pt-16 lg:pt-0"></div>



<script>

function toggleStaffSidebar(){

document
.getElementById(
"staffSidebar"
)
.classList.toggle(
"-translate-x-full"
);

document
.getElementById(
"staffOverlay"
)
.classList.toggle(
"hidden"
);

}



function closeStaffSidebar(){

document
.getElementById(
"staffSidebar"
)
.classList.add(
"-translate-x-full"
);

document
.getElementById(
"staffOverlay"
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
"staffSidebar"
)
.classList.remove(
"-translate-x-full"
);

document
.getElementById(
"staffOverlay"
)
.classList.add(
"hidden"
);

}

}
);

</script>