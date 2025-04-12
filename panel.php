<?php
/* made with love from alex - xshadow */
session_start();

require_once "includes/config.php";
require_once "includes/functions.php";

if (!is_logged_in() || ($_SESSION["username"] !== "Admin" && (!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== 1))) {
    header("location: index.php");
    exit;
}

if (isset($_POST["delete_user"]) && isset($_POST["user_id"]) && isset($_POST["delete_reason"]) && isset($_POST["delete_duration"]) && isset($_POST["delete_unit"])) {
    $user_id = $_POST["user_id"];
    $delete_reason = trim($_POST["delete_reason"]);
    $delete_duration = intval($_POST["delete_duration"]);
    $delete_unit = $_POST["delete_unit"];
    $admin_id = $_SESSION["id"];
    
    if ($delete_duration <= 0) {
        $delete_err = "Die Dauer muss größer als 0 sein.";
    } else {
        $check_admin_sql = "SELECT is_admin, username FROM users WHERE id = ?";
        $is_admin = false;
        $username = "";
        
        if ($check_stmt = mysqli_prepare($link, $check_admin_sql)) {
            mysqli_stmt_bind_param($check_stmt, "i", $user_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_bind_result($check_stmt, $admin_status, $username);
            mysqli_stmt_fetch($check_stmt);
            $is_admin = ($admin_status == 1);
            mysqli_stmt_close($check_stmt);
        }
        
        if (!$is_admin && $username !== "Admin") {
            $check_columns = [
                "is_deleted" => "TINYINT(1) DEFAULT 0",
                "deleted_at" => "DATETIME",
                "deleted_by" => "INT",
                "delete_reason" => "TEXT",
                "delete_until" => "DATETIME"
            ];
            
            foreach ($check_columns as $column => $type) {
                $check_column_sql = "SHOW COLUMNS FROM users LIKE '$column'";
                $column_result = mysqli_query($link, $check_column_sql);
                
                if (!$column_result || mysqli_num_rows($column_result) == 0) {
                    $add_column_sql = "ALTER TABLE users ADD COLUMN $column $type";
                    mysqli_query($link, $add_column_sql);
                }
            }
            
            $deleted_at = date('Y-m-d H:i:s');
            
            $delete_until = date('Y-m-d H:i:s');
            switch ($delete_unit) {
                case 'seconds':
                    $delete_until = date('Y-m-d H:i:s', strtotime("+$delete_duration seconds"));
                    break;
                case 'minutes':
                    $delete_until = date('Y-m-d H:i:s', strtotime("+$delete_duration minutes"));
                    break;
                case 'hours':
                    $delete_until = date('Y-m-d H:i:s', strtotime("+$delete_duration hours"));
                    break;
                case 'days':
                    $delete_until = date('Y-m-d H:i:s', strtotime("+$delete_duration days"));
                    break;
                default:
                    $delete_until = date('Y-m-d H:i:s', strtotime("+$delete_duration days"));
            }
            
            $update_sql = "UPDATE users SET is_deleted = 1, deleted_at = ?, deleted_by = ?, delete_reason = ?, delete_until = ? WHERE id = ?";
            
            if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "sissi", $deleted_at, $admin_id, $delete_reason, $delete_until, $user_id);
                
                try {
                    if (mysqli_stmt_execute($update_stmt)) {
                        $log_sql = "INSERT INTO admin_logs (admin_id, action_type, target_user_id, reason, duration, duration_unit) VALUES (?, 'delete', ?, ?, ?, ?)";
                        if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                            mysqli_stmt_bind_param($log_stmt, "iisis", $admin_id, $user_id, $delete_reason, $delete_duration, $delete_unit);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        }
                        
                        mysqli_stmt_close($update_stmt);
                        header("location: panel.php");
                        exit;
                    } else {
                        throw new Exception(mysqli_error($link));
                    }
                } catch (Exception $e) {
                    $delete_err = "Fehler beim Löschen des Benutzers: " . $e->getMessage();
                }
                
                mysqli_stmt_close($update_stmt);
            } else {
                $delete_err = "Fehler beim Vorbereiten der Abfrage: " . mysqli_error($link);
            }
        } else {
            $delete_err = "Admin-Benutzer können nicht gelöscht werden.";
        }
    }
}

if (isset($_POST["restore_user"]) && isset($_POST["user_id"])) {
    $user_id = $_POST["user_id"];
    $admin_id = $_SESSION["id"];
    
    $update_sql = "UPDATE users SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL, delete_reason = NULL, delete_until = NULL WHERE id = ?";
    
    if ($update_stmt = mysqli_prepare($link, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "i", $user_id);
        
        try {
            if (mysqli_stmt_execute($update_stmt)) {
                $log_sql = "INSERT INTO admin_logs (admin_id, action_type, target_user_id) VALUES (?, 'restore', ?)";
                if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                    mysqli_stmt_bind_param($log_stmt, "ii", $admin_id, $user_id);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                }
                
                mysqli_stmt_close($update_stmt);
                header("location: panel.php");
                exit;
            } else {
                throw new Exception(mysqli_error($link));
            }
        } catch (Exception $e) {
            $restore_err = "Fehler beim Wiederherstellen des Benutzers: " . $e->getMessage();
        }
        
        mysqli_stmt_close($update_stmt);
    } else {
        $restore_err = "Fehler beim Vorbereiten der Abfrage: " . mysqli_error($link);
    }
}

