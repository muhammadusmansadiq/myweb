<?php
// pages/supervisor/view_group.php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// Check if the user is logged in and has the role of Supervisor (RoleID = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

$supervisorID = $_SESSION['user_id'];
$groupID = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = "";
$success = "";

if ($groupID <= 0) {
    header("Location: manage_groups.php");
    exit();
}

// Verify this supervisor owns this group
try {
    $stmt = $pdo->prepare("
        SELECT 
            g.GroupID, 
            g.GroupName, 
            g.Description, 
            g.Status,
            g.CreatedAt
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
        $error = "You do not have permission to view this group or the group does not exist.";
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Fetch students in the group if group is valid
if (empty($error)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sg.StudentGroupID,
                u.UserID,
                u.Username,
                u.Email,
                CONCAT(p.FirstName, ' ', p.LastName) AS StudentName,
                sg.JoinedAt,
                sg.Status AS MembershipStatus
            FROM 
                StudentGroups sg
            JOIN 
                Users u ON sg.StudentID = u.UserID
            LEFT JOIN 
                Profile p ON u.UserID = p.UserID
            WHERE 
                sg.GroupID = :groupID
            ORDER BY 
                p.FirstName, p.LastName
        ");
        $stmt->execute([':groupID' => $groupID]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching students: " . $e->getMessage();
    }
}

// Fetch projects for this group
if (empty($error)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.ProjectID,
                p.Title,
                p.Description,
                p.Status,
                p.CreatedAt,
                (SELECT COUNT(*) FROM Milestones m WHERE m.ProjectID = p.ProjectID) AS MilestoneCount,
                (SELECT COUNT(*) FROM Submissions s WHERE s.ProjectID = p.ProjectID) AS SubmissionCount
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

// Create new project handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $objectives = trim($_POST['objectives']);
    
    if (empty($title) || empty($description)) {
        $error = "Project title and description are required.";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert the project
            $stmt = $pdo->prepare("
                INSERT INTO Projects (
                    Title,
                    Description,
                    Objectives,
                    GroupID,
                    SupervisorID,
                    Status,
                    CreatedAt,
                    UpdatedAt
                ) VALUES (
                    :title,
                    :description,
                    :objectives,
                    :groupID,
                    :supervisorID,
                    'Initiated',
                    NOW(),
                    NOW()
                )
            ");
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':objectives' => $objectives,
                ':groupID' => $groupID,
                ':supervisorID' => $supervisorID
            ]);
            
            $projectID = $pdo->lastInsertId();
            
            // Record in project history
            $stmt = $pdo->prepare("
                INSERT INTO ProjectHistory (
                    ProjectID,
                    Action,
                    ActionDate,
                    UserID
                ) VALUES (
                    :projectID,
                    'Project Created',
                    NOW(),
                    :userID
                )
            ");
            $stmt->execute([
                ':projectID' => $projectID,
                ':userID' => $supervisorID
            ]);
            
            // Commit the transaction
            $pdo->commit();
            
            $success = "Project created successfully.";
            
            // Refresh the page
            header("Location: view_group.php?id=$groupID");
            exit();
        } catch (PDOException $e) {
            // Rollback the transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error creating project: " . $e->getMessage();
        }
    }
}

// Handle student removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_student') {
    $studentGroupID = intval($_POST['student_group_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM StudentGroups WHERE StudentGroupID = :studentGroupID");
        $stmt->execute([':studentGroupID' => $studentGroupID]);
        
        $success = "Student removed from group successfully.";
        
        // Refresh the page
        header("Location: view_group.php?id=$groupID");
        exit();
    } catch (PDOException $e) {
        $error = "Error removing student: " . $e->getMessage();
    }
}

// Add student to group handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $studentID = intval($_POST['student_id']);
    
    if ($studentID <= 0) {
        $error = "Invalid student ID.";
    } else {
        try {
            // Check if student is already in any group
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS count 
                FROM StudentGroups 
                WHERE StudentID = :studentID
            ");
            $stmt->execute([':studentID' => $studentID]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                $error = "This student is already in a group.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO StudentGroups (
                        GroupID,
                        StudentID,
                        JoinedAt,
                        Status
                    ) VALUES (
                        :groupID,
                        :studentID,
                        NOW(),
                        'Active'
                    )
                ");
                $stmt->execute([
                    ':groupID' => $groupID,
                    ':studentID' => $studentID
                ]);
                
                $success = "Student added to group successfully.";
                
                // Refresh the page
                header("Location: view_group.php?id=$groupID");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Error adding student: " . $e->getMessage();
        }
    }
}

