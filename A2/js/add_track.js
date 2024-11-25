const apiKey = '123';
const raceApiBaseUrl = 'https://lab-95a11ac6-8103-422e-af7e-4a8532f40144.australiaeast.cloudapp.azure.com:7124/race_api';

// Add a new track
async function addTrack(event) {
    event.preventDefault();

    const trackName = document.getElementById('trackName').value;
    const trackType = document.getElementById('trackType').value;
    const trackLaps = parseInt(document.getElementById('trackLaps').value);
    const baseLapTime = parseFloat(document.getElementById('baseLapTime').value);

    try {
        const response = await fetch(`${raceApiBaseUrl}/track`, {
            method: 'POST',
            headers: {
                'X-Api-Key': apiKey,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name: trackName,
                type: trackType,
                laps: trackLaps,
                baseLapTime: baseLapTime
            })
        });

        const data = await response.json();
        alert(data.result);

        // Redirect back to the tracks list after adding
        window.location.href = 'tracks.html';

    } catch (error) {
        console.error('Error adding track:', error);
        alert('Failed to add track. Please try again.');
    }
}
