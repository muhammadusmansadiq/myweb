<?php
/**
 * Common functions for the Dissertation Management System
 */

/**
 * Redirects the user to a specified URL
 * 
 * @param string $url The URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Displays an alert message
 * 
 * @param string $message The message to display
 * @param string $type The type of alert ('danger', 'success', 'warning', 'info')
 * @return void
 */
function alert($message, $type = 'danger') {
    echo "<div class='alert alert-$type' role='alert'>$message</div>";
}

/**
 * Gets the user's role name based on their ID
 * 
 * @param PDO $pdo PDO database connection
 * @param int $user_id The user ID
 * @return string|null The role name or null if not found
 */
function get_user_role($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT RoleID FROM Users WHERE UserID = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $role_id = $user['RoleID'];
            $stmt = $pdo->prepare("SELECT RoleName FROM Roles WHERE RoleID = :role_id");
            $stmt->execute(['role_id' => $role_id]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            return $role ? $role['RoleName'] : null;
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
    return null;
}

/**
 * Gets the user's full name or username
 * 
 * @param PDO $pdo PDO database connection
 * @param int $user_id The user ID
 * @return string The user's full name or username
 */
function get_user_name($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                CONCAT(p.FirstName, ' ', p.LastName) AS FullName, 
                u.Username 
            FROM 
                Users u
            LEFT JOIN 
                Profile p ON u.UserID = p.UserID
            WHERE 
                u.UserID = :user_id
        ");
        $stmt->execute([':user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return "Unknown User";
        }
        
        return !empty($user['FullName']) && $user['FullName'] != ' ' ? $user['FullName'] : $user['Username'];
    } catch (PDOException $e) {
        return "Unknown User";
    }
}

/**
 * Formats a date nicely
 * 
 * @param string $date The date to format
 * @param string $format The format string
 * @return string The formatted date
 */
function format_date($date, $format = 'F d, Y') {
    return date($format, strtotime($date));
}

/**
 * Calculates the percentage of a value compared to a max value
 * 
 * @param float $value The current value
 * @param float $max The maximum value
 * @param int $decimals Number of decimal places
 * @return float The percentage
 */
function calculate_percentage($value, $max, $decimals = 1) {
    if ($max == 0) {
        return 0;
    }
    return round(($value / $max) * 100, $decimals);
}

/**
 * Formats a file size in a human-readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function format_file_size($bytes) {
    if ($bytes < 1024) {
        return $bytes . ' bytes';
    } elseif ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    } else {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}

/**
 * Creates a notification for a user
 * 
 * @param PDO $pdo PDO database connection
 * @param int $user_id The recipient user ID
 * @param string $title The notification title
 * @param string $message The notification message
 * @return bool Success or failure
 */
function create_notification($pdo, $user_id, $title, $message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Notifications (UserID, Title, Message, CreatedAt)
            VALUES (:user_id, :title, :message, NOW())
        ");
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':title' => $title,
            ':message' => $message
        ]);
        return $result;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Create notifications for all members in a group
 * 
 * @param PDO $pdo PDO database connection
 * @param int $group_id The group ID
 * @param string $title The notification title
 * @param string $message The notification message
 * @return int Number of notifications created
 */
