<?php
/* made with love from alex - xshadow */
define('BASE_DIR', __DIR__);

$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

$logFile = $logDir . '/server.log';
$logMessage = date('Y-m-d H:i:s') . " [request] {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} from {$_SERVER['REMOTE_ADDR']}\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

$url = parse_url($_SERVER['REQUEST_URI']);
$path = $url['path'];

if ($path === '/' || $path === '') {
    include __DIR__ . '/index.php';
    return true;
}

$filePath = __DIR__ . $path;
if (file_exists($filePath)) {
    if (preg_match('/\.php$/', $path)) {
        include $filePath;
        return true;
    }
    
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    
    $contentTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'html' => 'text/html',
        'txt' => 'text/plain',
        'json' => 'application/json',
        'pdf' => 'application/pdf',
    ];
    
    if (isset($contentTypes[$extension])) {
        header('Content-Type: ' . $contentTypes[$extension]);
    }
    
    readfile($filePath);
    return true;
}

if ($path === '/test') {
    include __DIR__ . '/test_server.php';
    return true;
}

header('HTTP/1.0 404 Not Found');
echo '<!DOCTYPE html>
<html>
<head>
    <title>404 Not Found</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            background-color: #15202B;
            color: white;
        }
        h1 {
            color: #1DA1F2;
            border-bottom: 2px solid #1DA1F2;
            padding-bottom: 10px;
        }
        .error {
            background-color: #192734;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .links {
            margin-top: 20px;
        }
        .links a {
            display: inline-block;
            margin-right: 15px;
            text-decoration: none;
            color: #1DA1F2;
            font-weight: bold;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>404 Not Found</h1>
    <div class="error">
        <p>The requested URL ' . htmlspecialchars($path) . ' was not found on this server.</p>
    </div>
    <div class="links">
        <h2>Quick Links</h2>
        <a href="/">Home Page</a>
        <a href="/auth/login.php">Login</a>
        <a href="/auth/register.php">Register</a>
        <a href="/profile.php">Profile</a>
    </div>
</body>
</html>';

$errorMessage = date('Y-m-d H:i:s') . " [error] 404 Not Found: {$path}\n";
file_put_contents($logFile, $errorMessage, FILE_APPEND);

return true;
