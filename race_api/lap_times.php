<?php
// Include the database connection
require 'db.php';

// Get the request method and path parameter if provided
$race_id = isset($path[1]) ? $path[1] : null;
$lap_number = isset($path[2]) ? $path[2] : null;

switch ($request_method) {
    case 'GET':
        if ($lap_number) {
            get_leaderboard($race_id, $lap_number);
        } else {
            get_race_laps($race_id);
        }
        break;
    case 'POST':
        add_lap($race_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(["result" => "Method not allowed"]);
        break;
}

// Function to add a lap time
function add_lap($race_id) {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['car_id'], $data['lap_number'], $data['lap_time'], $data['crashed'])) {
        $stmt = $conn->prepare("INSERT INTO lap_times (race_id, car_id, lap_number, lap_time, crashed) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidi", $race_id, $data['car_id'], $data['lap_number'], $data['lap_time'], $data['crashed']);

        if ($stmt->execute()) {
            echo json_encode(["result" => "Lap time added successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["result" => "Failed to add lap time"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["result" => "Invalid input"]);
    }
}

// Function to get all laps for a race
function get_race_laps($race_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM lap_times WHERE race_id = ?");
    $stmt->bind_param("i", $race_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $laps = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($laps);
}

// Function to get the leaderboard for a race at a specific lap number
function get_leaderboard($race_id, $lap_number) {
    global $conn;

    // Collect starting positions
    $starting_positions = [];
    $result = $conn->query("SELECT car_id, position FROM starting_positions WHERE race_id = $race_id ORDER BY position");
    while ($row = $result->fetch_assoc()) {
        $starting_positions[$row['car_id']] = $row['position'];
    }

    // Collect lap times
    $lap_times = [];
    $result = $conn->query("SELECT car_id, lap_time, crashed FROM lap_times WHERE race_id = $race_id AND lap_number <= $lap_number");
    while ($row = $result->fetch_assoc()) {
        if (!isset($lap_times[$row['car_id']])) {
            $lap_times[$row['car_id']] = [
                "total_time" => 0,
                "laps_completed" => 0,
                "crashed" => false
            ];
        }

        if (!$row['crashed']) {
            $lap_times[$row['car_id']]['total_time'] += $row['lap_time'];
            $lap_times[$row['car_id']]['laps_completed'] += 1;
        } else {
            $lap_times[$row['car_id']]['crashed'] = true;
        }
    }

    // Calculate leaderboard
    $leaderboard = [];
    foreach ($lap_times as $car_id => $stats) {
        $leaderboard[] = [
            "car_id" => $car_id,
            "starting_position" => $starting_positions[$car_id],
            "laps_completed" => $stats['laps_completed'],
            "total_time" => $stats['total_time'] + ($starting_positions[$car_id] * 5), // Add starting position penalty
            "crashed" => $stats['crashed']
        ];
    }

    // Sort leaderboard by laps completed and total time
    usort($leaderboard, function ($a, $b) {
        if ($a['laps_completed'] == $b['laps_completed']) {
            return $a['total_time'] - $b['total_time'];
        }
        return $b['laps_completed'] - $a['laps_completed'];
    });

    echo json_encode($leaderboard);
}
?>
