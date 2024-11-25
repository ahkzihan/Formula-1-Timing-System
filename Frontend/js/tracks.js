const apiKey = '123';
const raceApiBaseUrl = 'https://lab-95a11ac6-8103-422e-af7e-4a8532f40144.australiaeast.cloudapp.azure.com:7124/race_api';

// Fetch and display all tracks
async function fetchTracks() {
    const response = await fetch(`${raceApiBaseUrl}/track`, {
        headers: { 'X-Api-Key': apiKey }
    });
    const data = await response.json();

    const tracksList = document.getElementById('tracksList');
    tracksList.innerHTML = '';  // Clear previous entries

    data.result.forEach(track => {
        const trackItem = document.createElement('div');
        trackItem.classList.add('track-item');
        
        // Track information
        trackItem.innerHTML = `<strong>Name:</strong> ${track.name} <br>
                               <strong>Type:</strong> ${track.type} <br>
                               <strong>Laps:</strong> ${track.laps} <br>
                               <strong>Base Lap Time:</strong> ${track.baseLapTime} seconds`;

        // Delete button
        const deleteButton = document.createElement('button');
        deleteButton.textContent = 'Delete';
        deleteButton.onclick = () => deleteTrack(track.id);

        trackItem.appendChild(deleteButton);
        tracksList.appendChild(trackItem);
    });
}

// Delete a track
async function deleteTrack(trackId) {
    if (confirm('Are you sure you want to delete this track?')) {
        const response = await fetch(`${raceApiBaseUrl}/track/${trackId}`, {
            method: 'DELETE',
            headers: { 'X-Api-Key': apiKey }
        });
        const data = await response.json();
        alert(data.result);
        fetchTracks();  // Refresh the list after deletion
    }
}

// Initial load
fetchTracks();
