<?php
// Enable CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key"); 
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight CORS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send response to preflight requests
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key");
    header("Access-Control-Max-Age: 3600");
    http_response_code(200);
    exit();
}

// Define your API key
$valid_api_key = '123'; // Set the API key to '123'

// Function to check if the API key is valid for restricted routes
function is_valid_api_key() {
    global $valid_api_key;

    // Get all headers
    $headers = getallheaders();

    // Normalize header names to lowercase
    $headers = array_change_key_case($headers, CASE_LOWER);

    // Check for the API key in headers
    if (isset($headers['x-api-key'])) {
        return $headers['x-api-key'] === $valid_api_key;
    }
    return false;
}

// Get the request method and URL path
$request_method = $_SERVER['REQUEST_METHOD'];
$path = explode('/', trim($_SERVER['REQUEST_URI'], '/'));

// Remove base path (race_api) if present
if ($path[0] === 'race_api') {
    array_shift($path);
}

// Check API key for restricted methods (only POST, PUT, DELETE require API key)
if (in_array($request_method, ['POST', 'PUT', 'DELETE'])) {
    if (!is_valid_api_key()) {
        // Return 401 Unauthorized if the API key is invalid
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized - Invalid API Key"]);
        exit();
    }
}

// Simple routing based on the URL path
if ($path[0] === 'track') {
    if (isset($path[1]) && $path[1] === 'scrape') {
        require 'scrape.php';  // Handle scraping logic for /track/scrape
    } else {
        require 'tracks.php';  // Handle general track functionality
    }
} elseif ($path[0] === 'race') {
    require 'races.php';  // Handle race functionality
} else {
    // Return 404 Not Found for undefined endpoints
    http_response_code(404);
    echo json_encode(["message" => "Endpoint not found"]);
}
?>
