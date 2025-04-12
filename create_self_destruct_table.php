<?php
/* made with love from alex - xshadow */
require_once "includes/config.php";

$create_table_sql = "
CREATE TABLE IF NOT EXISTS `self_destruct` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `countdown_seconds` int(11) NOT NULL DEFAULT 60,
  `start_time` datetime DEFAULT NULL,
  `activated_by` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (mysqli_query($link, $create_table_sql)) {
    echo "Self-destruct table created successfully.<br>";
    
    $check_sql = "SELECT * FROM self_destruct WHERE id = 1";
    $result = mysqli_query($link, $check_sql);
    
    if (mysqli_num_rows($result) == 0) {
        $insert_sql = "INSERT INTO self_destruct (id, is_active, countdown_seconds) VALUES (1, 0, 60)";
        if (mysqli_query($link, $insert_sql)) {
            echo "Initial self-destruct record created successfully.<br>";
        } else {
            echo "Error creating initial self-destruct record: " . mysqli_error($link) . "<br>";
        }
    } else {
        echo "Self-destruct record already exists.<br>";
    }
} else {
    echo "Error creating self-destruct table: " . mysqli_error($link) . "<br>";
}

$create_announcements_table_sql = "
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (mysqli_query($link, $create_announcements_table_sql)) {
    echo "Announcements table created successfully.<br>";
} else {
    echo "Error creating announcements table: " . mysqli_error($link) . "<br>";
}

$create_admin_logs_table_sql = "
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `admin_username` varchar(255) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_username` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (mysqli_query($link, $create_admin_logs_table_sql)) {
    echo "Admin logs table created successfully.<br>";
} else {
    echo "Error creating admin logs table: " . mysqli_error($link) . "<br>";
}

echo "<br>All tables created successfully. <a href='index.php'>Go back to home</a>";
?>
