<?php
// Include the database connection
require 'db.php';

// Get the request method and path parameter if provided
$car_id = isset($path[1]) ? $path[1] : null;

switch ($request_method) {
    case 'GET':
        if ($car_id && isset($path[2]) && $path[2] === 'driver') {
            get_car_driver($car_id);
        } elseif ($car_id && isset($path[2]) && $path[2] === 'lap') {
            calculate_lap_result($car_id);
        } elseif ($car_id) {
            get_car($car_id);
        } else {
            get_all_cars();
        }
        break;
    case 'POST':
        add_car();
        break;
    case 'PUT':
        if ($car_id && isset($path[2]) && $path[2] === 'driver') {
            set_driver_for_car($car_id);
        } elseif ($car_id) {
            update_car($car_id);
        }
        break;
    case 'DELETE':
        if ($car_id && isset($path[2]) && $path[2] === 'driver') {
            remove_driver_from_car($car_id);
        } elseif ($car_id) {
            delete_car($car_id);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(["result" => "Method not allowed"]);
        break;
}

// Function to get all cars
function get_all_cars() {
    global $conn;
    $result = $conn->query("
        SELECT cars.id, cars.suitability_race, cars.suitability_street, cars.reliability, drivers.name, drivers.number 
        FROM cars
        LEFT JOIN drivers ON cars.driver_id = drivers.id
    ");
    
    $cars = [];
    while ($row = $result->fetch_assoc()) {
        $car = [
            "id" => (int)$row['id'],
            "driver" => $row['name'] ? [
                "name" => $row['name'],
                "uri" => "https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api/driver/" . $row['number']
            ] : null,
            "suitability" => [
                "race" => (int)$row['suitability_race'],
                "street" => (int)$row['suitability_street']
            ],
            "reliability" => (int)$row['reliability']
        ];
        $cars[] = $car;
    }

    echo json_encode(["code" => 200, "result" => $cars]);
}

// Function to get a specific car
function get_car($id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT cars.id, cars.suitability_race, cars.suitability_street, cars.reliability, drivers.name, drivers.number 
        FROM cars
        LEFT JOIN drivers ON cars.driver_id = drivers.id
        WHERE cars.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $car = $result->fetch_assoc();

    if ($car) {
        $car_response = [
            "id" => (string)$car['id'],  // Output id as a string to match the expected format
            "driver" => $car['name'] ? [
                "name" => $car['name'],
                "uri" => "https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api/driver/" . $car['number']
            ] : null,
            "suitability" => [
                "race" => (int)$car['suitability_race'],
                "street" => (int)$car['suitability_street']
            ],
            "reliability" => (int)$car['reliability']
        ];

        echo json_encode(["code" => 200, "result" => $car_response]);  // Notice the single result object
    } else {
        http_response_code(404);
        echo json_encode(["result" => "Car not found", "debug_id" => $id]);
    }
}

// Function to get the driver of a specific car
function get_car_driver($id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT drivers.name, drivers.number, drivers.short_name, drivers.skill_street, drivers.skill_race 
        FROM cars
        JOIN drivers ON cars.driver_id = drivers.id
        WHERE cars.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();

    if ($driver) {
        $driver_response = [
            "name" => $driver['name'],
            "number" => (int)$driver['number'],
            "shortName" => $driver['short_name'],  // Assuming 'short_name' is the column
            "skill" => [
                "street" => (int)$driver['skill_street'],
                "race" => (int)$driver['skill_race']
            ]
        ];
        echo json_encode(["code" => 200, "result" => $driver_response]);
    } else {
        http_response_code(404);
        echo json_encode(["result" => "Driver not found for this car", "debug_id" => $id]);
    }
}

// Function to add a new car 
function add_car() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['suitability'], $data['reliability'])) {

        // Validate that suitability values sum to 100
        $suitability_race = (int)$data['suitability']['race'];
        $suitability_street = (int)$data['suitability']['street'];
        if (($suitability_race + $suitability_street) !== 100) {
            http_response_code(400);
            echo json_encode(["result" => "Suitability values must sum to 100"]);
            return;
        }

        // Validate that reliability is within the range 0-100
        $reliability = (int)$data['reliability'];
        if ($reliability < 0 || $reliability > 100) {
            http_response_code(400);
            echo json_encode(["result" => "Reliability must be between 0 and 100"]);
            return;
        }

        // Handle driver if provided, otherwise set driver_id to NULL
        if (isset($data['driver'])) {
            // Check if the driver is already assigned to another car
            $driver_stmt = $conn->prepare("SELECT id FROM drivers WHERE number = ?");
            $driver_stmt->bind_param("i", $data['driver']);
            $driver_stmt->execute();
            $driver_result = $driver_stmt->get_result();
            $driver_data = $driver_result->fetch_assoc();

            if (!$driver_data) {
                http_response_code(400);
                echo json_encode(["result" => "Invalid driver number"]);
                return;
            }

            $driver_id = $driver_data['id'];

            // Check if the driver is already assigned to another car
            $check_stmt = $conn->prepare("SELECT id FROM cars WHERE driver_id = ?");
            $check_stmt->bind_param("i", $driver_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) {
                http_response_code(400);
                echo json_encode(["result" => "Driver is already assigned to another car"]);
                return;
            }
        } else {
            // No driver provided, set driver_id to NULL
            $driver_id = null;
        }

        // Insert car with or without a driver_id
        $stmt = $conn->prepare("INSERT INTO cars (driver_id, suitability_race, suitability_street, reliability) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $driver_id, $suitability_race, $suitability_street, $data['reliability']);
        
        if ($stmt->execute()) {
            echo json_encode(["result" => "Car added"]);
        } else {
            http_response_code(500);
            echo json_encode(["result" => "Failed to add car"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["result" => "Invalid input"]);
    }
}

// Function to delete a car
function delete_car($id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM cars WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["result" => "Car deleted"]);
    } else {
        http_response_code(500);
        echo json_encode(["result" => "Failed to delete car"]);
    }
}
// Function to update a car (Allow cars without drivers and include suitability sum validation)
function update_car($id) {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['suitability'], $data['reliability'])) {

        // Validate that suitability values sum to 100
        $suitability_race = (int)$data['suitability']['race'];
        $suitability_street = (int)$data['suitability']['street'];
        if (($suitability_race + $suitability_street) !== 100) {
            http_response_code(400);
            echo json_encode(["result" => "Suitability values must sum to 100"]);
            return;
        }
        // Validate that reliability is within the range 0-100
        $reliability = (int)$data['reliability'];
        if ($reliability < 0 || $reliability > 100) {
            http_response_code(400);
            echo json_encode(["result" => "Reliability must be between 0 and 100"]);
            return;
        }

        // Only handle driver if provided in the request
        if (isset($data['driver'])) {
            // Handle driver if provided
            $stmt_driver = $conn->prepare("SELECT id FROM drivers WHERE number = ?");
            $stmt_driver->bind_param("i", $data['driver']);
            $stmt_driver->execute();
            $driver_result = $stmt_driver->get_result();
            $driver_data = $driver_result->fetch_assoc();

            if (!$driver_data) {
                http_response_code(400);
                echo json_encode(["result" => "Invalid driver number"]);
                return;
            }
            $driver_id = $driver_data['id'];

            // Check if the driver is already assigned to another car
            $check_stmt = $conn->prepare("SELECT id FROM cars WHERE driver_id = ? AND id != ?");
            $check_stmt->bind_param("ii", $driver_id, $id);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) {
                http_response_code(400);
                echo json_encode(["result" => "Driver is already assigned to another car"]);
                return;
            }
        } else {
            // No driver provided, keep the current driver_id
            $driver_id = null;  // You could leave it unchanged instead of explicitly setting to null
        }

        // Prepare the SQL to update car's properties and driver only if driver is provided
        if ($driver_id !== null) {
            // Update car along with driver_id
            $stmt = $conn->prepare("UPDATE cars SET driver_id=?, suitability_race=?, suitability_street=?, reliability=? WHERE id=?");
            $stmt->bind_param("iiiii", $driver_id, $suitability_race, $suitability_street, $data['reliability'], $id);
        } else {
            // Update car without modifying driver_id
            $stmt = $conn->prepare("UPDATE cars SET suitability_race=?, suitability_street=?, reliability=? WHERE id=?");
            $stmt->bind_param("iiii", $suitability_race, $suitability_street, $data['reliability'], $id);
        }

        // Execute the update statement
        if ($stmt->execute()) {
            echo json_encode(["result" => "Car updated"]);
        } else {
            http_response_code(500);
            echo json_encode(["result" => "Failed to update car"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["result" => "Invalid input"]);
    }
}

