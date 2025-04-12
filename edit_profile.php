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
    
    if(!isset($user['allow_dm'])) {
        $user['allow_dm'] = 1;
    }
    
    return $user;
}

$user = get_user_by_id($_SESSION["id"]);
if (!$user) {
    session_destroy();
    header("location: auth/login.php");
    exit;
}

$username = $email = $bio = $phone = $current_password = $new_password = $confirm_password = "";
$username_err = $email_err = $bio_err = $phone_err = $current_password_err = $new_password_err = $confirm_password_err = $profile_image_err = $banner_image_err = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST["username"])) {
        $username = trim($_POST["username"]);
        if (empty($username)) {
            $username_err = "Bitte gib einen Benutzernamen ein.";
        } elseif ($username !== $user["username"]) {
            $sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "si", $username, $_SESSION["id"]);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        $username_err = "Dieser Benutzername ist bereits vergeben.";
                    }
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $username = $user["username"]; // Behalte den gleichen Benutzernamen bei
        }
    } else {
        $username = $user["username"]; // Behalte den gleichen Benutzernamen bei
    }
    
    if (isset($_POST["email"])) {
        $email = trim($_POST["email"]);
        if (empty($email)) {
            $email_err = "Bitte gib eine E-Mail-Adresse ein.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Bitte gib eine gültige E-Mail-Adresse ein.";
        } elseif ($email !== $user["email"]) {
            $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "si", $email, $_SESSION["id"]);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        $email_err = "Diese E-Mail-Adresse ist bereits registriert.";
                    }
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $email = $user["email"]; // Behalte die gleiche E-Mail bei
        }
    } else {
        $email = $user["email"]; // Behalte die gleiche E-Mail bei
    }
    
    if (!empty($_POST["current_password"]) || !empty($_POST["new_password"]) || !empty($_POST["confirm_password"])) {
        $current_password = trim($_POST["current_password"]);
        if (empty($current_password)) {
            $current_password_err = "Bitte gib dein aktuelles Passwort ein.";
        } elseif (!password_verify($current_password, $user["password"])) {
            $current_password_err = "Das aktuelle Passwort ist falsch.";
        }
        
        $new_password = trim($_POST["new_password"]);
        if (empty($new_password)) {
            $new_password_err = "Bitte gib ein neues Passwort ein.";
        } elseif (strlen($new_password) < 6) {
            $new_password_err = "Das Passwort muss mindestens 6 Zeichen lang sein.";
        }
        
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($confirm_password)) {
            $confirm_password_err = "Bitte bestätige das neue Passwort.";
        } elseif ($new_password != $confirm_password) {
            $confirm_password_err = "Die Passwörter stimmen nicht überein.";
        }
    }
    
    $bio = trim($_POST["bio"]);
    if (strlen($bio) > 500) {
        $bio_err = "Die Bio darf nicht länger als 500 Zeichen sein.";
    }
    
    $phone = trim($_POST["phone"]);
    if (!empty($phone) && !preg_match("/^[0-9\-\(\)\/\+\s]*$/", $phone)) {
        $phone_err = "Bitte gib eine gültige Telefonnummer ein.";
    }
    
    $profile_image = $user["profile_image"];
    $banner_image = $user["banner_image"];
    
    if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] == 0) {
        $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $filename = $_FILES["profile_image"]["name"];
        $filetype = $_FILES["profile_image"]["type"];
        $filesize = $_FILES["profile_image"]["size"];
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $profile_image_err = "Bitte wähle ein gültiges Dateiformat (JPG, JPEG, PNG, GIF).";
        }
        
        $maxsize = 5 * 1024 * 1024;
        if ($filesize > $maxsize) {
            $profile_image_err = "Die Dateigröße überschreitet das zulässige Limit (5MB).";
        }
        
        if (in_array($filetype, $allowed) && empty($profile_image_err)) {
            if (!is_dir("assets/images")) {
                mkdir("assets/images", 0755, true);
            }
            
            $profile_image = uniqid() . "." . $ext;
            $target_file = "assets/images/" . $profile_image;
            
            if (!move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                $profile_image_err = "Beim Hochladen der Datei ist ein Fehler aufgetreten.";
                $profile_image = $user["profile_image"]; // Bei einem Fehler das alte Bild behalten
            }
        }
    }
    
    if (isset($_FILES["banner_image"]) && $_FILES["banner_image"]["error"] == 0) {
        $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $filename = $_FILES["banner_image"]["name"];
        $filetype = $_FILES["banner_image"]["type"];
        $filesize = $_FILES["banner_image"]["size"];
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $banner_image_err = "Bitte wähle ein gültiges Dateiformat (JPG, JPEG, PNG, GIF).";
        }
        
        $maxsize = 5 * 1024 * 1024;
        if ($filesize > $maxsize) {
            $banner_image_err = "Die Dateigröße überschreitet das zulässige Limit (5MB).";
        }
        
        if (in_array($filetype, $allowed) && empty($banner_image_err)) {
            if (!is_dir("assets/images")) {
                mkdir("assets/images", 0755, true);
            }
            
            $banner_image = uniqid() . "." . $ext;
            $target_file = "assets/images/" . $banner_image;
            
            if (!move_uploaded_file($_FILES["banner_image"]["tmp_name"], $target_file)) {
                $banner_image_err = "Beim Hochladen der Datei ist ein Fehler aufgetreten.";
                $banner_image = $user["banner_image"]; // Bei einem Fehler das alte Bild behalten
            }
        }
    }
    
    if (empty($username_err) && empty($email_err) && empty($bio_err) && empty($phone_err) && 
        empty($profile_image_err) && empty($banner_image_err) && 
        (empty($current_password_err) || empty($current_password)) && 
        (empty($new_password_err) || empty($new_password)) && 
        (empty($confirm_password_err) || empty($confirm_password))) {
        
        $allow_dm = isset($_POST["allow_dm"]) ? 1 : 0;
        
        $sql = "UPDATE users SET username = ?, email = ?, bio = ?, phone = ?, profile_image = ?, banner_image = ?, allow_dm = ? WHERE id = ?";
         
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssssii", $username, $email, $bio, $phone, $profile_image, $banner_image, $allow_dm, $_SESSION["id"]);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Profil erfolgreich aktualisiert.";
                
                if ($_SESSION["username"] != $username) {
                    $_SESSION["username"] = $username;
                }
                
                $user = get_user_by_id($_SESSION["id"]);
            } else {
                echo "Ups! Etwas ist schiefgelaufen. Bitte versuche es später noch einmal.";
            }

            mysqli_stmt_close($stmt);
        }
        
        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password) && 
            empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
            
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            
            if ($stmt = mysqli_prepare($link, $sql)) {
                $param_password = password_hash($new_password, PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($stmt, "si", $param_password, $_SESSION["id"]);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Profil und Passwort erfolgreich aktualisiert.";
                } else {
                    echo "Ups! Etwas ist bei der Passwortaktualisierung schiefgelaufen. Bitte versuche es später noch einmal.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil bearbeiten - SocialApp</title>
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
            padding: 20px;
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
        .form-group {
            margin-bottom: 20px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            background-color: #253341;
            border: 1px solid #38444D;
            border-radius: 5px;
            color: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #1DA1F2;
        }
        .error-text {
            color: #E0245E;
            font-size: 14px;
            margin-top: 5px;
        }
        .success-text {
            color: #17BF63;
            font-size: 16px;
            padding: 10px;
            background-color: rgba(23, 191, 99, 0.1);
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn-primary {
            background-color: #1DA1F2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-secondary {
            background-color: transparent;
            color: white;
            border: 1px solid #38444D;
            padding: 10px 20px;
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
                <a href="profile.php" class="nav-item">
                    <i class="far fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="edit_profile.php" class="nav-item active">
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
        
        <div class="content">
            <h1 class="text-xl font-bold mb-4">Profil bearbeiten</h1>
            
            <?php if(!empty($success_message)): ?>
                <div class="success-text mb-4">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="bg-gray-800 rounded-lg p-4">
                <div class="form-group">
                    <label class="block mb-2">Benutzername</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="form-control <?php echo (!empty($username_err)) ? 'border-red-500' : ''; ?>">
                    <span class="error-text"><?php echo $username_err; ?></span>
                </div>
                
                <div class="form-group">
                    <label class="block mb-2">E-Mail</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control <?php echo (!empty($email_err)) ? 'border-red-500' : ''; ?>">
                    <span class="error-text"><?php echo $email_err; ?></span>
                </div>
                
                <div class="form-group border-t border-gray-700 pt-4 mt-6">
                    <h2 class="text-lg font-bold mb-4">Passwort ändern</h2>
                    
                    <div class="mb-4">
                        <label class="block mb-2">Aktuelles Passwort</label>
                        <input type="password" name="current_password" class="form-control <?php echo (!empty($current_password_err)) ? 'border-red-500' : ''; ?>">
                        <span class="error-text"><?php echo $current_password_err; ?></span>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2">Neues Passwort</label>
                        <input type="password" name="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'border-red-500' : ''; ?>">
                        <span class="error-text"><?php echo $new_password_err; ?></span>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2">Neues Passwort bestätigen</label>
                        <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'border-red-500' : ''; ?>">
                        <span class="error-text"><?php echo $confirm_password_err; ?></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="block mb-2">Bio</label>
                    <textarea name="bio" class="form-control <?php echo (!empty($bio_err)) ? 'border-red-500' : ''; ?>" rows="4"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                    <span class="error-text"><?php echo $bio_err; ?></span>
                </div>
                
                <div class="form-group">
                    <label class="block mb-2">Telefon</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="form-control <?php echo (!empty($phone_err)) ? 'border-red-500' : ''; ?>">
                    <span class="error-text"><?php echo $phone_err; ?></span>
                </div>
                
                <div class="form-group">
                    <label class="flex items-center">
                        <input type="checkbox" name="allow_dm" value="1" <?php echo (isset($user['allow_dm']) && $user['allow_dm'] == 1) ? 'checked' : ''; ?> class="mr-2">
                        <span>Direktnachrichten erlauben</span>
                    </label>
                    <p class="text-gray-500 text-sm mt-1">Wenn aktiviert, können andere Benutzer dir Direktnachrichten senden</p>
                </div>
                
                <div class="form-group">
                    <label class="block mb-2">Profilbild</label>
                    <input type="file" name="profile_image" class="form-control <?php echo (!empty($profile_image_err)) ? 'border-red-500' : ''; ?>">
                    <span class="error-text"><?php echo $profile_image_err; ?></span>
                    <?php if(!empty($user['profile_image']) && $user['profile_image'] != 'default.jpg' && $user['profile_image'] != 'default.png'): ?>
                        <div class="mt-2">
                            <img src="assets/images/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Aktuelles Profilbild" class="w-16 h-16 rounded-full">
                            <p class="text-gray-500 text-sm mt-1">Aktuelles Profilbild</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="block mb-2">Bannerbild</label>
                    <input type="file" name="banner_image" class="form-control <?php echo (!empty($banner_image_err)) ? 'border-red-500' : ''; ?>">
                    <span class="error-text"><?php echo $banner_image_err; ?></span>
                    <?php if(!empty($user['banner_image']) && $user['banner_image'] != 'default_banner.jpg'): ?>
                        <div class="mt-2">
                            <img src="assets/images/<?php echo htmlspecialchars($user['banner_image']); ?>" alt="Aktuelles Bannerbild" class="w-full h-32 object-cover rounded">
                            <p class="text-gray-500 text-sm mt-1">Aktuelles Bannerbild</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-between">
                    <a href="profile.php" class="btn-secondary">Abbrechen</a>
                    <button type="submit" class="btn-primary">Änderungen speichern</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
