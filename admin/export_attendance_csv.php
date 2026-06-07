<?php
session_start();
require_once "../config/db.php";

if (
!isset($_SESSION["user_id"])
||
$_SESSION["role"]!="admin"
){
exit();
}

$month =
$_GET["month"]
??
date("Y-m");

header(
"Content-Type: text/csv"
);

header(
"Content-Disposition: attachment; filename=attendance_report_$month.csv"
);

$output =
fopen(
"php://output",
"w"
);

/* CSV Header */

fputcsv(
$output,
[
"Teller",
"Present",
"Late",
"Total Attendance",
"Attendance Percentage"
]
);

$sql = "

SELECT

u.full_name,

COUNT(a.id) total,

SUM(
CASE
WHEN a.status='present'
THEN 1
ELSE 0
END
) present,

SUM(
CASE
WHEN a.status='late'
THEN 1
ELSE 0
END
) late

FROM users u

LEFT JOIN attendance a
ON
u.id=a.user_id
AND DATE_FORMAT(
a.check_in_time,
'%Y-%m'
)=?

WHERE u.role='teller'

GROUP BY u.id

ORDER BY u.full_name

";

$stmt =
$conn
->prepare(
$sql
);

$stmt
->bind_param(
"s",
$month
);

$stmt
->execute();

$result =
$stmt
->get_result();

while(
$row=
$result
->fetch_assoc()
){

$total =
$row["total"];

$percentage =
$total
?
round(
(
(
$row["present"]
+
$row["late"]
)
/
22
)
*
100
)
:
0;

fputcsv(
$output,
[
$row["full_name"],
$row["present"],
$row["late"],
$total,
$percentage."%"
]
);

}

fclose(
$output
);

exit();