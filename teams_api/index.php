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
    exit(); // Stop further execution for OPTIONS requests
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

    // Log headers for debugging (remove or comment out in production)
    // error_log(print_r($headers, true));

    // Check for the API key in headers
    if (isset($headers['x-api-key'])) {
        return $headers['x-api-key'] === $valid_api_key;
    }
    return false;
}

// Get the request method
$request_method = $_SERVER['REQUEST_METHOD'];

// Parse the URL to get the path without query parameters
$request_uri = $_SERVER['REQUEST_URI'];
$parsed_url = parse_url($request_uri);
$path = explode('/', trim($parsed_url['path'], '/'));
// Remove base path (teams_api) if present
if ($path[0] === 'teams_api') {
    array_shift($path);
}

// Check API key for restricted methods
if (in_array($request_method, ['POST', 'PUT', 'DELETE'])) {
    if (!is_valid_api_key()) {
        // Return 401 Unauthorized if the API key is invalid
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized - Invalid API Key"]);
        exit();
    }
}

// Simple routing based on the URL path
if ($path[0] === 'driver') {
    require 'drivers.php';  // Handle drivers functionality
} elseif ($path[0] === 'car') {
    require 'cars.php';  // Handle cars functionality
} else {
    // Return 404 Not Found for undefined endpoints
    http_response_code(404);
    echo json_encode(["message" => "Endpoint not found"]);
}
?>