if (isset($_POST["permanent_delete_user"]) && isset($_POST["user_id"])) {
    $user_id = $_POST["user_id"];
    $delete_messages = isset($_POST["delete_messages"]) && $_POST["delete_messages"] == 1;
    $admin_id = $_SESSION["id"];
    
    mysqli_begin_transaction($link);
    
    try {
        if ($delete_messages) {
            $check_table_sql = "SHOW TABLES LIKE 'posts'";
            $table_result = mysqli_query($link, $check_table_sql);
            
            if ($table_result && mysqli_num_rows($table_result) > 0) {
                $delete_posts_sql = "DELETE FROM posts WHERE user_id = ?";
                if ($posts_stmt = mysqli_prepare($link, $delete_posts_sql)) {
                    mysqli_stmt_bind_param($posts_stmt, "i", $user_id);
                    mysqli_stmt_execute($posts_stmt);
                    mysqli_stmt_close($posts_stmt);
                }
            }
            
            $check_table_sql = "SHOW TABLES LIKE 'messages'";
            $table_result = mysqli_query($link, $check_table_sql);
            
            if ($table_result && mysqli_num_rows($table_result) > 0) {
                $delete_messages_sql = "DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?";
                if ($messages_stmt = mysqli_prepare($link, $delete_messages_sql)) {
                    mysqli_stmt_bind_param($messages_stmt, "ii", $user_id, $user_id);
                    mysqli_stmt_execute($messages_stmt);
                    mysqli_stmt_close($messages_stmt);
                }
            }
            
            $check_table_sql = "SHOW TABLES LIKE 'comments'";
            $table_result = mysqli_query($link, $check_table_sql);
            
            if ($table_result && mysqli_num_rows($table_result) > 0) {
                $delete_comments_sql = "DELETE FROM comments WHERE user_id = ?";
                if ($comments_stmt = mysqli_prepare($link, $delete_comments_sql)) {
                    mysqli_stmt_bind_param($comments_stmt, "i", $user_id);
                    mysqli_stmt_execute($comments_stmt);
                    mysqli_stmt_close($comments_stmt);
                }
            }
        }
        
        $sql = "DELETE FROM users WHERE id = ? AND is_deleted = 1";
        
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $log_sql = "INSERT INTO admin_logs (admin_id, action_type, target_user_id, reason) VALUES (?, 'permanent_delete', ?, ?)";
                if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                    $reason = $delete_messages ? "Vollständige Löschung mit allen Nachrichten" : "Löschung ohne Nachrichten";
                    mysqli_stmt_bind_param($log_stmt, "iis", $admin_id, $user_id, $reason);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                }
                
                mysqli_stmt_close($stmt);
                
                mysqli_commit($link);
                
                header("location: panel.php");
                exit;
            } else {
                throw new Exception(mysqli_error($link));
            }
            
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception("Fehler beim Vorbereiten der Abfrage: " . mysqli_error($link));
        }
    } catch (Exception $e) {
        mysqli_rollback($link);
        $permanent_delete_err = "Fehler beim endgültigen Löschen des Benutzers: " . $e->getMessage();
    }
}

if (isset($_POST["edit_user"]) && isset($_POST["user_id"]) && isset($_POST["new_username"])) {
    $user_id = $_POST["user_id"];
    $new_username = trim($_POST["new_username"]);
    
    if (empty($new_username)) {
        $edit_err = "Benutzername darf nicht leer sein.";
    } else {
        $sql = "SELECT id FROM users WHERE username = ? AND id != ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $new_username, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $edit_err = "Dieser Benutzername ist bereits vergeben.";
            } else {
                $check_admin_sql = "SELECT is_admin FROM users WHERE id = ?";
                $is_admin = false;
                
                if ($check_stmt = mysqli_prepare($link, $check_admin_sql)) {
                    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
                    mysqli_stmt_execute($check_stmt);
                    mysqli_stmt_bind_result($check_stmt, $admin_status);
                    mysqli_stmt_fetch($check_stmt);
                    $is_admin = ($admin_status == 1);
                    mysqli_stmt_close($check_stmt);
                }
                
                if (!$is_admin || $user_id == $_SESSION["id"]) {
                    $update_sql = "UPDATE users SET username = ? WHERE id = ?";
                    if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                        mysqli_stmt_bind_param($update_stmt, "si", $new_username, $user_id);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                        
                        header("location: panel.php");
                        exit;
                    }
                }
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

if (isset($_POST["mute_user"]) && isset($_POST["user_id"]) && isset($_POST["mute_reason"]) && isset($_POST["mute_duration"]) && isset($_POST["mute_unit"])) {
    $user_id = $_POST["user_id"];
    $mute_reason = trim($_POST["mute_reason"]);
    $mute_duration = intval($_POST["mute_duration"]);
    $mute_unit = $_POST["mute_unit"];
    $admin_id = $_SESSION["id"];
    
    $check_columns = [
        "is_muted" => "TINYINT(1) DEFAULT 0",
        "mute_reason" => "TEXT",
        "mute_until" => "DATETIME",
        "muted_by" => "INT"
    ];
    
    foreach ($check_columns as $column => $type) {
        $check_column_sql = "SHOW COLUMNS FROM users LIKE '$column'";
        $column_result = mysqli_query($link, $check_column_sql);
        
        if (!$column_result || mysqli_num_rows($column_result) == 0) {
            $add_column_sql = "ALTER TABLE users ADD COLUMN $column $type";
            mysqli_query($link, $add_column_sql);
        }
    }
    
    if (empty($mute_reason)) {
        $mute_err = "Grund darf nicht leer sein.";
    } else if ($mute_duration <= 0) {
        $mute_err = "Die Dauer muss größer als 0 sein.";
    } else {
        $check_admin_sql = "SELECT is_admin, username FROM users WHERE id = ?";
        $is_admin = false;
        $username = "";
        
        if ($check_stmt = mysqli_prepare($link, $check_admin_sql)) {
            mysqli_stmt_bind_param($check_stmt, "i", $user_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_bind_result($check_stmt, $admin_status, $username);
            mysqli_stmt_fetch($check_stmt);
            $is_admin = ($admin_status == 1);
            mysqli_stmt_close($check_stmt);
        }
        
        if (!$is_admin && $username !== "Admin") {
            $mute_until = date('Y-m-d H:i:s');
            switch ($mute_unit) {
                case 'seconds':
                    $mute_until = date('Y-m-d H:i:s', strtotime("+$mute_duration seconds"));
                    break;
                case 'minutes':
                    $mute_until = date('Y-m-d H:i:s', strtotime("+$mute_duration minutes"));
                    break;
                case 'hours':
                    $mute_until = date('Y-m-d H:i:s', strtotime("+$mute_duration hours"));
                    break;
                case 'days':
                    $mute_until = date('Y-m-d H:i:s', strtotime("+$mute_duration days"));
                    break;
                default:
                    $mute_until = date('Y-m-d H:i:s', strtotime("+$mute_duration days"));
            }
            
            $update_sql = "UPDATE users SET is_muted = 1, mute_reason = ?, mute_until = ?, muted_by = ? WHERE id = ?";
            
            if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "ssii", $mute_reason, $mute_until, $admin_id, $user_id);
                
                try {
                    if (mysqli_stmt_execute($update_stmt)) {
                        $log_sql = "INSERT INTO admin_logs (admin_id, action_type, target_user_id, reason, duration, duration_unit) VALUES (?, 'mute', ?, ?, ?, ?)";
                        if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                            mysqli_stmt_bind_param($log_stmt, "iisis", $admin_id, $user_id, $mute_reason, $mute_duration, $mute_unit);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        }
                        
                        mysqli_stmt_close($update_stmt);
                        header("location: panel.php");
                        exit;
                    } else {
                        throw new Exception(mysqli_error($link));
                    }
                } catch (Exception $e) {
                    $mute_err = "Fehler beim Stummschalten des Benutzers: " . $e->getMessage();
                }
                
                mysqli_stmt_close($update_stmt);
            } else {
                $mute_err = "Fehler beim Vorbereiten der Abfrage: " . mysqli_error($link);
            }
        } else {
            $mute_err = "Admin-Benutzer können nicht stummgeschaltet werden.";
        }
    }
}

