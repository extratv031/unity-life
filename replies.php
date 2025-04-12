<?php
/* made with love from alex - xshadow */
function get_replies($post_id) {
    global $link;
    
    $replies = array();
    
    if ($link) {
        $check_table_sql = "SHOW TABLES LIKE 'replies'";
        $table_result = mysqli_query($link, $check_table_sql);
        
        if ($table_result && mysqli_num_rows($table_result) > 0) {
            $sql = "SELECT r.*, u.username, u.profile_image 
                   FROM replies r 
                   JOIN users u ON r.user_id = u.id 
                   WHERE r.post_id = ? 
                   ORDER BY r.created_at ASC";
            
            if($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $post_id);
                
                if(mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    
                    while ($row = mysqli_fetch_assoc($result)) {
                        $replies[] = $row;
                    }
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    return $replies;
}

function count_replies($post_id) {
    global $link;
    
    $count = 0;
    
    if ($link) {
        $check_table_sql = "SHOW TABLES LIKE 'replies'";
        $table_result = mysqli_query($link, $check_table_sql);
        
        if ($table_result && mysqli_num_rows($table_result) > 0) {
            $sql = "SELECT COUNT(*) as count FROM replies WHERE post_id = ?";
            
            if($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $post_id);
                
                if(mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    $count = $row['count'];
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    return $count;
}

function add_reply($post_id, $user_id, $content) {
    global $link;
    
    $success = false;
    
    if ($link) {
        $check_table_sql = "SHOW TABLES LIKE 'replies'";
        $table_result = mysqli_query($link, $check_table_sql);
        
        if (!$table_result || mysqli_num_rows($table_result) == 0) {
            $create_table_sql = "CREATE TABLE replies (
                id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                post_id INT NOT NULL,
                user_id INT NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            mysqli_query($link, $create_table_sql);
        }
        
        $sql = "INSERT INTO replies (post_id, user_id, content) VALUES (?, ?, ?)";
        
        if($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "iis", $post_id, $user_id, $content);
            
            if(mysqli_stmt_execute($stmt)) {
                $success = true;
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    return $success;
}
