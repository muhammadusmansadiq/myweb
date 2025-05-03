<?php
// pages/supervisor/group_feedback.php
include_once '../../includes/header.php';
include_once '../../config/db.php';
include_once '../../includes/functions.php';

// Check if the user is logged in and has the role of Supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

$supervisorID = $_SESSION['user_id'];
$groupID = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

$error = "";
$success = "";

// Verify that the group belongs to this supervisor
try {
    $stmt = $pdo->prepare("
        SELECT 
            g.GroupID,
            g.GroupName,
            g.Description,
            g.SupervisorID
        FROM 
            Groups g
        WHERE 
            g.GroupID = :groupID
            AND g.SupervisorID = :supervisorID
            AND g.Status = 'Active'
    ");
    $stmt->execute([
        ':groupID' => $groupID,
        ':supervisorID' => $supervisorID
    ]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        $error = "Group not found or you don't have permission to access it.";
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get group members
if (empty($error)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sg.StudentGroupID,
                sg.StudentID,
                u.Username,
                u.Email,
                CONCAT(p.FirstName, ' ', p.LastName) AS StudentName
            FROM 
                StudentGroups sg
            JOIN 
                Users u ON sg.StudentID = u.UserID
            LEFT JOIN 
                Profile p ON u.UserID = p.UserID
            WHERE 
                sg.GroupID = :groupID
                AND sg.Status = 'Active'
            ORDER BY 
                p.FirstName, p.LastName
        ");
        $stmt->execute([':groupID' => $groupID]);
        $groupMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching group members: " . $e->getMessage();
    }
}

// Get projects for this group
if (empty($error)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.ProjectID,
                p.Title,
                p.Status
            FROM 
                Projects p
            WHERE 
                p.GroupID = :groupID
            ORDER BY 
                p.CreatedAt DESC
        ");
        $stmt->execute([':groupID' => $groupID]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching projects: " . $e->getMessage();
    }
}

// Get previous feedback for this group
if (empty($error)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                f.FeedbackID,
                f.FeedbackText,
                f.FeedbackFilePath,
                f.SentAt,
                CONCAT(p.FirstName, ' ', p.LastName) AS SenderName,
                u.Username AS SenderUsername
            FROM 
                GroupFeedback f
            JOIN 
                Users u ON f.SenderID = u.UserID
            LEFT JOIN 
                Profile p ON u.UserID = p.UserID
            WHERE 
                f.GroupID = :groupID
            ORDER BY 
                f.SentAt DESC
        ");
        $stmt->execute([':groupID' => $groupID]);
        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching feedback: " . $e->getMessage();
    }
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    $feedbackText = trim($_POST['feedback_text']);
    $feedbackFilePath = '';
    
    if (empty($feedbackText)) {
        $error = "Feedback text is required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Handle file upload if present
            if (!empty($_FILES['feedback_file']['name'])) {
                $targetDir = "../../uploads/feedback/{$supervisorID}/";
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                
                $fileName = basename($_FILES["feedback_file"]["name"]);
                $targetFile = $targetDir . uniqid() . '_' . $fileName;
                
                // Check file size (limit to 10MB)
                if ($_FILES["feedback_file"]["size"] > 10000000) {
                    throw new Exception("File is too large. Maximum size is 10MB.");
                }
                
                if (move_uploaded_file($_FILES["feedback_file"]["tmp_name"], $targetFile)) {
                    $feedbackFilePath = $targetFile;
                } else {
                    throw new Exception("Failed to upload file.");
                }
            }
            
            // Insert feedback into database
            $stmt = $pdo->prepare("
                INSERT INTO GroupFeedback (
                    GroupID,
                    SenderID,
                    FeedbackText,
                    FeedbackFilePath,
                    SentAt
                ) VALUES (
                    :groupID,
                    :senderID,
                    :feedbackText,
                    :feedbackFilePath,
                    NOW()
                )
            ");
            $stmt->execute([
                ':groupID' => $groupID,
                ':senderID' => $supervisorID,
                ':feedbackText' => $feedbackText,
                ':feedbackFilePath' => $feedbackFilePath
            ]);
            
            // Create notifications for all students in the group
            $supervisorName = get_user_name($pdo, $supervisorID);
            $notificationTitle = "New Group Feedback";
            $notificationMessage = "Supervisor $supervisorName has posted new feedback for your group.";
            
            $insertNotification = $pdo->prepare("
                INSERT INTO Notifications (UserID, Title, Message, CreatedAt)
                VALUES (:userID, :title, :message, NOW())
            ");
            
            foreach ($groupMembers as $member) {
                $insertNotification->execute([
                    ':userID' => $member['StudentID'],
                    ':title' => $notificationTitle,
                    ':message' => $notificationMessage
                ]);
            }
            
            $pdo->commit();
            
            $success = "Feedback submitted successfully.";
            
            // Refresh the page to show the new feedback
            header("Location: group_feedback.php?group_id=" . $groupID . "&success=1");
            exit();
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error submitting feedback: " . $e->getMessage();
        }
    }
}

// Check for success parameter in URL
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Feedback submitted successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Feedback</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.10.5/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center">Group Feedback</h1>
        
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
        
        <?php if (empty($error) && !empty($group)): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Sidebar - Group Info -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                        <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                            <h2 class="text-xl font-bold text-white">Group Information</h2>
                        </div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold mb-2"><?php echo htmlspecialchars($group['GroupName']); ?></h3>
                            <?php if (!empty($group['Description'])): ?>
                                <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($group['Description'])); ?></p>
                            <?php else: ?>
                                <p class="text-gray-500 italic mb-4">No description available</p>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <h4 class="font-medium text-gray-700 mb-2">Group Members</h4>
                                <?php if (empty($groupMembers)): ?>
                                    <p class="text-gray-500 italic">No active members in this group.</p>
                                <?php else: ?>
                                    <ul class="space-y-2">
                                        <?php foreach ($groupMembers as $member): ?>
                                            <li class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                                    <?php echo strtoupper(substr(($member['StudentName'] ?: $member['Username']), 0, 1)); ?>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium"><?php echo htmlspecialchars($member['StudentName'] ?: $member['Username']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($member['Email']); ?></p>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($projects)): ?>
                                <div class="mt-6">
                                    <h4 class="font-medium text-gray-700 mb-2">Projects</h4>
                                    <ul class="space-y-2">
                                        <?php foreach ($projects as $project): ?>
                                            <li>
                                                <a href="view_submission.php?project_id=<?php echo $project['ProjectID']; ?>" class="text-blue-500 hover:text-blue-700">
                                                    <?php echo htmlspecialchars($project['Title']); ?>
                                                </a>
                                                <span class="text-xs 
                                                    <?php
                                                        switch ($project['Status']) {
                                                            case 'Accepted':
                                                                echo 'text-green-500';
                                                                break;
                                                            case 'Rejected':
                                                                echo 'text-red-500';
                                                                break;
                                                            case 'Completed':
                                                                echo 'text-blue-500';
                                                                break;
                                                            default:
                                                                echo 'text-gray-500';
                                                        }
                                                    ?>">
                                                    (<?php echo htmlspecialchars($project['Status']); ?>)
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-6">
                                <a href="manage_groups.php" class="inline-flex items-center text-sm text-blue-500 hover:text-blue-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    Back to groups
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Content - Feedback -->
                <div class="lg:col-span-2">
                    <!-- New Feedback Form -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                        <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                            <h2 class="text-xl font-bold text-white">Add Feedback</h2>
                        </div>
                        <div class="p-6">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="submit_feedback">
                                
                                <div class="mb-4">
                                    <label for="feedback_text" class="block text-sm font-medium text-gray-700 mb-1">Feedback</label>
                                    <textarea 
                                        id="feedback_text" 
                                        name="feedback_text" 
                                        rows="5" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="Provide feedback to the entire group..."
                                        required
                                    ></textarea>
                                    <p class="text-sm text-gray-500 mt-1">This feedback will be visible to all members of the group.</p>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="feedback_file" class="block text-sm font-medium text-gray-700 mb-1">Attach File (Optional)</label>
                                    <div class="flex items-center">
                                        <input 
                                            type="file" 
                                            id="feedback_file" 
                                            name="feedback_file"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500"
                                        >
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1">Maximum file size: 10MB</p>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button 
                                        type="submit" 
                                        class="px-6 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                                    >
                                        Submit Feedback
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Previous Feedback -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                            <h2 class="text-xl font-bold text-white">Previous Feedback</h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($feedbacks)): ?>
                                <p class="text-gray-500 italic text-center">No feedback has been provided to this group yet.</p>
                            <?php else: ?>
                                <div class="space-y-6">
                                    <?php foreach ($feedbacks as $feedback): ?>
                                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                                            <div class="bg-gray-50 px-4 py-3 flex justify-between items-center">
                                                <div>
                                                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($feedback['SenderName'] ?: $feedback['SenderUsername']); ?></span>
                                                    <span class="text-sm text-gray-500 ml-2"><?php echo date('F d, Y \a\t h:i A', strtotime($feedback['SentAt'])); ?></span>
                                                </div>
                                                <?php if (!empty($feedback['FeedbackFilePath'])): ?>
                                                    <a href="<?php echo htmlspecialchars($feedback['FeedbackFilePath']); ?>" class="inline-flex items-center text-sm text-blue-500 hover:text-blue-700" download>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                        </svg>
                                                        Download Attachment
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="p-4">
                                                <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($feedback['FeedbackText'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (empty($group) && empty($error)): ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <h2 class="text-2xl font-bold mb-4">Group Not Found</h2>
                <p class="text-gray-600 mb-6">The group you are looking for does not exist or you don't have permission to access it.</p>
                <a href="manage_groups.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Return to Your Groups
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>