if (isset($_POST["unmute_user"]) && isset($_POST["user_id"])) {
    $user_id = $_POST["user_id"];
    $admin_id = $_SESSION["id"];
    
    $update_sql = "UPDATE users SET is_muted = 0, mute_reason = NULL, mute_until = NULL, muted_by = NULL WHERE id = ?";
    
    if ($update_stmt = mysqli_prepare($link, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "i", $user_id);
        
        try {
            if (mysqli_stmt_execute($update_stmt)) {
                $log_sql = "INSERT INTO admin_logs (admin_id, action_type, target_user_id) VALUES (?, 'unmute', ?)";
                if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                    mysqli_stmt_bind_param($log_stmt, "ii", $admin_id, $user_id);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                }
                
                mysqli_stmt_close($update_stmt);
                header("location: panel.php");
                exit;
            } else {
                throw new Exception(mysqli_error($link));
            }
        } catch (Exception $e) {
            $unmute_err = "Fehler beim Aufheben der Stummschaltung: " . $e->getMessage();
        }
        
        mysqli_stmt_close($update_stmt);
    } else {
        $unmute_err = "Fehler beim Vorbereiten der Abfrage: " . mysqli_error($link);
    }
}

if (isset($_POST["ban_user"]) && isset($_POST["user_id"]) && isset($_POST["ban_reason"]) && isset($_POST["ban_duration"]) && isset($_POST["ban_unit"])) {
    $user_id = $_POST["user_id"];
    $ban_reason = trim($_POST["ban_reason"]);
    $ban_duration = intval($_POST["ban_duration"]);
    $ban_unit = $_POST["ban_unit"];
    $admin_id = $_SESSION["id"];
    
    $check_columns = [
        "is_banned" => "TINYINT(1) DEFAULT 0",
        "ban_reason" => "TEXT",
        "ban_until" => "DATETIME",
        "banned_by" => "INT"
    ];
    
    foreach ($check_columns as $column => $type) {
        $check_column_sql = "SHOW COLUMNS FROM users LIKE '$column'";
        $column_result = mysqli_query($link, $check_column_sql);
        
        if (!$column_result || mysqli_num_rows($column_result) == 0) {
            $add_column_sql = "ALTER TABLE users ADD COLUMN $column $type";
            mysqli_query($link, $add_column_sql);
        }
    }
    
    if (empty($ban_reason)) {
        $ban_err = "Grund darf nicht leer sein.";
    } else if ($ban_duration <= 0) {
        $ban_err = "Die Dauer muss größer als 0 sein.";
    } else {
        $check_admin_sql = "SELECT is_admin, username FROM users WHERE id = ?";
        $is_admin = false;
        $username = "";
        
        if ($check_stmt = mysqli_prepare($link, $check_admin_sql)) {
            mysqli_stmt_bind_param($check_stmt, "i", $user_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_bind_result($check_stmt, $admin_status, $username);
            mysqli_stmt_fetch($check_stmt);
            $is_admin = ($admin_status == 1);
            mysqli_stmt_close($check_stmt);
        }
        
        if (!$is_admin && $username !== "Admin") {
            $ban_until = date('Y-m-d H:i:s');
            switch ($ban_unit) {
                case 'seconds':
                    $ban_until = date('Y-m-d H:i:s', strtotime("+$ban_duration seconds"));
                    break;
                case 'minutes':
                    $ban_until = date('Y-m-d H:i:s', strtotime("+$ban_duration minutes"));
                    break;
                case 'hours':
                    $ban_until = date('Y-m-d H:i:s', strtotime("+$ban_duration hours"));
                    break;
                case 'days':
                    $ban_until = date('Y-m-d H:i:s', strtotime("+$ban_duration days"));
                    break;
                default:
                    $ban_until = date('Y-m-d H:i:s', strtotime("+$ban_duration days"));
            }
            
            $update_sql = "UPDATE users SET is_banned = 1, ban_reason = ?, ban_until = ?, banned_by = ? WHERE id = ?";
            
            if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "ssii", $ban_reason, $ban_until, $admin_id, $user_id);
                
                try {
                    if (mysqli_stmt_execute($update_stmt)) {
                        $log_sql = "INSERT INTO admin_logs (admin_id, action_type, target_user_id, reason, duration, duration_unit) VALUES (?, 'ban', ?, ?, ?, ?)";
                        if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                            mysqli_stmt_bind_param($log_stmt, "iisis", $admin_id, $user_id, $ban_reason, $ban_duration, $ban_unit);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        }
                        
                        $check_session_sql = "SELECT session_id FROM active_sessions WHERE user_id = ?";
                        if ($session_stmt = mysqli_prepare($link, $check_session_sql)) {
                            mysqli_stmt_bind_param($session_stmt, "i", $user_id);
                            if (mysqli_stmt_execute($session_stmt)) {
                                mysqli_stmt_store_result($session_stmt);
                                if (mysqli_stmt_num_rows($session_stmt) > 0) {
                                    mysqli_stmt_bind_result($session_stmt, $session_id);
                                    mysqli_stmt_fetch($session_stmt);
                                    
                                    $delete_session_sql = "DELETE FROM active_sessions WHERE user_id = ?";
                                    if ($delete_stmt = mysqli_prepare($link, $delete_session_sql)) {
                                        mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
                                        mysqli_stmt_execute($delete_stmt);
                                        mysqli_stmt_close($delete_stmt);
                                    }
                                }
                            }
                            mysqli_stmt_close($session_stmt);
                        }
                        
                        mysqli_stmt_close($update_stmt);
                        header("location: panel.php");
                        exit;
                    } else {
                        throw new Exception(mysqli_error($link));
                    }
                } catch (Exception $e) {
                    $ban_err = "Fehler beim Bannen des Benutzers: " . $e->getMessage();
                }
                
                mysqli_stmt_close($update_stmt);
            } else {
                $ban_err = "Fehler beim Vorbereiten der Abfrage: " . mysqli_error($link);
            }
        } else {
            $ban_err = "Admin-Benutzer können nicht gebannt werden.";
        }
    }
}

