<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database connection
require 'db.php';

// Get the request method and path parameter if provided
$race_id = isset($path[1]) ? $path[1] : null;

switch ($request_method) {
    case 'GET':
       if (isset($path[2]) && $path[2] === 'entrant') {
            get_race_entrants($race_id);  // Fetch the entrants for the given race ID
        } elseif ($race_id) {
            get_race($race_id);  // Fetch details of a specific race
        } else {
            get_all_races();  // Fetch all races
        }
        break;
    case 'POST':
        if (isset($path[2]) && $path[2] === 'entrant') {
            add_entrant($race_id); // Add entrant to the race
        } elseif (isset($path[2]) && $path[2] === 'lap') {
            add_lap($race_id); // Add lap to the race
        } elseif (isset($path[2]) && $path[2] === 'qualify') {
            qualify_race($race_id); // Qualify race
        } else {
            http_response_code(405);
            echo json_encode(["result" => "Method not allowed"]);
        }
        break;
    case 'DELETE':
        if (isset($path[2]) && $path[2] === 'entrant') {
            remove_entrant($race_id); // Remove entrant from the race
        } else {
            http_response_code(405);
            echo json_encode(["result" => "Method not allowed"]);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(["result" => "Method not allowed"]);
        break;
}


// Function to get all races with detailed information
function get_all_races() {
    global $conn;

    // Fetch all races and their corresponding track information
    $result = $conn->query("
        SELECT races.id AS race_id, races.track_id, races.created_at, tracks.name AS track_name 
        FROM races 
        JOIN tracks ON races.track_id = tracks.id
    ");

    $races = [];
    while ($race_row = $result->fetch_assoc()) {
        // Fetch entrants for each race
        $entrants_stmt = $conn->prepare("SELECT car_id FROM starting_positions WHERE race_id = ?");
        $entrants_stmt->bind_param("i", $race_row['race_id']);
        $entrants_stmt->execute();
        $entrants_result = $entrants_stmt->get_result();
        $entrants = [];
        while ($entrant_row = $entrants_result->fetch_assoc()) {
            $entrants[] = "https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api/car/" . $entrant_row['car_id'];
        }

        // Fetch laps for each race
        $laps_stmt = $conn->prepare("SELECT lap_number FROM lap_times WHERE race_id = ?");
        $laps_stmt->bind_param("i", $race_row['race_id']);
        $laps_stmt->execute();
        $laps_result = $laps_stmt->get_result();
        $laps = [];
        while ($lap_row = $laps_result->fetch_assoc()) {
            $laps[] = [
                "number" => $lap_row['lap_number'],
                "lapTimes" => []  // This can be populated if lap times per entrant are available
            ];
        }

        // Build the race response
        $race_response = [
            "track" => [
                "name" => $race_row['track_name'],
                "uri" => "https://lab-95a11ac6-8103-422e-af7e-4a8532f40144.australiaeast.cloudapp.azure.com:7124/race_api/track/" . $race_row['track_id']
            ],
            "id" => (int)$race_row['race_id'],
            "entrants" => $entrants,  // Entrants array
            "startingPositions" => [],  // If you want to fetch and include starting positions
            "laps" => $laps  // Laps array
        ];

        $races[] = $race_response;
    }

    echo json_encode(["code" => 200, "result" => $races]);
}

// Function to get a specific race
// Function to get a specific race
function get_race($race_id) {
    global $conn;

    // Fetch race details
    $stmt = $conn->prepare("SELECT * FROM races WHERE id = ?");
    $stmt->bind_param("i", $race_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $race = $result->fetch_assoc();

    if ($race) {
        // Fetch track information for this race
        $track_stmt = $conn->prepare("SELECT * FROM tracks WHERE id = ?");
        $track_stmt->bind_param("i", $race['track_id']);
        $track_stmt->execute();
        $track_result = $track_stmt->get_result();
        $track = $track_result->fetch_assoc();

        // Fetch entrants for this race
        $entrants_stmt = $conn->prepare("SELECT car_id FROM starting_positions WHERE race_id = ?");
        $entrants_stmt->bind_param("i", $race_id);
        $entrants_stmt->execute();
        $entrants_result = $entrants_stmt->get_result();
        $entrants = [];
        while ($row = $entrants_result->fetch_assoc()) {
            $entrants[] = "https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api/car/" . $row['car_id'];
        }

        // Prepare race response without startingPositions and laps
        $race_response = [
            "id" => $race['id'],
            "track" => [
                "name" => $track['name'],
                "uri" => "https://lab-95a11ac6-8103-422e-af7e-4a8532f40144.australiaeast.cloudapp.azure.com:7124/race_api/track/" . $track['id']
            ],
            "entrants" => $entrants
        ];

        echo json_encode(["code" => 200, "result" => $race_response]);
    } else {
        http_response_code(404);
        echo json_encode(["result" => "Race not found"]);
    }
}

// Function to add an entrant to a race
function add_entrant($race_id) {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    // Check if the payload contains the key 'entrant'
    if (isset($data['entrant'])) {
        $car_id = extract_car_id_from_uri($data['entrant']); // extracting car id from 'entrant'

        // Check if the entrant already exists in the race
        $check_stmt = $conn->prepare("SELECT * FROM starting_positions WHERE race_id = ? AND car_id = ?");
        $check_stmt->bind_param("ii", $race_id, $car_id);
        if (!$check_stmt->execute()) {
            http_response_code(500);
            echo json_encode(["result" => "Failed to check entrant: " . $conn->error]);
            return;
        }
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            http_response_code(400);
            echo json_encode(["result" => "Car is already an entrant in this race"]);
            return;
        }

        // Add the entrant with a default position (e.g., -1 indicating unqualified)
        $stmt = $conn->prepare("INSERT INTO starting_positions (race_id, car_id, position) VALUES (?, ?, ?)");
        $default_position = -1;  // Setting a default value for position
        $stmt->bind_param("iii", $race_id, $car_id, $default_position);

        if ($stmt->execute()) {
            echo json_encode(["result" => "Entrant added successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["result" => "Failed to add entrant: " . $conn->error]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["result" => "Invalid input"]);
    }
}

// Function to fetch the entrants for the given race ID
function get_race_entrants($race_id) {
    global $conn;

    // Prepare the query to get entrants for the race
    $stmt = $conn->prepare("SELECT car_id FROM starting_positions WHERE race_id = ?");
    $stmt->bind_param("i", $race_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $entrants = [];
    while ($row = $result->fetch_assoc()) {
        // Append each car URI to the entrants array
        $entrants[] = "https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api/car/" . $row['car_id'];
    }

    // Return the result with "result" as the key instead of "entrants"
    echo json_encode([
        "code" => 200,
        "result" => $entrants  // Changed from "entrants" to "result"
    ]);
}

function remove_entrant($race_id) {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    // Check if the payload contains the key 'entrant'
    if (isset($data['entrant'])) {
        $car_id = extract_car_id_from_uri($data['entrant']); // Extracting car ID from 'entrant'

        // Check if the entrant exists in the race
        $check_stmt = $conn->prepare("SELECT * FROM starting_positions WHERE race_id = ? AND car_id = ?");
        $check_stmt->bind_param("ii", $race_id, $car_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["result" => "Entrant not found in this race"]);
            return;
        }

        // Delete the entrant from the race
        $delete_stmt = $conn->prepare("DELETE FROM starting_positions WHERE race_id = ? AND car_id = ?");
        $delete_stmt->bind_param("ii", $race_id, $car_id);

        if ($delete_stmt->execute()) {
            echo json_encode(["result" => "Entrant removed successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["result" => "Failed to remove entrant: " . $conn->error]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["result" => "Invalid input"]);
    }
}


// Function to qualify a race (assign starting positions based on driver skill)
function qualify_race($race_id) {
    global $conn;

    // Check if the race has entrants
    $entrants_stmt = $conn->prepare("SELECT car_id FROM starting_positions WHERE race_id = ?");
    $entrants_stmt->bind_param("i", $race_id);
    $entrants_stmt->execute();
    $entrants_result = $entrants_stmt->get_result();

    // If no entrants, return 400
    if ($entrants_result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(["result" => "No entrants to qualify"]);
        return;
    }

    // Check if starting positions are already populated
    $check_positions_stmt = $conn->prepare("SELECT * FROM starting_positions WHERE race_id = ? AND position != -1");
    $check_positions_stmt->bind_param("i", $race_id);
    $check_positions_stmt->execute();
    $positions_result = $check_positions_stmt->get_result();

    if ($positions_result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(["result" => "Starting positions already populated"]);
        return;
    }

    // Fetch track type to determine driver skill type
    $track_stmt = $conn->prepare("SELECT type FROM tracks WHERE id = (SELECT track_id FROM races WHERE id = ?)");
    $track_stmt->bind_param("i", $race_id);
    $track_stmt->execute();
    $track_result = $track_stmt->get_result();
    $track = $track_result->fetch_assoc();
    $track_type = $track['type'];

    // Fetch entrants and assign positions based on driver skill
    $entrants = [];
    while ($row = $entrants_result->fetch_assoc()) {
        $car_id = $row['car_id'];

        // Fetch driver skill; default to 0 if the API response is not 200 OK
        $driver_skill = get_driver_skill($car_id, $track_type);

        $entrants[] = ["car_id" => $car_id, "skill" => $driver_skill];
    }

    // Sort entrants by skill in descending order
    usort($entrants, function ($a, $b) {
        return $b['skill'] - $a['skill'];
    });

    // Assign starting positions based on sorted skill
    $position = 1;  // Starting from 1
    foreach ($entrants as $entrant) {
        $car_id = $entrant['car_id'];
        $stmt = $conn->prepare("UPDATE starting_positions SET position = ? WHERE car_id = ? AND race_id = ?");
        $stmt->bind_param("iii", $position, $car_id, $race_id);
        $stmt->execute();
        $position++;
    }

    echo json_encode(["result" => "Race qualified successfully"]);
}

function get_driver_skill($car_id, $track_type) {
    // Step 1: Get car details
    $car_url = "https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api/car/$car_id";

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    // Fetch car data
    $car_response = @file_get_contents($car_url, false, $context);
    if ($car_response === false) {
        // If the API call fails, return skill 0
        return 0;
    }

    $car_data = json_decode($car_response, true);

    // Check if the car has a driver
    if (!isset($car_data['result']['driver']['uri'])) {
        // If no driver is assigned to the car, return skill 0
        return 0;
    }

    // Step 2: Get driver details
    $driver_uri = $car_data['result']['driver']['uri'];

    // Fetch driver data
    $driver_response = @file_get_contents($driver_uri, false, $context);
    if ($driver_response === false) {
        // If the API call fails, return skill 0
        return 0;
    }

    $driver_data = json_decode($driver_response, true);

    // Check if driver skill data is available
    if (!isset($driver_data['result']['skill'])) {
        // If skill data is missing, return skill 0
        return 0;
    }

    $skill_data = $driver_data['result']['skill'];

    // Validate that the sum of skills is exactly 100
    $total_skill = array_sum($skill_data);
    if ($total_skill !== 100) {
        // If the skills do not sum to 100, return skill 0
        return 0;
    }

    // Get the skill value for the given track type
    if ($track_type === 'street') {
        $skill = isset($skill_data['street']) ? $skill_data['street'] : 0;
    } elseif ($track_type === 'race') {
        $skill = isset($skill_data['race']) ? $skill_data['race'] : 0;
    } else {
        // If track type is invalid, return skill 0
        $skill = 0;
    }

    return $skill;
}

function get_car_lap_data($car_id, $track_type, $base_lap_time) {
    $url = "https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api/car/$car_id/lap";

    $track_data = [
        "trackType" => $track_type,
        "baseLapTime" => $base_lap_time
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($track_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    // Disable SSL verification (For Testing Purposes Only)
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);

    // Check for curl errors
    if ($response === false) {
        curl_close($ch);
        return [
            "time" => 0,
            "crashed" => true
        ];
    }

    curl_close($ch);

    $lap_data = json_decode($response, true);

    if (isset($lap_data['time'])) {
        return [
            "time" => (float)$lap_data['time'],
            "crashed" => $lap_data['crashed']
        ];
    } else {
        return [
            "time" => 0,
            "crashed" => true
        ];
    }
}

// Function to add a new lap for a race
function add_lap($race_id) {
    global $conn;

    // Check if race exists
    $race_stmt = $conn->prepare("SELECT * FROM races WHERE id = ?");
    $race_stmt->bind_param("i", $race_id);
    $race_stmt->execute();
    $race = $race_stmt->get_result()->fetch_assoc();

    if (!$race) {
        http_response_code(404);
        echo json_encode(["result" => "Race not found"]);
        return;
    }

    // Check if the race has entrants and starting positions
    $entrants_stmt = $conn->prepare("SELECT car_id FROM starting_positions WHERE race_id = ?");
    $entrants_stmt->bind_param("i", $race_id);
    $entrants_stmt->execute();
    $entrants_result = $entrants_stmt->get_result();

    if ($entrants_result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(["result" => "No entrants found for this race"]);
        return;
    }

    // Check if laps exceed total number of laps for the track
    $track_stmt = $conn->prepare("SELECT laps, base_lap_time, type FROM tracks WHERE id = ?");
    $track_stmt->bind_param("i", $race['track_id']);
    $track_stmt->execute();
    $track = $track_stmt->get_result()->fetch_assoc();

    $current_lap_stmt = $conn->prepare("SELECT COUNT(*) as lap_count FROM lap_times WHERE race_id = ?");
    $current_lap_stmt->bind_param("i", $race_id);
    $current_lap_stmt->execute();
    $current_lap = $current_lap_stmt->get_result()->fetch_assoc();

    if ($current_lap['lap_count'] >= $track['laps']) {
        http_response_code(400);
        echo json_encode(["result" => "Race has already reached the maximum number of laps"]);
        return;
    }

    // Increment lap number
    $lap_number = $current_lap['lap_count'] + 1;

    // Loop through each entrant and get their lap data from the Teams API
    while ($entrant = $entrants_result->fetch_assoc()) {
        $car_id = $entrant['car_id'];
        $lap_data = get_car_lap_data($car_id, $track['type'], $track['base_lap_time']);

        // Add the lap time to the database
        $stmt = $conn->prepare("INSERT INTO lap_times (race_id, car_id, lap_number, lap_time, crashed) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidi", $race_id, $car_id, $lap_number, $lap_data['time'], $lap_data['crashed']);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(["result" => "Failed to add lap time for car $car_id"]);
            return;
        }
    }

    echo json_encode(["result" => "Lap added successfully", "lap_number" => $lap_number]);
}

// Helper function to get starting positions
function get_starting_positions($race_id) {
    global $conn;
    $starting_positions = [];
    $stmt = $conn->prepare("SELECT car_id, position FROM starting_positions WHERE race_id = ?");
    $stmt->bind_param("i", $race_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $starting_positions[] = (int)$row['position'];
    }
    return $starting_positions;
}

// Helper function to extract car ID from a car URI
function extract_car_id_from_uri($uri) {
    return (int)substr($uri, strrpos($uri, '/') + 1);
}


?>
