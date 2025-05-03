<?php
// pages/supervisor/dashboard.php
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

// Fetch supervisor's groups with counts of students, projects, and pending submissions
try {
    $stmt = $pdo->prepare("
        SELECT 
            g.GroupID, 
            g.GroupName, 
            g.Status,
            g.CreatedAt,
            (SELECT COUNT(*) FROM StudentGroups sg WHERE sg.GroupID = g.GroupID AND sg.Status = 'Active') AS StudentCount,
            (SELECT COUNT(*) FROM Projects p WHERE p.GroupID = g.GroupID) AS ProjectCount,
            (
                SELECT COUNT(*) 
                FROM Submissions s 
                JOIN Projects p ON s.ProjectID = p.ProjectID 
                WHERE p.GroupID = g.GroupID AND s.ReviewStatus = 'Pending'
            ) AS PendingSubmissions
        FROM 
            Groups g
        WHERE 
            g.SupervisorID = :supervisorID AND g.Status = 'Active'
        ORDER BY 
            g.CreatedAt DESC
    ");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching groups: " . $e->getMessage();
}

// Fetch upcoming milestones across all groups
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.MilestoneID,
            m.MilestoneTitle,
            m.DueDate,
            m.Status,
            p.ProjectID,
            p.Title AS ProjectTitle,
            g.GroupID,
            g.GroupName
        FROM 
            Milestones m
        JOIN 
            Projects p ON m.ProjectID = p.ProjectID
        JOIN 
            Groups g ON p.GroupID = g.GroupID
        WHERE 
            g.SupervisorID = :supervisorID
            AND m.Status = 'Pending'
            AND m.DueDate >= CURDATE()
        ORDER BY 
            m.DueDate ASC
        LIMIT 10
    ");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $upcomingMilestones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching milestones: " . $e->getMessage();
}

// Fetch recent submissions across all groups
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.SubmissionID,
            s.SubmissionType,
            s.SubmittedAt,
            s.Status,
            s.ReviewStatus,
            p.ProjectID,
            p.Title AS ProjectTitle,
            g.GroupID,
            g.GroupName,
            m.MilestoneTitle
        FROM 
            Submissions s
        JOIN 
            Projects p ON s.ProjectID = p.ProjectID
        JOIN 
            Groups g ON p.GroupID = g.GroupID
        JOIN 
            Milestones m ON s.MilestoneID = m.MilestoneID
        WHERE 
            g.SupervisorID = :supervisorID
        ORDER BY 
            s.SubmittedAt DESC
        LIMIT 10
    ");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $recentSubmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching submissions: " . $e->getMessage();
}

