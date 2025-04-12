<?php
/* made with love from alex - xshadow */
require_once "includes/config.php";

header('Content-Type: application/json');

$check_table_sql = "SHOW TABLES LIKE 'self_destruct'";
$result = mysqli_query($link, $check_table_sql);

$response = [
    'is_active' => false,
    'remaining_seconds' => 0
];

if ($result && mysqli_num_rows($result) > 0) {
    $sql = "SELECT * FROM self_destruct WHERE id = 1";
    $result = mysqli_query($link, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $self_destruct = mysqli_fetch_assoc($result);
        
        $is_active = isset($self_destruct['is_active']) && $self_destruct['is_active'] == 1;
        $countdown_seconds = isset($self_destruct['countdown_seconds']) ? $self_destruct['countdown_seconds'] : 60;
        $start_time = isset($self_destruct['start_time']) ? $self_destruct['start_time'] : null;
        
        $remaining_seconds = 0;
        if ($is_active && $start_time) {
            $start_timestamp = strtotime($start_time);
            $end_timestamp = $start_timestamp + $countdown_seconds;
            $current_timestamp = time();
            $remaining_seconds = max(0, $end_timestamp - $current_timestamp);
            
            if ($remaining_seconds == 0) {
                $update_sql = "UPDATE self_destruct SET is_active = 0 WHERE id = 1";
                mysqli_query($link, $update_sql);
                $is_active = false;
            }
        }
        
        $response['is_active'] = $is_active;
        $response['remaining_seconds'] = $remaining_seconds;
    }
}

echo json_encode($response);
?>
