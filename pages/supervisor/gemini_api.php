<?php

/**
 * Uploads a file to the Gemini API file service.
 *
 * @param string $filePath The path to the local file to upload.
 * @param string $apiKey Your Google AI API Key.
 * @param string $mimeType Optional: The MIME type of the file (e.g., 'application/pdf'). If empty, cURL might try to guess.
 * @return array|false An array containing 'uri' and 'mimeType' on success, false on failure.
 */
function uploadFileToGemini(string $filePath, string $apiKey, string $mimeType = ''): array|false
{
    // --- Basic File Validation ---
    if (!file_exists($filePath)) {
        echo "Error: File not found at path: {$filePath}\n";
        return false;
    }
    if (!is_readable($filePath)) {
        echo "Error: File is not readable: {$filePath}\n";
        return false;
    }

    // --- Determine MIME Type if not provided ---
    if (empty($mimeType) && function_exists('mime_content_type')) {
        $mimeType = mime_content_type($filePath);
        if ($mimeType === false) {
            echo "Warning: Could not automatically determine MIME type for {$filePath}. Consider providing it manually.\n";
            // Provide a generic default
            $mimeType = 'application/octet-stream'; // Generic binary type
        }
    } elseif (empty($mimeType)) {
         // Fallback if mime_content_type is not available and no type provided
         $mimeType = 'application/octet-stream';
         echo "Warning: MIME type not provided and could not be determined automatically. Using {$mimeType}.\n";
    }

    // --- Prepare cURL Request for File Upload ---
    $url = "https://generativelanguage.googleapis.com/v1beta/files?key={$apiKey}";

    // Use CURLFile for safe file uploads
    $cfile = new CURLFile($filePath, $mimeType, basename($filePath));

    // Data payload for multipart/form-data
    $postData = ['file' => $cfile];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); // Send as multipart/form-data
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increase timeout for potentially large uploads
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

    // --- Execute and Process Response ---
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        echo 'cURL Error during file upload: ' . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    if ($httpCode !== 200) {
        echo "HTTP Error during file upload: {$httpCode}\n";
        echo "Response Body: {$response}\n";
        return false;
    }

    $responseData = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'JSON Decode Error during file upload: ' . json_last_error_msg() . "\n";
        return false;
    }

    // --- Extract File URI ---
    if (isset($responseData['file']['uri']) && isset($responseData['file']['mimeType'])) {
        echo "File uploaded successfully. URI: " . $responseData['file']['uri'] . "\n";
        return [
            'uri' => $responseData['file']['uri'],
            'mimeType' => $responseData['file']['mimeType'] // Return the confirmed MIME type
        ];
    } else {
        echo "Unexpected API response structure during file upload.\n";
        echo "Full Response: " . print_r($responseData, true) . "\n";
        return false;
    }
}

/**
 * Sends a multimodal prompt (text + file) to the Gemini API.
 *
 * @param string $prompt The text part of the prompt.
 * @param string $fileUri The URI of the file previously uploaded via uploadFileToGemini.
 * @param string $fileMimeType The MIME type of the uploaded file.
 * @param string $apiKey Your Google AI API Key.
 * @param string $model The multimodal model to use (e.g., 'gemini-1.5-flash-latest', 'gemini-1.5-pro-latest').
 * @return string|false The generated text content or false on failure.
 */
