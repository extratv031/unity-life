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
    
    if (empty($user['banner_image'])) {
        $user['banner_image'] = 'default_banner.jpg';
    }
    
    return $user;
}

function get_user_by_username($username) {
    global $link;
    $user = array();
    
    $sql = "SELECT * FROM users WHERE username = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    if (!empty($user) && empty($user['profile_image'])) {
        $user['profile_image'] = 'default.png';
    }
    
    if (!empty($user) && empty($user['banner_image'])) {
        $user['banner_image'] = 'default_banner.jpg';
    }
    
    return $user;
}

function count_followers($user_id) {
    global $link;
    $count = 0;
    
    $sql = "SELECT COUNT(*) as count FROM follows WHERE following_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $count = $row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    
    return $count;
}

function count_following($user_id) {
    global $link;
    $count = 0;
    
    $sql = "SELECT COUNT(*) as count FROM follows WHERE follower_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $count = $row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    
    return $count;
}

function count_posts($user_id) {
    global $link;
    $count = 0;
    
    $sql = "SELECT COUNT(*) as count FROM posts WHERE user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $count = $row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    
    return $count;
}

function is_following($follower_id, $following_id) {
    global $link;
    $result = false;
    
    $sql = "SELECT * FROM follows WHERE follower_id = ? AND following_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $follower_id, $following_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $result = true;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    return $result;
}

if (isset($_GET["username"])) {
    $profile_username = $_GET["username"];
    $profile_user = get_user_by_username($profile_username);
    
    if (!$profile_user) {
        echo '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Benutzer nicht gefunden - SocialApp</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-900 text-white">
            <div class="container mx-auto px-4 py-16 text-center">
                <h1 class="text-3xl font-bold mb-4">Benutzer nicht gefunden</h1>
                <p class="mb-8">Der gesuchte Benutzer existiert nicht.</p>
                <a href="index.php" class="text-blue-400 hover:underline">Zurück zur Startseite</a>
            </div>
        </body>
        </html>';
        exit;
    }
} else {
    $profile_username = $_SESSION["username"];
    $profile_user = get_user_by_id($_SESSION["id"]);
}

$follower_count = count_followers($profile_user["id"]);
$following_count = count_following($profile_user["id"]);
$post_count = count_posts($profile_user["id"]);

$is_following = false;
$is_own_profile = false;

if (isset($_SESSION["id"])) {
    if ($_SESSION["id"] == $profile_user["id"]) {
        $is_own_profile = true;
    } else {
        $is_following = is_following($_SESSION["id"], $profile_user["id"]);
    }
}

if (isset($_POST["follow_action"]) && !$is_own_profile) {
    $action = $_POST["follow_action"];
    
    if ($action == "follow") {
        $sql = "INSERT INTO follows (follower_id, following_id) VALUES (?, ?)";
        
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $_SESSION["id"], $profile_user["id"]);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $is_following = true;
            $follower_count++;
        }
    } elseif ($action == "unfollow") {
        $sql = "DELETE FROM follows WHERE follower_id = ? AND following_id = ?";
        
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $_SESSION["id"], $profile_user["id"]);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $is_following = false;
            $follower_count--;
        }
    }
}

