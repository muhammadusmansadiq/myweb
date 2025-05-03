<?php
// pages/supervisor/gemini_project_generator.php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// Check if the user is logged in and has the role of Supervisor (RoleID = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

$supervisorID = $_SESSION['user_id'];
$error = "";
$success = "";
$generatedProject = null;

// Handle project generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_project') {
    $topic = trim($_POST['topic']);
    $domain = trim($_POST['domain']);
    $technologies = isset($_POST['technologies']) ? $_POST['technologies'] : [];
    $complexity = trim($_POST['complexity']);
    
    if (empty($topic) || empty($domain) || empty($complexity)) {
        $error = "Topic, domain, and complexity are required.";
    } else {
        try {
            // In a real implementation, call the Gemini API here
            // For now, we'll simulate the response
            $projectDetails = simulateGeminiProjectGeneration($topic, $domain, $technologies, $complexity);
            
            // Store the generated project in the database
            $stmt = $pdo->prepare("
                INSERT INTO GeneratedProjects (
                    SupervisorID,
                    Title,
                    Description,
                    Objectives,
                    TechnologyStack,
                    Complexity
                ) VALUES (
                    :supervisorID,
                    :title,
                    :description,
                    :objectives,
                    :technologyStack,
                    :complexity
                )
            ");
            
            $stmt->execute([
                ':supervisorID' => $supervisorID,
                ':title' => $projectDetails['title'],
                ':description' => $projectDetails['description'],
                ':objectives' => implode("\n- ", $projectDetails['objectives']),
                ':technologyStack' => implode(", ", $projectDetails['techStack']),
                ':complexity' => $complexity
            ]);
            
            $generatedProjectID = $pdo->lastInsertId();
            
            // Set the generated project for display
            $generatedProject = [
                'id' => $generatedProjectID,
                'title' => $projectDetails['title'],
                'description' => $projectDetails['description'],
                'objectives' => $projectDetails['objectives'],
                'techStack' => $projectDetails['techStack'],
                'complexity' => $complexity
            ];
            
            $success = "Project generated successfully.";
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        } catch (Exception $e) {
            $error = "Error generating project: " . $e->getMessage();
        }
    }
}

