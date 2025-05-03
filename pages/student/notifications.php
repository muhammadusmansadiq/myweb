<?php
// pages/student/notifications.php
include_once '../../includes/header.php';
include_once '../../config/db.php';
include_once '../../includes/functions.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Mark specific notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    try {
        $stmt = $pdo->prepare("
            UPDATE Notifications
            SET IsRead = 1
            WHERE NotificationID = :notification_id AND UserID = :user_id
        ");
        $stmt->execute([
            ':notification_id' => $notification_id,
            ':user_id' => $user_id
        ]);
        
        $success = "Notification marked as read.";
    } catch (PDOException $e) {
        $error = "Error marking notification as read: " . $e->getMessage();
    }
}

// Mark all notifications as read
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == '1') {
    try {
        $stmt = $pdo->prepare("
            UPDATE Notifications
            SET IsRead = 1
            WHERE UserID = :user_id
        ");
        $stmt->execute([':user_id' => $user_id]);
        
        $success = "All notifications marked as read.";
    } catch (PDOException $e) {
        $error = "Error marking notifications as read: " . $e->getMessage();
    }
}

// Delete a notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("
            DELETE FROM Notifications
            WHERE NotificationID = :notification_id AND UserID = :user_id
        ");
        $stmt->execute([
            ':notification_id' => $notification_id,
            ':user_id' => $user_id
        ]);
        
        $success = "Notification deleted.";
    } catch (PDOException $e) {
        $error = "Error deleting notification: " . $e->getMessage();
    }
}

// Clear all notifications
if (isset($_GET['clear_all']) && $_GET['clear_all'] == '1') {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM Notifications
            WHERE UserID = :user_id
        ");
        $stmt->execute([':user_id' => $user_id]);
        
        $success = "All notifications cleared.";
    } catch (PDOException $e) {
        $error = "Error clearing notifications: " . $e->getMessage();
    }
}

// Get all notifications for the user
try {
    // Pagination settings
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // Count total notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM Notifications
        WHERE UserID = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $total_notifications = $stmt->fetchColumn();
    $total_pages = ceil($total_notifications / $per_page);
    
    // Get notifications for current page
    $stmt = $pdo->prepare("
        SELECT 
            NotificationID,
            Title,
            Message,
            IsRead,
            CreatedAt
        FROM 
            Notifications
        WHERE 
            UserID = :user_id
        ORDER BY 
            CreatedAt DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS UnreadCount
        FROM Notifications
        WHERE UserID = :user_id AND IsRead = 0
    ");
    $stmt->execute([':user_id' => $user_id]);
    $unread_count = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Error fetching notifications: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Notifications</h1>
                
                <div class="flex space-x-2">
                    <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold py-2 px-4 rounded">
                            Mark All as Read
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($notifications)): ?>
                        <a href="?clear_all=1" onclick="return confirm('Are you sure you want to clear all notifications?');" class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold py-2 px-4 rounded">
                            Clear All
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($notifications)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <h2 class="text-2xl font-semibold text-gray-500 mb-2">No Notifications</h2>
                    <p class="text-gray-500">You don't have any notifications at the moment.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="p-6 <?php echo $notification['IsRead'] ? 'bg-white' : 'bg-blue-50'; ?> hover:bg-gray-50 transition duration-150">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-medium text-gray-900 mb-1">
                                            <?php if (!$notification['IsRead']): ?>
                                                <span class="inline-block w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($notification['Title']); ?>
                                        </h3>
                                        <p class="text-gray-600 mb-2"><?php echo nl2br(htmlspecialchars($notification['Message'])); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo date('F d, Y \a\t h:i A', strtotime($notification['CreatedAt'])); ?></p>
                                    </div>
                                    
                                    <div class="flex space-x-2 ml-4">
                                        <?php if (!$notification['IsRead']): ?>
                                            <a href="?mark_read=<?php echo $notification['NotificationID']; ?>" class="text-blue-500 hover:text-blue-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?delete=<?php echo $notification['NotificationID']; ?>" onclick="return confirm('Are you sure you want to delete this notification?');" class="text-red-500 hover:text-red-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center mt-6">
                        <nav class="inline-flex rounded-md shadow">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-500 cursor-not-allowed">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            if ($end_page - $start_page < 4 && $start_page > 1) {
                                $start_page = max(1, $end_page - 4);
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">
                                        <?php echo $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-500 cursor-not-allowed">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="mt-8 text-center">
                <a href="dashboard.php" class="inline-flex items-center text-blue-500 hover:text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>