$posts = array();
$sql = "SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $profile_user["id"]);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $posts[] = $row;
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
    <title><?php echo htmlspecialchars($profile_user['username']); ?> - SocialApp</title>
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
        .profile-header {
            position: relative;
            height: 200px;
            background-size: cover;
            background-position: center;
            border-bottom: 1px solid #38444D;
        }
        .profile-avatar {
            width: 132px;
            height: 132px;
            border-radius: 50%;
            border: 4px solid #15202B;
            position: absolute;
            bottom: -60px;
            left: 16px;
            object-fit: cover;
        }
        .profile-info {
            padding: 16px;
            margin-top: 60px;
            border-bottom: 1px solid #38444D;
        }
        .stats-container {
            display: flex;
            padding: 16px;
            border-bottom: 1px solid #38444D;
        }
        .stat {
            flex: 1;
            text-align: center;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
        }
        .stat-label {
            font-size: 14px;
            color: #8899A6;
        }
        .follow-btn {
            background-color: #1DA1F2;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
        }
        .unfollow-btn {
            background-color: transparent;
            color: white;
            border: 1px solid white;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
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
                <a href="messages.php" class="nav-item">
                    <i class="far fa-envelope"></i>
                    <span>Messages</span>
                </a>
                <a href="profile.php" class="nav-item active">
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
            
            <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): 
                $current_user = get_user_by_id($_SESSION["id"]);
            ?>
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
            <div class="profile-header" style="background-image: url('assets/images/<?php echo htmlspecialchars($profile_user['banner_image']); ?>');">
                <img src="assets/images/<?php echo htmlspecialchars($profile_user['profile_image']); ?>" alt="Profile" class="profile-avatar">
                
                <?php if(!$is_own_profile): ?>
                    <div class="float-right mt-4 mr-4 flex">
                        <?php if(isset($profile_user['allow_dm']) && $profile_user['allow_dm'] == 1): ?>
                            <a href="messages.php?user_id=<?php echo $profile_user['id']; ?>" class="mr-2 bg-transparent border border-white text-white px-3 py-2 rounded-full hover:bg-gray-800">
                                <i class="far fa-envelope"></i> Nachricht
                            </a>
                        <?php endif; ?>
                        
                        <form method="post">
                            <?php if($is_following): ?>
                                <input type="hidden" name="follow_action" value="unfollow">
                                <button type="submit" class="unfollow-btn">Folge ich</button>
                            <?php else: ?>
                                <input type="hidden" name="follow_action" value="follow">
                                <button type="submit" class="follow-btn">Folgen</button>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php elseif($is_own_profile): ?>
                    <a href="edit_profile.php" class="float-right mt-4 mr-4 bg-transparent border border-white text-white px-4 py-2 rounded-full hover:bg-gray-800">
                        Profil bearbeiten
                    </a>
                <?php endif; ?>
            </div>

            <div class="profile-info">
                <h1 class="text-xl font-bold"><?php echo htmlspecialchars($profile_user['username']); ?></h1>
                <p class="text-gray-500">@<?php echo htmlspecialchars($profile_user['username']); ?></p>
                
                <?php if(!empty($profile_user['bio'])): ?>
                    <p class="my-3"><?php echo nl2br(htmlspecialchars($profile_user['bio'])); ?></p>
                <?php endif; ?>
                
                <?php if(!empty($profile_user['phone'])): ?>
                    <p class="text-gray-500 mt-2">
                        <i class="fas fa-phone-alt mr-2"></i>
                        <?php echo htmlspecialchars($profile_user['phone']); ?>
                    </p>
                <?php endif; ?>
                
                <p class="text-gray-500 mt-2">
                    <i class="far fa-calendar-alt mr-2"></i>
                    Beigetreten <?php echo date('F Y', strtotime($profile_user['created_at'])); ?>
                </p>
            </div>

            <div class="stats-container">
                <div class="stat">
                    <div class="stat-value"><?php echo $post_count; ?></div>
                    <div class="stat-label">Beiträge</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo $following_count; ?></div>
                    <div class="stat-label">Folge ich</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo $follower_count; ?></div>
                    <div class="stat-label">Follower</div>
                </div>
            </div>

            <div class="p-4">
                <h2 class="text-xl font-bold mb-4">Beiträge</h2>
                
                <div class="space-y-4">
                    <?php if(empty($posts)): ?>
                        <div class="bg-gray-800 rounded-lg p-6 text-center">
                            <p class="text-gray-400">Noch keine Beiträge.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($posts as $post): ?>
                            <div class="bg-gray-800 rounded-lg p-4">
                                <div class="flex">
                                    <img src="assets/images/<?php echo htmlspecialchars($profile_user['profile_image']); ?>" alt="Profile" class="w-12 h-12 rounded-full mr-3">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <h3 class="font-bold"><?php echo htmlspecialchars($profile_user['username']); ?></h3>
                                            <span class="text-gray-500 ml-2">@<?php echo htmlspecialchars($profile_user['username']); ?></span>
                                            <span class="text-gray-500 mx-2">·</span>
                                            <span class="text-gray-500"><?php echo date('d.m.Y', strtotime($post['created_at'])); ?></span>
                                        </div>
                                        <p class="mt-2 mb-3"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                        <div class="flex justify-between text-gray-500">
                                            <button class="flex items-center hover:text-blue-400">
                                                <i class="far fa-comment mr-1"></i>
                                                <span>0</span>
                                            </button>
                                            <button class="flex items-center hover:text-green-400">
                                                <i class="fas fa-retweet mr-1"></i>
                                                <span>0</span>
                                            </button>
                                            <button class="flex items-center hover:text-red-400">
                                                <i class="far fa-heart mr-1"></i>
                                                <span>0</span>
                                            </button>
                                            <button class="flex items-center hover:text-blue-400">
                                                <i class="far fa-share-square"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
