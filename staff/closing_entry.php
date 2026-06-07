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

/* SESSION */
$stmt=$conn->prepare("
SELECT *
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

$res=$stmt->get_result();

if($res->num_rows==0){
header("Location: opening_select_platforms.php");
exit();
}

$session=$res->fetch_assoc();

$session_id=$session["id"];
$status=$session["status"];

/* LOCK */
$locked=
(
$status=="pending_approval_closing"
||
$status=="approved_closing"
);

/* PLATFORM DATA */

$stmt=$conn->prepare("
SELECT
bpe.id,
p.name,
bpe.opening_amount,
bpe.closing_amount

FROM balance_platform_entries bpe

JOIN platforms p
ON p.id=bpe.platform_id

WHERE session_id=?
");

$stmt->bind_param(
"i",
$session_id
);

$stmt->execute();

$platforms=
$stmt
->get_result();

/* SAVE */

if(
$_SERVER["REQUEST_METHOD"]==="POST"
&&
!$locked
){

$closing_total=0;

foreach(
$_POST["closing"]
as
$id=>$amount
){

$amount=
floatval(
str_replace(
",",
"",
$amount
));

$closing_total+=$amount;

$up=
$conn->prepare("
UPDATE
balance_platform_entries
SET closing_amount=?
WHERE id=?
");

$up->bind_param(
"di",
$amount,
$id
);

$up->execute();

}

/* OUTGOING */

$stmt=
$conn
->prepare("
SELECT
COALESCE(
SUM(amount),
0
)
total

FROM balance_adjustments

WHERE balance_session_id=?
AND type='outgoing'
");

$stmt->bind_param(
"i",
$session_id
);

$stmt->execute();

$outgoing=
$stmt
->get_result()
->fetch_assoc()["total"];

/* INCOMING */

$stmt=
$conn
->prepare("
SELECT
COALESCE(
SUM(amount),
0
)
total

FROM balance_adjustments

WHERE balance_session_id=?
AND type='incoming'
");

$stmt->bind_param(
"i",
$session_id
);

$stmt->execute();

$incoming=
$stmt
->get_result()
->fetch_assoc()["total"];

/* ENGINE */

$expected=
$session["opening_total"]
-
$outgoing
+
$incoming;

$difference=
$closing_total
-
$expected;

/* SAVE SESSION */

$stmt=
$conn
->prepare("
UPDATE balance_sessions

SET
closing_total=?,
difference=?,
status='pending_approval_closing'

WHERE id=?
");

$stmt->bind_param(
"ddi",
$closing_total,
$difference,
$session_id
);

$stmt->execute();

header(
"Location: dashboard.php"
);

exit();

}

?>

<!DOCTYPE html>

<html>

<head>

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
Closing Balances
</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-900 text-white">

<?php include "../includes/sidebar.php"; ?>

<div class="md:ml-64 p-4">

<div class="max-w-3xl mx-auto">

<div class="bg-gray-800 rounded-2xl p-6">

<h1 class="text-2xl font-bold mb-6">
Closing Balances
</h1>

<?php if($locked): ?>

<div class="bg-yellow-600 p-3 rounded mb-5">
Closing already submitted.
</div>

<?php endif; ?>

<form method="POST">

<div class="space-y-5">

<?php while(
$p=
$platforms->fetch_assoc()
): ?>

<div>

<label class="block mb-2">

<?= htmlspecialchars(
$p["name"]
) ?>

<span class="text-gray-400">

(Opening:
<?= number_format(
$p["opening_amount"],
2
) ?>

)

</span>

</label>

<input
type="text"

name="closing[<?= $p["id"] ?>]"

value="<?= $p["closing_amount"] ? number_format($p["closing_amount"],2) : '' ?>"

class="money w-full p-4 rounded bg-gray-700"

<?= $locked?"disabled":"" ?>

oninput="formatMoney(this)"
>

</div>

<?php endwhile; ?>

<div class="bg-gray-700 p-4 rounded">

Closing Total

<div
id="total"
class="text-2xl font-bold mt-2"
>

0.00

</div>

</div>

<?php if(!$locked): ?>

<button
class="w-full bg-red-600 hover:bg-red-700 rounded-xl p-4 mt-5"
>

Submit Closing

</button>

<?php endif; ?>

</div>

</form>

</div>

</div>

</div>

<script>

function formatNumber(v){

return Number(
v
).toLocaleString(
undefined,
{
minimumFractionDigits:2,
maximumFractionDigits:2
}
);

}

function formatMoney(el){

let v=
el.value
.replace(
/,/g,
''
);

if(
isNaN(v)
||
v==''
){

el.value='';

calc();

return;

}

el.value=
formatNumber(v);

calc();

}

function calc(){

let total=0;

document
.querySelectorAll(
'.money'
)
.forEach(i=>{

let n=
parseFloat(
i.value.replace(/,/g,'')

)||0;

total+=n;

});

document
.getElementById(
'total'
)
.innerHTML=
formatNumber(
total
);

}

calc();

</script>

</body>

</html>