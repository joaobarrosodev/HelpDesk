/**
 * Helper utilities for message handling in the HelpDesk system
 */

// Determine if we're in admin context based on URL
const isAdminContext = window.location.pathname.includes('/admin/');

/**
 * Send a message via AJAX
 * 
 * @param {FormData} formData The form data to send
 * @param {string} deviceId The device ID
 * @param {Function} onSuccess Success callback
 * @param {Function} onError Error callback
 */
function sendMessage(formData, deviceId, onSuccess, onError) {
    // Add device ID to the form data
    formData.append('deviceId', deviceId);
    
    // Determine the correct endpoint based on context
    const endpoint = isAdminContext ? 'inserir_mensagem.php' : '../inserir_mensagem.php';
    
    // Send the message
    fetch(endpoint, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error sending message: ${response.status}`);
        }
        // Handle both JSON and text responses
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                return { status: 'success', message: text };
            }
        });
    })
    .then(data => {
        if (data.status === 'error') {
            throw new Error(data.message || 'Error sending message');
        }
        if (onSuccess) onSuccess(data);
    })
    .catch(error => {
        if (onError) onError(error);
    });
}

/**
 * Check for new messages
 * 
 * @param {string} ticketId The ticket ID
 * @param {string} deviceId The device ID
 * @param {string} lastCheck The timestamp of the last check
 * @param {Function} onNewMessages Callback for new messages
 */
function checkForNewMessages(ticketId, deviceId, lastCheck, onNewMessages) {
    const timestamp = new Date().getTime();
    const baseUrl = isAdminContext ? '..' : '';
    
    fetch(`${baseUrl}/silent_sync.php?ticketId=${encodeURIComponent(ticketId)}&deviceId=${encodeURIComponent(deviceId)}&lastCheck=${encodeURIComponent(lastCheck)}&_=${timestamp}`)
        .then(response => {
            if (!response.ok) {
                return null;
            }
            return response.json();
        })
        .then(data => {
            if (!data || data.error) {
                return;
            }
            
            if (data.hasNewMessages && data.messages && data.messages.length > 0 && onNewMessages) {
                onNewMessages(data.messages);
            }
            
            return data;
        })
        .catch(() => {
            // Silent error handling
        });
}

/**
 * Format a time string for display
 * 
 * @param {string} time The time string to format
 * @returns {string} Formatted time string
 */
function formatMessageTime(time) {
    try {
        const timeObj = new Date(time);
        if (isNaN(timeObj)) {
            // Try parsing as MySQL datetime
            const parts = time.split(/[- :]/);
            if (parts.length >= 6) {
                const timeObj = new Date(parts[0], parts[1]-1, parts[2], parts[3], parts[4], parts[5]);
                return timeObj.toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'});
            } else {
                return time;
            }
        } else {
            return timeObj.toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'});
        }
    } catch (e) {
        return time;
    }
}
