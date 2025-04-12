<?php
/* made with love from alex - xshadow */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "includes/functions.php";

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["create_demo"])) {
    $demo_users = [
        [
            "username" => "SiNaArts",
            "email" => "sinaarts@example.com",
            "password" => "password123",
            "bio" => "❤️ Deine Ansprechpartner für Zeichnungen, Fotografie und Grafikdesign ✨",
            "phone" => "32389259",
            "verified" => 1
        ],
        [
            "username" => "JohnDoe",
            "email" => "johndoe@example.com",
            "password" => "password123",
            "bio" => "Digital artist and web developer. Love creating beautiful things.",
            "phone" => "5551234567",
            "verified" => 1
        ],
        [
            "username" => "JaneSmith",
            "email" => "janesmith@example.com",
            "password" => "password123",
            "bio" => "Photographer and nature lover. Capturing the beauty of the world one photo at a time.",
            "phone" => "5559876543",
            "verified" => 1
        ]
    ];
    
    $created_users = [];
    
    foreach ($demo_users as $user) {
        $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ss", $user["username"], $user["email"]);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $sql = "SELECT id FROM users WHERE username = ?";
                    $stmt2 = mysqli_prepare($link, $sql);
                    mysqli_stmt_bind_param($stmt2, "s", $user["username"]);
                    mysqli_stmt_execute($stmt2);
                    mysqli_stmt_bind_result($stmt2, $user_id);
                    mysqli_stmt_fetch($stmt2);
                    mysqli_stmt_close($stmt2);
                    
                    $created_users[] = [
                        "id" => $user_id,
                        "username" => $user["username"]
                    ];
                } else {
                    $sql = "INSERT INTO users (username, email, password, bio, phone, verified) VALUES (?, ?, ?, ?, ?, ?)";
                    
                    if ($stmt2 = mysqli_prepare($link, $sql)) {
                        $hashed_password = password_hash($user["password"], PASSWORD_DEFAULT);
                        
                        mysqli_stmt_bind_param($stmt2, "sssssi", $user["username"], $user["email"], $hashed_password, $user["bio"], $user["phone"], $user["verified"]);
                        
                        if (mysqli_stmt_execute($stmt2)) {
                            $user_id = mysqli_insert_id($link);
                            $created_users[] = [
                                "id" => $user_id,
                                "username" => $user["username"]
                            ];
                        } else {
                            $error_message .= "Error creating user " . $user["username"] . ": " . mysqli_error($link) . "<br>";
                        }
                        
                        mysqli_stmt_close($stmt2);
                    }
                }
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    if (!empty($created_users)) {
        $demo_posts = [
            [
                "user" => "SiNaArts",
                "content" => "Willkommen auf meiner Seite! Hier teile ich meine neuesten Kunstwerke und Fotografien. Schaut euch um und lasst mir gerne Feedback da!"
            ],
            [
                "user" => "SiNaArts",
                "content" => "Heute habe ich ein neues Projekt begonnen. Ich kann es kaum erwarten, euch die Ergebnisse zu zeigen!"
            ],
            [
                "user" => "SiNaArts",
                "content" => "Die Kunst ist eine Reflexion der Seele. Was spiegelt deine Kunst wider?"
            ],
            [
                "user" => "JohnDoe",
                "content" => "Just finished a new digital art piece. What do you think? #digitalart #creativity"
            ],
            [
                "user" => "JohnDoe",
                "content" => "Working on a new website design. The creative process is always exciting!"
            ],
            [
                "user" => "JaneSmith",
                "content" => "Captured this beautiful sunset today. Nature is the greatest artist of all. #photography #nature"
            ],
            [
                "user" => "JaneSmith",
                "content" => "Photography tip: The golden hour (just after sunrise or before sunset) provides the best natural lighting for outdoor photos."
            ]
        ];
        
        foreach ($demo_posts as $post) {
            $user_id = null;
            foreach ($created_users as $user) {
                if ($user["username"] === $post["user"]) {
                    $user_id = $user["id"];
                    break;
                }
            }
            
            if ($user_id) {
                $sql = "INSERT INTO posts (user_id, content) VALUES (?, ?)";
                
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "is", $user_id, $post["content"]);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        $error_message .= "Error creating post: " . mysqli_error($link) . "<br>";
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        if (count($created_users) >= 3) {
            $sql = "INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)";
            
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $created_users[1]["id"], $created_users[0]["id"]);
                mysqli_stmt_execute($stmt);
                
                mysqli_stmt_bind_param($stmt, "ii", $created_users[2]["id"], $created_users[0]["id"]);
                mysqli_stmt_execute($stmt);
                
                mysqli_stmt_bind_param($stmt, "ii", $created_users[0]["id"], $created_users[1]["id"]);
                mysqli_stmt_execute($stmt);
                
                mysqli_stmt_close($stmt);
            }
        }
        
        $success_message = "Demo data created successfully! You can now log in with any of these accounts:<br>";
        foreach ($demo_users as $user) {
            $success_message .= "- Username: <strong>" . $user["username"] . "</strong>, Password: <strong>" . $user["password"] . "</strong><br>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Demo Data - SocialApp</title>
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
        <h1 class="text-2xl font-bold mb-6 text-center">Create Demo Data</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <div class="bg-gray-800 p-4 rounded-lg mb-6">
            <h2 class="text-xl font-bold mb-4">What This Will Do</h2>
            <p class="mb-4">This script will create demo users and sample posts to help you test the application. It will:</p>
            <ul class="list-disc pl-5 space-y-2 mb-4">
                <li>Create 3 demo user accounts (if they don't already exist)</li>
                <li>Add sample posts for each user</li>
                <li>Create follow relationships between users</li>
            </ul>
            <p class="text-yellow-400"><i class="fas fa-exclamation-triangle mr-2"></i> Note: This is for testing purposes only. In a production environment, you should delete this file.</p>
        </div>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="create_demo" value="1">
            <div class="text-center">
                <button type="submit" class="btn-primary">Create Demo Data <i class="fas fa-database ml-2"></i></button>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <a href="index.php" class="text-blue-400 hover:underline">Back to SocialApp</a>
        </div>
    </div>
</body>
</html>
