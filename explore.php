<?php
/* made with love from alex - xshadow */
require_once "includes/header.php";

$db_setup = false;
try {
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if($conn) {
        $db_setup = true;
        mysqli_close($conn);
    }
} catch (Exception $e) {
}

if(!$db_setup) {
    header("location: install.php");
    exit;
}

if(!is_logged_in()) {
    header("location: auth/login.php");
    exit;
}

$current_user = get_user_by_id($_SESSION["id"]);

$search_query = "";
if(isset($_GET["search"]) && !empty(trim($_GET["search"]))) {
    $search_query = trim($_GET["search"]);
}

$users = array();
if(!empty($search_query)) {
    $sql = "SELECT * FROM users WHERE id != ? AND (username LIKE ? OR bio LIKE ?) ORDER BY username ASC";
    
    if($stmt = mysqli_prepare($link, $sql)) {
        $search_param = "%" . $search_query . "%";
        mysqli_stmt_bind_param($stmt, "iss", $_SESSION["id"], $search_param, $search_param);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            while($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
        }
        
        mysqli_stmt_close($stmt);
    }
} else {
    $sql = "SELECT * FROM users WHERE id != ? ORDER BY username ASC";
    
    if($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            while($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
        }
        
        mysqli_stmt_close($stmt);
    }
}

if(isset($_POST["follow_action"]) && isset($_POST["user_id"])) {
    $action = $_POST["follow_action"];
    $user_id = $_POST["user_id"];
    
    if($action == "follow") {
        $sql = "INSERT INTO follows (follower_id, following_id) VALUES (?, ?)";
        
        if($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $_SESSION["id"], $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } elseif($action == "unfollow") {
        $sql = "DELETE FROM follows WHERE follower_id = ? AND following_id = ?";
        
        if($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $_SESSION["id"], $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    header("location: explore.php" . (!empty($search_query) ? "?search=" . urlencode($search_query) : ""));
    exit;
}
?>

<div class="p-4">
    <h1 class="text-xl font-bold mb-4">Explore</h1>
    
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="mb-6">
        <div class="relative">
            <input type="text" name="search" class="w-full bg-gray-800 border border-gray-700 rounded-full py-2 px-4 pl-10 focus:outline-none focus:border-blue-500" placeholder="Search users..." value="<?php echo $search_query; ?>">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-500"></i>
            </div>
            <?php if(!empty($search_query)): ?>
                <a href="explore.php" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-white">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
    
    <div class="space-y-4">
        <?php if(empty($users)): ?>
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <?php if(!empty($search_query)): ?>
                    <p class="text-gray-400">No users found matching "<?php echo htmlspecialchars($search_query); ?>".</p>
                <?php else: ?>
                    <p class="text-gray-400">No users to display.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach($users as $user): ?>
                <div class="bg-gray-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <img src="assets/images/<?php echo $user['profile_image']; ?>" alt="Profile" class="w-12 h-12 rounded-full mr-3">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-bold"><?php echo $user['username']; ?></h3>
                                    <p class="text-gray-500">@<?php echo $user['username']; ?></p>
                                </div>
                                <div class="flex items-center">
                                    <form method="post">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <?php if(is_following($_SESSION["id"], $user["id"])): ?>
                                            <input type="hidden" name="follow_action" value="unfollow">
                                            <button type="submit" class="bg-transparent border border-gray-500 text-white px-4 py-1 rounded-full hover:bg-gray-700">Following</button>
                                        <?php else: ?>
                                            <input type="hidden" name="follow_action" value="follow">
                                            <button type="submit" class="bg-blue-500 text-white px-4 py-1 rounded-full hover:bg-blue-600">Follow</button>
                                        <?php endif; ?>
                                    </form>
                                    <a href="profile.php?username=<?php echo $user['username']; ?>" class="ml-2 text-blue-400 hover:underline">
                                        <i class="fas fa-user"></i> Profile
                                    </a>
                                </div>
                            </div>
                            <?php if(!empty($user['bio'])): ?>
                                <p class="mt-2 text-gray-300"><?php echo nl2br(htmlspecialchars(substr($user['bio'], 0, 100) . (strlen($user['bio']) > 100 ? '...' : ''))); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
require_once "includes/footer.php";
?>
