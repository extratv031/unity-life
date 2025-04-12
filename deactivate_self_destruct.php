<?php
/* made with love from alex - xshadow */
require_once "includes/config.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/html');
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Deactivate Self-Destruct</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body class="bg-gray-900 text-white p-8">
            <div class="max-w-md mx-auto">
                <h1 class="text-3xl font-bold mb-6 text-green-500">Deactivate Self-Destruct</h1>
                
                <div class="bg-gray-800 p-6 rounded-lg">
                    <form id="deactivate-form" method="POST" class="space-y-4">
                        <div>
                            <label for="reason" class="block mb-2">Reason for deactivation:</label>
                            <textarea id="reason" name="reason" rows="3" class="w-full bg-gray-700 text-white px-4 py-2 rounded"></textarea>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 flex items-center justify-center w-full">
                                <i class="fas fa-stop-circle mr-2"></i>
                                DEACTIVATE SELF-DESTRUCT
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="mt-6 text-center">
                    <a href="index.php" class="text-blue-400 hover:underline">Back to Home</a>
                </div>
            </div>
        </body>
        </html>';
        exit;
    }
    
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$reason = isset($_POST['reason']) ? $_POST['reason'] : '';
$deactivated_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown';

$update_sql = "UPDATE self_destruct SET 
    is_active = 0, 
    start_time = NULL 
    WHERE id = 1";

$success = mysqli_query($link, $update_sql);

if ($success) {
    $log_sql = "INSERT INTO admin_logs (admin_username, action, details, ip_address) VALUES (?, 'deactivate_self_destruct', ?, ?)";
    $log_stmt = mysqli_prepare($link, $log_sql);
    $log_details = "Reason: " . $reason;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    mysqli_stmt_bind_param($log_stmt, "sss", $deactivated_by, $log_details, $ip_address);
    mysqli_stmt_execute($log_stmt);
    
    if (isset($_POST['reason'])) {
        header('Content-Type: text/html');
        echo '<script>
            alert("Selbstzerstörung deaktiviert!");
            window.location.href = "index.php";
        </script>';
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Self-destruct deactivated']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Failed to deactivate self-destruct: ' . mysqli_error($link)]);
}
?>
