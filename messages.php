<?php
/* made with love from alex - xshadow */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!defined('DB_SERVER')) {
    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'socialapp_db');
}

if (!isset($link) || !$link) {
    $link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if (!$link) {
        die("Datenbankverbindungsfehler: " . mysqli_connect_error());
    }
}

$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if (!$link) {
    die("Datenbankverbindungsfehler: " . mysqli_connect_error());
}

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: auth/login.php");
    exit;
}

function get_user_by_id($user_id) {
    global $link;
    $user = array();
    
    $sql = "SELECT * FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    if (empty($user['profile_image'])) {
        $user['profile_image'] = 'default.png';
    }
    
    return $user;
}

$current_user = get_user_by_id($_SESSION["id"]);

$messages_table_exists = false;
$check_table_sql = "SHOW TABLES LIKE 'messages'";
$result = mysqli_query($link, $check_table_sql);
if ($result && mysqli_num_rows($result) > 0) {
    $messages_table_exists = true;
} else {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    if (mysqli_query($link, $create_table_sql)) {
        $messages_table_exists = true;
    }
}

$selected_user_id = null;
$selected_user = null;
if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $selected_user_id = $_GET['user_id'];
    $selected_user = get_user_by_id($selected_user_id);
    
    if ($messages_table_exists && $selected_user_id) {
        $update_sql = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
        $update_stmt = mysqli_prepare($link, $update_sql);
        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "ii", $selected_user_id, $_SESSION["id"]);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
    }
}

$message_err = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["message"]) && isset($_POST["receiver_id"])) {
    $message = trim($_POST["message"]);
    $receiver_id = trim($_POST["receiver_id"]);
    
    if (empty($message)) {
        $message_err = "Nachricht darf nicht leer sein.";
    } else {
        $receiver = get_user_by_id($receiver_id);
        if (!$receiver || !isset($receiver["allow_dm"]) || $receiver["allow_dm"] != 1) {
            $message_err = "Dieser Benutzer akzeptiert keine Direktnachrichten.";
        } else {
            $sql = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
            
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iis", $_SESSION["id"], $receiver_id, $message);
                
                if (mysqli_stmt_execute($stmt)) {
                    header("location: messages.php?user_id=" . $receiver_id);
                    exit;
                } else {
                    $message_err = "Etwas ist schiefgelaufen. Bitte versuche es später noch einmal.";
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$conversations = array();
if ($messages_table_exists) {
    $sql = "SELECT DISTINCT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END AS user_id
            FROM messages 
            WHERE sender_id = ? OR receiver_id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $_SESSION["id"], $_SESSION["id"], $_SESSION["id"]);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $user = get_user_by_id($row['user_id']);
                if ($user) {
                    $unread_count = 0;
                    $unread_sql = "SELECT COUNT(*) AS count FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
                    $unread_stmt = mysqli_prepare($link, $unread_sql);
                    if ($unread_stmt) {
                        mysqli_stmt_bind_param($unread_stmt, "ii", $row['user_id'], $_SESSION["id"]);
                        mysqli_stmt_execute($unread_stmt);
                        $unread_result = mysqli_stmt_get_result($unread_stmt);
                        if ($unread_row = mysqli_fetch_assoc($unread_result)) {
                            $unread_count = $unread_row['count'];
                        }
                        mysqli_stmt_close($unread_stmt);
                    }
                    
                    $last_message = "";
                    $last_message_time = "";
                    $last_message_sql = "SELECT message, created_at FROM messages 
                                         WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
                                         ORDER BY created_at DESC LIMIT 1";
                    $last_message_stmt = mysqli_prepare($link, $last_message_sql);
                    if ($last_message_stmt) {
                        mysqli_stmt_bind_param($last_message_stmt, "iiii", $_SESSION["id"], $row['user_id'], $row['user_id'], $_SESSION["id"]);
                        mysqli_stmt_execute($last_message_stmt);
                        $last_message_result = mysqli_stmt_get_result($last_message_stmt);
                        if ($last_message_row = mysqli_fetch_assoc($last_message_result)) {
                            $last_message = $last_message_row['message'];
                            $last_message_time = $last_message_row['created_at'];
                        }
                        mysqli_stmt_close($last_message_stmt);
                    }
                    
                    $conversations[] = array(
                        'user' => $user,
                        'unread_count' => $unread_count,
                        'last_message' => $last_message,
                        'last_message_time' => $last_message_time
                    );
                }
            }
        }
        
        mysqli_stmt_close($stmt);
    }
}