if (isset($_POST["unban_user"]) && isset($_POST["user_id"])) {
    $user_id = $_POST["user_id"];
    $admin_id = $_SESSION["id"];
    
    $update_sql = "UPDATE users SET is_banned = 0, ban_reason = NULL, ban_until = NULL, banned_by = NULL WHERE id = ?";
    
    if ($update_stmt = mysqli_prepare($link, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "i", $user_id);
        
        try {
            if (mysqli_stmt_execute($update_stmt)) {
                $log_sql = "INSERT INTO admin_logs (admin_id, action_type, target_user_id) VALUES (?, 'unban', ?)";
                if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                    mysqli_stmt_bind_param($log_stmt, "ii", $admin_id, $user_id);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                }
                
                mysqli_stmt_close($update_stmt);
                header("location: panel.php");
                exit;
            } else {
                throw new Exception(mysqli_error($link));
            }
        } catch (Exception $e) {
            $unban_err = "Fehler beim Aufheben des Banns: " . $e->getMessage();
        }
        
        mysqli_stmt_close($update_stmt);
    } else {
        $unban_err = "Fehler beim Vorbereiten der Abfrage: " . mysqli_error($link);
    }
}

if (isset($_POST["make_admin"]) && isset($_POST["user_id"])) {
    $user_id = $_POST["user_id"];
    
    $check_column_sql = "SHOW COLUMNS FROM users LIKE 'is_admin'";
    $column_result = mysqli_query($link, $check_column_sql);
    
    if (!$column_result || mysqli_num_rows($column_result) == 0) {
        $add_column_sql = "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0";
        mysqli_query($link, $add_column_sql);
    }
    
    $update_sql = "UPDATE users SET is_admin = 1 WHERE id = ?";
    
    if ($update_stmt = mysqli_prepare($link, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "i", $user_id);
        
        try {
            if (mysqli_stmt_execute($update_stmt)) {
                mysqli_stmt_close($update_stmt);
                header("location: panel.php");
                exit;
            } else {
                throw new Exception(mysqli_error($link));
            }
        } catch (Exception $e) {
            $admin_err = "Fehler beim Erteilen von Admin-Rechten: " . $e->getMessage();
        }
        
        mysqli_stmt_close($update_stmt);
    } else {
        $admin_err = "Fehler beim Vorbereiten der Abfrage: " . mysqli_error($link);
    }
}

if (isset($_POST["remove_admin"]) && isset($_POST["user_id"])) {
    $user_id = $_POST["user_id"];
    $admin_id = $_SESSION["id"];
    
    $check_sql = "SELECT username FROM users WHERE id = ?";
    
    if ($check_stmt = mysqli_prepare($link, $check_sql)) {
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $username);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);
        
        if ($username !== "Admin") {
            $update_sql = "UPDATE users SET is_admin = 0 WHERE id = ?";
            
            if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "i", $user_id);
                
                try {
                    if (mysqli_stmt_execute($update_stmt)) {
                        $log_sql = "INSERT INTO admin_logs (admin_id, action_type, target_user_id) VALUES (?, 'remove_admin', ?)";
                        if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                            mysqli_stmt_bind_param($log_stmt, "ii", $admin_id, $user_id);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        }
                        
                        if (isset($_SESSION["id"]) && $_SESSION["id"] == $user_id) {
                            session_unset();
                            session_destroy();
                            
                            header("location: auth/login.php");
                            exit;
                        }
                        
                        mysqli_stmt_close($update_stmt);
                        header("location: panel.php");
                        exit;
                    } else {
                        throw new Exception(mysqli_error($link));
                    }
                } catch (Exception $e) {
                    $admin_err = "Fehler beim Entziehen von Admin-Rechten: " . $e->getMessage();
                }
                
                mysqli_stmt_close($update_stmt);
            } else {
                $admin_err = "Fehler beim Vorbereiten der Abfrage: " . mysqli_error($link);
            }
        }
    }
}

$announcement_err = "";
$announcement_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["announcement"])) {
    $announcement = trim($_POST["announcement"]);
    
    if (empty($announcement)) {
        $announcement_err = "Ankündigung darf nicht leer sein.";
    } else {
        $check_table_sql = "SHOW TABLES LIKE 'announcements'";
        $result = mysqli_query($link, $check_table_sql);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            $create_table_sql = "CREATE TABLE announcements (
                id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            mysqli_query($link, $create_table_sql);
        }
        
        $sql = "INSERT INTO announcements (message) VALUES (?)";
        
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $announcement);
            
            if (mysqli_stmt_execute($stmt)) {
                $announcement_success = "Ankündigung erfolgreich erstellt.";
            } else {
                $announcement_err = "Fehler beim Erstellen der Ankündigung.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

if (isset($_POST["delete_announcement"]) && isset($_POST["announcement_id"])) {
    $announcement_id = $_POST["announcement_id"];
    
    $sql = "DELETE FROM announcements WHERE id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $announcement_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        header("location: panel.php");
        exit;
    }
}