// Function to set a driver for a car
function set_driver_for_car($id) {
    global $conn;
    $data = json_decode(file_get_contents("php://input"), true);

    // Check if the 'number' field exists in the payload
    if (isset($data['number'])) {
        $driver_number = $data['number'];  // Extract driver number from the payload
       // echo "Driver number received: " . $driver_number;  // Debug log

        // Ensure the driver exists
        $stmt_driver = $conn->prepare("SELECT id FROM drivers WHERE number = ?");
        $stmt_driver->bind_param("i", $driver_number);
        $stmt_driver->execute();
        $stmt_driver->store_result();
        
        if ($stmt_driver->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["result" => "Driver not found"]);
            return;
        }

        $stmt_driver->bind_result($driver_id);
        $stmt_driver->fetch();
       // echo "Driver ID found: " . $driver_id;  // Debug log

        // Ensure the driver isn't assigned to another car
        $stmt_check = $conn->prepare("SELECT id FROM cars WHERE driver_id = ?");
        $stmt_check->bind_param("i", $driver_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            http_response_code(400);
            echo json_encode(["result" => "Driver is already assigned to another car"]);
            return;
        }

        //echo "Driver is not assigned to another car";  // Debug log

        // Update car with driver_id
        $stmt = $conn->prepare("UPDATE cars SET driver_id=? WHERE id=?");
        $stmt->bind_param("ii", $driver_id, $id);
        
        if ($stmt->execute()) {
            echo json_encode(["result" => "Driver set for car"]);
        } else {
            http_response_code(500);
            echo json_encode(["result" => "Failed to set driver"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["result" => "Invalid input"]);
    }
}

// Function to remove a driver from a car
function remove_driver_from_car($id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE cars SET driver_id=NULL WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["result" => "Driver removed from car"]);
    } else {
        http_response_code(500);
        echo json_encode(["result" => "Failed to remove driver from car"]);
    }
}

