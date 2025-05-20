/**
 * WebSocket client for HelpDesk
 * Handles real-time message updates
 */

class WebSocketClient {
    constructor(options = {}) {
        this.options = Object.assign({
            host: window.location.hostname,
            port: 8080,
            reconnectInterval: 1000,
            debug: false
        }, options);
        
        this.socket = null;
        this.connected = false;
        this.ticketId = null;
        this.eventListeners = {};
        this.reconnectTimer = null;
        
        // Bind methods
        this.connect = this.connect.bind(this);
        this.subscribe = this.subscribe.bind(this);
        this.sendMessage = this.sendMessage.bind(this);
        this.onMessage = this.onMessage.bind(this);
        this.onOpen = this.onOpen.bind(this);
        this.onClose = this.onClose.bind(this);
        this.onError = this.onError.bind(this);
        this.log = this.log.bind(this);
    }
    
    /**
     * Connect to the WebSocket server
     */
    connect() {
        if (this.socket && (this.socket.readyState === WebSocket.OPEN || this.socket.readyState === WebSocket.CONNECTING)) {
            this.log('Already connected or connecting');
            return;
        }
        
        try {
            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsUrl = `${protocol}//${this.options.host}:${this.options.port}`;
            
            this.log(`Connecting to ${wsUrl}`);
            this.socket = new WebSocket(wsUrl);
            
            this.socket.onopen = this.onOpen;
            this.socket.onclose = this.onClose;
            this.socket.onerror = this.onError;
            this.socket.onmessage = this.onMessage;
            
            // Clear any existing reconnect timer
            if (this.reconnectTimer) {
                clearTimeout(this.reconnectTimer);
                this.reconnectTimer = null;
            }
        } catch (error) {
            this.log('Connection error: ' + error.message);
            this.scheduleReconnect();
        }
    }
    
    /**
     * Subscribe to updates for a specific ticket
     */
    subscribe(ticketId) {
        if (!ticketId) {
            this.log('Error: No ticket ID provided');
            return false;
        }
        
        this.ticketId = ticketId;
        this.log(`Subscribing to ticket: ${ticketId}`);
        
        if (this.connected) {
            this.socket.send(JSON.stringify({
                type: 'subscribe',
                ticketId: ticketId
            }));
            return true;
        } else {
            this.log('Not connected, cannot subscribe');
            // Try reconnecting
            this.connect();
            return false;
        }
    }
    
    /**
     * Send a message related to a ticket
     */
    sendMessage(ticketId, message) {
        if (!ticketId || !message) {
            this.log('Error: Missing required parameters (ticketId or message)');
            this.trigger('error', {message: 'Missing required parameters'});
            return false;
        }
        
        if (!this.connected) {
            this.log('Not connected, cannot send message');
            this.trigger('error', {message: 'Not connected to server'});
            return false;
        }
        
        this.log(`Sending message for ticket ${ticketId}`);
        this.socket.send(JSON.stringify({
            type: 'message',
            ticketId: ticketId,
            message: message,
            timestamp: Date.now()
        }));
        
        return true;
    }
    
    /**
     * Handle incoming messages
     */
    onMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.log('Received: ' + JSON.stringify(data));
            
            // Handle different message types
            switch (data.type) {
                case 'update':
                    this.trigger('update', data);
                    break;
                    
                case 'subscribed':
                    this.trigger('subscribed', data);
                    break;
                    
                case 'messageReceived':
                    this.trigger('messageReceived', data);
                    break;
                    
                case 'error':
                    this.trigger('error', data);
                    break;
                    
                case 'pong':
                    this.trigger('pong', data);
                    break;
                    
                default:
                    this.log(`Unknown message type: ${data.type}`);
            }
        } catch (error) {
            this.log('Error processing message: ' + error.message);
        }
    }
    
    /**
     * Handle connection open
     */
    onOpen() {
        this.connected = true;
        this.log('Connection established');
        this.trigger('connected');
        
        // If we have a ticket ID, subscribe right away
        if (this.ticketId) {
            this.subscribe(this.ticketId);
        }
        
        // Start ping interval to keep connection alive
        this.startPingInterval();
    }
    
    /**
     * Handle connection close
     */
    onClose(event) {
        this.connected = false;
        this.log(`Connection closed: ${event.code} - ${event.reason}`);
        this.trigger('disconnected', event);
        
        // Clear ping interval
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }
        
        // Schedule reconnection
        this.scheduleReconnect();
    }
    
    /**
     * Handle connection error
     */
    onError(error) {
        this.log('WebSocket error: ' + (error.message || 'Unknown error'));
        this.trigger('error', {message: 'Connection error'});
    }
    
    /**
     * Schedule reconnection attempt
     */
    scheduleReconnect() {
        if (!this.reconnectTimer) {
            this.log(`Scheduling reconnect in ${this.options.reconnectInterval}ms`);
            this.reconnectTimer = setTimeout(() => {
                this.connect();
            }, this.options.reconnectInterval);
        }
    }
    
    /**
     * Start ping interval to keep connection alive
     */
    startPingInterval() {
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
        }
        
        this.pingInterval = setInterval(() => {
            if (this.connected) {
                this.log('Sending ping');
                this.socket.send(JSON.stringify({
                    type: 'ping',
                    timestamp: Date.now()
                }));
            }
        }, 30000); // Every 30 seconds
    }
    
    /**
     * Register an event listener
     */
    on(event, callback) {
        if (!this.eventListeners[event]) {
            this.eventListeners[event] = [];
        }
        this.eventListeners[event].push(callback);
        return this;
    }
    
    /**
     * Unregister an event listener
     */
    off(event, callback) {
        if (this.eventListeners[event]) {
            this.eventListeners[event] = this.eventListeners[event].filter(cb => cb !== callback);
        }
        return this;
    }
    
    /**
     * Trigger an event
     */
    trigger(event, data = {}) {
        if (this.eventListeners[event]) {
            this.eventListeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error('Error in event listener', error);
                }
            });
        }
    }
    
    /**
     * Log messages if debug is enabled
     */
    log(message) {
        if (this.options.debug) {
            console.log(`[WebSocket] ${message}`);
        }
    }
    
    /**
     * Close the WebSocket connection
     */
    close() {
        if (this.socket) {
            this.socket.close();
        }
        
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }
        
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }
    }
}

// Create global instance
if (typeof window !== 'undefined') {
    window.wsClient = new WebSocketClient({
        debug: true
    });
}