function callGeminiMultimodalAPI(string $prompt, string $fileUri, string $fileMimeType, string $apiKey, string $model = 'gemini-1.5-flash-latest'): string|false
{
    // API Endpoint URL for content generation
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    // Request payload structure for multimodal input
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt], // Text part
                    [ // File part
                        'fileData' => [
                            'mimeType' => $fileMimeType,
                            'fileUri' => $fileUri
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 2048,
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
        ]
    ];

    $jsonData = json_encode($data);

    // Initialize cURL session for content generation
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Allow more time for processing potentially large inputs
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

    // Execute the cURL request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'cURL Error during content generation: ' . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check HTTP status code
    if ($httpCode !== 200) {
        echo "HTTP Error during content generation: {$httpCode}\n";
        echo "Response Body: {$response}\n";
        return false;
    }

    // Decode JSON response
    $responseData = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'JSON Decode Error during content generation: ' . json_last_error_msg() . "\n";
        return false;
    }

    // --- Extract the generated text ---
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($responseData['promptFeedback']['blockReason'])) {
        echo "Prompt Blocked: " . $responseData['promptFeedback']['blockReason'] . "\n";
        // Optional: Log safety ratings
        if (isset($responseData['promptFeedback']['safetyRatings'])) {
            echo "Safety Ratings: " . print_r($responseData['promptFeedback']['safetyRatings'], true) . "\n";
        }
        return false;
    } elseif (isset($responseData['error'])) {
        echo "API Error: " . $responseData['error']['message'] . " (Code: " . $responseData['error']['code'] . ")\n";
        return false;
    } else {
        echo "Unexpected API response structure during content generation.\n";
        echo "Full Response: " . print_r($responseData, true) . "\n";
        return false;
    }
}

/**
 * Simple function to analyze a document using Gemini
 * 
 * @param string $filePath Path to the document file
 * @param string $prompt The prompt to ask about the document
 * @param string $apiKey Your Google AI API Key
 * @return string|false The generated analysis or false on failure
 */
function analyzeDocumentWithGemini(string $filePath, string $prompt, string $apiKey): string|false {
    // Auto-detect mime type
    $mimeType = mime_content_type($filePath);
    
    // Upload file to Gemini
    $fileData = uploadFileToGemini($filePath, $apiKey, $mimeType);
    
    if ($fileData === false) {
        return false;
    }
    
    // Process the file with Gemini
    return callGeminiMultimodalAPI($prompt, $fileData['uri'], $fileData['mimeType'], $apiKey);
}

/**
 * Wrapper function to analyze a dissertation or thesis document
 * 
 * @param string $filePath Path to the document file
 * @param string $apiKey Your Google AI API Key
 * @return array|false Analysis results including feedback, recommendations, and score
 */
function analyzeDissertation(string $filePath, string $apiKey): array|false {
    $prompt = "Please analyze this dissertation document and provide the following:
    1. Overall quality assessment (score out of 10)
    2. Strengths of the work
    3. Areas for improvement
    4. Quality of research methodology
    5. Clarity of arguments and conclusions
    6. Recommendations for the student
    
    Format your response as JSON with the following structure:
    {
        \"score\": 7.5,
        \"strengths\": [\"point 1\", \"point 2\"],
        \"improvements\": [\"point 1\", \"point 2\"],
        \"methodology\": \"assessment text\",
        \"clarity\": \"assessment text\",
        \"recommendations\": [\"recommendation 1\", \"recommendation 2\"]
    }";
    
    $result = analyzeDocumentWithGemini($filePath, $prompt, $apiKey);
    
    if ($result === false) {
        return false;
    }
    
    // Try to parse the JSON response
    $jsonResult = json_decode($result, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If not valid JSON, return the raw text
        return [
            'raw_response' => $result,
            'error' => 'Response was not in valid JSON format'
        ];
    }
    
    return $jsonResult;
}

// Example usage:
if (isset($_GET['test']) && $_GET['test'] == 1) {
    // **IMPORTANT:** Replace with your actual API key
    $apiKey = 'AIzaSyDEp0gdZFyLCmC7ZrRrRjVEYeqv_XfHNJ8';
    
    // **IMPORTANT:** Replace with the correct path to your PDF file
    $pdfFilePath = '/path/to/your/document.pdf';
    
    // Example 1: Simple file upload and analysis
    echo "Analyzing document: {$pdfFilePath}\n";
    $analysis = analyzeDissertation($pdfFilePath, $apiKey);
    
    if ($analysis !== false) {
        echo "\n--- Gemini Analysis --- \n";
        print_r($analysis);
        echo "\n-----------------------\n";
    } else {
        echo "Failed to get response from Gemini API.\n";
    }
}
?>