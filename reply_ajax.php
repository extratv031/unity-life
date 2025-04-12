<?php
/* made with love from alex - xshadow */
session_start();

require_once "includes/config.php";
require_once "includes/functions.php";

if (!function_exists('get_replies')) {
    require_once "includes/reply_functions.php";
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

if (function_exists('is_muted')) {
    $mute_status = is_muted($_SESSION["id"]);
    if ($mute_status['is_muted']) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Du bist stummgeschaltet bis ' . date('d.m.Y H:i', strtotime($mute_status['mute_until'])) . '. Grund: ' . $mute_status['mute_reason']
        ]);
        exit;
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'add':
        if (!isset($_POST['post_id']) || !isset($_POST['content']) || empty(trim($_POST['content']))) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Fehlende Daten']);
            exit;
        }
        
        $post_id = intval($_POST['post_id']);
        $user_id = $_SESSION["id"];
        $content = trim($_POST['content']);
        
        $success = add_reply($post_id, $user_id, $content);
        
        if ($success) {
            $replies = get_replies($post_id);
            $reply_count = count_replies($post_id);
            
            $new_reply = end($replies);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'reply' => $new_reply,
                'reply_count' => $reply_count
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Fehler beim Hinzufügen der Antwort']);
        }
        break;
        
    case 'get':
        if (!isset($_POST['post_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Keine Post-ID angegeben']);
            exit;
        }
        
        $post_id = intval($_POST['post_id']);
        
        $replies = get_replies($post_id);
        $reply_count = count_replies($post_id);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'replies' => $replies,
            'reply_count' => $reply_count
        ]);
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Ungültige Aktion']);
        break;
}
