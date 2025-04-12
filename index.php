<?php
/* made with love from alex - xshadow */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();


if (file_exists("includes/functions.php")) {
    require_once "includes/functions.php";
} else {
    function is_logged_in() {
        return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
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
}

if (function_exists('check_user_status')) {
    check_user_status();
}

if (!is_logged_in()) {
    header("location: auth/login.php");
    exit;
}

if (isset($_POST["like_action"]) && isset($_POST["post_id"])) {
    $post_id = $_POST["post_id"];
    
    toggle_like($_SESSION["id"], $post_id);
    
    header("location: index.php");
    exit;
}

if (isset($_SESSION["id"])) {
    $user_id = $_SESSION["id"];
    $session_id = session_id();
    
    $check_table_sql = "SHOW TABLES LIKE 'active_sessions'";
    $table_result = mysqli_query($link, $check_table_sql);
    
    if ($table_result && mysqli_num_rows($table_result) > 0) {
        $check_sql = "SELECT id FROM active_sessions WHERE user_id = ? AND session_id = ?";
        if ($check_stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($check_stmt, "is", $user_id, $session_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) == 0) {
                $insert_sql = "INSERT INTO active_sessions (user_id, session_id) VALUES (?, ?)";
                if ($insert_stmt = mysqli_prepare($link, $insert_sql)) {
                    mysqli_stmt_bind_param($insert_stmt, "is", $user_id, $session_id);
                    mysqli_stmt_execute($insert_stmt);
                    mysqli_stmt_close($insert_stmt);
                }
            }
            
            mysqli_stmt_close($check_stmt);
        }
    }
}

$users_table_exists = false;
$check_users_sql = "SHOW TABLES LIKE 'users'";
$users_result = mysqli_query($link, $check_users_sql);
if ($users_result && mysqli_num_rows($users_result) > 0) {
    $users_table_exists = true;
}

if (!$users_table_exists) {
    header("location: install.php");
    exit;
}

$user = [];
if (isset($_SESSION["id"])) {
    $user = get_user_by_id($_SESSION["id"]);
    if (empty($user)) {
        session_destroy();
        header("location: auth/login.php");
        exit;
    }
}

