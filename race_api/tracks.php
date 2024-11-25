<?php
// Include the database connection
require 'db.php';

// Get the request method and path parameter if provided
$track_id = isset($path[1]) ? $path[1] : null;

switch ($request_method) {
    case 'GET':
        if ($track_id && isset($path[2]) && $path[2] === 'races') {
            get_races_for_track($track_id);
        } elseif ($track_id) {
            get_track($track_id);
        } else {
            get_all_tracks();
        }
        break;
    case 'POST':
        if ($track_id && isset($path[2]) && $path[2] === 'races') {
            add_race_for_track($track_id);  // Add race for a specific track
        } else {
            add_track();
        }
        break;
    case 'DELETE':
        if ($track_id) {
            delete_track($track_id);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(["result" => "Method not allowed"]);
        break;
}

// Function to get all tracks
function get_all_tracks() {
    global $conn;
    $result = $conn->query("SELECT * FROM tracks");
    $tracks = [];

    while ($row = $result->fetch_assoc()) {
        $tracks[] = [
            "id" => (int)$row['id'],
            "name" => $row['name'],
            "type" => $row['type'],
            "laps" => (int)$row['laps'],
            "baseLapTime" => (float)$row['base_lap_time']
        ];
    }

    echo json_encode(["code" => 200, "result" => $tracks]);
}

// Function to get a specific track
function get_track($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM tracks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $track = $result->fetch_assoc();

    if ($track) {
        $track_response = [
            "id" => (int)$track['id'],
            "name" => $track['name'],
            "type" => $track['type'],
            "laps" => (int)$track['laps'],
            "baseLapTime" => (float)$track['base_lap_time']
        ];

        // Return the track as a single object, not wrapped in an array
        echo json_encode(["code" => 200, "result" => $track_response]);
    } else {
        http_response_code(404);
        echo json_encode(["result" => "Track not found", "debug_id" => $id]);
    }
}

// Function to add a new track
function add_track() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);
    if (isset($data['name'], $data['type'], $data['laps'], $data['baseLapTime'])) {
        $stmt = $conn->prepare("INSERT INTO tracks (name, type, laps, base_lap_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $data['name'], $data['type'], $data['laps'], $data['baseLapTime']);
        if ($stmt->execute()) {
            echo json_encode(["result" => "Track added"]);
        } else {
            http_response_code(500);
            echo json_encode(["result" => "Failed to add track"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["result" => "Invalid input"]);
    }
}

// Function to delete a track
function delete_track($id) {
    global $conn;

    // Check if the track is associated with any races
    $stmt_check = $conn->prepare("SELECT * FROM races WHERE track_id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        http_response_code(400);
        echo json_encode(["result" => "Track cannot be deleted, associated with races"]);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM tracks WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["result" => "Track deleted"]);
    } else {
        http_response_code(500);
        echo json_encode(["result" => "Failed to delete track"]);
    }
}

// Function to get races for a specific track
function get_races_for_track($track_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM races WHERE track_id = ?");
    $stmt->bind_param("i", $track_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $races = [];
    while ($row = $result->fetch_assoc()) {
        $races[] = [
            "id" => $row['id'],
            "track_id" => $row['track_id'],
            "created_at" => $row['created_at']
        ];
    }
    echo json_encode(["code" => 200, "result" => $races]);
}

// Function to add a new race for a track
function add_race_for_track($track_id) {
    global $conn;

    // Check if the track exists
    $stmt_check = $conn->prepare("SELECT id FROM tracks WHERE id = ?");
    $stmt_check->bind_param("i", $track_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["result" => "Track not found"]);
        return;
    }

    // Insert the race linked to the track
    $stmt = $conn->prepare("INSERT INTO races (track_id) VALUES (?)");
    $stmt->bind_param("i", $track_id);

    if ($stmt->execute()) {
        echo json_encode(["result" => "Race added successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["result" => "Failed to add race"]);
    }
}
?>