// Fetch recent feedback across all groups
try {
    $stmt = $pdo->prepare("
        SELECT 
            f.FeedbackID,
            f.FeedbackText,
            f.SentAt,
            f.SenderID,
            f.ReceiverID,
            p.ProjectID,
            p.Title AS ProjectTitle,
            g.GroupID,
            g.GroupName,
            CONCAT(pr.FirstName, ' ', pr.LastName) AS SenderName,
            u.Username AS SenderUsername
        FROM 
            Feedback f
        JOIN 
            Projects p ON f.ProjectID = p.ProjectID
        JOIN 
            Groups g ON p.GroupID = g.GroupID
        JOIN 
            Users u ON f.SenderID = u.UserID
        LEFT JOIN 
            Profile pr ON u.UserID = pr.UserID
        WHERE 
            g.SupervisorID = :supervisorID
        ORDER BY 
            f.SentAt DESC
        LIMIT 10
    ");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $recentFeedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching feedback: " . $e->getMessage();
}

// Get supervisor's profile info
try {
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(p.FirstName, ' ', p.LastName) AS SupervisorName,
            u.Email,
            u.GroupCount,
            u.Username
        FROM 
            Users u
        LEFT JOIN 
            Profile p ON u.UserID = p.UserID
        WHERE 
            u.UserID = :supervisorID
    ");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $supervisorProfile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching profile: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
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
        
        <!-- Welcome Section -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-8">
                <h1 class="text-3xl font-bold text-white mb-2">Welcome, <?php echo htmlspecialchars($supervisorProfile['SupervisorName'] ?: $supervisorProfile['Username']); ?></h1>
                <p class="text-blue-100">
                    You are supervising <?php echo count($groups); ?> group(s) with a total of 
                    <?php 
                        $totalStudents = 0;
                        foreach ($groups as $group) {
                            $totalStudents += $group['StudentCount'];
                        }
                        echo $totalStudents;
                    ?> students
                </p>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Groups Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold">My Groups</h2>
                        <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                            <?php echo count($groups); ?>/3
                        </span>
                    </div>
                    <?php if (count($groups) < 3): ?>
                        <a href="manage_groups.php" class="block text-center bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
                            Manage Groups
                        </a>
                    <?php else: ?>
                        <p class="text-yellow-600 text-sm mb-2">You've reached the maximum number of groups (3)</p>
                        <a href="manage_groups.php" class="block text-center bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
                            View Groups
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Submissions Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold">Pending Reviews</h2>
                        <?php 
                            $totalPending = 0;
                            foreach ($groups as $group) {
                                $totalPending += $group['PendingSubmissions'];
                            }
                        ?>
                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                            <?php echo $totalPending; ?> submission<?php echo $totalPending !== 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    <a href="view_all_submissions.php" class="block text-center bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
                        Review Submissions
                    </a>
                </div>
            </div>
            
            <!-- Upcoming Milestones Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold">Upcoming Milestones</h2>
                        <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                            <?php echo count($upcomingMilestones); ?>
                        </span>
                    </div>
                    <a href="create_milestone.php" class="block text-center bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
                        Create Milestone
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Groups Overview -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                <h2 class="text-xl font-bold text-white">My Groups</h2>
            </div>
            <div class="p-6">
                <?php if (empty($groups)): ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500 text-lg">You haven't created any groups yet.</p>
                        <a href="manage_groups.php" class="inline-block mt-4 bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
                            Create Your First Group
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($groups as $group): ?>
                            <div class="border rounded-lg shadow-sm overflow-hidden">
                                <div class="bg-blue-50 p-4 border-b">
                                    <h3 class="text-lg font-bold"><?php echo htmlspecialchars($group['GroupName']); ?></h3>
                                    <p class="text-sm text-gray-500">Created: <?php echo date('M d, Y', strtotime($group['CreatedAt'])); ?></p>
                                </div>
                                <div class="p-4">
                                    <div class="grid grid-cols-3 gap-4 mb-4">
                                        <div class="text-center">
                                            <p class="text-sm text-gray-500">Students</p>
                                            <p class="text-xl font-bold"><?php echo $group['StudentCount']; ?></p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-sm text-gray-500">Projects</p>
                                            <p class="text-xl font-bold"><?php echo $group['ProjectCount']; ?></p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-sm text-gray-500">Pending</p>
                                            <p class="text-xl font-bold <?php echo $group['PendingSubmissions'] > 0 ? 'text-yellow-600' : 'text-gray-700'; ?>">
                                                <?php echo $group['PendingSubmissions']; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex justify-between">
                                        <a href="view_group.php?id=<?php echo $group['GroupID']; ?>" class="text-blue-500 hover:text-blue-700">
                                            View Details
                                        </a>
                                        <a href="create_milestone.php?group_id=<?php echo $group['GroupID']; ?>" class="text-green-500 hover:text-green-700">
                                            Add Milestone
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Recent Feedback -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 p-4">
                    <h2 class="text-xl font-bold text-white">Recent Feedback</h2>
                </div>
                <div class="p-4">
                    <?php if (empty($recentFeedback)): ?>
                        <p class="text-gray-500 italic text-center py-4">No recent feedback found.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentFeedback as $feedback): ?>
                                <div class="border rounded-md p-3">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <span class="font-medium"><?php echo htmlspecialchars($feedback['ProjectTitle']); ?></span>
                                            <p class="text-xs text-gray-500">
                                                From: <?php echo htmlspecialchars($feedback['SenderName'] ?: $feedback['SenderUsername']); ?> 
                                                on <?php echo date('M d, Y', strtotime($feedback['SentAt'])); ?>
                                            </p>
                                        </div>
                                        <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-full">
                                            <?php echo htmlspecialchars($feedback['GroupName']); ?>
                                        </span>
                                    </div>
                                    <p class="text-gray-700 text-sm"><?php echo nl2br(htmlspecialchars(substr($feedback['FeedbackText'], 0, 100) . (strlen($feedback['FeedbackText']) > 100 ? '...' : ''))); ?></p>
                                    <div class="mt-2 text-right">
                                        <a href="view_feedback.php?student_id=<?php echo $feedback['ReceiverID']; ?>&project_id=<?php echo $feedback['ProjectID']; ?>" class="text-blue-500 hover:text-blue-700 text-xs font-medium">
                                            View Full Conversation
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="view_all_feedback.php" class="text-blue-500 hover:text-blue-700">View All Feedback →</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Activity Summary -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-pink-600 to-pink-800 p-4">
                    <h2 class="text-xl font-bold text-white">Activity Summary</h2>
                </div>
                <div class="p-4">
                    <canvas id="activityChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-gray-700 to-gray-900 p-4">
                <h2 class="text-xl font-bold text-white">Quick Actions</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="manage_groups.php" class="bg-blue-100 hover:bg-blue-200 text-blue-800 text-center rounded-lg p-4 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Manage Groups</span>
                    </a>
                    <a href="create_milestone.php" class="bg-green-100 hover:bg-green-200 text-green-800 text-center rounded-lg p-4 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Create Milestone</span>
                    </a>
                    <a href="view_all_submissions.php" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 text-center rounded-lg p-4 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Review Submissions</span>
                    </a>
                    <a href="view_all_feedback.php" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-800 text-center rounded-lg p-4 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        <span>View Feedback</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Activity Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('activityChart').getContext('2d');
            
            // Sample data - in a real application, this would come from the database
            const submissionCounts = <?php 
                // Generate sample submission data for the last 7 days
                $submissionData = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $count = 0;
                    foreach ($recentSubmissions as $submission) {
                        $submissionDate = date('Y-m-d', strtotime($submission['SubmittedAt']));
                        if ($submissionDate === $date) {
                            $count++;
                        }
                    }
                    $submissionData[] = $count;
                }
                echo json_encode($submissionData);
            ?>;
            
            const feedbackCounts = <?php 
                // Generate sample feedback data for the last 7 days
                $feedbackData = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $count = 0;
                    foreach ($recentFeedback as $feedback) {
                        $feedbackDate = date('Y-m-d', strtotime($feedback['SentAt']));
                        if ($feedbackDate === $date) {
                            $count++;
                        }
                    }
                    $feedbackData[] = $count;
                }
                echo json_encode($feedbackData);
            ?>;
            
            const labels = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('en-US', { weekday: 'short' }));
            }
            
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Submissions',
                            data: submissionCounts,
                            backgroundColor: 'rgba(79, 70, 229, 0.6)',
                            borderColor: 'rgba(79, 70, 229, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Feedback',
                            data: feedbackCounts,
                            backgroundColor: 'rgba(219, 39, 119, 0.6)',
                            borderColor: 'rgba(219, 39, 119, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>d-cols-2 gap-8 mb-8">
            <!-- Upcoming Milestones -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-green-800 p-4">
                    <h2 class="text-xl font-bold text-white">Upcoming Milestones</h2>
                </div>
                <div class="p-4">
                    <?php if (empty($upcomingMilestones)): ?>
                        <p class="text-gray-500 italic text-center py-4">No upcoming milestones found.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Left</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($upcomingMilestones as $milestone): ?>
                                        <?php
                                            $dueDate = new DateTime($milestone['DueDate']);
                                            $today = new DateTime();
                                            $interval = $today->diff($dueDate);
                                            $daysLeft = $interval->days;
                                            
                                            $rowClass = '';
                                            if ($daysLeft <= 3) {
                                                $rowClass = 'bg-yellow-50';
                                            }
                                            if ($daysLeft <= 1) {
                                                $rowClass = 'bg-red-50';
                                            }
                                        ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td class="px-4 py-2 text-sm">
                                                <span title="<?php echo htmlspecialchars($milestone['MilestoneTitle']); ?>">
                                                    <?php echo htmlspecialchars(mb_substr($milestone['MilestoneTitle'], 0, 20) . (mb_strlen($milestone['MilestoneTitle']) > 20 ? '...' : '')); ?>
                                                </span>
                                                <span class="block text-xs text-gray-500">
                                                    <?php echo htmlspecialchars(mb_substr($milestone['ProjectTitle'], 0, 20) . (mb_strlen($milestone['ProjectTitle']) > 20 ? '...' : '')); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($milestone['GroupName']); ?></td>
                                            <td class="px-4 py-2 text-sm"><?php echo date('M d, Y', strtotime($milestone['DueDate'])); ?></td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="<?php echo $daysLeft <= 3 ? 'text-red-600 font-bold' : ''; ?>">
                                                    <?php echo $daysLeft; ?> day<?php echo $daysLeft !== 1 ? 's' : ''; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Submissions -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                    <h2 class="text-xl font-bold text-white">Recent Submissions</h2>
                </div>
                <div class="p-4">
                    <?php if (empty($recentSubmissions)): ?>
                        <p class="text-gray-500 italic text-center py-4">No recent submissions found.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($recentSubmissions as $submission): ?>
                                        <tr>
                                            <td class="px-4 py-2 text-sm">
                                                <span title="<?php echo htmlspecialchars($submission['SubmissionType']); ?>">
                                                    <?php echo htmlspecialchars($submission['SubmissionType']); ?>
                                                </span>
                                                <span class="block text-xs text-gray-500">
                                                    <?php echo htmlspecialchars(mb_substr($submission['MilestoneTitle'], 0, 20) . (mb_strlen($submission['MilestoneTitle']) > 20 ? '...' : '')); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($submission['GroupName']); ?></td>
                                            <td class="px-4 py-2 text-sm"><?php echo date('M d, Y', strtotime($submission['SubmittedAt'])); ?></td>
                                            <td class="px-4 py-2 text-sm">
                                                <?php 
                                                    $statusColor = '';
                                                    if ($submission['ReviewStatus'] === 'Pending') {
                                                        $statusColor = 'bg-yellow-100 text-yellow-800';
                                                    } elseif ($submission['ReviewStatus'] === 'Accepted') {
                                                        $statusColor = 'bg-green-100 text-green-800';
                                                    } elseif ($submission['ReviewStatus'] === 'Rejected') {
                                                        $statusColor = 'bg-red-100 text-red-800';
                                                    }
                                                ?>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusColor; ?>">
                                                    <?php echo htmlspecialchars($submission['ReviewStatus']); ?>
                                                </span>
                                                <?php if ($submission['Status'] === 'Late'): ?>
                                                    <span class="block text-xs text-red-500">Late</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="view_all_submissions.php" class="text-blue-500 hover:text-blue-700">View All Submissions →</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:gri