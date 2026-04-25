// Store pending scans in localStorage when offline
const OFFLINE_QUEUE_KEY = 'offline_scans';

function saveScanOffline(scanData) {
    let queue = JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
    queue.push({ ...scanData, timestamp: new Date().toISOString() });
    localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    console.log('Scan saved offline');
}

async function syncOfflineScans() {
    if (!navigator.onLine) return;
    let queue = JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
    if (queue.length === 0) return;

    for (let scan of queue) {
        try {
            const response = await fetch('/api/documents/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + localStorage.getItem('token')
                },
                body: JSON.stringify(scan)
            });
            if (response.ok) {
                // Remove from queue
                queue = queue.filter(s => s.timestamp !== scan.timestamp);
            }
        } catch (err) {
            console.error('Sync failed', err);
        }
    }
    localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
}

// Listen for online event
window.addEventListener('online', syncOfflineScans);

// Modify your scan submission to check online status
async function submitScan(scanData) {
    if (navigator.onLine) {
        const response = await fetch('/api/documents/scan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + localStorage.getItem('token') },
            body: JSON.stringify(scanData)
        });
        if (!response.ok) saveScanOffline(scanData);
        return response;
    } else {
        saveScanOffline(scanData);
        alert('You are offline. Scan saved and will sync when connection returns.');
    }
}