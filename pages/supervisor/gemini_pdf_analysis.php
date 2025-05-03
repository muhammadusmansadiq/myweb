<?php
// pages/supervisor/gemini_pdf_analysis.php
include_once '../../includes/header.php';
include_once '../../config/db.php';
include_once 'gemini_api.php'; // Include the Gemini API helper functions

// Check if the user is logged in and has the role of Supervisor (RoleID = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

$supervisorID = $_SESSION['user_id'];
$error = "";
$success = "";
$pdfText = "";
$summary = "";
$isLoading = false; // Flag to track if we're waiting for the API

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'analyze_pdf') {
    // Check if file was uploaded without errors
    if (isset($_FILES["pdf_file"]) && $_FILES["pdf_file"]["error"] == 0) {
        $allowed = array("pdf" => "application/pdf");
        $filename = $_FILES["pdf_file"]["name"];
        $filetype = $_FILES["pdf_file"]["type"];
        $filesize = $_FILES["pdf_file"]["size"];

        // Verify file extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed)) {
            $error = "Error: Please select a valid PDF file.";
        }

        // Verify file size - 10MB maximum
        $maxsize = 10 * 1024 * 1024;
        if ($filesize > $maxsize) {
            $error = "Error: File size is larger than the allowed limit (10MB).";
        }

        // Verify MIME type of the file
        if (in_array($filetype, $allowed)) {
            // Create upload directory if it doesn't exist
            $uploadDir = "../../uploads/supervisor_temp/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate a unique filename
            $uniqueFilename = uniqid() . "_" . $filename;
            $uploadPath = $uploadDir . $uniqueFilename;

            // Move the file to the upload directory
            if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $uploadPath)) {
                $success = "File uploaded successfully. Processing with Gemini AI...";
                $isLoading = true; // Set the loading flag
                
                try {
                    // Call the Gemini API to get PDF summary
                    $summary = summarizePDFWithGemini($uploadPath, $filename);
                    
                    // Store the summary in the database
                    $stmt = $pdo->prepare("
                        INSERT INTO PDFAnalysis (
                            SupervisorID, 
                            FileName, 
                            FilePath, 
                            Summary, 
                            AnalyzedAt
                        ) VALUES (
                            :supervisorID, 
                            :fileName, 
                            :filePath, 
                            :summary, 
                            NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        ':supervisorID' => $supervisorID,
                        ':fileName' => $filename,
                        ':filePath' => $uploadPath,
                        ':summary' => $summary
                    ]);
                    
                    $isLoading = false; // Clear the loading flag
                    $success = "Analysis completed successfully!";
                    
                } catch (Exception $e) {
                    $isLoading = false; // Clear the loading flag
                    $error = "API Error: " . $e->getMessage();
                }
                
            } else {
                $error = "Error: There was a problem uploading your file. Please try again.";
            }
        } else {
            $error = "Error: There was a problem with your file type. Please upload a PDF.";
        }
    } else {
        $error = "Error: " . $_FILES["pdf_file"]["error"];
    }
}

