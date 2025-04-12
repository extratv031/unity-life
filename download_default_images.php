<?php
/* made with love from alex - xshadow */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$default_profile_url = "https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y";
$default_banner_url = "https://images.unsplash.com/photo-1557683316-973673baf926?w=1200&h=400&fit=crop";

$default_profile_path = "assets/images/default.jpg";
$default_banner_path = "assets/images/default_banner.jpg";

if (!file_exists($default_profile_path)) {
    echo "Downloading default profile image...<br>";
    $profile_image = file_get_contents($default_profile_url);
    if ($profile_image !== false) {
        file_put_contents($default_profile_path, $profile_image);
        echo "Default profile image downloaded successfully.<br>";
    } else {
        echo "Failed to download default profile image.<br>";
    }
} else {
    echo "Default profile image already exists.<br>";
}

if (!file_exists($default_banner_path)) {
    echo "Downloading default banner image...<br>";
    $banner_image = file_get_contents($default_banner_url);
    if ($banner_image !== false) {
        file_put_contents($default_banner_path, $banner_image);
        echo "Default banner image downloaded successfully.<br>";
    } else {
        echo "Failed to download default banner image.<br>";
    }
} else {
    echo "Default banner image already exists.<br>";
}

echo "Done!";
?>