$messages = array();
if ($selected_user_id && $messages_table_exists) {
    $sql = "SELECT * FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
            ORDER BY created_at ASC";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiii", $_SESSION["id"], $selected_user_id, $selected_user_id, $_SESSION["id"]);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $messages[] = $row;
            }
        }
        
        mysqli_stmt_close($stmt);
    }
}

$all_users = array();
$sql = "SELECT * FROM users WHERE id != ? ORDER BY username ASC";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $all_users[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="user-id" content="<?php echo $_SESSION["id"]; ?>">
    <title>Nachrichten - SocialApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #15202B;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            padding: 20px;
            border-right: 1px solid #38444D;
        }
        .content {
            flex: 1;
            display: flex;
        }
        .conversations-list {
            width: 350px;
            border-right: 1px solid #38444D;
            overflow-y: auto;
        }
        .message-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .messages-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        .message-input {
            padding: 15px;
            border-top: 1px solid #38444D;
        }
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-radius: 30px;
            margin-bottom: 8px;
            cursor: pointer;
            color: white;
            text-decoration: none;
        }
        .nav-item:hover {
            background-color: #1e2732;
        }
        .nav-item.active {
            font-weight: bold;
        }
        .nav-item i {
            font-size: 20px;
            margin-right: 16px;
        }
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #38444D;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .conversation-item:hover {
            background-color: #1e2732;
        }
        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 18px;
            margin-bottom: 10px;
        }
        .message-bubble.sent {
            background-color: #1DA1F2;
            margin-left: auto;
        }
        .message-bubble.received {
            background-color: #3A444C;
        }
        .unread-badge {
            background-color: #1DA1F2;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .date-separator {
            position: relative;
            margin: 20px 0;
        }
        .date-separator::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: #38444D;
            z-index: 1;
        }
        .date-separator span {
            position: relative;
            z-index: 2;
            background-color: #15202B;
            padding: 0 10px;
        }
    </style>
    <script src="assets/js/messages.js"></script>
