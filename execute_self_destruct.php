<?php
/* made with love from alex - xshadow */
set_time_limit(300);
ini_set('max_execution_time', 300);

require_once "includes/config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit;
}

$check_sql = "SELECT * FROM self_destruct WHERE id = 1 AND is_active = 1";
$result = mysqli_query($link, $check_sql);

if ($result && mysqli_num_rows($result) > 0) {
    $self_destruct = mysqli_fetch_assoc($result);
    
    $countdown_seconds = isset($self_destruct['countdown_seconds']) ? $self_destruct['countdown_seconds'] : 60;
    $start_time = isset($self_destruct['start_time']) ? $self_destruct['start_time'] : null;
    
    if ($start_time) {
        $start_timestamp = strtotime($start_time);
        $end_timestamp = $start_timestamp + $countdown_seconds;
        $current_timestamp = time();
        
        if ($current_timestamp >= ($end_timestamp - 2)) {
            $log_message = "Self-destruct executed at " . date('Y-m-d H:i:s');
            error_log($log_message);
            
            $update_sql = "UPDATE self_destruct SET is_active = 0 WHERE id = 1";
            mysqli_query($link, $update_sql);
            
            $app_dir = dirname(__FILE__);
            
            $goodbye_html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SocialApp - Selbstzerstört</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-red-900 text-white">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-gray-900 p-8 rounded-lg shadow-lg max-w-md w-full text-center">
            <div class="mb-6">
                <i class="fas fa-skull-crossbones text-red-500 text-6xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-4">Achtung</h1>
            <p class="text-xl text-white mb-6">Die SocialApp wurde selbstzerstört. Alle Daten sind permanent gelöscht.</p>
            <p class="text-gray-400 mb-8">Danke, dass ihr Teil von uns wart.</p>
            <div class="border-t border-gray-700 pt-6">
                <p class="text-gray-500 text-sm">Die Anwendung muss neu installiert werden, um sie wieder nutzen zu können.</p>
            </div>
        </div>
    </div>
</body>
</html>';
            
            $htaccess_content = 'RewriteEngine On
RewriteBase /SocialApp/
RewriteRule ^(.*)$ index.php [L]';
            
            function delete_all_files($dir, $exclude = array()) {
                if (!is_dir($dir)) {
                    return;
                }
                
                $files = glob($dir . '/*');
                foreach ($files as $file) {
                    $basename = basename($file);
                    
                    if (in_array($basename, $exclude) || in_array($file, $exclude)) {
                        continue;
                    }
                    
                    if (is_dir($file)) {
                        delete_all_files($file, $exclude);
                        @rmdir($file);
                    } else {
                        @chmod($file, 0777); // Try to set full permissions
                        @unlink($file);
                    }
                }
            }
            
            $exclude = array(
                basename(__FILE__), // This script
                'includes/config.php', // Config file
                $app_dir . '/includes/config.php', // Full path to config file
                'config.php', // Just in case
                'index.php', // We'll overwrite this later
                '.htaccess' // We'll overwrite this later
            );
            
            file_put_contents($app_dir . '/index.php', $goodbye_html);
            file_put_contents($app_dir . '/.htaccess', $htaccess_content);
            
            delete_all_files($app_dir, $exclude);
            
            $farewell_message = "SocialApp wurde am " . date('Y-m-d H:i:s') . " selbstzerstört.\n";
            $farewell_message .= "Die Datenbank ist noch intakt, aber alle Dateien wurden gelöscht.\n";
            $farewell_message .= "Um die App wiederherzustellen, müssen Sie die Dateien neu installieren.";
            
            file_put_contents($app_dir . '/farewell.txt', $farewell_message);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Self-destruct executed successfully']);
            exit;
        }
    }
}

http_response_code(400); // Bad Request
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Self-destruct conditions not met']);
?>
