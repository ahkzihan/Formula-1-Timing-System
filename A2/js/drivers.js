const apiKey = '123';
const teamsApiBaseUrl = 'https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api';

// Fetch and display drivers
async function fetchDrivers() {
    const response = await fetch(`${teamsApiBaseUrl}/driver`, {
        headers: { 'X-Api-Key': apiKey }
    });
    const data = await response.json();
    
    const driversList = document.getElementById('driversList');
    driversList.innerHTML = '';  // Clear previous entries

    data.result.forEach(driver => {
        const driverItem = document.createElement('div');
        driverItem.classList.add('driver-item');
        
        // Driver information
        const driverInfo = document.createElement('div');
        driverInfo.classList.add('driver-info');
        driverInfo.innerHTML = `<strong>Name:</strong> ${driver.name}<br>
                                <strong>Short Name:</strong> ${driver.shortName}<br>
                                <strong>Number:</strong> ${driver.number}`;
        
        // Action buttons
        const driverActions = document.createElement('div');
        driverActions.classList.add('driver-actions');
        
        const editButton = document.createElement('button');
        editButton.textContent = 'Edit';
        editButton.onclick = () => editDriver(driver.number);
        
        const deleteButton = document.createElement('button');
        deleteButton.textContent = 'Delete';
        deleteButton.onclick = () => deleteDriver(driver.number);
        
        driverActions.appendChild(editButton);
        driverActions.appendChild(deleteButton);
        
        driverItem.appendChild(driverInfo);
        driverItem.appendChild(driverActions);
        driversList.appendChild(driverItem);
    });
}

// Open modal to add driver
function openAddDriverModal() {
    window.location.href = 'add_driver.html';
}

// Edit a driver
function editDriver(driverNumber) {
    alert(`Edit driver functionality for Driver Number: ${driverNumber} is under construction.`);
}

// Delete a driver
async function deleteDriver(driverNumber) {
    if (confirm(`Are you sure you want to delete Driver Number: ${driverNumber}?`)) {
        const response = await fetch(`${teamsApiBaseUrl}/driver/${driverNumber}`, {
            method: 'DELETE',
            headers: { 'X-Api-Key': apiKey }
        });
        const data = await response.json();
        alert(data.result);
        fetchDrivers();  // Refresh the list after deletion
    }
}

// Initialize the page by fetching drivers
fetchDrivers();