if (!function_exists('has_liked_post')) {
    function has_liked_post($user_id, $post_id) {
        global $link;
        
        $result = false;
        
        if ($link) {
            $check_table_sql = "SHOW TABLES LIKE 'likes'";
            $table_result = mysqli_query($link, $check_table_sql);
            
            if ($table_result && mysqli_num_rows($table_result) > 0) {
                $sql = "SELECT * FROM likes WHERE user_id = ? AND post_id = ?";
                
                if($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
                    
                    if(mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_store_result($stmt);
                        
                        if(mysqli_stmt_num_rows($stmt) > 0) {
                            $result = true;
                        }
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        return $result;
    }
}

if (!function_exists('count_likes')) {
    function count_likes($post_id) {
        global $link;
        
        $count = 0;
        
        if ($link) {
            $check_table_sql = "SHOW TABLES LIKE 'likes'";
            $table_result = mysqli_query($link, $check_table_sql);
            
            if ($table_result && mysqli_num_rows($table_result) > 0) {
                $sql = "SELECT COUNT(*) as count FROM likes WHERE post_id = ?";
                
                if($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "i", $post_id);
                    
                    if(mysqli_stmt_execute($stmt)) {
                        $result = mysqli_stmt_get_result($stmt);
                        $row = mysqli_fetch_assoc($result);
                        $count = $row['count'];
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        return $count;
    }
}

if (!function_exists('toggle_like')) {
    function toggle_like($user_id, $post_id) {
        global $link;
        
        if ($link) {
            if (has_liked_post($user_id, $post_id)) {
                $sql = "DELETE FROM likes WHERE user_id = ? AND post_id = ?";
                
                if($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    return false; // Hat nicht mehr geliked
                }
            } else {
                $sql = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
                
                if($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    return true; // Hat jetzt geliked
                }
            }
        }
        
        return null; // Fehler
    }
}

if (!function_exists('count_replies')) {
    function count_replies($post_id) {
        global $link;
        
        $count = 0;
        
        if ($link) {
            $check_table_sql = "SHOW TABLES LIKE 'replies'";
            $table_result = mysqli_query($link, $check_table_sql);
            
            if ($table_result && mysqli_num_rows($table_result) > 0) {
                $sql = "SELECT COUNT(*) as count FROM replies WHERE post_id = ?";
                
                if($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "i", $post_id);
                    
                    if(mysqli_stmt_execute($stmt)) {
                        $result = mysqli_stmt_get_result($stmt);
                        $row = mysqli_fetch_assoc($result);
                        $count = $row['count'];
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        return $count;
    }
}

if (!function_exists('get_replies')) {
    function get_replies($post_id) {
        global $link;
        
        $replies = array();
        
        if ($link) {
            $check_table_sql = "SHOW TABLES LIKE 'replies'";
            $table_result = mysqli_query($link, $check_table_sql);
            
            if ($table_result && mysqli_num_rows($table_result) > 0) {
                $sql = "SELECT r.*, u.username, u.profile_image 
                       FROM replies r 
                       JOIN users u ON r.user_id = u.id 
                       WHERE r.post_id = ? 
                       ORDER BY r.created_at ASC";
                
                if($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "i", $post_id);
                    
                    if(mysqli_stmt_execute($stmt)) {
                        $result = mysqli_stmt_get_result($stmt);
                        
                        while ($row = mysqli_fetch_assoc($result)) {
                            $replies[] = $row;
                        }
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        return $replies;
    }
}

$posts = array();

if (isset($_SESSION["id"]) && $_SESSION["id"]) {
    $follows_table_exists = false;
    $check_follows_sql = "SHOW TABLES LIKE 'follows'";
    $follows_result = mysqli_query($link, $check_follows_sql);
    if ($follows_result && mysqli_num_rows($follows_result) > 0) {
        $follows_table_exists = true;
    }
    
    $posts_table_exists = false;
    $check_posts_sql = "SHOW TABLES LIKE 'posts'";
    $posts_result = mysqli_query($link, $check_posts_sql);
    if ($posts_result && mysqli_num_rows($posts_result) > 0) {
        $posts_table_exists = true;
    }
    
    if ($follows_table_exists && $posts_table_exists) {
        try {
            $sql = "SELECT p.*, u.username, u.profile_image 
                   FROM posts p 
                   JOIN users u ON p.user_id = u.id 
                   WHERE p.user_id IN (
                       SELECT following_id FROM follows WHERE follower_id = ?
                   ) OR p.user_id = ? 
                   ORDER BY p.created_at DESC 
                   LIMIT 20";

            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $_SESSION["id"], $_SESSION["id"]);
                
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    
                    while ($row = mysqli_fetch_assoc($result)) {
                        $row['likes_count'] = count_likes($row['id']);
                        $row['has_liked'] = has_liked_post($_SESSION["id"], $row['id']);
                        
                        $row['replies_count'] = count_replies($row['id']);
                        
                        $posts[] = $row;
                    }
                }
                
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
        }
    }
}

$post_err = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["post_content"])) {
    $content = trim($_POST["post_content"]);
    
    if (empty($content)) {
        $post_err = "Beitrag darf nicht leer sein.";
    } elseif (isset($_SESSION["id"]) && $_SESSION["id"]) {
        $mute_status = is_muted($_SESSION["id"]);
        $is_muted = $mute_status['is_muted'];
        $mute_reason = $mute_status['mute_reason'];
        $mute_until = $mute_status['mute_until'];
        
        if ($is_muted) {
            $post_err = "Du bist bis zum " . date('d.m.Y H:i', strtotime($mute_until)) . " stummgeschaltet. Grund: " . $mute_reason;
        } else {
            if ($posts_table_exists) {
                try {
                    $sql = "INSERT INTO posts (user_id, content) VALUES (?, ?)";
                    
                    if ($stmt = mysqli_prepare($link, $sql)) {
                        mysqli_stmt_bind_param($stmt, "is", $_SESSION["id"], $content);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            header("location: index.php");
                            exit;
                        } else {
                            $post_err = "Etwas ist schiefgelaufen. Bitte versuche es später noch einmal.";
                        }
                        
                        mysqli_stmt_close($stmt);
                    }
                } catch (Exception $e) {
                    $post_err = "Fehler beim Erstellen des Beitrags: " . $e->getMessage();
                }
            } else {
                $post_err = "Datenbanktabellen sind nicht richtig eingerichtet. Bitte führe zuerst die Installation aus.";
            }
        }
    } else {
        $post_err = "Du musst eingeloggt sein, um einen Beitrag zu erstellen.";
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SocialApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/self_destruct.js"></script>
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
            max-width: 600px;
            border-right: 1px solid #38444D;
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
        .like-button {
            transition: all 0.2s ease;
        }
        .like-button.liked {
            color: #e0245e;
            transform: scale(1.1);
        }
        .like-button.liked i {
            font-weight: 900; /* Solid Heart */
        }
        .like-button:hover {
            color: #e0245e;
        }
        .like-count {
            transition: all 0.2s ease;
        }
        .animate-like {
            animation: pulse 0.3s ease;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .comment-button:hover {
            color: #1da1f2;
        }
        .replies-container {
            max-height: 350px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #38444D #15202B;
        }
        .replies-container::-webkit-scrollbar {
            width: 6px;
        }
        .replies-container::-webkit-scrollbar-track {
            background: #15202B;
        }
        .replies-container::-webkit-scrollbar-thumb {
            background-color: #38444D;
            border-radius: 6px;
        }
    </style>
    <script src="assets/js/replies.js"></script>
</head>
<body>
    <?php
    $announcements = array();
    $check_table_sql = "SHOW TABLES LIKE 'announcements'";
    $result = mysqli_query($link, $check_table_sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $sql = "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 1";
        $result = mysqli_query($link, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            $announcements = mysqli_fetch_assoc($result);
        }
    }
    ?>

    <?php if(!empty($announcements)): ?>
    <div class="bg-red-600 text-white p-3 text-center">
        <div class="max-w-screen-xl mx-auto">
            <?php echo nl2br(htmlspecialchars($announcements['message'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="container">
    <div class="sidebar hidden md:block">
        <div class="mb-6">
            <img src="/assets/images/logo.png" alt="logo" class="w-20 h-20 rounded-full mx-auto">
        </div>
        <nav>
                <a href="index.php" class="nav-item active">
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
                <a href="messages.php" class="nav-item">
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
                
                <?php if((isset($_SESSION["username"]) && $_SESSION["username"] === "Admin") || (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === 1)): ?>
                <a href="panel.php" class="nav-item">
                    <i class="fas fa-user-shield"></i>
                    <span>Admin Panel</span>
                </a>
                <?php endif; ?>
                
                <button class="bg-blue-500 text-white w-full py-3 rounded-full font-bold mt-5 hover:bg-blue-600">
                    Post
                </button>
            </nav>
            
            <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($user) && !empty($user)): ?>
                <div class="mt-4 p-3 flex items-center">
                    <img src="assets/images/<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'default.png'; ?>" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                    <div>
                        <div class="font-bold"><?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : "User"; ?></div>
                        <div class="text-gray-500">@<?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : "user"; ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="content p-4">
            <h1 class="text-xl font-bold mb-4">Home</h1>
            
            <div class="bg-gray-800 rounded-lg p-4 mb-6">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="flex">
                        <img src="assets/images/<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'default.png'; ?>" alt="Profile" class="w-12 h-12 rounded-full mr-3">
                        <div class="flex-1">
                            <textarea name="post_content" class="w-full bg-transparent border-b border-gray-700 p-2 mb-3 focus:outline-none focus:border-blue-500" placeholder="What's happening?" rows="2"></textarea>
                            <?php if(!empty($post_err)): ?>
                                <p class="text-red-500 text-sm mb-2"><?php echo $post_err; ?></p>
                            <?php endif; ?>
                            <div class="flex justify-between items-center">
                                <div class="flex space-x-4 text-blue-400">
                                    <button type="button" class="hover:text-blue-500"><i class="far fa-image"></i></button>
                                    <button type="button" class="hover:text-blue-500"><i class="fas fa-poll"></i></button>
                                    <button type="button" class="hover:text-blue-500"><i class="far fa-smile"></i></button>
                                    <button type="button" class="hover:text-blue-500"><i class="far fa-calendar"></i></button>
                                </div>
                                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-full font-bold hover:bg-blue-600">Post</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="space-y-4">
                <?php if(empty($posts)): ?>
                    <div class="bg-gray-800 rounded-lg p-6 text-center">
                        <p class="text-gray-400">No posts to show. Follow some users to see their posts here!</p>
                        <a href="explore.php" class="text-blue-400 hover:underline mt-2 inline-block">Explore users</a>
                    </div>
                <?php else: ?>
                    <?php foreach($posts as $post): ?>
                        <div class="bg-gray-800 rounded-lg p-4 post-container" data-post-id="<?php echo $post['id']; ?>">
                            <div class="flex">
                                <img src="assets/images/<?php echo !empty($post['profile_image']) ? htmlspecialchars($post['profile_image']) : 'default.png'; ?>" alt="Profile" class="w-12 h-12 rounded-full mr-3">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <h3 class="font-bold"><?php echo htmlspecialchars($post['username']); ?></h3>
                                        <span class="text-gray-500 ml-2">@<?php echo htmlspecialchars($post['username']); ?></span>
                                        <span class="text-gray-500 mx-2">·</span>
                                        <span class="text-gray-500"><?php echo date('M j', strtotime($post['created_at'])); ?></span>
                                    </div>
                                    <p class="mt-2 mb-3"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                    <div class="flex justify-between text-gray-500">
                                        <button class="flex items-center hover:text-blue-400 comment-button" data-post-id="<?php echo $post['id']; ?>">
                                            <i class="far fa-comment mr-1"></i>
                                            <span class="comment-count"><?php echo $post['replies_count']; ?></span>
                                        </button>
                                        
                                        <button class="flex items-center hover:text-green-400">
                                            <i class="fas fa-retweet mr-1"></i>
                                            <span>0</span>
                                        </button>
                                        
                                        <form method="post" class="m-0 p-0 like-form">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <input type="hidden" name="like_action" value="toggle">
                                            <button type="submit" class="flex items-center like-button <?php echo $post['has_liked'] ? 'liked' : ''; ?>">
                                                <i class="<?php echo $post['has_liked'] ? 'fas' : 'far'; ?> fa-heart mr-1"></i>
                                                <span class="like-count"><?php echo $post['likes_count']; ?></span>
                                            </button>
                                        </form>
                                        
                                        <button class="flex items-center hover:text-blue-400">
                                            <i class="far fa-share-square"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="replies-container hidden mt-3 pt-3 border-t border-gray-600"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const likeForms = document.querySelectorAll('.like-form');
        
        likeForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Standardverhalten des Formulars verhindern
                
                const formData = new FormData(form);
                const postId = formData.get('post_id');
                const likeButton = form.querySelector('.like-button');
                const likeIcon = likeButton.querySelector('i');
                const likeCount = likeButton.querySelector('.like-count');
                
                fetch('like_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.liked) {
                            likeButton.classList.add('liked');
                            likeIcon.classList.remove('far');
                            likeIcon.classList.add('fas');
                        } else {
                            likeButton.classList.remove('liked');
                            likeIcon.classList.remove('fas');
                            likeIcon.classList.add('far');
                        }
                        
                        likeCount.textContent = data.count;
                        
                        likeButton.classList.add('animate-like');
                        setTimeout(() => {
                            likeButton.classList.remove('animate-like');
                        }, 300);
                    }
                })
                .catch(error => {
                    console.error('Fehler bei der Like-Anfrage:', error);
                });
            });
        });
    });
    </script>
</body>
</html>
