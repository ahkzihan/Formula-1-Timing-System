const apiKey = '123';
const teamsApiBaseUrl = 'https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api';

let selectedDriverId = null;

// Function to select a driver (opens a prompt for simplicity)
function selectDriver() {
    const driverId = prompt("Enter Driver ID to associate:");
    if (driverId) {
        selectedDriverId = driverId;
        document.getElementById('selectedDriver').innerText = `Driver: ${driverId}`;
    }
}

// Function to clear the selected driver
function clearDriver() {
    selectedDriverId = null;
    document.getElementById('selectedDriver').innerText = "Driver: Not selected";
}

// Function to dynamically adjust race suitability when street suitability changes
function updateRaceSuitability() {
    const streetValue = parseInt(document.getElementById('suitabilityStreet').value);
    document.getElementById('suitabilityRace').value = 100 - streetValue;
}

// Function to dynamically adjust street suitability when race suitability changes
function updateStreetSuitability() {
    const raceValue = parseInt(document.getElementById('suitabilityRace').value);
    document.getElementById('suitabilityStreet').value = 100 - raceValue;
}

// Function to submit the car data to the API
async function submitCar() {
    const streetSuitability = parseInt(document.getElementById('suitabilityStreet').value);
    const raceSuitability = parseInt(document.getElementById('suitabilityRace').value);
    const reliability = parseInt(document.getElementById('reliability').value);

    const carData = {
        suitability: {
            street: streetSuitability,
            race: raceSuitability
        },
        reliability: reliability
    };

    if (selectedDriverId) {
        carData.driver = `https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api/driver/${selectedDriverId}`;
    }

    try {
        const response = await fetch(`${teamsApiBaseUrl}/car`, {
            method: 'POST',
            headers: {
                'X-Api-Key': apiKey,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(carData)
        });

        const result = await response.json();
        
        if (response.ok) {
            alert("Car created successfully!");
            window.location.href = 'cars.html';  // Redirect back to cars page
        } else {
            alert(`Error: ${result.result}`);
        }
    } catch (error) {
        console.error("Failed to create car:", error);
        alert("An error occurred while creating the car. Please try again.");
    }
}
