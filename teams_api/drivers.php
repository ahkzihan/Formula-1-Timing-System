<?php
// Include the database connection
require 'db.php';  // You'll create this file for database connection

// Get the request method and path parameter if provided
$driver_number = isset($path[1]) ? $path[1] : null;

switch ($request_method) {
    case 'GET':
        if ($driver_number) {
            get_driver($driver_number);
        } else {
            get_all_drivers();
        }
        break;
    case 'POST':
        add_driver();
        break;
    case 'PUT':
        if ($driver_number) {
            update_driver($driver_number);
        }
        break;
    case 'DELETE':
        if ($driver_number) {
            delete_driver($driver_number);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(["result" => "Method not allowed"]);
        break;
}

// Function to get all drivers
function get_all_drivers() {
    global $conn;
    $result = $conn->query("SELECT * FROM drivers");
    $drivers = [];
    
    while ($row = $result->fetch_assoc()) {
        // Cast numeric values to integers to avoid issues with type mismatch
        $row['id'] = (int)$row['id'];
        $row['number'] = (int)$row['number'];
        $row['skill_race'] = (int)$row['skill_race'];
        $row['skill_street'] = (int)$row['skill_street'];
        $drivers[] = [
            "number" => $row['number'],
            "shortName" => $row['short_name'],  // Ensure this matches your JSON output
            "name" => $row['name'],
            "skill" => [
                "race" => $row['skill_race'],
                "street" => $row['skill_street']
            ]
        ];
    }

    echo json_encode(["code" => 200, "result" => $drivers]);
}

// Function to get a specific driver
function get_driver($number) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM drivers WHERE number = ?");
    $stmt->bind_param("i", $number);  // Ensure the number is passed as an integer
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();
    
    if ($driver) {
        // Cast numeric values to integers
        $driver['id'] = (int)$driver['id'];
        $driver['number'] = (int)$driver['number'];
        $driver['skill_race'] = (int)$driver['skill_race'];
        $driver['skill_street'] = (int)$driver['skill_street'];

        $driver_response = [
            "number" => $driver['number'],
            "shortName" => $driver['short_name'],  // Ensure this matches your JSON output
            "name" => $driver['name'],
            "skill" => [
                "race" => $driver['skill_race'],
                "street" => $driver['skill_street']
            ]
        ];

        // Return the result as an object instead of an array
        echo json_encode(["code" => 200, "result" => $driver_response]);
    } else {
        http_response_code(404);
        echo json_encode(["result" => "Driver not found", "debug_number" => $number]);
    }
}


// Function to add a new driver with JSON input
// Function to add a new driver with JSON input
function add_driver() {
    global $conn;

    // Read JSON data from POST
    $data = json_decode(file_get_contents("php://input"), true);

    // Ensure all fields are provided
    if (isset($data['number'], $data['shortName'], $data['name'], $data['skill']['race'], $data['skill']['street'])) {
        // Extract and sanitize input values
        $number = (int)$data['number'];
        $shortName = $data['shortName'];
        $name = $data['name'];
        $race_skill = (int)$data['skill']['race'];
        $street_skill = (int)$data['skill']['street'];

        // Validate skill values are integers between 0 and 100
        if ($race_skill >= 0 && $race_skill <= 100 && $street_skill >= 0 && $street_skill <= 100) {
            // Validate that skill values sum to 100
            if ($race_skill + $street_skill === 100) {
                // Check if driver number already exists
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM drivers WHERE number = ?");
                $check_stmt->bind_param("i", $number);
                $check_stmt->execute();
                $check_stmt->bind_result($count);
                $check_stmt->fetch();
                $check_stmt->close();

                if ($count > 0) {
                    // Driver number already exists
                    http_response_code(400); // Bad Request
                    echo json_encode(["result" => "Driver number already exists"]);
                } else {
                    // Proceed with inserting the new driver
                    $stmt = $conn->prepare("INSERT INTO drivers (number, short_name, name, skill_race, skill_street) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issii", $number, $shortName, $name, $race_skill, $street_skill);

                    // Execute and check for success
                    if ($stmt->execute()) {
                        http_response_code(200); 
                        echo json_encode(["code" => 200, "result" => "Driver added"]);
                    } else {
                        http_response_code(500); // Internal server error
                        echo json_encode(["result" => "Failed to add driver"]);
                    }
                    $stmt->close();
                }
            } else {
                http_response_code(400);
                echo json_encode(["result" => "Skill values must sum to 100"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["result" => "Skill values must be integers between 0 and 100"]);
        }
    } else {
        http_response_code(400); // Bad request for invalid input
        echo json_encode(["result" => "Invalid input, all fields are required"]);
    }
}


// Function to update a driver
function update_driver($number) {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    // Ensure all fields are provided
    if (isset($data['shortName'], $data['name'], $data['skill']['race'], $data['skill']['street'])) {
        // Extract and sanitize input values
        $shortName = $data['shortName'];
        $name = $data['name'];
        $race_skill = (int)$data['skill']['race'];
        $street_skill = (int)$data['skill']['street'];

        // Validate skill values are integers between 0 and 100
        if ($race_skill >= 0 && $race_skill <= 100 && $street_skill >= 0 && $street_skill <= 100) {
            // Validate that skill values sum to 100
            if ($race_skill + $street_skill === 100) {
                // Prepare the SQL statement
                $stmt = $conn->prepare("UPDATE drivers SET short_name=?, name=?, skill_race=?, skill_street=? WHERE number=?");
                $stmt->bind_param("ssiii", $shortName, $name, $race_skill, $street_skill, $number);

                // Execute and check for success
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(["result" => "Driver updated"]);
                    } else {
                        http_response_code(404);
                        echo json_encode(["result" => "Driver not found"]);
                    }
                } else {
                    http_response_code(500);
                    echo json_encode(["result" => "Failed to update driver"]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["result" => "Skill values must sum to 100"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["result" => "Skill values must be integers between 0 and 100"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["result" => "Invalid input, all fields are required"]);
    }
}

// Function to delete a driver
function delete_driver($number) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM drivers WHERE number=?");
    $stmt->bind_param("i", $number);
    if ($stmt->execute()) {
        echo json_encode(["result" => "Driver deleted"]);
    } else {
        http_response_code(500);
        echo json_encode(["result" => "Failed to delete driver"]);
    }
}
?>
