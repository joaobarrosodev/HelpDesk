document.addEventListener('DOMContentLoaded', function() {
    // Handler for closing tickets
    document.getElementById('fechar-ticket-btn').addEventListener('click', function() {
        const ticketId = this.getAttribute('data-ticket-id');
        
        fetch(`fechar_ticket.php?id=${ticketId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Add message to chat
                    addMessageToChatInterface('sistema', 'Ticket fechado com sucesso', data.timestamp);
                    
                    // Update UI to reflect closed state
                    document.getElementById('status-indicator').textContent = 'Concluído';
                    document.getElementById('status-indicator').classList.add('status-closed');
                    
                    // Disable further message sending
                    document.getElementById('send-message-btn').disabled = true;
                    document.getElementById('message-input').disabled = true;
                    document.getElementById('message-input').placeholder = 'Este ticket está fechado';
                } else {
                    // Show error message
                    alert('Erro ao fechar ticket: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar solicitação');
            });
    });
    
    // Function to add message to chat interface
    function addMessageToChatInterface(sender, message, timestamp) {
        const chatContainer = document.querySelector('.chat-messages-container');
        const messageElement = document.createElement('div');
        
        messageElement.classList.add('message');
        if (sender === 'sistema') {
            messageElement.classList.add('system-message');
        } else if (sender === 'admin') {
            messageElement.classList.add('admin-message');
        } else {
            messageElement.classList.add('user-message');
        }
        
        messageElement.innerHTML = `
            <div class="message-content">
                <p>${message}</p>
            </div>
            <div class="message-timestamp">${timestamp}</div>
        `;
        
        chatContainer.appendChild(messageElement);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
});
