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
            <title>Activate Self-Destruct</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body class="bg-gray-900 text-white p-8">
            <div class="max-w-md mx-auto">
                <h1 class="text-3xl font-bold mb-6 text-red-500">Activate Self-Destruct</h1>
                
                <div class="bg-gray-800 p-6 rounded-lg">
                    <form id="self-destruct-form" method="POST" class="space-y-4">
                        <div class="flex space-x-2">
                            <div class="flex-1">
                                <label for="countdown" class="block mb-2">Countdown:</label>
                                <input type="number" id="countdown" name="countdown" min="1" value="60" class="w-full bg-gray-700 text-white px-4 py-2 rounded">
                            </div>
                            <div class="w-1/3">
                                <label for="time_unit" class="block mb-2">Unit:</label>
                                <select id="time_unit" name="time_unit" class="w-full bg-gray-700 text-white px-4 py-2 rounded">
                                    <option value="seconds">Sekunden</option>
                                    <option value="minutes">Minuten</option>
                                    <option value="hours">Stunden</option>
                                    <option value="days">Tage</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label for="reason" class="block mb-2">Reason:</label>
                            <textarea id="reason" name="reason" rows="3" class="w-full bg-gray-700 text-white px-4 py-2 rounded"></textarea>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" class="bg-red-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-red-700 flex items-center justify-center w-full">
                                <i class="fas fa-skull-crossbones mr-2"></i>
                                ACTIVATE SELF-DESTRUCT
                            </button>
                        </div>
                        
                        <div class="text-xs text-gray-400 mt-4">
                            <p class="font-bold text-red-500">WARNING:</p>
                            <p>This action will permanently delete all data in the database. This cannot be undone.</p>
                        </div>
                    </form>
                </div>
                
                <div class="mt-6 text-center">
                    <a href="index.php" class="text-blue-400 hover:underline">Back to Home</a>
                </div>
            </div>
            
            <script>
                document.getElementById("self-destruct-form").addEventListener("submit", function(e) {
                    if (!confirm("WARNUNG: Dies wird alle Daten PERMANENT löschen! Fortfahren?")) {
                        e.preventDefault();
                        return false;
                    }
                    
                    return true;
                });
            </script>
        </body>
        </html>';
        exit;
    }
    
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$countdown_value = isset($_POST['countdown']) ? intval($_POST['countdown']) : 60;
$time_unit = isset($_POST['time_unit']) ? $_POST['time_unit'] : 'seconds';
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';
$activated_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown';

$countdown_seconds = $countdown_value;
switch ($time_unit) {
    case 'minutes':
        $countdown_seconds = $countdown_value * 60;
        break;
    case 'hours':
        $countdown_seconds = $countdown_value * 3600;
        break;
    case 'days':
        $countdown_seconds = $countdown_value * 86400;
        break;
}

if ($countdown_seconds < 10) {
    $countdown_seconds = 10;
}

$update_sql = "UPDATE self_destruct SET 
    is_active = 1, 
    countdown_seconds = ?, 
    start_time = NOW(), 
    activated_by = ?, 
    reason = ?,
    time_value = ?,
    time_unit = ?
    WHERE id = 1";

$stmt = mysqli_prepare($link, $update_sql);
mysqli_stmt_bind_param($stmt, "issss", $countdown_seconds, $activated_by, $reason, $countdown_value, $time_unit);
$success = mysqli_stmt_execute($stmt);

if ($success) {
    $log_sql = "INSERT INTO admin_logs (admin_username, action, details, ip_address) VALUES (?, 'activate_self_destruct', ?, ?)";
    $log_stmt = mysqli_prepare($link, $log_sql);
    $log_details = "Countdown: " . $countdown_value . " " . $time_unit . ". Reason: " . $reason;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    mysqli_stmt_bind_param($log_stmt, "sss", $activated_by, $log_details, $ip_address);
    mysqli_stmt_execute($log_stmt);
    
    if (isset($_POST['countdown'])) {
        header('Content-Type: text/html');
        
        $time_display = $countdown_value . ' ';
        switch ($time_unit) {
            case 'seconds':
                $time_display .= 'Sekunden';
                break;
            case 'minutes':
                $time_display .= 'Minuten';
                break;
            case 'hours':
                $time_display .= 'Stunden';
                break;
            case 'days':
                $time_display .= 'Tage';
                break;
        }
        
        echo '<script>
            alert("Selbstzerstörung aktiviert! Countdown: ' . $time_display . '.");
            window.location.href = "index.php";
        </script>';
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Self-destruct activated', 
        'countdown_seconds' => $countdown_seconds,
        'time_value' => $countdown_value,
        'time_unit' => $time_unit
    ]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Failed to activate self-destruct: ' . mysqli_error($link)]);
}
?>
