<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DigiMarker - Agent Panel</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Admin Panel Specific Styles */
    body.admin-panel {
      margin: 0;
      padding: 0;
      background: #f5f7fa;
      font-family: 'Poppins', sans-serif;
    }

    .admin-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .admin-header h1 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 600;
    }

    .admin-header .agent-selector {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .admin-container {
      display: grid;
      grid-template-columns: 300px 350px 1fr;
      height: calc(100vh - 70px);
      gap: 0;
    }

    .panel-column {
      background: white;
      border-right: 1px solid #e2e8f0;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }

    .panel-column:last-child {
      border-right: none;
    }

    .column-header {
      padding: 20px;
      border-bottom: 2px solid #e2e8f0;
      background: #f8fafc;
      font-weight: 600;
      font-size: 1rem;
      color: #1e293b;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .column-header i {
      color: #667eea;
    }

    .column-content {
      flex: 1;
      overflow-y: auto;
      padding: 15px;
    }

    /* Agents List */
    .agent-card {
      padding: 15px;
      margin-bottom: 12px;
      border-radius: 10px;
      border: 2px solid #e2e8f0;
      transition: all 0.3s;
      background: white;
    }

    .agent-card:hover {
      border-color: #667eea;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
    }

    .agent-card.selected {
      border-color: #667eea;
      background: #f0f4ff;
    }

    .agent-card-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
    }

    .agent-status-indicator {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.85rem;
      color: #64748b;
      margin-bottom: 10px;
    }

    .agent-status-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .agent-status-dot.online {
      background: #10b981;
      box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
    }

    .agent-status-dot.offline {
      background: #94a3b8;
    }

    .agent-name {
      font-weight: 600;
      color: #1e293b;
      font-size: 1rem;
      margin-bottom: 8px;
    }

    .agent-toggle-btn {
      width: 100%;
      padding: 8px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      background: white;
      color: #64748b;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.9rem;
    }

    .agent-toggle-btn:hover {
      border-color: #667eea;
      color: #667eea;
    }

    .agent-toggle-btn.online {
      background: #10b981;
      border-color: #10b981;
      color: white;
    }

    .agent-toggle-btn.online:hover {
      background: #059669;
      border-color: #059669;
    }

    .agent-toggle-btn.offline {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #64748b;
    }

    .agent-toggle-btn.offline:hover {
      background: #e2e8f0;
      border-color: #94a3b8;
    }

    /* Waiting Chats Queue */
    .waiting-chat-card {
      padding: 15px;
      margin-bottom: 10px;
      border-radius: 10px;
      border: 2px solid #f59e0b;
      background: #fffbeb;
      position: relative;
      animation: pulse-glow 2s ease-in-out infinite;
    }

    @keyframes pulse-glow {
      0%, 100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
      }
      50% {
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
      }
    }

    .waiting-chat-card.new {
      border-color: #10b981;
      background: #ecfdf5;
    }

    .waiting-chat-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .waiting-chat-id {
      font-weight: 600;
      color: #1e293b;
      font-size: 0.9rem;
    }

    .new-badge {
      background: #10b981;
      color: white;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 0.7rem;
      font-weight: 600;
    }

    .waiting-chat-time {
      font-size: 0.75rem;
      color: #64748b;
      margin-bottom: 10px;
    }

    .accept-btn {
      width: 100%;
      padding: 10px;
      border: none;
      border-radius: 8px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }

    .accept-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .accept-btn:disabled {
      background: #cbd5e1;
      cursor: not-allowed;
      transform: none;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #94a3b8;
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 15px;
      opacity: 0.5;
    }

    /* Active Chat Window */
    .chat-window {
      display: flex;
      flex-direction: column;
      height: 100%;
    }

    .chat-header {
      padding: 20px;
      border-bottom: 2px solid #e2e8f0;
      background: #f8fafc;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .chat-header-info h3 {
      margin: 0;
      font-size: 1rem;
      color: #1e293b;
    }

    .chat-header-info span {
      font-size: 0.85rem;
      color: #64748b;
    }

    .disconnect-btn {
      padding: 8px 16px;
      border: 2px solid #ef4444;
      border-radius: 8px;
      background: transparent;
      color: #ef4444;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.9rem;
    }

    .disconnect-btn:hover {
      background: #ef4444;
      color: white;
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
      background: #f5f7fa;
    }

    .chat-message {
      margin-bottom: 15px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }

    .chat-message.user {
      flex-direction: row;
      justify-content: flex-start;
    }

    .chat-message.agent,
    .chat-message.bot,
    .chat-message.system {
      flex-direction: row-reverse;
      justify-content: flex-start;
    }

    .chat-message-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      flex-shrink: 0;
    }

    .chat-message.user .chat-message-avatar {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .chat-message.agent .chat-message-avatar {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }

    .chat-message.bot .chat-message-avatar {
      background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
      color: white;
    }

    .chat-message.system .chat-message-avatar {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: white;
    }

    .chat-message-content {
      max-width: 70%;
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 0.9rem;
      line-height: 1.5;
    }

    .chat-message.user .chat-message-content {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      margin-right: auto;
    }

    .chat-message.agent .chat-message-content {
      background: #ecfdf5;
      color: #065f46;
      border-right: 3px solid #10b981;
      border-left: none;
      margin-left: auto;
    }

    .chat-message.bot .chat-message-content {
      background: white;
      color: #1e293b;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-left: auto;
    }

    .chat-message.system .chat-message-content {
      background: #fffbeb;
      color: #92400e;
      border-right: 3px solid #f59e0b;
      border-left: none;
      font-style: italic;
      margin-left: auto;
    }

    .chat-message-time {
      font-size: 0.7rem;
      color: #94a3b8;
      margin-top: 4px;
    }

    .chat-input-area {
      padding: 20px;
      border-top: 2px solid #e2e8f0;
      background: white;
      display: flex;
      gap: 10px;
    }

    .chat-input-area input {
      flex: 1;
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.9rem;
      outline: none;
      transition: all 0.3s;
    }

    .chat-input-area input:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .chat-input-area button {
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }

    .chat-input-area button:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .chat-input-area button:disabled {
      background: #cbd5e1;
      cursor: not-allowed;
      transform: none;
    }

    /* Scrollbar styling */
    .panel-column::-webkit-scrollbar,
    .chat-messages::-webkit-scrollbar {
      width: 6px;
    }

    .panel-column::-webkit-scrollbar-track,
    .chat-messages::-webkit-scrollbar-track {
      background: #f1f5f9;
    }

    .panel-column::-webkit-scrollbar-thumb,
    .chat-messages::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 3px;
    }

    .panel-column::-webkit-scrollbar-thumb:hover,
    .chat-messages::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }

    /* New Agent Button */
    .new-agent-btn {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      background: white;
      color: #667eea;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
    }

    .new-agent-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal-content {
      background: white;
      border-radius: 12px;
      padding: 30px;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .modal-header h2 {
      margin: 0;
      font-size: 1.5rem;
      color: #1e293b;
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      color: #94a3b8;
      cursor: pointer;
      padding: 0;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.3s;
    }

    .modal-close:hover {
      background: #f1f5f9;
      color: #1e293b;
    }

    .modal-form {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .form-group label {
      font-weight: 500;
      color: #1e293b;
      font-size: 0.9rem;
    }

    .form-group input {
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.9rem;
      outline: none;
      transition: all 0.3s;
    }

    .form-group input:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .modal-actions {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 10px;
    }

    .modal-btn {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.9rem;
    }

    .modal-btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .modal-btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .modal-btn-secondary {
      background: #f1f5f9;
      color: #64748b;
    }

    .modal-btn-secondary:hover {
      background: #e2e8f0;
    }

    .modal-btn:disabled {
      background: #cbd5e1;
      cursor: not-allowed;
      transform: none;
    }
  </style>
</head>
<body class="admin-panel">
  <div class="admin-header">
    <h1><i class="fas fa-headset"></i> DigiMarker Agent Panel</h1>
    <button class="new-agent-btn" id="new-agent-btn" onclick="document.getElementById('new-agent-modal').classList.add('active')">
      <i class="fas fa-user-plus"></i> New Agent
    </button>
  </div>
  
  <!-- API Key Info (hidden by default, shown if needed) -->
  <div id="api-key-info" style="display: none; padding: 15px; background: #fffbeb; border-bottom: 2px solid #f59e0b; color: #92400e; font-size: 0.9rem;">
    <strong>📝 API Key Setup:</strong> Set <code>DIGIMARKER_AGENT_API_KEY</code> in <code>config/config.local.php</code> 
    and enter the same key when prompted. See <code>ADMIN_PANEL_SETUP.md</code> for details.
  </div>

  <div class="admin-container">
    <!-- Column 1: Agents List -->
    <div class="panel-column">
      <div class="column-header">
        <i class="fas fa-users"></i>
        <span>Agents</span>
      </div>
      <div class="column-content" id="agents-list">
        <div class="empty-state">
          <i class="fas fa-spinner fa-spin"></i>
          <p>Loading agents...</p>
        </div>
      </div>
    </div>

    <!-- Column 2: Waiting Chats Queue -->
    <div class="panel-column">
      <div class="column-header">
        <i class="fas fa-clock"></i>
        <span>Waiting Chats</span>
        <span id="queue-status" style="margin-left: auto; font-size: 0.85rem; font-weight: normal; color: #64748b;"></span>
      </div>
      <div class="column-content" id="waiting-chats">
        <div class="empty-state">
          <i class="fas fa-inbox"></i>
          <p>No waiting chats</p>
        </div>
      </div>
    </div>

    <!-- Column 3: Active Chat Window -->
    <div class="panel-column">
      <div class="chat-window" id="chat-window">
        <div class="chat-header">
          <div class="chat-header-info">
            <h3>Select a chat to start</h3>
            <span>Accept a chat from the waiting queue</span>
          </div>
          <button class="disconnect-btn" id="disconnect-btn" style="display: none;">
            <i class="fas fa-times"></i> Disconnect
          </button>
        </div>
        <div class="chat-messages" id="chat-messages">
          <div class="empty-state">
            <i class="fas fa-comments"></i>
            <p>No active chat selected</p>
          </div>
        </div>
        <div class="chat-input-area" id="chat-input-area" style="display: none;">
          <input type="text" id="chat-input" placeholder="Type your message...">
          <button id="send-chat-btn"><i class="fas fa-paper-plane"></i> Send</button>
        </div>
      </div>
    </div>
  </div>

  <!-- New Agent Modal -->
  <div class="modal-overlay" id="new-agent-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-user-plus"></i> Add New Agent</h2>
        <button class="modal-close" id="close-modal">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form class="modal-form" id="new-agent-form">
        <div class="form-group">
          <label for="agent-name">Name *</label>
          <input type="text" id="agent-name" name="name" required placeholder="Enter agent name">
        </div>
        <div class="form-group">
          <label for="agent-email">Email *</label>
          <input type="email" id="agent-email" name="email" required placeholder="Enter agent email">
        </div>
        <div class="modal-actions">
          <button type="button" class="modal-btn modal-btn-secondary" id="cancel-btn">Cancel</button>
          <button type="submit" class="modal-btn modal-btn-primary" id="submit-btn">Add Agent</button>
        </div>
      </form>
    </div>
  </div>

  <script src="admin.js"></script>
</body>
</html>

