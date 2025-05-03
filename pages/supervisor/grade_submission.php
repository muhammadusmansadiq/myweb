<?php
// pages/supervisor/grade_submission.php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// // Check if the user is logged in and has the role of Supervisor (RoleID = 2)
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
//     header("Location: ../login.php");
//     exit();
// }

$supervisorID = $_SESSION['user_id'];
$error = "";
$success = "";

// Get submission_id from URL
$submissionID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($submissionID <= 0) {
    header("Location: view_submissions.php");
    exit();
}

// Handle submission grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_submission') {
    $action = $_POST['review_action']; // 'accept' or 'reject'
    $feedback = trim($_POST['feedback']);
    $obtainedMarks = isset($_POST['obtained_marks']) ? intval($_POST['obtained_marks']) : 0;
    $maxMarks = isset($_POST['max_marks']) ? intval($_POST['max_marks']) : 0;
    
    // Validate marks
    if ($obtainedMarks < 0) {
        $error = "Obtained marks cannot be negative.";
    } elseif ($obtainedMarks > $maxMarks) {
        $error = "Obtained marks cannot exceed maximum marks.";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update submission review status
            $reviewStatus = ($action === 'accept') ? 'Accepted' : 'Rejected';
            $stmt = $pdo->prepare("
                UPDATE Submissions 
                SET 
                    ReviewStatus = :reviewStatus,
                    Remarks = :remarks,
                    ReviewedBy = :reviewedBy,
                    ReviewedAt = NOW(),
                    ObtainedMarks = :obtainedMarks
                WHERE 
                    SubmissionID = :submissionID
            ");
            $stmt->execute([
                ':reviewStatus' => $reviewStatus,
                ':remarks' => $feedback,
                ':reviewedBy' => $supervisorID,
                ':obtainedMarks' => $obtainedMarks,
                ':submissionID' => $submissionID
            ]);
            
            // Get the project ID for this submission
            $stmt = $pdo->prepare("SELECT ProjectID FROM Submissions WHERE SubmissionID = :submissionID");
            $stmt->execute([':submissionID' => $submissionID]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $submissionProjectID = $result['ProjectID'];
            
            // Add to project history
            $historyAction = "Submission " . $reviewStatus . " with " . $obtainedMarks . " marks";
            $stmt = $pdo->prepare("
                INSERT INTO ProjectHistory (
                    ProjectID, 
                    Action, 
                    ActionDate, 
                    UserID
                ) VALUES (
                    :projectID, 
                    :action, 
                    NOW(), 
                    :userID
                )
            ");
            $stmt->execute([
                ':projectID' => $submissionProjectID,
                ':action' => $historyAction,
                ':userID' => $supervisorID
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Submission has been " . strtolower($reviewStatus) . " and graded successfully.";
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error updating submission: " . $e->getMessage();
        }
    }
}

// Fetch the submission details
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.SubmissionID,
            s.SubmissionType,
            s.Version,
            s.SubmittedAt,
            s.Status AS SubmissionStatus,
            s.ReviewStatus,
            s.Remarks,
            s.ReviewedBy,
            s.ReviewedAt,
            s.ObtainedMarks,
            p.ProjectID,
            p.Title AS ProjectTitle,
            p.GroupID,
            m.MilestoneID,
            m.MilestoneTitle,
            m.DueDate,
            m.MaxMarks,
            g.GroupName
        FROM 
            Submissions s
        JOIN 
            Projects p ON s.ProjectID = p.ProjectID
        JOIN 
            Milestones m ON s.MilestoneID = m.MilestoneID
        JOIN 
            Groups g ON p.GroupID = g.GroupID
        WHERE 
            s.SubmissionID = :submissionID
            AND g.SupervisorID = :supervisorID
    ");
    $stmt->execute([
        ':submissionID' => $submissionID,
        ':supervisorID' => $supervisorID
    ]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        $error = "You do not have permission to grade this submission or it doesn't exist.";
    } else {
        // Fetch files for this submission
        $stmt = $pdo->prepare("
            SELECT 
                f.FileID,
                f.FileName,
                f.FilePath,
                f.FileType,
                f.FileSize,
                f.UploadedAt,
                f.UploadedBy,
                CONCAT(pr.FirstName, ' ', pr.LastName) AS UploaderName,
                u.Username AS UploaderUsername
            FROM 
                FileUploads f
            JOIN 
                Users u ON f.UploadedBy = u.UserID
            LEFT JOIN 
                Profile pr ON u.UserID = pr.UserID
            WHERE 
                f.SubmissionID = :submissionID
            ORDER BY 
                f.UploadedAt ASC
        ");
        $stmt->execute([':submissionID' => $submissionID]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If the submission was already reviewed, get reviewer's info
        if ($submission['ReviewStatus'] !== 'Pending' && $submission['ReviewedBy']) {
            $stmt = $pdo->prepare("
                SELECT 
                    CONCAT(p.FirstName, ' ', p.LastName) AS ReviewerName,
                    u.Username AS ReviewerUsername
                FROM 
                    Users u
                LEFT JOIN 
                    Profile p ON u.UserID = p.UserID
                WHERE 
                    u.UserID = :reviewerID
            ");
            $stmt->execute([':reviewerID' => $submission['ReviewedBy']]);
            $reviewer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reviewer) {
                $submission['ReviewerName'] = $reviewer['ReviewerName'] ?: $reviewer['ReviewerUsername'];
            }
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submission</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-5xl mx-auto">
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
                
                <?php if (!$submission): ?>
                    <div class="mt-8 text-center">
                        <a href="view_submissions.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                            Back to Submissions
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
                
                <div class="mt-8 text-center">
                    <a href="view_submissions.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Back to Submissions
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($submission)): ?>
                <!-- Submission Details Section -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h1 class="text-2xl font-bold text-white">Grade Submission</h1>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mb-6">
                            <div>
                                <p class="text-sm text-gray-500">Project</p>
                                <p class="text-lg font-medium"><?php echo htmlspecialchars($submission['ProjectTitle']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Group</p>
                                <p class="text-lg font-medium"><?php echo htmlspecialchars($submission['GroupName']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Milestone</p>
                                <p class="text-lg font-medium"><?php echo htmlspecialchars($submission['MilestoneTitle']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Submission Type</p>
                                <p class="text-lg font-medium"><?php echo htmlspecialchars($submission['SubmissionType']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Due Date</p>
                                <p class="text-lg font-medium"><?php echo date('F d, Y', strtotime($submission['DueDate'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Submitted On</p>
                                <p class="text-lg font-medium">
                                    <?php echo date('F d, Y, h:i A', strtotime($submission['SubmittedAt'])); ?>
                                    <?php if ($submission['SubmissionStatus'] === 'Late'): ?>
                                        <span class="text-red-500 text-sm ml-2">Late Submission</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Maximum Marks</p>
                                <p class="text-lg font-medium"><?php echo $submission['MaxMarks'] ?: 'Not specified'; ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Current Status</p>
                                <p class="text-lg font-medium">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium
                                        <?php
                                            switch ($submission['ReviewStatus']) {
                                                case 'Accepted':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'Rejected':
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                                default:
                                                    echo 'bg-yellow-100 text-yellow-800';
                                            }
                                        ?>">
                                        <?php echo htmlspecialchars($submission['ReviewStatus']); ?>
                                    </span>
                                    
                                    <?php if ($submission['ReviewStatus'] !== 'Pending'): ?>
                                        <span class="text-gray-500 text-sm ml-2">
                                            by <?php echo htmlspecialchars($submission['ReviewerName']); ?> 
                                            on <?php echo date('M d, Y', strtotime($submission['ReviewedAt'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($submission['Remarks']) && $submission['ReviewStatus'] !== 'Pending'): ?>
                            <div class="mb-6">
                                <h3 class="text-lg font-medium mb-2">Feedback</h3>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="whitespace-pre-line"><?php echo nl2br(htmlspecialchars($submission['Remarks'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($files)): ?>
                            <div class="mb-6">
                                <h3 class="text-lg font-medium mb-2">Submitted Files</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php foreach ($files as $file): ?>
                                        <?php
                                            // Determine file icon based on file type
                                            $fileExtension = pathinfo($file['FileName'], PATHINFO_EXTENSION);
                                            $fileIcon = '';
                                            
                                            switch (strtolower($fileExtension)) {
                                                case 'pdf':
                                                    $fileIcon = '<svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path><path d="M3 8a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path></svg>';
                                                    break;
                                                case 'doc':
                                                case 'docx':
                                                    $fileIcon = '<svg class="w-8 h-8 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>';
                                                    break;
                                                case 'xls':
                                                case 'xlsx':
                                                case 'csv':
                                                    $fileIcon = '<svg class="w-8 h-8 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1h4a1 1 0 001-1v-1h-5v2zm0-4h5V8h-5v2zM9 8H4v2h5V8z" clip-rule="evenodd"></path></svg>';
                                                    break;
                                                case 'zip':
                                                case 'rar':
                                                case '7z':
                                                    $fileIcon = '<svg class="w-8 h-8 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2h8v1H6V6zm0 3h8v1H6V9zm0 3h8v1H6v-1z" clip-rule="evenodd"></path></svg>';
                                                    break;
                                                case 'jpg':
                                                case 'jpeg':
                                                case 'png':
                                                case 'gif':
                                                    $fileIcon = '<svg class="w-8 h-8 text-pink-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path></svg>';
                                                    break;
                                                default:
                                                    $fileIcon = '<svg class="w-8 h-8 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>';
                                                    break;
                                            }
                                            
                                            // Format file size
                                            $fileSize = $file['FileSize'];
                                            if ($fileSize < 1024) {
                                                $formattedSize = $fileSize . ' bytes';
                                            } elseif ($fileSize < 1024 * 1024) {
                                                $formattedSize = round($fileSize / 1024, 1) . ' KB';
                                            } else {
                                                $formattedSize = round($fileSize / (1024 * 1024), 1) . ' MB';
                                            }
                                        ?>
                                        <div class="flex items-center p-3 border rounded-md">
                                            <div class="flex-shrink-0">
                                                <?php echo $fileIcon; ?>
                                            </div>
                                            <div class="ml-3 flex-grow">
                                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($file['FileName']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo $formattedSize; ?> • Uploaded <?php echo date('M d, Y, h:i A', strtotime($file['UploadedAt'])); ?></p>
                                                <p class="text-xs text-gray-500">By <?php echo htmlspecialchars($file['UploaderName'] ?: $file['UploaderUsername']); ?></p>
                                            </div>
                                            <a href="download_file.php?id=<?php echo $file['FileID']; ?>" class="ml-3 bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-semibold py-1 px-3 rounded-full transition duration-200">
                                                Download
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($submission['ReviewStatus'] === 'Pending'): ?>
                            <!-- Grading Form -->
                            <div class="mt-8 bg-gray-50 p-6 rounded-lg border">
                                <h3 class="text-xl font-bold mb-4">Grade Submission</h3>
                                <form method="POST" class="space-y-6">
                                    <input type="hidden" name="action" value="grade_submission">
                                    <input type="hidden" name="max_marks" value="<?php echo $submission['MaxMarks']; ?>">
                                    
                                    <div>
                                        <label for="obtained_marks" class="block text-sm font-medium text-gray-700 mb-1">Marks</label>
                                        <div class="flex items-center">
                                            <input 
                                                type="number" 
                                                id="obtained_marks" 
                                                name="obtained_marks" 
                                                min="0" 
                                                max="<?php echo $submission['MaxMarks']; ?>" 
                                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-24 sm:text-sm border-gray-300 rounded-md" 
                                                required
                                            >
                                            <span class="mx-2 text-gray-500">/</span>
                                            <span class="text-gray-700"><?php echo $submission['MaxMarks']; ?></span>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Enter the marks obtained out of <?php echo $submission['MaxMarks']; ?> maximum marks.
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <label for="feedback" class="block text-sm font-medium text-gray-700 mb-1">Feedback</label>
                                        <textarea 
                                            id="feedback" 
                                            name="feedback" 
                                            rows="5" 
                                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" 
                                            placeholder="Provide feedback to the student about their submission..."
                                            required
                                        ></textarea>
                                    </div>
                                    
                                    <div class="flex justify-end space-x-4 pt-4 border-t">
                                        <button 
                                            type="submit" 
                                            name="review_action" 
                                            value="reject" 
                                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            Reject Submission
                                        </button>
                                        <button 
                                            type="submit" 
                                            name="review_action" 
                                            value="accept" 
                                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                        >
                                            Accept Submission
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Previously Graded Information -->
                            <div class="mt-8 bg-gray-50 p-6 rounded-lg border">
                                <h3 class="text-xl font-bold mb-4">Grading Information</h3>
                                <div class="mb-4">
                                    <p class="text-sm text-gray-500">Marks Obtained</p>
                                    <p class="text-xl font-bold">
                                        <?php echo $submission['ObtainedMarks']; ?> / <?php echo $submission['MaxMarks']; ?>
                                        <span class="text-sm font-normal text-gray-500 ml-2">
                                            (<?php echo round(($submission['ObtainedMarks'] / $submission['MaxMarks']) * 100); ?>%)
                                        </span>
                                    </p>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-sm text-gray-500">Status</p>
                                    <p class="text-lg">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium
                                            <?php echo $submission['ReviewStatus'] === 'Accepted' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo htmlspecialchars($submission['ReviewStatus']); ?>
                                        </span>
                                    </p>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-sm text-gray-500">Graded By</p>
                                    <p class="text-lg"><?php echo htmlspecialchars($submission['ReviewerName']); ?></p>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-sm text-gray-500">Graded On</p>
                                    <p class="text-lg"><?php echo date('F d, Y, h:i A', strtotime($submission['ReviewedAt'])); ?></p>
                                </div>
                                
                                <div class="flex justify-end">
                                    <a href="view_submissions.php" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Back to Submissions
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>