// Fetch available students (not in any group)
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.UserID,
            u.Username,
            u.Email,
            CONCAT(p.FirstName, ' ', p.LastName) AS StudentName
        FROM 
            Users u
        LEFT JOIN 
            Profile p ON u.UserID = p.UserID
        WHERE 
            u.RoleID = 3
            AND u.StatusID = 5
            AND u.UserID NOT IN (SELECT StudentID FROM StudentGroups)
        ORDER BY 
            p.FirstName, p.LastName
    ");
    $stmt->execute();
    $availableStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching available students: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Group</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.10.5/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8" x-data="{ showCreateProjectModal: false, showAddStudentModal: false }">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Group Details</h1>
            <a href="manage_groups.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Groups
            </a>
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
        
        <?php if (!empty($group)): ?>
            <!-- Group Details Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-6">
                    <h2 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($group['GroupName']); ?></h2>
                    <p class="text-blue-100 mt-1">Created: <?php echo date('F d, Y', strtotime($group['CreatedAt'])); ?></p>
                </div>
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-2">Group Description</h3>
                    <p class="text-gray-700 mb-6"><?php echo nl2br(htmlspecialchars($group['Description'] ?: 'No description provided.')); ?></p>
                    
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Group Members (<?php echo count($students); ?>)</h3>
                        <button 
                            @click="showAddStudentModal = true" 
                            class="bg-green-500 hover:bg-green-600 text-white text-sm font-medium py-2 px-4 rounded transition-colors duration-200">
                            Add Student
                        </button>
                    </div>
                    
                    <?php if (empty($students)): ?>
                        <p class="text-gray-500 italic mb-6">No students in this group yet.</p>
                    <?php else: ?>
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($students as $student): ?>
                                    <div class="bg-white rounded-md shadow-sm p-4 flex items-start justify-between">
                                        <div>
                                            <h4 class="font-semibold"><?php echo htmlspecialchars($student['StudentName'] ?: $student['Username']); ?></h4>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($student['Email']); ?></p>
                                            <p class="text-xs text-gray-400">Joined: <?php echo date('M d, Y', strtotime($student['JoinedAt'])); ?></p>
                                        </div>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to remove this student from the group?');">
                                            <input type="hidden" name="action" value="remove_student">
                                            <input type="hidden" name="student_group_id" value="<?php echo $student['StudentGroupID']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Create Project Modal -->
            <div 
                x-show="showCreateProjectModal" 
                @click.away="showCreateProjectModal = false" 
                class="fixed inset-0 flex items-center justify-center z-50"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-90"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-90"
                style="background-color: rgba(0, 0, 0, 0.5);"
            >
                <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-xl mx-3">
                    <h3 class="text-2xl font-bold mb-4">Create New Project</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_project">
                        <div class="mb-4">
                            <label for="title" class="block text-gray-700 font-bold mb-2">Project Title</label>
                            <input type="text" id="title" name="title" required 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        <div class="mb-4">
                            <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                            <textarea id="description" name="description" rows="3" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                        </div>
                        <div class="mb-6">
                            <label for="objectives" class="block text-gray-700 font-bold mb-2">Objectives</label>
                            <textarea id="objectives" name="objectives" rows="3"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" @click="showCreateProjectModal = false" 
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                                Cancel
                            </button>
                            <button type="submit" 
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Create Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Add Student Modal -->
            <div 
                x-show="showAddStudentModal" 
                @click.away="showAddStudentModal = false" 
                class="fixed inset-0 flex items-center justify-center z-50"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-90"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-90"
                style="background-color: rgba(0, 0, 0, 0.5);"
            >
                <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-3">
                    <h3 class="text-2xl font-bold mb-4">Add Student to Group</h3>
                    <?php if (empty($availableStudents)): ?>
                        <p class="text-gray-500 mb-4">No available students found. All students are already assigned to groups.</p>
                        <div class="flex justify-end">
                            <button type="button" @click="showAddStudentModal = false" 
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                                Close
                            </button>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_student">
                            <div class="mb-6">
                                <label for="student_id" class="block text-gray-700 font-bold mb-2">Select Student</label>
                                <select id="student_id" name="student_id" required 
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="">Select a student...</option>
                                    <?php foreach ($availableStudents as $student): ?>
                                        <option value="<?php echo $student['UserID']; ?>">
                                            <?php echo htmlspecialchars($student['StudentName'] ?: $student['Username']); ?> - 
                                            <?php echo htmlspecialchars($student['Email']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" @click="showAddStudentModal = false" 
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                                    Cancel
                                </button>
                                <button type="submit" 
                                    class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                                    Add Student
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <h2 class="text-2xl font-bold mb-4">Group Not Found</h2>
                <p class="text-gray-700 mb-6">The group you are trying to view doesn't exist or you don't have permission to view it.</p>
                <a href="manage_groups.php" class="inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Return to Groups
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>; ?>
                    
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Group Projects (<?php echo count($projects); ?>)</h3>
                        <button 
                            @click="showCreateProjectModal = true" 
                            class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium py-2 px-4 rounded transition-colors duration-200">
                            Create Project
                        </button>
                    </div>
                    
                    <?php if (empty($projects)): ?>
                        <p class="text-gray-500 italic">No projects in this group yet.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($projects as $project): ?>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-lg"><?php echo htmlspecialchars($project['Title']); ?></h4>
                                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars(substr($project['Description'], 0, 150) . (strlen($project['Description']) > 150 ? '...' : '')); ?></p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium
                                            <?php 
                                                switch ($project['Status']) {
                                                    case 'Initiated':
                                                        echo 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'Proposal Submitted':
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'Accepted':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'Rejected':
                                                        echo 'bg-red-100 text-red-800';
                                                        break;
                                                    case 'Completed':
                                                        echo 'bg-purple-100 text-purple-800';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-100 text-gray-800';
                                                }
                                            ?>">
                                            <?php echo htmlspecialchars($project['Status']); ?>
                                        </span>
                                    </div>
                                    <div class="mt-4 flex items-center text-sm text-gray-500 justify-between">
                                        <div class="flex space-x-4">
                                            <span><?php echo date('M d, Y', strtotime($project['CreatedAt'])); ?></span>
                                            <span><?php echo $project['MilestoneCount']; ?> milestone<?php echo $project['MilestoneCount'] !== 1 ? 's' : ''; ?></span>
                                            <span><?php echo $project['SubmissionCount']; ?> submission<?php echo $project['SubmissionCount'] !== 1 ? 's' : ''; ?></span>
                                        </div>
                                        <div class="flex space-x-3">
                                            <a href="create_milestone.php?project_id=<?php echo $project['ProjectID']; ?>" class="text-green-600 hover:text-green-800">
                                                Add Milestone
                                            </a>
                                            <a href="view_project.php?id=<?php echo $project['ProjectID']; ?>" class="text-blue-600 hover:text-blue-800">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif