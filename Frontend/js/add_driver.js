const apiKey = '123';  // Make sure to update with your actual API key if necessary
const teamsApiBaseUrl = 'https://lab-d00a6b41-7f81-4587-a3ab-fa25e5f6d9cf.australiaeast.cloudapp.azure.com:7027/teams_api';

// Function to validate skill inputs and ensure they sum to 100
function validateSkills() {
    const streetSkill = parseInt(document.getElementById('driverSkillStreet').value) || 0;
    const raceSkill = parseInt(document.getElementById('driverSkillRace').value) || 0;
    if (streetSkill + raceSkill !== 100) {
        alert('The total skill rating for Street and Race tracks must be exactly 100.');
        return false;
    }
    return true;
}

// Function to handle form submission and add the driver
async function addDriver(event) {
    event.preventDefault();  // Prevent the form from submitting the traditional way

    if (!validateSkills()) return;  // Stop if skill validation fails

    const driverData = {
        name: document.getElementById('driverName').value,
        number: parseInt(document.getElementById('driverNumber').value),
        shortName: document.getElementById('driverShortName').value,
        skill: {
            street: parseInt(document.getElementById('driverSkillStreet').value),
            race: parseInt(document.getElementById('driverSkillRace').value),
        }
    };

    try {
        const response = await fetch(`${teamsApiBaseUrl}/driver`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Api-Key': apiKey
            },
            body: JSON.stringify(driverData)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            alert('Driver added successfully!');
            window.location.href = 'drivers.html';  // Redirect to drivers list page
        } else {
            alert(`Error: ${result.result || 'Unable to add driver.'}`);
        }
    } catch (error) {
        alert(`Error: ${error.message}`);
    }
}
