// WebSocket client for HelpDesk chat
// This file will be used for standalone implementations
// If we detect that the parent page already has WebSocket functionality,
// we'll avoid initializing duplicate connections

// Expose a global helper for the main page to check
window.chatWebSocketHelper = {
    isInitialized: false,
    init: function(ticketId, parentDeviceId) {
        // If already initialized by parent page, don't initialize again
        if (window.wsConnected !== undefined || this.isInitialized) {
            console.log("WebSocket already initialized by parent page");
            return;
        }

        console.log("Initializing standalone WebSocket client");
        this.isInitialized = true;

        // Start the standalone client
        initStandaloneClient(ticketId, parentDeviceId);
    }
};

// The standalone client implementation (only used if parent page doesn't have WebSocket)
function initStandaloneClient(ticketId, parentDeviceId) {
    let socket;
    let isConnected = false;
    let reconnectAttempts = 0;
    const maxReconnectAttempts = 5;
    const reconnectInterval = 3000; // 3 seconds

    // Use the parent's deviceId if provided, otherwise generate our own
    const deviceId = parentDeviceId || generateDeviceId();

    // Use secure WebSocket if page is loaded over HTTPS
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.hostname}:8080`;

    try {
        console.log("Connecting to WebSocket server...");
        socket = new WebSocket(wsUrl);

        socket.onopen = function() {
            console.log('Standalone WebSocket connected');
            isConnected = true;
            reconnectAttempts = 0;

            // Subscribe to this ticket's channel
            if (ticketId) {
                console.log("Subscribing to ticket: " + ticketId);
                socket.send(JSON.stringify({
                    action: 'subscribe',
                    ticketId: ticketId,
                    deviceId: deviceId
                }));
            }
        };

        socket.onmessage = function(event) {
            console.log("Received message from WebSocket", event.data);
            try {
                const data = JSON.parse(event.data);
                // Handle message here
            } catch (e) {
                console.error("Error parsing WebSocket message", e);
            }
        };

        socket.onclose = function() {
            console.log('Standalone WebSocket connection closed');
            isConnected = false;

            if (reconnectAttempts < maxReconnectAttempts) {
                reconnectAttempts++;
                setTimeout(function() {
                    console.log(`Attempting to reconnect (${reconnectAttempts}/${maxReconnectAttempts})...`);
                    initStandaloneClient(ticketId, deviceId);
                }, reconnectInterval);
            }
        };

        socket.onerror = function(error) {
            console.error('Standalone WebSocket error:', error);
        };
    } catch (e) {
        console.error('Failed to initialize standalone WebSocket:', e);
    }
}

// Helper function to generate a device ID
function generateDeviceId() {
    let id = localStorage.getItem('helpdesk_device_id');
    if (!id) {
        id = 'device_' + Math.random().toString(36).substring(2, 15);
        localStorage.setItem('helpdesk_device_id', id);
    }
    return id;
}