// Function to assign a generated project to a specific group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_project') {
    $generatedProjectID = intval($_POST['generated_project_id']);
    $groupID = intval($_POST['group_id']);
    
    if ($generatedProjectID <= 0 || $groupID <= 0) {
        $error = "Invalid project or group ID.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Fetch the generated project details
            $stmt = $pdo->prepare("
                SELECT * FROM GeneratedProjects 
                WHERE GeneratedProjectID = :id AND SupervisorID = :supervisorID
            ");
            $stmt->execute([
                ':id' => $generatedProjectID,
                ':supervisorID' => $supervisorID
            ]);
            $projectDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$projectDetails) {
                throw new Exception("Project not found or you don't have permission to assign it.");
            }
            
            // Verify the group belongs to this supervisor
            $stmt = $pdo->prepare("
                SELECT GroupID FROM Groups 
                WHERE GroupID = :groupID AND SupervisorID = :supervisorID
            ");
            $stmt->execute([
                ':groupID' => $groupID,
                ':supervisorID' => $supervisorID
            ]);
            $groupResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$groupResult) {
                throw new Exception("Group not found or you don't have permission to assign to it.");
            }
            
            // Create a new project in the Projects table
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
                ':title' => $projectDetails['Title'],
                ':description' => $projectDetails['Description'],
                ':objectives' => $projectDetails['Objectives'],
                ':groupID' => $groupID,
                ':supervisorID' => $supervisorID
            ]);
            
            $projectID = $pdo->lastInsertId();
            
            // Mark the generated project as assigned
            $stmt = $pdo->prepare("
                UPDATE GeneratedProjects 
                SET IsAssigned = TRUE 
                WHERE GeneratedProjectID = :id
            ");
            $stmt->execute([':id' => $generatedProjectID]);
            
            // Add to project history
            $stmt = $pdo->prepare("
                INSERT INTO ProjectHistory (
                    ProjectID,
                    Action,
                    ActionDate,
                    UserID
                ) VALUES (
                    :projectID,
                    'Project Initiated (AI-Generated)',
                    NOW(),
                    :supervisorID
                )
            ");
            $stmt->execute([
                ':projectID' => $projectID,
                ':supervisorID' => $supervisorID
            ]);
            
            $pdo->commit();
            $success = "Project successfully assigned to the group.";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// Fetch supervisor's groups for assignment dropdown
try {
    $stmt = $pdo->prepare("
        SELECT GroupID, GroupName 
        FROM Groups 
        WHERE SupervisorID = :supervisorID AND Status = 'Active'
    ");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $supervisorGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silent error - just show empty list
    $supervisorGroups = [];
}

// Fetch recent generated projects
try {
    $stmt = $pdo->prepare("
        SELECT 
            GeneratedProjectID,
            Title,
            Complexity,
            GeneratedAt,
            IsAssigned
        FROM 
            GeneratedProjects
        WHERE 
            SupervisorID = :supervisorID
        ORDER BY 
            GeneratedAt DESC
        LIMIT 10
    ");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silent error - just show empty list
    $recentProjects = [];
}

// Simulate Gemini API call for project generation
function simulateGeminiProjectGeneration($topic, $domain, $technologies, $complexity) {
    // In a real implementation, this would call the Gemini API with proper authentication
    // API_KEY would be stored securely in environment variables
    
    // Sample project titles by domain
    $domainTitles = [
        'web' => [
            'Interactive Web Portal for ' . $topic,
            'Responsive Web Application for ' . $topic . ' Management',
            'Progressive Web App for ' . $topic . ' Analysis'
        ],
        'mobile' => [
            'Mobile App for ' . $topic . ' Tracking',
            'Cross-platform Mobile Solution for ' . $topic,
            $topic . ' Companion Mobile Application'
        ],
        'ai' => [
            'AI-Powered ' . $topic . ' Analysis System',
            'Machine Learning Solution for ' . $topic . ' Prediction',
            'Intelligent ' . $topic . ' Classification System'
        ],
        'iot' => [
            'IoT-based ' . $topic . ' Monitoring System',
            'Smart ' . $topic . ' Management with IoT',
            'Connected Devices for ' . $topic . ' Automation'
        ],
        'security' => [
            'Secure ' . $topic . ' Authentication System',
            $topic . ' Security Assessment Platform',
            'Encrypted ' . $topic . ' Communication System'
        ],
        'data' => [
            $topic . ' Data Visualization Dashboard',
            'Big Data Analysis Platform for ' . $topic,
            'Data-Driven ' . $topic . ' Management System'
        ]
    ];
    
    // Default to web domain if the provided domain isn't in our list
    $selectedDomain = isset($domainTitles[$domain]) ? $domain : 'web';
    
    // Select a random title from the appropriate domain
    $titleOptions = $domainTitles[$selectedDomain];
    $title = $titleOptions[array_rand($titleOptions)];
    
    // Generate description based on domain and complexity
    $descriptions = [
        'web' => "This web-based application focuses on $topic, providing users with a comprehensive platform to interact with relevant data and functionalities. " . 
                "The system will implement modern web development practices with " . ($complexity == 'advanced' ? "advanced features like real-time updates, progressive enhancement, and offline capabilities." : "a clean, intuitive interface and responsive design."),
        'mobile' => "A mobile application dedicated to $topic that allows users to access key functionalities on the go. " . 
                   "The app will be designed with " . ($complexity == 'advanced' ? "advanced mobile features including push notifications, location services, and camera integration." : "a user-friendly interface optimized for mobile devices."),
        'ai' => "An intelligent system that leverages artificial intelligence to analyze and process $topic data. " . 
               "The solution will implement " . ($complexity == 'advanced' ? "sophisticated machine learning algorithms, natural language processing, and predictive analytics." : "basic AI concepts to enhance user experience and data processing."),
        'iot' => "An Internet of Things solution focused on $topic, connecting physical devices to a centralized monitoring and control system. " . 
                "The project will include " . ($complexity == 'advanced' ? "sensor integration, real-time data processing, and automated responses based on collected data." : "basic device connectivity and data collection from IoT devices."),
        'security' => "A security-focused application addressing $topic concerns, implementing robust protection mechanisms and best practices. " . 
                     "The system includes " . ($complexity == 'advanced' ? "advanced encryption, multi-factor authentication, and security logging and monitoring." : "basic security principles to protect user data and system integrity."),
        'data' => "A data-centric platform that collects, processes, and visualizes information related to $topic. " . 
                 "The system will implement " . ($complexity == 'advanced' ? "sophisticated data processing pipelines, interactive visualizations, and advanced analytics." : "basic data collection and representation techniques.")
    ];
    
    $description = $descriptions[$selectedDomain];
    
    // Generate objectives based on complexity and domain
    $baseObjectives = [
        "Develop a comprehensive system addressing $topic requirements",
        "Implement a user-friendly interface for interaction with the system",
        "Ensure data integrity and security throughout the application"
    ];
    
    $advancedObjectives = [
        'web' => [
            "Implement responsive design for optimal viewing across devices",
            "Incorporate progressive web app features for offline functionality",
            "Optimize performance using modern front-end techniques"
        ],
        'mobile' => [
            "Develop cross-platform compatibility for iOS and Android",
            "Implement push notifications for important updates",
            "Optimize for low data usage and battery consumption"
        ],
        'ai' => [
            "Train and validate machine learning models for accurate predictions",
            "Implement natural language processing for user queries",
            "Create visualization tools for AI decision explanation"
        ],
        'iot' => [
            "Establish secure communication between IoT devices and central server",
            "Implement real-time data processing from multiple sensors",
            "Create an alerting system for anomalous conditions"
        ],
        'security' => [
            "Implement advanced encryption for data at rest and in transit",
            "Create comprehensive security logging and monitoring",
            "Conduct penetration testing and vulnerability assessments"
        ],
        'data' => [
            "Build ETL pipelines for data from multiple sources",
            "Create interactive dashboards for data exploration",
            "Implement data analytics algorithms for insight generation"
        ]
    ];
    
    $objectives = $baseObjectives;
    
    if ($complexity == 'advanced' || $complexity == 'intermediate') {
        $domainSpecificObjectives = $advancedObjectives[$selectedDomain];
        // For intermediate, add fewer advanced objectives
        if ($complexity == 'intermediate') {
            $domainSpecificObjectives = array_slice($domainSpecificObjectives, 0, 1);
        }
        $objectives = array_merge($objectives, $domainSpecificObjectives);
    }
    
    // Use provided technologies or suggest appropriate ones if empty
    if (empty($technologies)) {
        $suggestedTech = [
            'web' => ['HTML', 'CSS', 'JavaScript', 'React', 'Node.js', 'MongoDB'],
            'mobile' => ['React Native', 'Flutter', 'Firebase', 'SQLite'],
            'ai' => ['Python', 'TensorFlow', 'scikit-learn', 'Pandas', 'NumPy'],
            'iot' => ['Arduino', 'Raspberry Pi', 'MQTT', 'Python', 'Node.js'],
            'security' => ['OAuth', 'JWT', 'SSL/TLS', 'Python', 'Java'],
            'data' => ['Python', 'SQL', 'Pandas', 'D3.js', 'Tableau', 'Power BI']
        ];
        
        $techStack = $suggestedTech[$selectedDomain];
    } else {
        $techStack = $technologies;
    }
    
    return [
        'title' => $title,
        'description' => $description,
        'objectives' => $objectives,
        'techStack' => $techStack
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Generator with Gemini AI</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center">Project Generator with Gemini AI</h1>
        
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
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Project Generator Form -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 p-4">
                    <h2 class="text-xl font-bold text-white">Generate a New Project Idea</h2>
                </div>
                
                <div class="p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="generate_project">
                        
                        <div>
                            <label for="topic" class="block text-sm font-medium text-gray-700 mb-1">Project Topic</label>
                            <input 
                                type="text" 
                                id="topic" 
                                name="topic" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="e.g., Healthcare, Education, Finance, E-commerce"
                                required
                            >
                        </div>
                        
                        <div>
                            <label for="domain" class="block text-sm font-medium text-gray-700 mb-1">Project Domain</label>
                            <select 
                                id="domain" 
                                name="domain" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                required
                            >
                                <option value="">Select domain...</option>
                                <option value="web">Web Development</option>
                                <option value="mobile">Mobile Development</option>
                                <option value="ai">Artificial Intelligence</option>
                                <option value="iot">Internet of Things</option>
                                <option value="security">Security</option>
                                <option value="data">Data Science</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="technologies" class="block text-sm font-medium text-gray-700 mb-1">Preferred Technologies (Optional)</label>
                            <select 
                                id="technologies" 
                                name="technologies[]" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                multiple
                            >
                                <option value="HTML">HTML</option>
                                <option value="CSS">CSS</option>
                                <option value="JavaScript">JavaScript</option>
                                <option value="React">React</option>
                                <option value="Angular">Angular</option>
                                <option value="Vue.js">Vue.js</option>
                                <option value="Node.js">Node.js</option>
                                <option value="PHP">PHP</option>
                                <option value="Laravel">Laravel</option>
                                <option value="Python">Python</option>
                                <option value="Django">Django</option>
                                <option value="Flask">Flask</option>
                                <option value="Java">Java</option>
                                <option value="Spring">Spring</option>
                                <option value="C#">C#</option>
                                <option value=".NET">.NET</option>
                                <option value="Swift">Swift</option>
                                <option value="Kotlin">Kotlin</option>
                                <option value="Flutter">Flutter</option>
                                <option value="React Native">React Native</option>
                                <option value="Firebase">Firebase</option>
                                <option value="MongoDB">MongoDB</option>
                                <option value="MySQL">MySQL</option>
                                <option value="PostgreSQL">PostgreSQL</option>
                                <option value="SQL Server">SQL Server</option>
                                <option value="TensorFlow">TensorFlow</option>
                                <option value="PyTorch">PyTorch</option>
                                <option value="scikit-learn">scikit-learn</option>
                                <option value="Arduino">Arduino</option>
                                <option value="Raspberry Pi">Raspberry Pi</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple technologies</p>
                        </div>
                        
                        <div>
                            <label for="complexity" class="block text-sm font-medium text-gray-700 mb-1">Project Complexity</label>
                            <select 
                                id="complexity" 
                                name="complexity" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                required
                            >
                                <option value="">Select complexity...</option>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                        
                        <div>
                            <button 
                                type="submit"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors duration-200"
                            >
                                Generate Project Idea
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Generated Project Display -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 p-4">
                    <h2 class="text-xl font-bold text-white">Generated Project</h2>
                </div>
                
                <div class="p-6">
                    <?php if ($generatedProject): ?>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($generatedProject['title']); ?></h3>
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($generatedProject['description'])); ?></p>
                            </div>
                            
                            <div>
                                <h4 class="text-lg font-medium mb-2">Objectives</h4>
                                <ul class="list-disc pl-5 space-y-1">
                                    <?php foreach ($generatedProject['objectives'] as $objective): ?>
                                        <li class="text-gray-700"><?php echo htmlspecialchars($objective); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 class="text-lg font-medium mb-2">Technology Stack</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($generatedProject['techStack'] as $tech): ?>
                                        <span class="px-2 py-1 bg-gray-200 rounded-full text-sm"><?php echo htmlspecialchars($tech); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-lg font-medium mb-2">Complexity</h4>
                                <span class="px-3 py-1 rounded-full text-sm font-medium 
                                    <?php 
                                        switch($generatedProject['complexity']) {
                                            case 'beginner':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'intermediate':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'advanced':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-blue-100 text-blue-800';
                                        }
                                    ?>">
                                    <?php echo ucfirst(htmlspecialchars($generatedProject['complexity'])); ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($supervisorGroups)): ?>
                                <div class="mt-6 pt-4 border-t border-gray-200">
                                    <h4 class="text-lg font-medium mb-2">Assign to Group</h4>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="assign_project">
                                        <input type="hidden" name="generated_project_id" value="<?php echo $generatedProject['id']; ?>">
                                        
                                        <div class="flex space-x-2">
                                            <select 
                                                name="group_id" 
                                                class="flex-grow px-4 py-2 border border-gray-300 rounded-l-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                required
                                            >
                                                <option value="">Select a group...</option>
                                                <?php foreach ($supervisorGroups as $group): ?>
                                                    <option value="<?php echo $group['GroupID']; ?>"><?php echo htmlspecialchars($group['GroupName']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <button 
                                                type="submit"
                                                class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-r-md focus:outline-none focus:shadow-outline transition-colors duration-200"
                                            >
                                                Assign
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 italic text-center">Generate a project idea to see its details here</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Generated Projects -->
        <?php if (!empty($recentProjects)): ?>
            <div class="mt-8 bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 p-4">
                    <h2 class="text-xl font-bold text-white">Recent Generated Projects</h2>
                </div>
                
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700">
                                    <th class="py-3 px-4 text-left">Title</th>
                                    <th class="py-3 px-4 text-left">Complexity</th>
                                    <th class="py-3 px-4 text-left">Generated</th>
                                    <th class="py-3 px-4 text-left">Status</th>
                                    <th class="py-3 px-4 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentProjects as $project): ?>
                                    <tr class="border-t hover:bg-gray-50">
                                        <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($project['Title']); ?></td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium 
                                                <?php 
                                                    switch($project['Complexity']) {
                                                        case 'beginner':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'intermediate':
                                                            echo 'bg-yellow-100 text-yellow-800';
                                                            break;
                                                        case 'advanced':
                                                            echo 'bg-red-100 text-red-800';
                                                            break;
                                                        default:
                                                            echo 'bg-blue-100 text-blue-800';
                                                    }
                                                ?>">
                                                <?php echo ucfirst(htmlspecialchars($project['Complexity'])); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-sm"><?php echo date('M d, Y', strtotime($project['GeneratedAt'])); ?></td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $project['IsAssigned'] ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo $project['IsAssigned'] ? 'Assigned' : 'Available'; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <a href="view_generated_project.php?id=<?php echo $project['GeneratedProjectID']; ?>" class="text-blue-500 hover:text-blue-700">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>