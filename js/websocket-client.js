// WebSocket Client for HelpDesk Chat
class HelpDeskWebSocket {
    constructor(ticketId, deviceId) {
        this.ticketId = ticketId;
        this.deviceId = deviceId;
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.reconnectDelay = 1000;
        this.isConnected = false;
        this.messageQueue = [];
        this.serverUrl = 'ws://' + window.location.hostname + ':8080';
        
        this.connect();
    }
    
    connect() {
        try {
            this.ws = new WebSocket(this.serverUrl);
            
            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.reconnectDelay = 1000;
                
                // Subscribe to ticket
                this.subscribe();
                
                // Send queued messages
                this.processMessageQueue();
                
                // Update UI
                this.updateConnectionStatus(true);
            };
            
            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleMessage(data);
                } catch (e) {
                    console.error('Error parsing WebSocket message:', e);
                }
            };
            
            this.ws.onclose = () => {
                console.log('WebSocket disconnected');
                this.isConnected = false;
                this.updateConnectionStatus(false);
                this.scheduleReconnect();
            };
            
            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.isConnected = false;
                this.updateConnectionStatus(false);
            };
            
        } catch (e) {
            console.error('Failed to create WebSocket:', e);
            this.scheduleReconnect();
        }
    }
    
    subscribe() {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                action: 'subscribe',
                ticketId: this.ticketId,
                deviceId: this.deviceId
            }));
        }
    }
    
    sendMessage(message) {
        const messageData = {
            action: 'newMessage',
            ticketId: this.ticketId,
            message: message,
            deviceId: this.deviceId
        };
        
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(messageData));
        } else {
            // Queue message for later
            this.messageQueue.push(messageData);
        }
    }
    
    handleMessage(data) {
        switch (data.action) {
            case 'connected':
                console.log('Connected with ID:', data.clientId);
                break;
                
            case 'subscribed':
                console.log('Subscribed to ticket:', data.ticketId);
                break;
                
            case 'newMessage':
                if (data.ticketId === this.ticketId) {
                    this.displayNewMessage(data.message);
                }
                break;
                
            case 'pong':
                // Keep-alive response
                break;
        }
    }
    
    displayNewMessage(message) {
        // Skip if message is from this device
        if (message.deviceId === this.deviceId || 
            message.sourceDeviceId === this.deviceId) {
            return;
        }
        
        // Check if message already exists
        const messageKey = message.messageId || 
            `${message.user}-${message.CommentTime}-${message.Message?.substring(0, 20)}`;
        
        if (document.querySelector(`[data-message-key="${messageKey}"]`)) {
            return;
        }
        
        // Add message to chat
        const chatBody = document.getElementById('chatBody');
        if (!chatBody) return;
        
        const isUser = parseInt(message.type) === 1;
        const messageClass = isUser ? 'message-user' : 'message-admin';
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${messageClass} new-message`;
        messageDiv.setAttribute('data-message-key', messageKey);
        
        const timeDisplay = this.formatTime(message.CommentTime);
        
        messageDiv.innerHTML = `
            <p class="message-content">${this.escapeHtml(message.Message)}</p>
            <div class="message-meta">
                <span class="message-user-info">${this.escapeHtml(message.user)}</span>
                <span class="message-time">${timeDisplay}</span>
            </div>
        `;
        
        chatBody.appendChild(messageDiv);
        
        // Scroll to bottom if near bottom
        if (chatBody.scrollHeight - chatBody.clientHeight <= chatBody.scrollTop + 100) {
            chatBody.scrollTop = chatBody.scrollHeight;
        }
    }
    
    formatTime(time) {
        try {
            const date = new Date(time);
            return date.toLocaleTimeString('pt-PT', {
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return time;
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('Max reconnection attempts reached');
            return;
        }
        
        this.reconnectAttempts++;
        const delay = Math.min(this.reconnectDelay * this.reconnectAttempts, 30000);
        
        console.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
        setTimeout(() => this.connect(), delay);
    }
    
    processMessageQueue() {
        while (this.messageQueue.length > 0 && this.isConnected) {
            const message = this.messageQueue.shift();
            this.ws.send(JSON.stringify(message));
        }
    }
    
    updateConnectionStatus(connected) {
        // Update any UI elements that show connection status
        const statusElements = document.querySelectorAll('.ws-status');
        statusElements.forEach(el => {
            if (connected) {
                el.classList.add('ws-status-connected');
                el.classList.remove('ws-status-disconnected');
            } else {
                el.classList.add('ws-status-disconnected');
                el.classList.remove('ws-status-connected');
            }
        });
    }
    
    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
    }
}

// Export for use
window.HelpDeskWebSocket = HelpDeskWebSocket;