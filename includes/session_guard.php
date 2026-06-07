<?php

function ensureDailySession($conn, $user_id)
{
    $today = date("Y-m-d");

    $stmt = $conn->prepare("
        SELECT id FROM balance_sessions
        WHERE user_id = ? AND balance_date = ?
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();

    $res = $stmt->get_result();

    if ($res->num_rows == 0) {

        $stmt = $conn->prepare("
            INSERT INTO balance_sessions
            (user_id, balance_date, status)
            VALUES (?, ?, 'draft')
        ");

        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();

        return $conn->insert_id;
    }

    return $res->fetch_assoc()["id"];
}