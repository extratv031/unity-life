<?php
/* made with love from alex - xshadow */
session_start();

require_once "includes/config.php";
require_once "includes/functions.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

if (!isset($_POST["post_id"]) || empty($_POST["post_id"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Keine Post-ID angegeben']);
    exit;
}

$post_id = intval($_POST["post_id"]);
$user_id = $_SESSION["id"];

$check_sql = "SELECT * FROM likes WHERE user_id = ? AND post_id = ?";
$has_liked = false;
$liked = false;

if ($check_stmt = mysqli_prepare($link, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $post_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    $has_liked = mysqli_stmt_num_rows($check_stmt) > 0;
    mysqli_stmt_close($check_stmt);
    
    if ($has_liked) {
        $sql = "DELETE FROM likes WHERE user_id = ? AND post_id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $liked = false;
        }
    } else {
        $sql = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $liked = true;
        }
    }
}

$count = 0;
$count_sql = "SELECT COUNT(*) as count FROM likes WHERE post_id = ?";
if ($count_stmt = mysqli_prepare($link, $count_sql)) {
    mysqli_stmt_bind_param($count_stmt, "i", $post_id);
    mysqli_stmt_execute($count_stmt);
    $result = mysqli_stmt_get_result($count_stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $count = $row['count'];
    }
    mysqli_stmt_close($count_stmt);
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'liked' => $liked,
    'count' => $count
]);
exit;