</head>
<body>
    <div class="container">
        <div class="sidebar hidden md:block">
            <div class="mb-6">
                <i class="fab fa-twitter text-3xl text-blue-400"></i>
            </div>
            
            <nav>
                <a href="index.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="explore.php" class="nav-item">
                    <i class="fas fa-hashtag"></i>
                    <span>Explore</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="far fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="messages.php" class="nav-item active">
                    <i class="far fa-envelope"></i>
                    <span>Messages</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="far fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="edit_profile.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="auth/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
                
                <button class="bg-blue-500 text-white w-full py-3 rounded-full font-bold mt-5 hover:bg-blue-600">
                    Post
                </button>
            </nav>
            
            <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($current_user) && !empty($current_user)): ?>
                <div class="mt-4 p-3 flex items-center">
                    <img src="assets/images/<?php echo !empty($current_user['profile_image']) ? htmlspecialchars($current_user['profile_image']) : 'default.png'; ?>" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                    <div>
                        <div class="font-bold"><?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : "User"; ?></div>
                        <div class="text-gray-500">@<?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : "user"; ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <div class="conversations-list">
                <div class="p-4 border-b border-gray-700">
                    <h2 class="text-xl font-bold">Nachrichten</h2>
                </div>
                
                <div class="p-4 border-b border-gray-700">
                    <button id="new-message-btn" class="bg-blue-500 text-white px-4 py-2 rounded-full w-full hover:bg-blue-600">
                        <i class="fas fa-plus mr-2"></i> Neue Nachricht
                    </button>
                </div>
                
                <div id="new-message-form" class="p-4 border-b border-gray-700 hidden">
                    <h3 class="font-bold mb-2">Wähle einen Benutzer zum Chatten:</h3>
                    <div class="max-h-40 overflow-y-auto">
                        <?php foreach($all_users as $user): ?>
                            <a href="messages.php?user_id=<?php echo $user['id']; ?>" class="flex items-center p-2 hover:bg-gray-800 rounded">
                                <img src="assets/images/<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'default.png'; ?>" alt="Profil" class="w-10 h-10 rounded-full mr-3">
                                <div>
                                    <h4 class="font-bold"><?php echo htmlspecialchars($user['username']); ?></h4>
                                    <p class="text-gray-500 text-sm">@<?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if(empty($conversations)): ?>
                    <div class="p-4 text-center text-gray-500">
                        <p>Noch keine Konversationen.</p>
                        <p class="mt-2">Starte eine neue Nachricht, um mit jemandem zu chatten!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($conversations as $conversation): ?>
                        <a href="messages.php?user_id=<?php echo $conversation['user']['id']; ?>" 
                           class="conversation-item flex items-center <?php echo ($selected_user_id == $conversation['user']['id']) ? 'bg-gray-800' : ''; ?>"
                           data-user-id="<?php echo $conversation['user']['id']; ?>">
                            <img src="assets/images/<?php echo !empty($conversation['user']['profile_image']) ? htmlspecialchars($conversation['user']['profile_image']) : 'default.png'; ?>" alt="Profil" class="w-12 h-12 rounded-full mr-3">
                            <div class="flex-1">
                                <div class="flex justify-between">
                                    <h3 class="font-bold"><?php echo htmlspecialchars($conversation['user']['username']); ?></h3>
                                    <?php if(!empty($conversation['last_message_time'])): ?>
                                    <span class="text-gray-500 text-xs"><?php echo date('d.m.', strtotime($conversation['last_message_time'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-gray-400 text-sm truncate"><?php echo !empty($conversation['last_message']) ? htmlspecialchars(substr($conversation['last_message'], 0, 50)) : ''; ?></p>
                            </div>
                            <?php if($conversation['unread_count'] > 0): ?>
                                <div class="unread-badge ml-2">
                                    <?php echo $conversation['unread_count']; ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="message-area">
                <?php if($selected_user): ?>
                    <div class="p-4 border-b border-gray-700 flex items-center">
                        <img src="assets/images/<?php echo !empty($selected_user['profile_image']) ? htmlspecialchars($selected_user['profile_image']) : 'default.png'; ?>" alt="Profil" class="w-10 h-10 rounded-full mr-3">
                        <div>
                            <h3 class="font-bold"><?php echo htmlspecialchars($selected_user['username']); ?></h3>
                            <p class="text-gray-500 text-sm">@<?php echo htmlspecialchars($selected_user['username']); ?></p>
                        </div>
                        <a href="profile.php?username=<?php echo htmlspecialchars($selected_user['username']); ?>" class="ml-auto text-blue-400 hover:underline">
                            <i class="fas fa-user"></i> Profil anzeigen
                        </a>
                    </div>
                    
                    <div class="messages-container" id="messages-container">
                        <?php if(empty($messages)): ?>
                            <div class="text-center text-gray-500 my-10">
                                <p>Noch keine Nachrichten.</p>
                                <p class="mt-2">Sende eine Nachricht, um die Konversation zu beginnen!</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $current_date = '';
                            foreach($messages as $message): 
                                $message_date = date('Y-m-d', strtotime($message['created_at']));
                                if($message_date != $current_date) {
                                    $current_date = $message_date;
                                    echo '<div class="text-center text-gray-500 date-separator" data-date="' . $message_date . '">';
                                    echo '<span>' . date('d. F Y', strtotime($message['created_at'])) . '</span>';
                                    echo '</div>';
                                }
                            ?>
                                <div class="<?php echo ($message['sender_id'] == $_SESSION["id"]) ? 'text-right' : ''; ?>" data-message-id="<?php echo $message['id']; ?>">
                                    <div class="message-bubble <?php echo ($message['sender_id'] == $_SESSION["id"]) ? 'sent' : 'received'; ?>">
                                        <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                        <p class="text-xs mt-1 <?php echo ($message['sender_id'] == $_SESSION["id"]) ? 'text-blue-200' : 'text-gray-400'; ?>">
                                            <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                            <?php if($message['sender_id'] == $_SESSION["id"]): ?>
                                                <?php if($message['is_read']): ?>
                                                    <i class="fas fa-check-double ml-1"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check ml-1"></i>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="message-input">
                        <form id="message-form" data-user-id="<?php echo $selected_user_id; ?>">
                            <div class="flex">
                                <input type="hidden" name="receiver_id" value="<?php echo $selected_user_id; ?>">
                                <textarea name="message" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg p-3 focus:outline-none focus:border-blue-500" placeholder="Schreibe eine Nachricht..." rows="2"></textarea>
                                <button type="submit" class="bg-blue-500 text-white rounded-full w-12 h-12 flex items-center justify-center ml-2 hover:bg-blue-600">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center text-gray-500">
                            <i class="far fa-envelope text-5xl mb-4"></i>
                            <h3 class="text-xl font-bold mb-2">Deine Nachrichten</h3>
                            <p class="mb-4">Sende private Nachrichten an andere Benutzer.</p>
                            <button id="new-message-btn-empty" class="bg-blue-500 text-white px-6 py-3 rounded-full hover:bg-blue-600">
                                <i class="fas fa-plus mr-2"></i> Neue Nachricht
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
