<?php
/* made with love from alex - xshadow */
session_start();

require_once "includes/config.php";
require_once "includes/functions.php";

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

$messages_table_exists = false;
$check_table_sql = "SHOW TABLES LIKE 'messages'";
$result = mysqli_query($link, $check_table_sql);
if ($result && mysqli_num_rows($result) > 0) {
    $messages_table_exists = true;
} else {
    echo json_encode(['success' => false, 'error' => 'Messages-Tabelle existiert nicht']);
    exit;
}

function get_unread_counts($user_id) {
    global $link;
    
    $unread_counts = [];
    
    $sql = "SELECT DISTINCT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END AS user_id
            FROM messages 
            WHERE sender_id = ? OR receiver_id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $partner_id = $row['user_id'];
                
                $unread_sql = "SELECT COUNT(*) AS count FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
                $unread_stmt = mysqli_prepare($link, $unread_sql);
                if ($unread_stmt) {
                    mysqli_stmt_bind_param($unread_stmt, "ii", $partner_id, $user_id);
                    mysqli_stmt_execute($unread_stmt);
                    $unread_result = mysqli_stmt_get_result($unread_stmt);
                    if ($unread_row = mysqli_fetch_assoc($unread_result)) {
                        $unread_count = $unread_row['count'];
                        if ($unread_count > 0) {
                            $unread_counts[$partner_id] = $unread_count;
                        }
                    }
                    mysqli_stmt_close($unread_stmt);
                }
            }
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return $unread_counts;
}

function format_message($message) {
    global $link;
    $sender_id = $message['sender_id'];
    $profile_image = 'default.png';
    
    $sql = "SELECT profile_image FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $sender_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $profile_image = !empty($row['profile_image']) ? $row['profile_image'] : 'default.png';
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    $message['profile_image'] = $profile_image;
    $message['message'] = htmlspecialchars($message['message']);
    
    return $message;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if (($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["message"]) && isset($_POST["receiver_id"]) && isset($_POST['ajax'])) || $action == 'send') {
    $message = trim($_POST["message"]);
    $receiver_id = trim($_POST["receiver_id"]);
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Nachricht darf nicht leer sein.']);
        exit;
    }
    
    $receiver = get_user_by_id($receiver_id);
    if (!$receiver || !isset($receiver["allow_dm"]) || $receiver["allow_dm"] != 1) {
        echo json_encode(['success' => false, 'error' => 'Dieser Benutzer akzeptiert keine Direktnachrichten.']);
        exit;
    }
    
    $sql = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iis", $_SESSION["id"], $receiver_id, $message);
        
        if (mysqli_stmt_execute($stmt)) {
            $message_id = mysqli_insert_id($link);
            
            $get_message_sql = "SELECT * FROM messages WHERE id = ?";
            $get_message_stmt = mysqli_prepare($link, $get_message_sql);
            mysqli_stmt_bind_param($get_message_stmt, "i", $message_id);
            mysqli_stmt_execute($get_message_stmt);
            $result = mysqli_stmt_get_result($get_message_stmt);
            $message_data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($get_message_stmt);
            
            $formatted_message = format_message($message_data);
            
            echo json_encode([
                'success' => true, 
                'message' => $formatted_message
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Fehler beim Senden der Nachricht.']);
            exit;
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'error' => 'Datenbankfehler beim Vorbereiten der Anfrage.']);
        exit;
    }
}

elseif ($action == 'check_new' && isset($_POST['user_id']) && isset($_POST['last_id'])) {
    $user_id = $_SESSION["id"];
    $partner_id = intval($_POST['user_id']);
    $last_id = intval($_POST['last_id']);
    
    $response = ['success' => true, 'messages' => [], 'updated_messages' => []];
    
    $sql = "SELECT * FROM messages 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND id > ? 
            ORDER BY created_at ASC";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiiii", $user_id, $partner_id, $partner_id, $user_id, $last_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $response['messages'][] = format_message($row);
            }
        }
        
        mysqli_stmt_close($stmt);
    }
    
    $update_sql = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
    $update_stmt = mysqli_prepare($link, $update_sql);
    if ($update_stmt) {
        mysqli_stmt_bind_param($update_stmt, "ii", $partner_id, $user_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
    
    $update_check_sql = "SELECT id, is_read FROM messages 
                        WHERE sender_id = ? AND receiver_id = ? 
                        AND id <= ? AND is_read = 1
                        ORDER BY created_at DESC
                        LIMIT 10";
    
    if ($update_check_stmt = mysqli_prepare($link, $update_check_sql)) {
        mysqli_stmt_bind_param($update_check_stmt, "iii", $user_id, $partner_id, $last_id);
        
        if (mysqli_stmt_execute($update_check_stmt)) {
            $result = mysqli_stmt_get_result($update_check_stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $response['updated_messages'][] = $row;
            }
        }
        
        mysqli_stmt_close($update_check_stmt);
    }
    
    $response['unread_counts'] = get_unread_counts($user_id);
    
    echo json_encode($response);
    exit;
}

elseif ($action == 'mark_read' && isset($_POST['user_id'])) {
    $user_id = $_SESSION["id"];
    $partner_id = intval($_POST['user_id']);
    
    $update_sql = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
    $update_stmt = mysqli_prepare($link, $update_sql);
    if ($update_stmt) {
        mysqli_stmt_bind_param($update_stmt, "ii", $partner_id, $user_id);
        mysqli_stmt_execute($update_stmt);
        $affected_rows = mysqli_stmt_affected_rows($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        echo json_encode([
            'success' => true, 
            'marked_read' => $affected_rows,
            'unread_counts' => get_unread_counts($user_id)
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Fehler beim Markieren als gelesen']);
        exit;
    }
}

else {
    echo json_encode(['success' => false, 'error' => 'Ungültige oder fehlende Aktion']);
    exit;
}
