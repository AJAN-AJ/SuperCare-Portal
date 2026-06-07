<?php
session_start();
require_once "../config/db.php";

ini_set('display_errors',1);
error_reporting(E_ALL);

if(
!isset($_SESSION["user_id"])
||
$_SESSION["role"]!="teller"
){
header("Location: ../login.php");
exit();
}

$user_id=$_SESSION["user_id"];
$today=date("Y-m-d");

$message="";
$success=false;

/* SESSION */

$stmt=$conn->prepare("
SELECT id
FROM balance_sessions
WHERE user_id=?
AND balance_date=?
");

$stmt->bind_param(
"is",
$user_id,
$today
);

$stmt->execute();

$res=
$stmt
->get_result();

if(
$res->num_rows==0
){
header(
"Location: opening_select_platforms.php"
);
exit();
}

$session=
$res->fetch_assoc();

$session_id=
$session["id"];

/* PLATFORMS */

$platforms=
$conn
->query("
SELECT
id,
name
FROM platforms
ORDER BY name
");

/* SAVE */

if(
$_SERVER["REQUEST_METHOD"]==="POST"
){

$platform_id=
intval(
$_POST["platform_id"]
);

$type=
$_POST["type"];

$amount=
floatval(
str_replace(
",",
"",
$_POST["amount"]
));

$description=
trim(
$_POST["description"]
);

if(
$amount<=0
){

$message=
"Amount must be greater than zero.";

}
elseif(
!in_array(
$type,
[
"incoming",
"outgoing"
]
)
){

$message=
"Invalid transaction type.";

}
else{

$stmt=
$conn
->prepare("
INSERT INTO
balance_adjustments

(
balance_session_id,
platform_id,
type,
amount,
description
)

VALUES

(
?,
?,
?,
?,
?
)

");

$stmt->bind_param(
"iisds",
$session_id,
$platform_id,
$type,
$amount,
$description
);

if(
$stmt->execute()
){

header(
"Location: dashboard.php"
);

exit();

}
else{

$message=
$stmt->error;

}

}

}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width,initial-scale=1">

<title>
Add Statement
</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-900 text-white">

<?php include "../includes/sidebar.php"; ?>

<div class="md:ml-64 p-4">

<div class="max-w-2xl mx-auto">

<div class="bg-gray-800 rounded-2xl p-6 shadow">

<h1 class="text-2xl font-bold mb-6">

Add Statement

</h1>

<?php if($message): ?>

<div class="bg-red-600 p-3 rounded mb-5">

<?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>

<form method="POST">

<div class="space-y-5">

<div>

<label class="block mb-2">

Platform

</label>

<select
name="platform_id"
required
class="w-full p-4 rounded bg-gray-700"
>

<?php while(
$p=
$platforms->fetch_assoc()
): ?>

<option value="<?= $p["id"] ?>">

<?= htmlspecialchars(
$p["name"]
) ?>

</option>

<?php endwhile; ?>

</select>

</div>

<div>

<label class="block mb-2">

Transaction Type

</label>

<select
name="type"
required
class="w-full p-4 rounded bg-gray-700"
>

<option value="outgoing">

Sent Money

</option>

<option value="incoming">

Received Money

</option>

</select>

</div>

<div>

<label class="block mb-2">

Amount

</label>

<input
type="text"
name="amount"
required

class="
money
w-full
p-4
rounded
bg-gray-700
"

placeholder="0.00"

oninput="formatMoney(this)"
>

</div>

<div>

<label class="block mb-2">

Description

</label>

<textarea

name="description"

rows="5"

class="
w-full
p-4
rounded
bg-gray-700
"

placeholder="
Optional notes
"

></textarea>

</div>

<button

class="
w-full
bg-green-600
hover:bg-green-700
p-4
rounded-xl
"

>

Save Statement

</button>

</div>

</form>

<div class="mt-6">

<a
href="dashboard.php"
class="text-gray-400"
>

← Back to Dashboard

</a>

</div>

</div>

</div>

</div>

<script>

function formatMoney(el){

let v=
el.value
.replace(
/,/g,
''
);

if(
v===''
||
isNaN(v)
){

el.value='';

return;

}

el.value=
Number(
v
).toLocaleString(
undefined,
{
minimumFractionDigits:2,
maximumFractionDigits:2
}
);

}

</script>

</body>

</html>