<?php
// Function to sanitize user input (for text prompts)
function sanitizeInput($input) {
    // Trim the input
    $input = trim($input);
    
    // Escape special characters
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

// Function to extract text from PDF (basic extraction without Composer)
function extractTextFromPdf($filename) {
    $content = file_get_contents($filename);

    if (empty($content)) {
        return '';
    }

    $text = '';
    $pattern = '/BT(.*?)ET/s';  // Regex to extract text from PDF stream
    if (preg_match_all($pattern, $content, $matches)) {
        foreach ($matches[1] as $match) {
            $match = preg_replace('/\((.*?)\)/s', '$1', $match);
            $text .= preg_replace('/[^(\x20-\x7F)]*/','', $match);
        }
    }

    return $text ?: "Could not extract readable text from PDF.";
}

// Function to call Gemini API
function callGeminiAPI($prompt, $apiKey) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No response from Gemini.';
}

// Function to convert markdown to HTML (for displaying response)
function markdownToHtml($text) {
    $text = htmlspecialchars($text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/\#\#\# (.*?)\n/', '<h3>$1</h3>', $text);
    $text = preg_replace('/\#\# (.*?)\n/', '<h2>$1</h2>', $text);
    $text = preg_replace('/\# (.*?)\n/', '<h1>$1</h1>', $text);
    $text = preg_replace('/\n/', '<br>', $text);
    return $text;
}

// If form is submitted (via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey = 'AIzaSyDEp0gdZFyLCmC7ZrRrRjVEYeqv_XfHNJ8';  // Replace with your API key
    $pdfUploaded = isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0;
    $userPrompt = sanitizeInput(trim($_POST['instructions'] ?? ''));  // Sanitize input

    $pdfText = "";

    // If a PDF file is uploaded, extract its content
    if ($pdfUploaded) {
        $pdfPath = $_FILES['pdf_file']['tmp_name'];
        $pdfText = extractTextFromPdf($pdfPath);
    }

    // Generate the final prompt based on whether a PDF or instructions are provided
    $finalPrompt = "";

    if ($pdfUploaded && $userPrompt) {
        $finalPrompt = "Analyze the following PDF content based on these instructions:\n\n" . $userPrompt . "\n\nContent:\n" . $pdfText;
    } elseif ($pdfUploaded) {
        $finalPrompt = "Analyze and summarize the following PDF content:\n\n" . $pdfText;
    } elseif ($userPrompt) {
        $finalPrompt = $userPrompt;
    } else {
        echo json_encode(['error' => 'Please upload a PDF or provide instructions.']);
        exit;
    }

    // Call Gemini API with the generated prompt
    $geminiResponse = callGeminiAPI($finalPrompt, $apiKey);

    // Convert the response from markdown to HTML
    $outputHtml = markdownToHtml($geminiResponse);

    // Return the result as JSON
    echo json_encode(['output' => $outputHtml]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Analyzer & Project Generator (with Loader)</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 30px;
            max-width: 900px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        textarea, input[type="file"], button {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        button {
            background: #28a745;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #218838;
        }
        #loader {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        #output {
            margin-top: 30px;
            background: #eef5f9;
            padding: 20px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>PDF Analyzer & Final Year Project Generator</h1>

    <form id="uploadForm">
        <label><strong>Upload PDF file (optional):</strong></label>
        <input type="file" name="pdf_file" accept="application/pdf">

        <label><strong>Or enter your project idea/details:</strong></label>
        <textarea name="instructions" rows="6" placeholder="Example: Build a mobile app for smart attendance using face recognition..."></textarea>

        <button type="submit">Analyze / Generate</button>
    </form>

    <div id="loader">
        <img src="https://i.gifer.com/ZZ5H.gif" width="50"><br>Processing... Please wait.
    </div>

    <div id="output"></div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    // Show loading spinner
    document.getElementById('loader').style.display = 'block';
    document.getElementById('output').innerHTML = '';

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Hide the loader when response is received
        document.getElementById('loader').style.display = 'none';
        
        if (data.error) {
            document.getElementById('output').innerHTML = '<p style="color:red;">' + data.error + '</p>';
        } else {
            document.getElementById('output').innerHTML = data.output;
        }
    })
    .catch(error => {
        document.getElementById('loader').style.display = 'none';
        document.getElementById('output').innerHTML = '<p style="color:red;">Error processing request.</p>';
    });
});
</script>

</body>
</html>