function notify_group_members($pdo, $group_id, $title, $message) {
    try {
        // Get all active students in the group
        $stmt = $pdo->prepare("
            SELECT StudentID 
            FROM StudentGroups 
            WHERE GroupID = :group_id AND Status = 'Active'
        ");
        $stmt->execute([':group_id' => $group_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        
        // Create a notification for each student
        foreach ($students as $student) {
            if (create_notification($pdo, $student['StudentID'], $title, $message)) {
                $count++;
            }
        }
        
        return $count;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Get the grade color class based on percentage
 * 
 * @param float $percentage The percentage value
 * @return string CSS class for the color
 */
function get_grade_color_class($percentage) {
    if ($percentage >= 70) {
        return 'text-green-600';
    } elseif ($percentage >= 50) {
        return 'text-yellow-600';
    } else {
        return 'text-red-600';
    }
}

/**
 * Get the progress bar color class based on percentage
 * 
 * @param float $percentage The percentage value
 * @return string CSS class for the color
 */
function get_progress_bar_color($percentage) {
    if ($percentage >= 70) {
        return 'bg-green-500';
    } elseif ($percentage >= 50) {
        return 'bg-yellow-500';
    } else {
        return 'bg-red-500';
    }
}

/**
 * Calculate days remaining until a deadline
 * 
 * @param string $due_date The due date
 * @return array Array with days_left and status
 */
function calculate_deadline_status($due_date) {
    $due_date_obj = new DateTime($due_date);
    $today = new DateTime();
    $interval = $today->diff($due_date_obj);
    $days_left = $due_date_obj > $today ? $interval->days : -$interval->days;
    
    $status = [
        'days_left' => $days_left,
        'label' => '',
        'class' => ''
    ];
    
    if ($days_left < 0) {
        $status['label'] = 'Overdue: ' . abs($days_left) . ' days';
        $status['class'] = 'bg-red-100 text-red-800';
    } elseif ($days_left == 0) {
        $status['label'] = 'Due today';
        $status['class'] = 'bg-yellow-100 text-yellow-800';
    } elseif ($days_left <= 3) {
        $status['label'] = 'Due in ' . $days_left . ' days';
        $status['class'] = 'bg-yellow-100 text-yellow-800';
    } else {
        $status['label'] = 'Due in ' . $days_left . ' days';
        $status['class'] = 'bg-blue-100 text-blue-800';
    }
    
    return $status;
}

/**
 * Get file icon based on file extension
 * 
 * @param string $filename The filename
 * @return string HTML for the file icon
 */
function get_file_icon($filename) {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    
    switch (strtolower($extension)) {
        case 'pdf':
            return '<svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path><path d="M3 8a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path></svg>';
        case 'doc':
        case 'docx':
            return '<svg class="w-8 h-8 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>';
        case 'xls':
        case 'xlsx':
        case 'csv':
            return '<svg class="w-8 h-8 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1h4a1 1 0 001-1v-1h-5v2zm0-4h5V8h-5v2zM9 8H4v2h5V8z" clip-rule="evenodd"></path></svg>';
        case 'zip':
        case 'rar':
        case '7z':
            return '<svg class="w-8 h-8 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2h8v1H6V6zm0 3h8v1H6V9zm0 3h8v1H6v-1z" clip-rule="evenodd"></path></svg>';
        case 'sql':
            return '<svg class="w-8 h-8 text-purple-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5.94 5.5c.944-.945 2.56-.276 5.074.248.41.086.82.183 1.226.298C10.868 4.251 9.022 3.911 7.6 5.333 6.9 6.033 6.5 7.633 6.5 7.633l-.74-.366C5.85 6.4 5.56 5.877 5.94 5.5zm8.25 3.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm1.138 9.992a7.998 7.998 0 01-3.333.938 8.025 8.025 0 01-6.047-2.672c-2.933-3.095-1.894-7.312-.694-9.054a.75.75 0 011.138.984c-.602.878-1.843 4.214.574 6.75a6.73 6.73 0 004.606 2.254 6.71 6.71 0 003.756-.752.75.75 0 01.9 1.202z" clip-rule="evenodd"></path></svg>';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return '<svg class="w-8 h-8 text-pink-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path></svg>';
        default:
            return '<svg class="w-8 h-8 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>';
    }
}

/**
 * Safely escape and output HTML content
 * 
 * @param string $text The text to escape
 * @return string Escaped text
 */
function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Truncate text to a specified length
 * 
 * @param string $text The text to truncate
 * @param int $length Maximum length
 * @param string $suffix The suffix to add when truncated
 * @return string Truncated text
 */
function truncate_text($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}
?>