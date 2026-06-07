<?php

function getBalanceEngine($conn, $session_id)
{
    /* 1. Get session */
    $stmt = $conn->prepare("
        SELECT opening_total, closing_total
        FROM balance_sessions
        WHERE id = ?
    ");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();

    $opening = floatval($session["opening_total"]);
    $actualClosing = floatval($session["closing_total"]);

    /* 2. Get adjustments */
    $stmt = $conn->prepare("
        SELECT type, SUM(amount) as total
        FROM balance_adjustments
        WHERE balance_session_id = ?
        GROUP BY type
    ");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $incoming = 0;
    $outgoing = 0;

    while ($row = $result->fetch_assoc()) {
        if ($row["type"] == "incoming") {
            $incoming = floatval($row["total"]);
        } else {
            $outgoing = floatval($row["total"]);
        }
    }

    /* 3. Expected balance */
    $expected = $opening - $outgoing + $incoming;

    /* 4. Difference */
    $difference = $actualClosing - $expected;

    /* 5. Status */
    if ($difference == 0) {
        $status = "balanced";
    } elseif ($difference > 0) {
        $status = "overage";
    } else {
        $status = "shortage";
    }

    return [
        "opening" => $opening,
        "incoming" => $incoming,
        "outgoing" => $outgoing,
        "expected" => $expected,
        "actual" => $actualClosing,
        "difference" => $difference,
        "status" => $status
    ];
}

?>