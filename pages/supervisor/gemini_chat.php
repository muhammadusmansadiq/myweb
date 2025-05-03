<?php
// pages/supervisor/gemini_chat.php
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

// Get conversation ID from URL if available
$conversationID = isset($_GET['id']) ? intval($_GET['id']) : 0;
$currentConversation = null;
$messages = [];

// Handle new conversation creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_conversation') {
    $topic = trim($_POST['topic']);
    
    if (empty($topic)) {
        $error = "Topic is required for a new conversation.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO GeminiConversations (
                    SupervisorID,
                    Topic
                ) VALUES (
                    :supervisorID,
                    :topic
                )
            ");
            
            $stmt->execute([
                ':supervisorID' => $supervisorID,
                ':topic' => $topic
            ]);
            
            $conversationID = $pdo->lastInsertId();
            header("Location: gemini_chat.php?id=" . $conversationID);
            exit();
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle sending a message in a conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $messageContent = trim($_POST['message']);
    $currentConversationID = intval($_POST['conversation_id']);
    
    if (empty($messageContent) || $currentConversationID <= 0) {
        $error = "Message cannot be empty and a valid conversation is required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Verify the conversation belongs to this supervisor
            $stmt = $pdo->prepare("
                SELECT ConversationID 
                FROM GeminiConversations 
                WHERE ConversationID = :conversationID AND SupervisorID = :supervisorID
            ");
            $stmt->execute([
                ':conversationID' => $currentConversationID,
                ':supervisorID' => $supervisorID
            ]);
            
            if (!$stmt->fetch()) {
                throw new Exception("Invalid conversation or you don't have permission to access it.");
            }
            
            // Insert the user's message
            $stmt = $pdo->prepare("
                INSERT INTO GeminiMessages (
                    ConversationID,
                    IsUserMessage,
                    Content
                ) VALUES (
                    :conversationID,
                    TRUE,
                    :content
                )
            ");
            
            $stmt->execute([
                ':conversationID' => $currentConversationID,
                ':content' => $messageContent
            ]);
            
            // In a real implementation, call the Gemini API here
            // For now, simulate a response
            $aiResponse = simulateGeminiResponse($messageContent);
            
            // Insert the AI's response
            $stmt = $pdo->prepare("
                INSERT INTO GeminiMessages (
                    ConversationID,
                    IsUserMessage,
                    Content
                ) VALUES (
                    :conversationID,
                    FALSE,
                    :content
                )
            ");
            
            $stmt->execute([
                ':conversationID' => $currentConversationID,
                ':content' => $aiResponse
            ]);
            
            // Update the conversation's last updated timestamp
            $stmt = $pdo->prepare("
                UPDATE GeminiConversations 
                SET LastUpdated = NOW() 
                WHERE ConversationID = :conversationID
            ");
            $stmt->execute([':conversationID' => $currentConversationID]);
            
            $pdo->commit();
            
            // Redirect to refresh and avoid form resubmission
            header("Location: gemini_chat.php?id=" . $currentConversationID);
            exit();
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// If a conversation ID is provided, verify it belongs to this supervisor and load its messages
if ($conversationID > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT * 
            FROM GeminiConversations 
            WHERE ConversationID = :conversationID AND SupervisorID = :supervisorID
        ");
        $stmt->execute([
            ':conversationID' => $conversationID,
            ':supervisorID' => $supervisorID
        ]);
        $currentConversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentConversation) {
            $error = "Invalid conversation or you don't have permission to access it.";
            $conversationID = 0;
        } else {
            // Load messages for this conversation
            $stmt = $pdo->prepare("
                SELECT * 
                FROM GeminiMessages 
                WHERE ConversationID = :conversationID 
                ORDER BY SentAt ASC
            ");
            $stmt->execute([':conversationID' => $conversationID]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch recent conversations for this supervisor
try {
    $stmt = $pdo->prepare("
        SELECT 
            ConversationID,
            Topic,
            LastUpdated,
            CreatedAt
        FROM 
            GeminiConversations
        WHERE 
            SupervisorID = :supervisorID
        ORDER BY 
            LastUpdated DESC
        LIMIT 10
    ");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $recentConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silent error - just show empty list
    $recentConversations = [];
}

// Simulate Gemini API response
function simulateGeminiResponse($userMessage) {
    // In a real implementation, this would call the Gemini API with proper credentials
    
    // Basic responses based on common dissertation-related queries
    $userMessageLower = strtolower($userMessage);
    
    if (strpos($userMessageLower, "literature review") !== false) {
        return "A literature review is a critical component of dissertation research. Here are some tips for conducting an effective literature review:

1. Start with clear research questions to guide your search
2. Use academic databases like Google Scholar, IEEE Xplore, ACM Digital Library, and your university's resources
3. Organize sources by themes or methodological approaches
4. Critically analyze and synthesize the literature, don't just summarize
5. Identify gaps in existing research that your work will address
6. Maintain a bibliography with citation management software like Zotero or Mendeley
7. Update your literature review throughout your research process

Would you like more specific guidance on a particular aspect of literature review?";
    }
    
    if (strpos($userMessageLower, "research methodology") !== false) {
        return "Research methodology is the systematic approach to solving your research problem. Here's guidance on selecting appropriate methodologies:

For computer science and software engineering dissertations, common methodologies include:

**Quantitative Methods:**
- Experiments and controlled studies
- Surveys and questionnaires
- Statistical analysis
- Benchmarking and performance testing

**Qualitative Methods:**
- Case studies
- Interviews and focus groups
- Observational studies
- Content analysis

**Design Science Research:**
- Development of artifacts (software, algorithms, frameworks)
- Evaluation through testing, user studies, or comparative analysis

**Mixed Methods:**
- Combining quantitative and qualitative approaches for comprehensive insights

The methodology should align with your research questions and objectives. What specific type of research are you conducting?";
    }
    
    if (strpos($userMessageLower, "research question") !== false) {
        return "Crafting strong research questions is fundamental to a successful dissertation. Good research questions should be:

1. **Clear and specific** - Avoiding vague or ambiguous terms
2. **Focused** - Narrow enough to be addressed within your scope
3. **Researchable** - Possible to investigate with available resources
4. **Significant** - Contributing meaningful knowledge to the field
5. **Complex** - Not answerable with a simple yes/no
6. **Aligned** - Connected to your research objectives

Examples from computer science:
- \"How does the implementation of X algorithm affect processing time for Y type of data?\"
- \"What factors influence user adoption of security features in mobile applications?\"
- \"How can neural networks be optimized to improve prediction accuracy for [specific domain]?\"

Would you like help refining a specific research question?";
    }
    
    if (strpos($userMessageLower, "writing") !== false || strpos($userMessageLower, "structure") !== false) {
        return "A well-structured dissertation typically includes these components:

1. **Title Page** - Follow your university's format requirements
2. **Abstract** - A concise summary of your research (typically 150-300 words)
3. **Acknowledgments** - Optional but customary
4. **Table of Contents** - Including lists of figures and tables
5. **Introduction** - Background, problem statement, aims, objectives, research questions
6. **Literature Review** - Critical analysis of relevant prior work
7. **Research Methodology** - Approach, methods, data collection and analysis techniques
8. **Results/Findings** - Presentation of data and outcomes
9. **Discussion** - Interpretation of results, relating back to literature
10. **Conclusions** - Summary of contributions, limitations, future work
11. **References** - Comprehensive bibliography of cited works
12. **Appendices** - Supplementary materials

Key writing tips:
- Maintain consistent academic tone
- Use clear, precise language
- Define technical terms
- Structure paragraphs logically
- Support claims with evidence
- Revise thoroughly for clarity

Is there a specific section you're struggling with?";
    }
    
    if (strpos($userMessageLower, "data analysis") !== false || strpos($userMessageLower, "statistics") !== false) {
        return "Data analysis approaches vary depending on your research questions and data types:

**Quantitative Data Analysis:**
- Descriptive statistics (means, medians, standard deviations)
- Inferential statistics (t-tests, ANOVA, regression)
- Statistical significance testing
- Data visualization (charts, graphs)

**Qualitative Data Analysis:**
- Thematic analysis
- Content analysis
- Discourse analysis
- Coding and categorization

**Computational Analysis:**
- Performance benchmarking
- Complexity analysis
- Accuracy/precision metrics
- Feature importance analysis
- Model validation techniques

Popular tools include:
- R and Python for statistical computing
- SPSS or SAS for statistical analysis
- NVivo or ATLAS.ti for qualitative analysis
- Specialized tools for specific domains

What type of data are you working with, and what insights are you trying to extract?";
    }
    
    // Default response if no specific pattern is matched
    return "Thank you for your question about \"" . substr($userMessage, 0, 50) . (strlen($userMessage) > 50 ? "..." : "") . "\". As an AI assistant, I can help with various aspects of dissertation research and supervision, including:

- Research methodology guidance
- Literature review approaches
- Research question formulation
- Data collection and analysis techniques
- Academic writing and structure
- Project planning and milestone setting
- Student supervision strategies
- Ethical considerations in research

Could you provide more details about your specific needs so I can give you more targeted assistance?";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemini AI Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .chat-container {
            height: calc(100vh - 300px);
            min-height: 400px;
        }
        
        .message-user {
            background-color: #e9f5ff;
            border-radius: 18px 18px 0 18px;
        }
        
        .message-ai {
            background-color: #f0f0f0;
            border-radius: 18px 18px 18px 0;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center">Gemini AI Research Assistant</h1>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar: Recent Conversations -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-teal-600 to-teal-800 p-4 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-white">Conversations</h2>
                        <button 
                            onclick="document.getElementById('new-conversation-modal').classList.remove('hidden')"
                            class="bg-white text-teal-700 hover:bg-teal-100 text-sm font-bold py-1 px-3 rounded transition-colors duration-200"
                        >
                            New
                        </button>
                    </div>
                    
                    <div class="p-4 space-y-2">
                        <?php if (empty($recentConversations)): ?>
                            <p class="text-gray-500 italic text-center">No conversations yet</p>
                        <?php else: ?>
                            <?php foreach ($recentConversations as $conversation): ?>
                                <a 
                                    href="gemini_chat.php?id=<?php echo $conversation['ConversationID']; ?>" 
                                    class="block p-3 rounded-lg hover:bg-gray-100 transition-colors duration-200 <?php echo ($conversationID == $conversation['ConversationID']) ? 'bg-teal-50 border border-teal-200' : ''; ?>"
                                >
                                    <h3 class="font-medium truncate">
                                        <?php echo htmlspecialchars($conversation['Topic']); ?>
                                    </h3>
                                    <p class="text-xs text-gray-500">
                                        <?php echo date('M d, Y, h:i A', strtotime($conversation['LastUpdated'])); ?>
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Main Chat Area -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-lg shadow-md overflow-hidden flex flex-col h-full">
                    <?php if (!$currentConversation): ?>
                        <!-- No Active Conversation -->
                        <div class="flex-grow flex items-center justify-center p-8">
                            <div class="text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                                <h2 class="text-xl font-semibold text-gray-700 mb-2">No Conversation Selected</h2>
                                <p class="text-gray-500 mb-4">Start a new conversation or select an existing one.</p>
                                <button 
                                    onclick="document.getElementById('new-conversation-modal').classList.remove('hidden')"
                                    class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors duration-200"
                                >
                                    Start New Conversation
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Active Conversation Header -->
                        <div class="bg-gradient-to-r from-teal-600 to-teal-800 p-4">
                            <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($currentConversation['Topic']); ?></h2>
                            <p class="text-teal-100 text-sm">
                                Started: <?php echo date('M d, Y, h:i A', strtotime($currentConversation['CreatedAt'])); ?>
                            </p>
                        </div>
                        
                        <!-- Chat Messages -->
                        <div class="flex-grow p-4 overflow-y-auto chat-container">
                            <div class="space-y-4">
                                <!-- Initial welcome message -->
                                <?php if (empty($messages)): ?>
                                    <div class="flex">
                                        <div class="max-w-3xl mx-auto p-4 rounded-lg message-ai">
                                            <p class="text-gray-800">
                                                Hello! I'm your research assistant powered by Gemini AI. I'm here to help with your dissertation supervision. You can ask me about research methodologies, literature review strategies, academic writing, project planning, or any other aspect of dissertation research and supervision.
                                            </p>
                                            <p class="text-gray-800 mt-2">
                                                How can I assist you today?
                                            </p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="flex <?php echo $message['IsUserMessage'] ? 'justify-end' : 'justify-start'; ?>">
                                            <div class="max-w-3xl p-4 rounded-lg <?php echo $message['IsUserMessage'] ? 'message-user' : 'message-ai'; ?>">
                                                <p class="text-gray-800 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($message['Content'])); ?></p>
                                                <p class="text-xs text-gray-500 mt-1 text-right">
                                                    <?php echo date('h:i A', strtotime($message['SentAt'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Message Input -->
                        <div class="p-4 border-t">
                            <form method="POST" class="flex">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="conversation_id" value="<?php echo $conversationID; ?>">
                                <input 
                                    type="text" 
                                    name="message" 
                                    placeholder="Type your message here..." 
                                    class="flex-grow px-4 py-2 border border-gray-300 rounded-l-md shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500"
                                    required
                                >
                                <button 
                                    type="submit"
                                    class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-6 rounded-r-md focus:outline-none focus:shadow-outline transition-colors duration-200"
                                >
                                    Send
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Conversation Modal -->
    <div id="new-conversation-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden" style="background-color: rgba(0, 0, 0, 0.5);">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-3">
            <h3 class="text-2xl font-bold mb-4">Start New Conversation</h3>
            <form method="POST">
                <input type="hidden" name="action" value="new_conversation">
                <div class="mb-4">
                    <label for="topic" class="block text-gray-700 font-bold mb-2">Conversation Topic</label>
                    <input 
                        type="text" 
                        id="topic" 
                        name="topic"
                        placeholder="e.g., Research Methodology, Literature Review" 
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        required
                    >
                </div>
                <div class="flex justify-end">
                    <button 
                        type="button" 
                        onclick="document.getElementById('new-conversation-modal').classList.add('hidden')"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded"
                    >
                        Start Conversation
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Auto-scroll to bottom of chat on page load
        window.onload = function() {
            const chatContainer = document.querySelector('.chat-container');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        }
    </script>
</body>
</html>