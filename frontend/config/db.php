<?php
// config/db.php — include this in every page
$host = "localhost"; $db = "anitrack"; $user = "root"; $pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

function notify($conn, $user_id, $type, $message, $link = '') {
    $s = $conn->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?,?,?,?)");
    $s->bind_param("isss", $user_id, $type, $message, $link);
    $s->execute(); $s->close();
}

function unread_count($conn, $uid) {
    $s = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $s->bind_param("i", $uid);
    $s->execute();
    $result = $s->get_result();
    $row = $result->fetch_row();
    $s->close();
    return (int)($row[0] ?? 0);
}