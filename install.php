<?php
/* made with love from alex - xshadow */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1 && isset($_POST['db_server'], $_POST['db_username'], $_POST['db_password'], $_POST['db_name'])) {
        $db_server = trim($_POST['db_server']);
        $db_username = trim($_POST['db_username']);
        $db_password = trim($_POST['db_password']);
        $db_name = trim($_POST['db_name']);
        
        $conn = @mysqli_connect($db_server, $db_username, $db_password);
        
        if (!$conn) {
            $error = "Database connection failed: " . mysqli_connect_error();
        } else {
            $sql = "CREATE DATABASE IF NOT EXISTS " . $db_name;
            if (mysqli_query($conn, $sql)) {
                $config_file = 'includes/config.php';
                $config_content = file_get_contents($config_file);
                
                $config_content = preg_replace('/define\(\'DB_SERVER\',\s*\'.*?\'\);/', "define('DB_SERVER', '$db_server');", $config_content);
                $config_content = preg_replace('/define\(\'DB_USERNAME\',\s*\'.*?\'\);/', "define('DB_USERNAME', '$db_username');", $config_content);
                $config_content = preg_replace('/define\(\'DB_PASSWORD\',\s*\'.*?\'\);/', "define('DB_PASSWORD', '$db_password');", $config_content);
                $config_content = preg_replace('/define\(\'DB_NAME\',\s*\'.*?\'\);/', "define('DB_NAME', '$db_name');", $config_content);
                
                file_put_contents($config_file, $config_content);
                
                $success = "Database connection successful! Database '$db_name' created or already exists.";
                $step = 2;
            } else {
                $error = "Error creating database: " . mysqli_error($conn);
            }
            
            mysqli_close($conn);
        }
    } elseif ($step === 2 && isset($_POST['smtp_host'], $_POST['smtp_username'], $_POST['smtp_password'], $_POST['smtp_port'], $_POST['smtp_from'], $_POST['smtp_from_name'])) {
        $smtp_host = trim($_POST['smtp_host']);
        $smtp_username = trim($_POST['smtp_username']);
        $smtp_password = trim($_POST['smtp_password']);
        $smtp_port = trim($_POST['smtp_port']);
        $smtp_from = trim($_POST['smtp_from']);
        $smtp_from_name = trim($_POST['smtp_from_name']);
        
        $config_file = 'includes/config.php';
        $config_content = file_get_contents($config_file);
        
        $config_content = preg_replace('/define\(\'SMTP_HOST\',\s*\'.*?\'\);/', "define('SMTP_HOST', '$smtp_host');", $config_content);
        $config_content = preg_replace('/define\(\'SMTP_USERNAME\',\s*\'.*?\'\);/', "define('SMTP_USERNAME', '$smtp_username');", $config_content);
        $config_content = preg_replace('/define\(\'SMTP_PASSWORD\',\s*\'.*?\'\);/', "define('SMTP_PASSWORD', '$smtp_password');", $config_content);
        $config_content = preg_replace('/define\(\'SMTP_PORT\',\s*\d+\);/', "define('SMTP_PORT', $smtp_port);", $config_content);
        $config_content = preg_replace('/define\(\'SMTP_FROM\',\s*\'.*?\'\);/', "define('SMTP_FROM', '$smtp_from');", $config_content);
        $config_content = preg_replace('/define\(\'SMTP_FROM_NAME\',\s*\'.*?\'\);/', "define('SMTP_FROM_NAME', '$smtp_from_name');", $config_content);
        
        file_put_contents($config_file, $config_content);
        
        $success = "Email settings updated successfully!";
        $step = 3;
    } elseif ($step === 3 && isset($_POST['create_tables'])) {
        require_once 'includes/config.php';
        
        $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        if (!$conn) {
            $error = "Database connection failed: " . mysqli_connect_error();
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS users (
                id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                profile_image VARCHAR(255) DEFAULT 'default.jpg',
                banner_image VARCHAR(255) DEFAULT 'default_banner.jpg',
                bio TEXT,
                phone VARCHAR(20),
                verified TINYINT(1) DEFAULT 0,
                verification_token VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            if (!mysqli_query($conn, $sql)) {
                $error = "Error creating users table: " . mysqli_error($conn);
            }
            
            $sql = "CREATE TABLE IF NOT EXISTS posts (
                id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                content TEXT NOT NULL,
                image VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            
            if (!mysqli_query($conn, $sql)) {
                $error = "Error creating posts table: " . mysqli_error($conn);
            }
            
            $sql = "CREATE TABLE IF NOT EXISTS follows (
                follower_id INT NOT NULL,
                following_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (follower_id, following_id),
                FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            
            if (!mysqli_query($conn, $sql)) {
                $error = "Error creating follows table: " . mysqli_error($conn);
            }
            
            $sql = "CREATE TABLE IF NOT EXISTS likes (
                user_id INT NOT NULL,
                post_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, post_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            )";
            
            if (!mysqli_query($conn, $sql)) {
                $error = "Error creating likes table: " . mysqli_error($conn);
            }
            
            mysqli_close($conn);
            
            if (empty($error)) {
                $success = "Database tables created successfully!";
                $step = 4;
            }
        }
    } elseif ($step === 4 && isset($_POST['download_images'])) {
        $default_profile_url = "https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y";
        $default_banner_url = "https://images.unsplash.com/photo-1557683316-973673baf926?w=1200&h=400&fit=crop";
        
        $default_profile_path = "assets/images/default.jpg";
        $default_banner_path = "assets/images/default_banner.jpg";
        
        $image_errors = [];
        
        if (!file_exists($default_profile_path)) {
            $profile_image = @file_get_contents($default_profile_url);
            if ($profile_image !== false) {
                file_put_contents($default_profile_path, $profile_image);
            } else {
                $image_errors[] = "Failed to download default profile image.";
            }
        }
        
        if (!file_exists($default_banner_path)) {
            $banner_image = @file_get_contents($default_banner_url);
            if ($banner_image !== false) {
                file_put_contents($default_banner_path, $banner_image);
            } else {
                $image_errors[] = "Failed to download default banner image.";
            }
        }
        
        if (empty($image_errors)) {
            $success = "Default images downloaded successfully!";
            $step = 5;
        } else {
            $error = implode("<br>", $image_errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SocialApp Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #15202B;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: #192734;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #1DA1F2;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }
        .step.active .step-number {
            background-color: #1DA1F2;
        }
        .step.completed .step-number {
            background-color: #17BF63;
        }
        .step:not(.active):not(.completed) .step-number {
            background-color: #38444D;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            background-color: #253341;
            border: 1px solid #38444D;
            border-radius: 5px;
            color: white;
            font-size: 16px;
        }
        .form-control:focus {
            outline: none;
            border-color: #1DA1F2;
        }
        .btn-primary {
            background-color: #1DA1F2;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-primary:hover {
            background-color: #1a91da;
        }
        .alert-error {
            background-color: #E0245E;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #17BF63;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-2xl font-bold mb-6 text-center">SocialApp Installation</h1>
        
        <div class="mb-8">
            <div class="step <?php echo $step === 1 ? 'active' : ($step > 1 ? 'completed' : ''); ?>">
                <div class="step-number"><?php echo $step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?></div>
                <div class="step-text">Database Configuration</div>
            </div>
            <div class="step <?php echo $step === 2 ? 'active' : ($step > 2 ? 'completed' : ''); ?>">
                <div class="step-number"><?php echo $step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?></div>
                <div class="step-text">Email Configuration</div>
            </div>
            <div class="step <?php echo $step === 3 ? 'active' : ($step > 3 ? 'completed' : ''); ?>">
                <div class="step-number"><?php echo $step > 3 ? '<i class="fas fa-check"></i>' : '3'; ?></div>
                <div class="step-text">Create Database Tables</div>
            </div>
            <div class="step <?php echo $step === 4 ? 'active' : ($step > 4 ? 'completed' : ''); ?>">
                <div class="step-number"><?php echo $step > 4 ? '<i class="fas fa-check"></i>' : '4'; ?></div>
                <div class="step-text">Download Default Images</div>
            </div>
            <div class="step <?php echo $step === 5 ? 'active' : ''; ?>">
                <div class="step-number">5</div>
                <div class="step-text">Finish</div>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <form method="post" action="?step=1">
                <h2 class="text-xl font-bold mb-4">Database Configuration</h2>
                <p class="mb-4">Enter your MySQL database details below:</p>
                
                <div class="form-group">
                    <label>Database Server</label>
                    <input type="text" name="db_server" class="form-control" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_username" class="form-control" value="root" required>
                </div>
                
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" class="form-control" value="socialapp_db" required>
                </div>
                
                <div class="text-right">
                    <button type="submit" class="btn-primary">Next <i class="fas fa-arrow-right ml-2"></i></button>
                </div>
            </form>
        <?php elseif ($step === 2): ?>
            <form method="post" action="?step=2">
                <h2 class="text-xl font-bold mb-4">Email Configuration</h2>
                <p class="mb-4">Enter your SMTP email settings for email verification:</p>
                
                <div class="form-group">
                    <label>SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control" value="smtp.example.com" required>
                </div>
                
                <div class="form-group">
                    <label>SMTP Username</label>
                    <input type="text" name="smtp_username" class="form-control" value="your_email@example.com" required>
                </div>
                
                <div class="form-group">
                    <label>SMTP Password</label>
                    <input type="password" name="smtp_password" class="form-control" value="your_email_password" required>
                </div>
                
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="number" name="smtp_port" class="form-control" value="587" required>
                </div>
                
                <div class="form-group">
                    <label>From Email</label>
                    <input type="email" name="smtp_from" class="form-control" value="noreply@yourwebsite.com" required>
                </div>
                
                <div class="form-group">
                    <label>From Name</label>
                    <input type="text" name="smtp_from_name" class="form-control" value="SocialApp" required>
                </div>
                
                <div class="text-right">
                    <a href="?step=1" class="btn-primary bg-gray-600 hover:bg-gray-700 mr-2"><i class="fas fa-arrow-left mr-2"></i> Back</a>
                    <button type="submit" class="btn-primary">Next <i class="fas fa-arrow-right ml-2"></i></button>
                </div>
            </form>
        <?php elseif ($step === 3): ?>
            <form method="post" action="?step=3">
                <h2 class="text-xl font-bold mb-4">Create Database Tables</h2>
                <p class="mb-4">Click the button below to create the necessary database tables:</p>
                
                <input type="hidden" name="create_tables" value="1">
                
                <div class="text-right">
                    <a href="?step=2" class="btn-primary bg-gray-600 hover:bg-gray-700 mr-2"><i class="fas fa-arrow-left mr-2"></i> Back</a>
                    <button type="submit" class="btn-primary">Create Tables <i class="fas fa-database ml-2"></i></button>
                </div>
            </form>
        <?php elseif ($step === 4): ?>
            <form method="post" action="?step=4">
                <h2 class="text-xl font-bold mb-4">Download Default Images</h2>
                <p class="mb-4">Click the button below to download default profile and banner images:</p>
                
                <input type="hidden" name="download_images" value="1">
                
                <div class="text-right">
                    <a href="?step=3" class="btn-primary bg-gray-600 hover:bg-gray-700 mr-2"><i class="fas fa-arrow-left mr-2"></i> Back</a>
                    <button type="submit" class="btn-primary">Download Images <i class="fas fa-download ml-2"></i></button>
                </div>
            </form>
        <?php elseif ($step === 5): ?>
            <h2 class="text-xl font-bold mb-4">Installation Complete!</h2>
            <p class="mb-4">Congratulations! SocialApp has been successfully installed.</p>
            
            <div class="bg-gray-800 p-4 rounded-lg mb-6">
                <h3 class="font-bold mb-2">Next Steps:</h3>
                <ol class="list-decimal pl-5 space-y-2">
                    <li>Delete or rename this installation file for security reasons.</li>
                    <li>Register a new account to start using the application.</li>
                    <li>Customize your profile and start posting!</li>
                </ol>
            </div>
            
            <div class="text-center">
                <a href="index.php" class="btn-primary">Go to SocialApp <i class="fas fa-external-link-alt ml-2"></i></a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
