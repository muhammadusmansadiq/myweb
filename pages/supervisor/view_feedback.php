<?php
// pages/supervisor/view_feedback.php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// Check if the user is logged in and has the role of Supervisor (RoleID = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

$supervisorID = $_SESSION['user_id'];
$projectID = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$groupID = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
$analysisID = isset($_GET['analysis_id']) ? intval($_GET['analysis_id']) : 0;
$addAnalysis = isset($_GET['add_analysis']) && $_GET['add_analysis'] == 1;

$error = "";
$success = "";

// Verify this supervisor has access to this project
if ($projectID > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.ProjectID,
                p.Title AS ProjectTitle,
                p.GroupID,
                g.GroupName,
                g.SupervisorID
            FROM 
                Projects p
            JOIN 
                Groups g ON p.GroupID = g.GroupID
            WHERE 
                p.ProjectID = :projectID 
                AND g.SupervisorID = :supervisorID
        ");
        $stmt->execute([
            ':projectID' => $projectID,
            ':supervisorID' => $supervisorID
        ]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            $error = "You do not have permission to view feedback for this project or the project does not exist.";
        } else {
            $groupID = $project['GroupID'];
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} elseif ($groupID > 0) {
    // If we have a group ID but not a project ID, get the group info
    try {
        $stmt = $pdo->prepare("
            SELECT 
                g.GroupID, 
                g.GroupName, 
                g.SupervisorID
            FROM 
                Groups g
            WHERE 
                g.GroupID = :groupID 
                AND g.SupervisorID = :supervisorID
        ");
        $stmt->execute([
            ':groupID' => $groupID,
            ':supervisorID' => $supervisorID
        ]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            $error = "You do not have permission to view feedback for this group or the group does not exist.";
        } else {
            // Get the first project for this group to use for feedback
            $stmt = $pdo->prepare("
                SELECT 
                    p.ProjectID,
                    p.Title AS ProjectTitle
                FROM 
                    Projects p
                WHERE 
                    p.GroupID = :groupID
                ORDER BY 
                    p.CreatedAt DESC
                LIMIT 1
            ");
            $stmt->execute([':groupID' => $groupID]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($project) {
                $projectID = $project['ProjectID'];
            } else {
                $error = "No projects found for this group.";
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "Missing project or group identifier.";
}

// Fetch students in the group
if (empty($error) && $groupID > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.UserID,
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
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching students: " . $e->getMessage();
    }
}

// Fetch feedback history
if (empty($error) && $projectID > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                f.FeedbackID,
                f.FeedbackText,
                f.FeedbackFilePath,
                f.SentAt,
                f.SenderID,
                f.ReceiverID,
                CONCAT(sp.FirstName, ' ', sp.LastName) AS SenderName,
                su.Username AS SenderUsername,
                su.RoleID AS SenderRoleID,
                CONCAT(rp.FirstName, ' ', rp.LastName) AS ReceiverName,
                ru.Username AS ReceiverUsername
            FROM 
                Feedback f
            JOIN 
                Users su ON f.SenderID = su.UserID
            LEFT JOIN 
                Profile sp ON su.UserID = sp.UserID
            LEFT JOIN 
                Users ru ON f.ReceiverID = ru.UserID
            LEFT JOIN 
                Profile rp ON ru.UserID = rp.UserID
            WHERE 
                f.ProjectID = :projectID
            ORDER BY 
                f.SentAt ASC
        ");
        $stmt->execute([':projectID' => $projectID]);
        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching feedback: " . $e->getMessage();
    }
}

// Get analysis data if specified
if ($addAnalysis && $analysisID > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sa.AnalysisData,
                f.FileName
            FROM 
                SubmissionAnalysis sa
            JOIN 
                FileUploads f ON sa.FileID = f.FileID
            WHERE 
                sa.AnalysisID = :analysisID
        ");
        $stmt->execute([':analysisID' => $analysisID]);
        $analysisData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($analysisData) {
            $analysis = json_decode($analysisData['AnalysisData'], true);
            
            // Prepare pre-filled feedback based on analysis
            $feedbackText = "# AI Analysis of " . $analysisData['FileName'] . "\n\n";
            $feedbackText .= "## Overall Score: " . ($analysis['score'] ?? 'N/A') . "/10\n\n";
            
            if (isset($analysis['strengths']) && !empty($analysis['strengths'])) {
                $feedbackText .= "## Strengths:\n";
                foreach ($analysis['strengths'] as $strength) {
                    $feedbackText .= "- " . $strength . "\n";
                }
                $feedbackText .= "\n";
            }
            
            if (isset($analysis['improvements']) && !empty($analysis['improvements'])) {
                $feedbackText .= "## Areas for Improvement:\n";
                foreach ($analysis['improvements'] as $improvement) {
                    $feedbackText .= "- " . $improvement . "\n";
                }
                $feedbackText .= "\n";
            }
            
            if (isset($analysis['recommendations']) && !empty($analysis['recommendations'])) {
                $feedbackText .= "## Recommendations:\n";
                foreach ($analysis['recommendations'] as $recommendation) {
                    $feedbackText .= "- " . $recommendation . "\n";
                }
                $feedbackText .= "\n";
            }
            
            $feedbackText .= "Please let me know if you have any questions about this feedback.";
        }
    } catch (PDOException $e) {
        $error = "Error fetching analysis data: " . $e->getMessage();
    }
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $receiverID = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
    $feedbackText = isset($_POST['feedback_text']) ? trim($_POST['feedback_text']) : '';
    $sendToAll = isset($_POST['send_to_all']) && $_POST['send_to_all'] == 1;
    
    if (empty($feedbackText)) {
        $error = "Feedback text is required.";
    } elseif (!$sendToAll && $receiverID <= 0) {
        $error = "Please select a recipient for your feedback.";
    } else {
        try {
            // Handle file upload if provided
            $feedbackFilePath = '';
            if (!empty($_FILES['feedback_file']['name'])) {
                $targetDir = "../../uploads/feedback/";
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                
                $fileName = basename($_FILES['feedback_file']['name']);
                $targetFile = $targetDir . uniqid() . '_' . $fileName;
                
                if (move_uploaded_file($_FILES['feedback_file']['tmp_name'], $targetFile)) {
                    $feedbackFilePath = $targetFile;
                } else {
                    throw new Exception("Error uploading file.");
                }
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            if ($sendToAll && !empty($students)) {
                // Send to all students in the group
                foreach ($students as $student) {
                    $stmt = $pdo->prepare("
                        INSERT INTO Feedback (
                            ProjectID,
                            SenderID,
                            ReceiverID,
                            FeedbackText,
                            FeedbackFilePath,
                            SentAt
                        ) VALUES (
                            :projectID,
                            :senderID,
                            :receiverID,
                            :feedbackText,
                            :feedbackFilePath,
                            NOW()
                        )
                    ");
                    $stmt->execute([
                        ':projectID' => $projectID,
                        ':senderID' => $supervisorID,
                        ':receiverID' => $student['UserID'],
                        ':feedbackText' => $feedbackText,
                        ':feedbackFilePath' => $feedbackFilePath
                    ]);
                }
                
                $success = "Feedback sent to all group members.";
            } else {
                // Send to a specific student
                $stmt = $pdo->prepare("
                    INSERT INTO Feedback (
                        ProjectID,
                        SenderID,
                        ReceiverID,
                        FeedbackText,
                        FeedbackFilePath,
                        SentAt
                    ) VALUES (
                        :projectID,
                        :senderID,
                        :receiverID,
                        :feedbackText,
                        :feedbackFilePath,
                        NOW()
                    )
                ");
                $stmt->execute([
                    ':projectID' => $projectID,
                    ':senderID' => $supervisorID,
                    ':receiverID' => $receiverID,
                    ':feedbackText' => $feedbackText,
                    ':feedbackFilePath' => $feedbackFilePath
                ]);
                
                $success = "Feedback sent successfully.";
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Refresh to show the new feedback
            header("Location: view_feedback.php?project_id=$projectID");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error sending feedback: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Project Feedback</h1>
            <?php if (isset($project)): ?>
                <a href="view_project.php?id=<?php echo $project['ProjectID']; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Project
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Dashboard
                </a>
            <?php endif; ?>
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
        
        <?php if (isset($project) && empty($error)): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                    <h2 class="text-xl font-bold text-white">Project Information</h2>
                </div>
                <div class="p-6">
                    <h3 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($project['ProjectTitle']); ?></h3>
                    <p class="text-gray-600">Group: <?php echo htmlspecialchars($project['GroupName']); ?></p>
                    
                    <?php if (!empty($students)): ?>
                        <div class="mt-4">
                            <h4 class="font-semibold mb-2">Group Members</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($students as $student): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($student['StudentName'] ?: $student['Username']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Feedback Conversation -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 p-4">
                    <h2 class="text-xl font-bold text-white">Feedback History</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($feedbacks)): ?>
                        <p class="text-center text-gray-500 italic">No feedback has been exchanged for this project yet.</p>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($feedbacks as $feedback): ?>
                                <?php 
                                    $isSupervisor = $feedback['SenderRoleID'] == 2;
                                    $isCurrentUser = $feedback['SenderID'] == $supervisorID;
                                    $alignClass = $isCurrentUser ? 'ml-auto' : 'mr-auto';
                                    $maxWidth = 'max-w-2xl';
                                    $bgColor = $isCurrentUser ? 'bg-blue-50' : 'bg-gray-50';
                                    $borderColor = $isCurrentUser ? 'border-blue-200' : 'border-gray-200';
                                ?>
                                <div class="flex <?php echo $isCurrentUser ? 'justify-end' : 'justify-start'; ?> w-full">
                                    <div class="<?php echo $maxWidth; ?> <?php echo $alignClass; ?> rounded-lg shadow-sm <?php echo $bgColor; ?> p-4 border <?php echo $borderColor; ?>">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <span class="font-medium">
                                                    <?php echo $isCurrentUser ? 'You' : htmlspecialchars($feedback['SenderName'] ?: $feedback['SenderUsername']); ?>
                                                    <?php if ($isSupervisor): ?>
                                                        <span class="text-xs bg-purple-100 text-purple-800 px-2 py-0.5 rounded ml-1">Supervisor</span>
                                                    <?php else: ?>
                                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded ml-1">Student</span>
                                                    <?php endif; ?>
                                                </span>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo date('M d, Y, h:i A', strtotime($feedback['SentAt'])); ?>
                                                    <?php if (!$isCurrentUser): ?>
                                                        to <?php echo htmlspecialchars($feedback['ReceiverName'] ?: $feedback['ReceiverUsername']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <?php if (!empty($feedback['FeedbackFilePath'])): ?>
                                                <a href="<?php echo htmlspecialchars($feedback['FeedbackFilePath']); ?>" target="_blank" class="text-blue-500 hover:text-blue-700 text-sm">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    Download Attachment
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="prose max-w-none">
                                            <?php 
                                                $markdownText = htmlspecialchars($feedback['FeedbackText']);
                                                
                                                // Convert markdown-like syntax to HTML
                                                // Headers
                                                $markdownText = preg_replace('/^# (.*?)$/m', '<h1 class="text-2xl font-bold mb-2">$1</h1>', $markdownText);
                                                $markdownText = preg_replace('/^## (.*?)$/m', '<h2 class="text-xl font-bold mb-2">$1</h2>', $markdownText);
                                                $markdownText = preg_replace('/^### (.*?)$/m', '<h3 class="text-lg font-bold mb-2">$1</h3>', $markdownText);
                                                
                                                // Lists
                                                $markdownText = preg_replace('/^- (.*?)$/m', '<li class="ml-6">$1</li>', $markdownText);
                                                $markdownText = preg_replace('/(<li.*?<\/li>\n)+/', '<ul class="list-disc mb-4">$0</ul>', $markdownText);
                                                
                                                // Bold and Italic
                                                $markdownText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $markdownText);
                                                $markdownText = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $markdownText);
                                                
                                                // Preserve line breaks
                                                $markdownText = nl2br($markdownText);
                                                
                                                echo $markdownText;
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Send Feedback Form -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-green-600 to-green-800 p-4">
                    <h2 class="text-xl font-bold text-white">Send Feedback</h2>
                </div>
                <div class="p-6">
                    <form method="POST" enctype="multipart/form-data">
                        <?php if (!empty($