// Function to calculate lap result (deterministic)
function calculate_lap_result($car_id) {
    global $conn;

    // For GET requests, use $_GET to access query parameters
    if (!isset($_GET['trackType'], $_GET['baseLapTime'])) {
        http_response_code(400);
        echo json_encode([
            "code" => 400,
            "result" => "Track type and base lap time are required"
        ]);
        return;
    }

    $trackType = $_GET['trackType'];
    $baseLapTime = (float)$_GET['baseLapTime'];

    // Fetch car and driver data
    $stmt = $conn->prepare("
        SELECT cars.suitability_race, cars.suitability_street, cars.reliability, 
               drivers.skill_race, drivers.skill_street 
        FROM cars 
        LEFT JOIN drivers ON cars.driver_id = drivers.id 
        WHERE cars.id = ?
    ");
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $car_driver = $result->fetch_assoc();

    // Error handling
    if (!$car_driver) {
        http_response_code(404);
        echo json_encode([
            "code" => 404,
            "result" => "Car not found"
        ]);
        return;
    }
    if (empty($car_driver['skill_race']) && empty($car_driver['skill_street'])) {
        http_response_code(418);
        echo json_encode([
            "code" => 418,
            "result" => "Car has no driver"
        ]);
        return;
    }

    // Inputs
    $reliability = (int)$car_driver['reliability'];

    // Crash determination
    $additional_reliability = ($trackType === 'street') ? 10 : 5;
    $max_random_value = $reliability + $additional_reliability;

    // Set seed for deterministic crash calculation
    $seed = crc32($car_id . $trackType . $baseLapTime);
    mt_srand($seed);

    // Generate a floating-point random number between 0 (inclusive) and max_random_value (inclusive)
    $crash_random_number = ($max_random_value) * (mt_rand() / mt_getrandmax());

    // Reset seed for true randomness in randomness factor
    mt_srand();
    

    // Determine if the car crashes
    if ($crash_random_number > $reliability) {
        // Car crashes
        $crashed = true;
        $lap_time = 0;
    } else {
        // Car does not crash
        $crashed = false;

        // Calculate speed
        $car_suitability = ($trackType === 'street') ? $car_driver['suitability_street'] : $car_driver['suitability_race'];
        $driver_skill = ($trackType === 'street') ? $car_driver['skill_street'] : $car_driver['skill_race'];

        // Ensure the car's suitability and driver's skill are available
        if ($car_suitability === null || $driver_skill === null) {
            http_response_code(500);
            echo json_encode([
                "code" => 500,
                "result" => "Car suitability or driver skill data missing"
            ]);
            return;
        }

        // Calculate speed
        $speed = ($car_suitability + $driver_skill + (100 - $reliability)) / 3;

        // Calculate lap time deterministically
        $lap_time = $baseLapTime + (10 * ($speed / 100));
    }

    // Generate randomness factor (random number between 0 and 5)
    $randomness = rand(0, 500) / 100;

    // Output result
    echo json_encode([
        "code" => 200,
        "result" => [
            "time" => round($lap_time, 3),
            "randomness" => round($randomness, 3),
            "crashed" => $crashed
        ]
    ]);
}
?>