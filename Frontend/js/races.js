const apiKey = '123';
const raceApiBaseUrl = 'https://lab-95a11ac6-8103-422e-af7e-4a8532f40144.australiaeast.cloudapp.azure.com:7124/race_api';

// Fetch and display races
async function fetchRaces() {
    const response = await fetch(`${raceApiBaseUrl}/race`, {
        headers: { 'X-Api-Key': apiKey }
    });
    const data = await response.json();

    const racesList = document.getElementById('racesList');
    racesList.innerHTML = '';  // Clear previous entries

    data.result.forEach(race => {
        const raceItem = document.createElement('div');
        raceItem.classList.add('race-item');

        // Race information
        const raceInfo = document.createElement('div');
        raceInfo.classList.add('race-info');
        raceInfo.innerHTML = `<strong>Race ID:</strong> ${race.id}<br>
                              <strong>Track:</strong> ${race.track.name}, Entrants: ${race.entrants.length}`;

        // Delete button
        const deleteButton = document.createElement('button');
        deleteButton.textContent = 'Delete';
        deleteButton.onclick = () => deleteRace(race.id);

        raceItem.appendChild(raceInfo);
        raceItem.appendChild(deleteButton);
        racesList.appendChild(raceItem);
    });
}

// Delete a race
async function deleteRace(raceId) {
    if (confirm(`Are you sure you want to delete Race ID: ${raceId}?`)) {
        const response = await fetch(`${raceApiBaseUrl}/race/${raceId}`, {
            method: 'DELETE',
            headers: { 'X-Api-Key': apiKey }
        });
        const data = await response.json();
        alert(data.result);
        fetchRaces();  // Refresh the list after deletion
    }
}

// Initial load
fetchRaces();
