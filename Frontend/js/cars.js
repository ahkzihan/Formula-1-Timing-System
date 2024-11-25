const apiKey = '123';
const teamsApiBaseUrl = 'https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api';

// Fetch and display cars
async function fetchCars() {
    try {
        const response = await fetch(`${teamsApiBaseUrl}/car`, {
            headers: { 'X-Api-Key': apiKey }
        });
        const data = await response.json();

        const carsList = document.getElementById('carsList');
        carsList.innerHTML = '';  // Clear previous entries

        if (response.ok) {
            data.result.forEach(car => {
                const carItem = document.createElement('div');
                carItem.classList.add('car-item');

                // Car information
                const carInfo = document.createElement('div');
                carInfo.classList.add('car-info');
                carInfo.innerHTML = `
                    <strong>Car ID:</strong> ${car.id}<br>
                    <strong>Driver:</strong> ${car.driver ? car.driver.name : 'No driver assigned'}<br>
                    <strong>Suitability:</strong> Race: ${car.suitability.race}%, Street: ${car.suitability.street}%<br>
                    <strong>Reliability:</strong> ${car.reliability}%
                `;

                // Action buttons
                const carActions = document.createElement('div');
                carActions.classList.add('car-actions');

                const editButton = document.createElement('button');
                editButton.textContent = 'Edit';
                editButton.onclick = () => editCar(car.id);

                const deleteButton = document.createElement('button');
                deleteButton.textContent = 'Delete';
                deleteButton.onclick = () => deleteCar(car.id);

                carActions.appendChild(editButton);
                carActions.appendChild(deleteButton);

                carItem.appendChild(carInfo);
                carItem.appendChild(carActions);
                carsList.appendChild(carItem);
            });
        } else {
            // Display error message if the API call fails
            carsList.innerHTML = `<p>Error fetching cars: ${data.result}</p>`;
        }
    } catch (error) {
        console.error("Failed to fetch cars:", error);
        alert("An error occurred while fetching the cars list.");
    }
}

// Open 'Add Car' Modal (this would typically open a modal or redirect to add page)
function openAddCarModal() {
    // Placeholder alert; replace with actual modal code if available
    alert("This would open the 'Add Car' modal.");
}

// Edit a car (navigate to edit or open edit modal)
function editCar(carId) {
    // Placeholder alert; replace with actual edit logic/modal
    alert(`Edit car functionality for Car ID: ${carId} is under construction.`);
}

// Delete a car with confirmation
async function deleteCar(carId) {
    if (confirm(`Are you sure you want to delete Car ID: ${carId}?`)) {
        try {
            const response = await fetch(`${teamsApiBaseUrl}/car/${carId}`, {
                method: 'DELETE',
                headers: { 'X-Api-Key': apiKey }
            });
            const data = await response.json();

            if (response.ok) {
                alert("Car deleted successfully.");
                fetchCars();  // Refresh the list after deletion
            } else {
                alert(`Failed to delete car: ${data.result}`);
            }
        } catch (error) {
            console.error("Failed to delete car:", error);
            alert("An error occurred while deleting the car.");
        }
    }
}

// Load cars when the page is loaded
document.addEventListener('DOMContentLoaded', fetchCars);
