<?php

/**
 * Sends a prompt to the Gemini API and returns the response.
 *
 * @param string $prompt The text prompt to send to the model.
 * @param string $apiKey Your Google AI API Key.
 * @param string $model The model to use (e.g., 'gemini-1.5-flash-latest').
 * @return string|false The generated text content or false on failure.
 */
function callGeminiAPI(string $prompt, string $apiKey, string $model = 'gemini-1.5-flash-latest'): string|false
{
    // API Endpoint URL - includes the model name and API key
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    // Request payload structure for the Gemini API
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        // Optional: Add generationConfig if needed
        // 'generationConfig' => [
        //     'temperature' => 0.9,
        //     'topK' => 1,
        //     'topP' => 1,
        //     'maxOutputTokens' => 2048,
        //     'stopSequences' => []
        // ],
        // Optional: Add safetySettings if needed
        // 'safetySettings' => [
        //     ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        //     // ... other categories
        // ]
    ];

    // Encode the data as JSON
    $jsonData = json_encode($data);

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url); // Set the URL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
    curl_setopt($ch, CURLOPT_POST, true); // Set the request method to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // Set the POST data (JSON payload)
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ // Set headers
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verify SSL certificate (recommended)
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set timeout in seconds
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Set connection timeout

    // Execute the cURL request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'cURL Error: ' . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }

    // Get the HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close the cURL session
    curl_close($ch);

    // Check if the HTTP request was successful (e.g., 200 OK)
    if ($httpCode !== 200) {
        echo "HTTP Error: {$httpCode}\n";
        echo "Response Body: {$response}\n"; // Show error response from API
        return false;
    }

    // Decode the JSON response
    $responseData = json_decode($response, true); // Use true for associative array

    // Check for JSON decoding errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'JSON Decode Error: ' . json_last_error_msg() . "\n";
        return false;
    }

    // --- Extract the generated text ---
    // Handle potential errors or variations in the response structure
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($responseData['promptFeedback']['blockReason'])) {
        // Handle cases where the prompt was blocked
        echo "Prompt Blocked: " . $responseData['promptFeedback']['blockReason'] . "\n";
        if (isset($responseData['promptFeedback']['safetyRatings'])) {
            echo "Safety Ratings: " . print_r($responseData['promptFeedback']['safetyRatings'], true) . "\n";
        }
        return false;
    } elseif (isset($responseData['error'])) {
        // Handle API errors returned in the JSON
        echo "API Error: " . $responseData['error']['message'] . " (Code: " . $responseData['error']['code'] . ")\n";
        return false;
    } else {
        // Handle unexpected response structure
        echo "Unexpected API response structure.\n";
        echo "Full Response: " . print_r($responseData, true) . "\n";
        return false;
    }
}

// --- Example Usage ---

// **IMPORTANT:** Replace 'YOUR_API_KEY' with your actual Google AI API key.
// You can get one from Google AI Studio: https://aistudio.google.com/app/apikey
$apiKey = 'AIzaSyDEp0gdZFyLCmC7ZrRrRjVEYeqv_XfHNJ8';

// The prompt you want to send to Gemini
$prompt = "Write a short poem about a rainy day in Sadiqabad.";

// Call the function
$generatedText = callGeminiAPI($prompt, $apiKey);

// Display the result
if ($generatedText !== false) {
    echo "--- Gemini Response --- \n";
    echo $generatedText;
    echo "\n----------------------\n";
} else {
    echo "Failed to get response from Gemini API.\n";
}

?>