$check_table_sql = "SHOW TABLES LIKE 'admin_logs'";
$result = mysqli_query($link, $check_table_sql);

if (!$result || mysqli_num_rows($result) == 0) {
    $create_table_sql = "CREATE TABLE admin_logs (
        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
        admin_id INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        target_user_id INT,
        reason TEXT,
        duration INT,
        duration_unit VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($link, $create_table_sql);
}

$check_table_sql = "SHOW TABLES LIKE 'active_sessions'";
$result = mysqli_query($link, $check_table_sql);

if (!$result || mysqli_num_rows($result) == 0) {
    $create_table_sql = "CREATE TABLE active_sessions (
        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id),
        UNIQUE KEY (session_id)
    )";
    mysqli_query($link, $create_table_sql);
}

$users = array();
$sql = "SELECT * FROM users ORDER BY username ASC";
$result = mysqli_query($link, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

$announcements = array();
$check_table_sql = "SHOW TABLES LIKE 'announcements'";
$result = mysqli_query($link, $check_table_sql);

if ($result && mysqli_num_rows($result) > 0) {
    $sql = "SELECT * FROM announcements ORDER BY created_at DESC";
    $result = mysqli_query($link, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $announcements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - SocialApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #15202B;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
    </style>
</head>
<body class="bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Admin Panel</h1>
            <a href="index.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                <i class="fas fa-arrow-left mr-2"></i>Zurück zur Startseite
            </a>
        </div>
        
        <div class="bg-gray-800 rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold mb-4 text-white">Benutzer-Verwaltung</h2>
            
            <?php if(isset($admin_err)): ?>
                <div class="bg-red-500 text-white p-3 mb-4 rounded">
                    <?php echo $admin_err; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($mute_err)): ?>
                <div class="bg-red-500 text-white p-3 mb-4 rounded">
                    <?php echo $mute_err; ?>
                </div>
            <?php endif; ?>
            
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="py-2 text-left text-white">ID</th>
                        <th class="py-2 text-left text-white">Benutzername</th>
                        <th class="py-2 text-left text-white">E-Mail</th>
                        <th class="py-2 text-left text-white">Status</th>
                        <th class="py-2 text-left text-white">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                        <tr class="border-b border-gray-700">
                            <td class="py-2 text-white"><?php echo $user['id']; ?></td>
                            <td class="py-2 text-white">
                                <?php echo htmlspecialchars($user['username']); ?>
                                <?php if($user['is_admin'] == 1): ?>
                                    <span class="ml-2 bg-blue-500 text-white text-xs px-2 py-1 rounded">Admin</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 text-white"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="py-2 text-white">
                                <?php if($user['is_deleted'] == 1): ?>
                                    <span class="text-gray-500">
                                        <i class="fas fa-trash mr-1"></i>
                                        Gelöscht am <?php echo date('d.m.Y H:i', strtotime($user['deleted_at'])); ?>
                                    </span>
                                <?php elseif($user['is_banned'] == 1): ?>
                                    <?php if(isset($user['ban_until']) && $user['ban_until'] > date('Y-m-d H:i:s')): ?>
                                        <span class="text-red-500">
                                            <i class="fas fa-ban mr-1"></i>
                                            Gebannt bis <?php echo date('d.m.Y H:i', strtotime($user['ban_until'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-green-500">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Bann abgelaufen (<?php echo date('d.m.Y H:i', strtotime($user['ban_until'])); ?>)
                                        </span>
                                    <?php endif; ?>
                                <?php elseif($user['is_muted'] == 1): ?>
                                    <?php if(isset($user['mute_until']) && $user['mute_until'] > date('Y-m-d H:i:s')): ?>
                                        <span class="text-yellow-500">
                                            <i class="fas fa-volume-mute mr-1"></i>
                                            Stummgeschaltet bis <?php echo date('d.m.Y H:i', strtotime($user['mute_until'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-green-500">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Stummschaltung abgelaufen (<?php echo date('d.m.Y H:i', strtotime($user['mute_until'])); ?>)
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-green-500">Aktiv</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 flex flex-wrap gap-2">
                                <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if($user['is_deleted'] == 1): ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="restore_user" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                                            <i class="fas fa-trash-restore"></i>
                                        </button>
                                    </form>
                                    
                                    <?php if(strtotime($user['deleted_at']) < strtotime('-7 days')): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="permanent_delete_user" class="bg-red-700 text-white px-3 py-1 rounded hover:bg-red-800">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif($user['username'] !== "Admin"): ?>
                                    <button onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if(!$user['is_deleted'] && $user['username'] !== "Admin"): ?>
                                    <?php if($user['is_muted'] == 1): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="unmute_user" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                                                <i class="fas fa-volume-up"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button onclick="openMuteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600">
                                            <i class="fas fa-volume-mute"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if(!$user['is_deleted'] && $user['username'] !== "Admin"): ?>
                                    <?php if($user['is_banned'] == 1): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="unban_user" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button onclick="openBanModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if(!$user['is_deleted']): ?>
                                    <?php if($user['is_admin'] == 0 && $user['username'] !== "Admin"): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="make_admin" class="bg-purple-500 text-white px-3 py-1 rounded hover:bg-purple-600">
                                                <i class="fas fa-user-shield"></i>
                                            </button>
                                        </form>
                                    <?php elseif($user['is_admin'] == 1 && $user['username'] !== "Admin"): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="remove_admin" class="bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600">
                                                <i class="fas fa-user"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="bg-gray-800 rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold mb-4 text-white">Ankündigungen</h2>
            
            <div class="mb-8">
                <h3 class="text-xl font-bold mb-2 text-white">Neue Ankündigung erstellen</h3>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-4">
                        <textarea name="announcement" class="w-full bg-gray-700 text-white p-3 rounded" rows="3" placeholder="Gib hier deine Ankündigung ein..."></textarea>
                        <?php if(!empty($announcement_err)): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $announcement_err; ?></p>
                        <?php endif; ?>
                        <?php if(!empty($announcement_success)): ?>
                            <p class="text-green-500 text-sm mt-1"><?php echo $announcement_success; ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Ankündigung erstellen</button>
                </form>
            </div>
            
            <div>
                <h3 class="text-xl font-bold mb-2 text-white">Bestehende Ankündigungen</h3>
                <?php if(empty($announcements)): ?>
                    <p class="text-gray-400">Keine Ankündigungen vorhanden.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach($announcements as $announcement): ?>
                            <div class="bg-gray-700 p-4 rounded">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-white mb-2"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                                        <p class="text-gray-400 text-sm"><?php echo date('d.m.Y H:i', strtotime($announcement['created_at'])); ?></p>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                        <button type="submit" name="delete_announcement" class="text-red-500 hover:text-red-400">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-red-900 rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold mb-4 text-white flex items-center">
                <i class="fas fa-bomb mr-2"></i> Selbstzerstörung
                <span class="ml-2 text-sm bg-red-600 text-white px-2 py-1 rounded">NUR IM NOTFALL!</span>
            </h2>
            
            <?php
            $check_table_sql = "SHOW TABLES LIKE 'self_destruct'";
            $result = mysqli_query($link, $check_table_sql);
            
            if (!$result || mysqli_num_rows($result) == 0) {
                $create_table_sql = "CREATE TABLE self_destruct (
                    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                    is_active TINYINT(1) DEFAULT 0,
                    countdown_seconds INT DEFAULT 60,
                    start_time DATETIME,
                    initiated_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                mysqli_query($link, $create_table_sql);
                
                $insert_sql = "INSERT INTO self_destruct (is_active, countdown_seconds) VALUES (0, 60)";
                mysqli_query($link, $insert_sql);
            }
            
            if (isset($_POST["activate_self_destruct"]) && isset($_POST["countdown_seconds"])) {
                $countdown_seconds = intval($_POST["countdown_seconds"]);
                $admin_id = $_SESSION["id"];
                
                if ($countdown_seconds < 10) {
                    $countdown_seconds = 10; // Mindestens 10 Sekunden
                }
                
                $start_time = date('Y-m-d H:i:s');
                
                $update_sql = "UPDATE self_destruct SET is_active = 1, countdown_seconds = ?, start_time = ?, initiated_by = ? WHERE id = 1";
                
                if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "isi", $countdown_seconds, $start_time, $admin_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $log_sql = "INSERT INTO admin_logs (admin_id, action_type, duration) VALUES (?, 'activate_self_destruct', ?)";
                        if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                            mysqli_stmt_bind_param($log_stmt, "ii", $admin_id, $countdown_seconds);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        }
                    }
                    
                    mysqli_stmt_close($update_stmt);
                }
            }
            
            if (isset($_POST["deactivate_self_destruct"])) {
                $admin_id = $_SESSION["id"];
                
                $update_sql = "UPDATE self_destruct SET is_active = 0, start_time = NULL WHERE id = 1";
                
                if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                    if (mysqli_stmt_execute($update_stmt)) {
                        $log_sql = "INSERT INTO admin_logs (admin_id, action_type) VALUES (?, 'deactivate_self_destruct')";
                        if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                            mysqli_stmt_bind_param($log_stmt, "i", $admin_id);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        }
                    }
                    
                    mysqli_stmt_close($update_stmt);
                }
            }
            
            $self_destruct = array();
            $sql = "SELECT * FROM self_destruct WHERE id = 1";
            $result = mysqli_query($link, $sql);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $self_destruct = mysqli_fetch_assoc($result);
            }
            
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
            ?>
            
            <div class="bg-red-800 p-6 rounded-lg mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-white">Status:</h3>
                        <?php if ($is_active): ?>
                            <div class="flex items-center mt-2">
                                <span class="inline-block w-3 h-3 bg-red-500 rounded-full mr-2 animate-pulse"></span>
                                <span class="text-red-300 font-bold">AKTIV - Selbstzerstörung in <span id="countdown"><?php echo $remaining_seconds; ?></span> Sekunden</span>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center mt-2">
                                <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                <span class="text-green-300">Inaktiv</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($is_active): ?>
                        <form method="post" class="inline">
                            <button type="submit" name="deactivate_self_destruct" class="bg-green-500 text-white px-4 py-2 rounded font-bold hover:bg-green-600">
                                <i class="fas fa-stop-circle mr-2"></i>STOPPEN
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if (!$is_active): ?>
                    <form method="post" class="mt-4">
                        <div class="mb-4">
                            <label for="countdown_seconds" class="block text-white mb-2">Countdown-Zeit (in Sekunden):</label>
                            <input type="number" name="countdown_seconds" id="countdown_seconds" class="w-full bg-gray-700 text-white p-2 rounded" min="10" value="<?php echo $countdown_seconds; ?>">
                        </div>
                        
                        <div class="flex items-center">
                            <button type="submit" name="activate_self_destruct" class="bg-red-500 text-white px-4 py-2 rounded font-bold hover:bg-red-600">
                                <i class="fas fa-skull-crossbones mr-2"></i>Selbstzerstörung aktivieren
                            </button>
                            <div class="ml-4 text-yellow-300">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                WARNUNG: Diese Aktion löscht ALLE Daten unwiderruflich!
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="bg-gray-900 p-4 rounded-lg">
                <h3 class="text-lg font-bold mb-2 text-white">Was passiert bei der Selbstzerstörung?</h3>
                <ul class="list-disc list-inside text-gray-300 space-y-2">
                    <li>Alle Benutzer erhalten einen Countdown-Popup</li>
                    <li>Nach Ablauf des Countdowns werden <span class="text-red-400 font-bold">ALLE</span> Daten aus der Datenbank gelöscht</li>
                    <li>Alle Benutzer werden ausgeloggt</li>
                    <li>Eine Abschiedsnachricht wird angezeigt</li>
                    <li>Die Anwendung wird unbrauchbar und muss neu installiert werden</li>
                </ul>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold mb-4 text-white">Admin Logs</h2>
            
            <?php
            $logs = array();
            $sql = "SELECT al.*, a.username as admin_username, u.username as target_username 
                   FROM admin_logs al 
                   LEFT JOIN users a ON al.admin_id = a.id 
                   LEFT JOIN users u ON al.target_user_id = u.id 
                   ORDER BY al.created_at DESC 
                   LIMIT 50";
            $result = mysqli_query($link, $sql);
            
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $logs[] = $row;
                }
            }
            ?>
            
            <div class="bg-gray-900 p-4 rounded-lg text-white font-mono text-sm h-96 overflow-y-auto">
                <?php if(empty($logs)): ?>
                    <p class="text-gray-400">Keine Logs vorhanden.</p>
                <?php else: ?>
                    <?php foreach($logs as $log): ?>
                        <div class="mb-2 border-b border-gray-700 pb-2">
                            <span class="text-gray-400">[<?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?>]</span>
                            <span class="text-blue-400"><?php echo htmlspecialchars($log['admin_username']); ?></span>
                            
                            <?php if($log['action_type'] == 'ban'): ?>
                                <span class="text-white">hat</span>
                                <span class="text-yellow-400"><?php echo htmlspecialchars($log['target_username']); ?></span>
                                <span class="text-red-400">gebannt</span>
                                <span class="text-white">für</span>
                                <span class="text-green-400"><?php echo $log['duration'] . ' ' . $log['duration_unit']; ?></span>
                                <span class="text-white">mit Grund:</span>
                                <span class="text-purple-400">"<?php echo htmlspecialchars($log['reason']); ?>"</span>
                            
                            <?php elseif($log['action_type'] == 'unban'): ?>
                                <span class="text-white">hat</span>
                                <span class="text-yellow-400"><?php echo htmlspecialchars($log['target_username']); ?></span>
                                <span class="text-green-400">entbannt</span>
                            
                            <?php elseif($log['action_type'] == 'mute'): ?>
                                <span class="text-white">hat</span>
                                <span class="text-yellow-400"><?php echo htmlspecialchars($log['target_username']); ?></span>
                                <span class="text-orange-400">stummgeschaltet</span>
                                <span class="text-white">für</span>
                                <span class="text-green-400"><?php echo $log['duration'] . ' ' . $log['duration_unit']; ?></span>
                                <span class="text-white">mit Grund:</span>
                                <span class="text-purple-400">"<?php echo htmlspecialchars($log['reason']); ?>"</span>
                            
                            <?php elseif($log['action_type'] == 'unmute'): ?>
                                <span class="text-white">hat</span>
                                <span class="text-yellow-400"><?php echo htmlspecialchars($log['target_username']); ?></span>
                                <span class="text-green-400">entstummt</span>
                            
                            <?php elseif($log['action_type'] == 'delete'): ?>
                                <span class="text-white">hat</span>
                                <span class="text-yellow-400"><?php echo htmlspecialchars($log['target_username']); ?></span>
                                <span class="text-red-400">gelöscht</span>
                                <span class="text-white">mit Grund:</span>
                                <span class="text-purple-400">"<?php echo htmlspecialchars($log['reason']); ?>"</span>
                            
                            <?php elseif($log['action_type'] == 'restore'): ?>
                                <span class="text-white">hat</span>
                                <span class="text-yellow-400"><?php echo htmlspecialchars($log['target_username']); ?></span>
                                <span class="text-green-400">wiederhergestellt</span>
                            
                            <?php elseif($log['action_type'] == 'remove_admin'): ?>
                                <span class="text-white">hat</span>
                                <span class="text-yellow-400"><?php echo htmlspecialchars($log['target_username']); ?></span>
                                <span class="text-red-400">Admin-Rechte entzogen</span>
                            
                            <?php else: ?>
                                <span class="text-white">hat Aktion</span>
                                <span class="text-purple-400"><?php echo htmlspecialchars($log['action_type']); ?></span>
                                <span class="text-white">auf</span>
                                <span class="text-yellow-400"><?php echo htmlspecialchars($log['target_username']); ?></span>
                                <span class="text-white">ausgeführt</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-gray-800 p-6 rounded-lg w-full max-w-md">
            <h3 class="text-xl font-bold mb-4 text-white">Benutzer bearbeiten</h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" id="editUserId" name="user_id">
                <div class="mb-4">
                    <label for="new_username" class="block text-white mb-2">Neuer Benutzername:</label>
                    <input type="text" id="editUsername" name="new_username" class="w-full bg-gray-700 text-white p-2 rounded">
                    <?php if(isset($edit_err)): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $edit_err; ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-500">Abbrechen</button>
                    <button type="submit" name="edit_user" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Speichern</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="muteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-gray-800 p-6 rounded-lg w-full max-w-md">
            <h3 class="text-xl font-bold mb-4 text-white">Benutzer stummschalten</h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" id="muteUserId" name="user_id">
                <div class="mb-4">
                    <label for="mute_reason" class="block text-white mb-2">Grund:</label>
                    <textarea name="mute_reason" id="muteReason" class="w-full bg-gray-700 text-white p-2 rounded" rows="3" placeholder="Grund für die Stummschaltung..."></textarea>
                    <?php if(isset($mute_err)): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $mute_err; ?></p>
                    <?php endif; ?>
                </div>
                <div class="mb-4">
                    <label for="mute_duration" class="block text-white mb-2">Dauer:</label>
                    <div class="flex space-x-2">
                        <input type="number" name="mute_duration" class="w-full bg-gray-700 text-white p-2 rounded" min="1" value="1">
                        <select name="mute_unit" class="bg-gray-700 text-white p-2 rounded">
                            <option value="seconds">Sekunden</option>
                            <option value="minutes">Minuten</option>
                            <option value="hours">Stunden</option>
                            <option value="days" selected>Tage</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeMuteModal()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-500">Abbrechen</button>
                    <button type="submit" name="mute_user" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">Stummschalten</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="banModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-gray-800 p-6 rounded-lg w-full max-w-md">
            <h3 class="text-xl font-bold mb-4 text-white">Benutzer bannen</h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" id="banUserId" name="user_id">
                <div class="mb-4">
                    <label for="ban_reason" class="block text-white mb-2">Grund:</label>
                    <textarea name="ban_reason" id="banReason" class="w-full bg-gray-700 text-white p-2 rounded" rows="3" placeholder="Grund für den Bann..."></textarea>
                    <?php if(isset($ban_err)): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $ban_err; ?></p>
                    <?php endif; ?>
                </div>
                <div class="mb-4">
                    <label for="ban_duration" class="block text-white mb-2">Dauer:</label>
                    <div class="flex space-x-2">
                        <input type="number" name="ban_duration" class="w-full bg-gray-700 text-white p-2 rounded" min="1" value="1">
                        <select name="ban_unit" class="bg-gray-700 text-white p-2 rounded">
                            <option value="seconds">Sekunden</option>
                            <option value="minutes">Minuten</option>
                            <option value="hours">Stunden</option>
                            <option value="days" selected>Tage</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeBanModal()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-500">Abbrechen</button>
                    <button type="submit" name="ban_user" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Bannen</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-gray-800 p-6 rounded-lg w-full max-w-md">
            <h3 class="text-xl font-bold mb-4 text-white">Benutzer löschen</h3>
            <p class="text-gray-300 mb-4">Der Benutzer wird für 7 Tage als gelöscht markiert und kann in dieser Zeit wiederhergestellt werden.</p>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" id="deleteUserId" name="user_id">
                <div class="mb-4">
                    <label for="delete_reason" class="block text-white mb-2">Grund:</label>
                    <textarea name="delete_reason" id="deleteReason" class="w-full bg-gray-700 text-white p-2 rounded" rows="3" placeholder="Grund für die Löschung..."></textarea>
                    <?php if(isset($delete_err)): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $delete_err; ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeDeleteModal()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-500">Abbrechen</button>
                    <button type="submit" name="delete_user" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Löschen</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let countdownInterval;
        let remainingSeconds = <?php echo $remaining_seconds; ?>;
        
        function updateCountdown() {
            const countdownElement = document.getElementById('countdown');
            if (countdownElement) {
                countdownElement.textContent = remainingSeconds;
                
                if (remainingSeconds <= 0) {
                    clearInterval(countdownInterval);
                    location.reload();
                } else {
                    remainingSeconds--;
                }
            }
        }
        
        function checkSelfDestructStatus() {
            fetch('check_self_destruct.php')
                .then(response => response.json())
                .then(data => {
                    const statusContainer = document.querySelector('.bg-red-800');
                    const statusIndicator = statusContainer.querySelector('.flex.items-center.mt-2');
                    const formContainer = statusContainer.querySelector('form.mt-4');
                    const stopButton = statusContainer.querySelector('form.inline');
                    
                    if (data.is_active) {
                        if (statusIndicator) {
                            statusIndicator.innerHTML = `
                                <span class="inline-block w-3 h-3 bg-red-500 rounded-full mr-2 animate-pulse"></span>
                                <span class="text-red-300 font-bold">AKTIV - Selbstzerstörung in <span id="countdown">${data.remaining_seconds}</span> Sekunden</span>
                            `;
                        }
                        
                        if (!stopButton && formContainer) {
                            const stopButtonHTML = `
                                <form method="post" class="inline">
                                    <button type="submit" name="deactivate_self_destruct" class="bg-green-500 text-white px-4 py-2 rounded font-bold hover:bg-green-600">
                                        <i class="fas fa-stop-circle mr-2"></i>STOPPEN
                                    </button>
                                </form>
                            `;
                            
                            const buttonContainer = document.querySelector('.flex.items-center.justify-between.mb-4');
                            if (buttonContainer) {
                                const div = document.createElement('div');
                                div.innerHTML = stopButtonHTML;
                                buttonContainer.appendChild(div.firstChild);
                            }
                        }
                        
                        if (formContainer) {
                            formContainer.style.display = 'none';
                        }
                        
                        if (!countdownInterval) {
                            remainingSeconds = data.remaining_seconds;
                            countdownInterval = setInterval(updateCountdown, 1000);
                        } else {
                            if (Math.abs(remainingSeconds - data.remaining_seconds) > 2) {
                                remainingSeconds = data.remaining_seconds;
                            }
                        }
                    } else {
                        if (statusIndicator) {
                            statusIndicator.innerHTML = `
                                <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                <span class="text-green-300">Inaktiv</span>
                            `;
                        }
                        
                        if (stopButton) {
                            stopButton.remove();
                        }
                        
                        if (formContainer) {
                            formContainer.style.display = 'block';
                        }
                        
                        if (countdownInterval) {
                            clearInterval(countdownInterval);
                            countdownInterval = null;
                        }
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Überprüfen des Selbstzerstörungsstatus:', error);
                });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            if (<?php echo $is_active ? 'true' : 'false'; ?>) {
                countdownInterval = setInterval(updateCountdown, 1000);
            }
            
            setInterval(checkSelfDestructStatus, 5000);
        });
        
        function openEditModal(userId, username) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUsername').value = username;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        function openMuteModal(userId, username) {
            document.getElementById('muteUserId').value = userId;
            document.getElementById('muteModal').classList.remove('hidden');
        }
        
        function closeMuteModal() {
            document.getElementById('muteModal').classList.add('hidden');
        }
        
        function openBanModal(userId, username) {
            document.getElementById('banUserId').value = userId;
            document.getElementById('banModal').classList.remove('hidden');
        }
        
        function closeBanModal() {
            document.getElementById('banModal').classList.add('hidden');
        }
        
        function openDeleteModal(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
    </script>
</body>
</html>