// Fetch recent PDF analyses
try {
    $stmt = $pdo->prepare("
        SELECT 
            AnalysisID,
            FileName,
            Summary,
            AnalyzedAt
        FROM 
            PDFAnalysis
        WHERE 
            SupervisorID = :supervisorID
        ORDER BY 
            AnalyzedAt DESC
        LIMIT 10
    ");
    
    $stmt->execute([':supervisorID' => $supervisorID]);
    $recentAnalyses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently handle this error - just show empty list
    $recentAnalyses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Analysis with Gemini</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Loading spinner CSS */
        .loader {
            border: 5px solid #f3f3f3;
            border-radius: 50%;
            border-top: 5px solid #3498db;
            width: 50px;
            height: 50px;
            -webkit-animation: spin 1.5s linear infinite;
            animation: spin 1.5s linear infinite;
            margin: 20px auto;
        }
        
        @-webkit-keyframes spin {
            0% { -webkit-transform: rotate(0deg); }
            100% { -webkit-transform: rotate(360deg); }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center">PDF Analysis with Gemini AI</h1>
        
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
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- PDF Upload Section -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                    <h2 class="text-xl font-bold text-white">Upload PDF for Analysis</h2>
                </div>
                
                <div class="p-6">
                    <form method="POST" enctype="multipart/form-data" class="space-y-4" id="pdfUploadForm">
                        <input type="hidden" name="action" value="analyze_pdf">
                        
                        <div class="mb-4">
                            <label for="pdf_file" class="block text-sm font-medium text-gray-700 mb-1">Select PDF File</label>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col w-full h-32 border-4 border-dashed hover:bg-gray-100 hover:border-purple-300 group">
                                    <div class="flex flex-col items-center justify-center pt-7">
                                        <svg class="w-10 h-10 text-purple-400 group-hover:text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <p class="pt-1 text-sm tracking-wider text-gray-400 group-hover:text-purple-600">
                                            Select a file or drag and drop
                                        </p>
                                        <p class="text-xs text-gray-500">PDF files only (max 10MB)</p>
                                    </div>
                                    <input 
                                        type="file" 
                                        id="pdf_file" 
                                        name="pdf_file" 
                                        accept="application/pdf"
                                        class="opacity-0"
                                        required
                                    />
                                </label>
                            </div>
                        </div>
                        
                        <div id="selectedFile" class="hidden">
                            <p class="text-sm text-gray-700">Selected file: <span id="fileName" class="font-medium"></span></p>
                        </div>
                        
                        <div>
                            <button 
                                type="submit"
                                id="submitButton"
                                class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors duration-200"
                            >
                                Analyze with Gemini AI
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Results Section -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                    <h2 class="text-xl font-bold text-white">Analysis Results</h2>
                </div>
                
                <div class="p-6">
                    <?php if ($isLoading): ?>
                        <!-- Loading Indicator -->
                        <div class="text-center py-8">
                            <div class="loader mb-4"></div>
                            <div class="text-purple-600 pulse font-medium">
                                Processing with Gemini AI...
                            </div>
                            <p class="text-gray-500 text-sm mt-2">
                                This may take a few moments depending on the size and complexity of your document.
                            </p>
                        </div>
                    <?php elseif (empty($summary)): ?>
                        <p class="text-gray-500 italic text-center">Upload a PDF to see its analysis</p>
                    <?php else: ?>
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold mb-2">Summary</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="whitespace-pre-line"><?php echo nl2br(htmlspecialchars($summary)); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Analyses -->
        <?php if (!empty($recentAnalyses)): ?>
            <div class="mt-8 bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                    <h2 class="text-xl font-bold text-white">Recent Analyses</h2>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($recentAnalyses as $analysis): ?>
                            <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors duration-200">
                                <h3 class="font-semibold"><?php echo htmlspecialchars($analysis['FileName']); ?></h3>
                                <p class="text-sm text-gray-500 mb-2">Analyzed on: <?php echo date('M d, Y, h:i A', strtotime($analysis['AnalyzedAt'])); ?></p>
                                <p class="text-gray-700 text-sm mb-2"><?php echo mb_substr(htmlspecialchars($analysis['Summary']), 0, 150) . '...'; ?></p>
                                <a href="view_analysis.php?id=<?php echo $analysis['AnalysisID']; ?>" class="text-blue-500 hover:text-blue-700 text-sm">
                                    View Full Analysis
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Display selected file name
        document.getElementById('pdf_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                document.getElementById('fileName').textContent = fileName;
                document.getElementById('selectedFile').classList.remove('hidden');
            } else {
                document.getElementById('selectedFile').classList.add('hidden');
            }
        });
        
        // Handle form submission and show loading state
        document.getElementById('pdfUploadForm').addEventListener('submit', function() {
            // Disable submit button
            document.getElementById('submitButton').disabled = true;
            document.getElementById('submitButton').classList.add('bg-purple-400');
            document.getElementById('submitButton').classList.remove('bg-purple-600', 'hover:bg-purple-700');
            document.getElementById('submitButton').textContent = 'Processing...';
            
            // The form will submit normally, and the page will refresh with the loading state
        });
        
        // Enable drag and drop for the file input
        const dropArea = document.querySelector('label.flex.flex-col');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('border-purple-300', 'bg-gray-100');
        }
        
        function unhighlight() {
            dropArea.classList.remove('border-purple-300', 'bg-gray-100');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('pdf_file').files = files;
            
            // Update the file name display
            if (files.length > 0) {
                document.getElementById('fileName').textContent = files[0].name;
                document.getElementById('selectedFile').classList.remove('hidden');
            }
        }
        
        <?php if ($isLoading): ?>
        // If we're in a loading state, simulate progress with AJAX
        document.addEventListener('DOMContentLoaded', function() {
            // Create an animated countdown
            let timeLeft = 45; // seconds
            const countdownElement = document.createElement('p');
            countdownElement.className = 'text-gray-500 text-sm mt-4';
            countdownElement.textContent = 'Estimated time remaining: ' + timeLeft + ' seconds';
            document.querySelector('.loader').after(countdownElement);
            
            const countdown = setInterval(function() {
                timeLeft--;
                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    countdownElement.textContent = 'Analysis is taking longer than expected. Please wait...';
                } else {
                    countdownElement.textContent = 'Estimated time remaining: ' + timeLeft + ' seconds';
                }
            }, 1000);
            
            // Auto-refresh the page after 1 minute to check for results
            setTimeout(function() {
                window.location.reload();
            }, 60000);
        });
        <?php endif; ?>
    </script>
</body>
</html>