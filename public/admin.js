// Admin Panel JavaScript
// Handles agent panel functionality with real-time updates

// Configuration
const API_BASE = 'api/agent';
const API_KEY_STORAGE = 'digimarker_agent_api_key';
const POLL_INTERVAL = 2000; // 2 seconds

// Get API Key from localStorage or prompt
function getApiKey() {
  let apiKey = localStorage.getItem(API_KEY_STORAGE);
  if (!apiKey) {
    apiKey = prompt('Enter Agent API Key:') || '';
    if (apiKey) {
      localStorage.setItem(API_KEY_STORAGE, apiKey);
    }
  }
  return apiKey;
}

const API_KEY = getApiKey();

// State
let currentAgentId = null;
let currentSessionId = null;
let lastMessageId = 0;
let pollingIntervals = {
  agents: null,
  queue: null,
  chat: null
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  // Always set up event listeners, even if API key is missing
  setupEventListeners();
  
  if (!API_KEY) {
    alert('API Key is required. Please refresh and enter your Agent API Key.');
    return;
  }
  loadAgents();
  startPolling();
});

// Event Listeners
function setupEventListeners() {
  const sendBtn = document.getElementById('send-chat-btn');
  const chatInput = document.getElementById('chat-input');
  const disconnectBtn = document.getElementById('disconnect-btn');
  const newAgentBtn = document.getElementById('new-agent-btn');
  const newAgentModal = document.getElementById('new-agent-modal');
  const closeModalBtn = document.getElementById('close-modal');
  const cancelBtn = document.getElementById('cancel-btn');
  const newAgentForm = document.getElementById('new-agent-form');

  if (sendBtn) {
    sendBtn.addEventListener('click', sendMessage);
  }
  if (chatInput) {
    chatInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        sendMessage();
      }
    });
  }
  if (disconnectBtn) {
    disconnectBtn.addEventListener('click', disconnectChat);
  }

  // New Agent Modal
  if (newAgentBtn && newAgentModal) {
    newAgentBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      newAgentModal.classList.add('active');
    });
  }

  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', closeModal);
  }
  if (cancelBtn) {
    cancelBtn.addEventListener('click', closeModal);
  }

  if (newAgentModal) {
    newAgentModal.addEventListener('click', (e) => {
      if (e.target === newAgentModal) {
        closeModal();
      }
    });
  }

  if (newAgentForm) {
    newAgentForm.addEventListener('submit', handleCreateAgent);
  }
}

function closeModal() {
  const newAgentModal = document.getElementById('new-agent-modal');
  const newAgentForm = document.getElementById('new-agent-form');
  newAgentModal.classList.remove('active');
  newAgentForm.reset();
}

async function handleCreateAgent(e) {
  e.preventDefault();
  
  const nameInput = document.getElementById('agent-name');
  const emailInput = document.getElementById('agent-email');
  const submitBtn = document.getElementById('submit-btn');
  
  const name = nameInput.value.trim();
  const email = emailInput.value.trim();

  if (!name || !email) {
    alert('Please fill in all fields');
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Adding...';

  try {
    await apiCall('create.php', {
      method: 'POST',
      body: JSON.stringify({
        name: name,
        email: email
      })
    });

    // Success - close modal and refresh agents list
    closeModal();
    loadAgents();
  } catch (error) {
    console.error('Failed to create agent:', error);
    alert('Failed to create agent: ' + error.message);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Add Agent';
  }
}

// API Helpers
async function apiCall(endpoint, options = {}) {
  const headers = {
    'Content-Type': 'application/json',
    'X-API-Key': API_KEY,
    ...options.headers
  };

  try {
    const response = await fetch(`${API_BASE}/${endpoint}`, {
      ...options,
      headers
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({ error: 'Unknown error' }));
      throw new Error(error.error || `HTTP ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
}

// Load Agents
async function loadAgents() {
  try {
    const data = await apiCall('agents.php');
    const agents = data.agents || [];
    
    renderAgentsList(agents);
  } catch (error) {
    console.error('Failed to load agents:', error);
  }
}

function renderAgentsList(agents) {
  const container = document.getElementById('agents-list');
  
  if (agents.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-users"></i>
        <p>No agents found</p>
      </div>
    `;
    return;
  }

  container.innerHTML = agents.map(agent => {
    const isOnline = agent.status === 'online';
    const isSelected = currentAgentId === agent.id;
    
    return `
      <div class="agent-card ${isSelected ? 'selected' : ''}" data-agent-id="${agent.id}">
        <div class="agent-name">${escapeHtml(agent.name)}</div>
        <div class="agent-status-indicator">
          <span class="agent-status-dot ${isOnline ? 'online' : 'offline'}"></span>
          <span>${isOnline ? 'Online' : 'Offline'}</span>
        </div>
        <button class="agent-toggle-btn ${isOnline ? 'online' : 'offline'}" 
                onclick="toggleAgentStatus(${agent.id}, '${isOnline ? 'offline' : 'online'}')"
                data-agent-id="${agent.id}">
          ${isOnline ? 'Go Offline' : 'Go Online'}
        </button>
      </div>
    `;
  }).join('');

  // Add click handlers for agent selection
  container.querySelectorAll('.agent-card').forEach(card => {
    card.addEventListener('click', (e) => {
      // Don't select if clicking the toggle button
      if (e.target.classList.contains('agent-toggle-btn')) {
        return;
      }
      const agentId = parseInt(card.dataset.agentId);
      selectAgent(agentId);
    });
  });
}

// Select Agent
function selectAgent(agentId) {
  currentAgentId = agentId;
  loadAgents(); // Refresh to update selected state
}

// Toggle Agent Status
async function toggleAgentStatus(agentId, newStatus) {
  try {
    await apiCall('update-status.php', {
      method: 'POST',
      body: JSON.stringify({
        agent_id: agentId,
        status: newStatus
      })
    });

    // Refresh agents list
    loadAgents();
    
    // If agent went offline and was selected, clear selection
    if (newStatus === 'offline' && currentAgentId === agentId) {
      currentAgentId = null;
    }
    
    // Refresh waiting chats
    loadWaitingChats();
  } catch (error) {
    console.error('Failed to update agent status:', error);
    alert('Failed to update status: ' + error.message);
  }
}

// Make toggleAgentStatus available globally
window.toggleAgentStatus = toggleAgentStatus;

// Load Waiting Chats
async function loadWaitingChats() {
  try {
    const data = await apiCall('queue.php');
    const sessions = data.sessions || [];
    
    // Check if any agent is online
    const agentsData = await apiCall('agents.php');
    const agents = agentsData.agents || [];
    const hasOnlineAgent = agents.some(a => a.status === 'online');
    
    renderWaitingChats(sessions, hasOnlineAgent);
  } catch (error) {
    console.error('Failed to load waiting chats:', error);
  }
}

function renderWaitingChats(sessions, hasOnlineAgent) {
  const container = document.getElementById('waiting-chats');
  const statusEl = document.getElementById('queue-status');
  
  if (!hasOnlineAgent) {
    statusEl.textContent = 'No agents online';
    container.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-user-slash"></i>
        <p>No agents are online</p>
        <p style="font-size: 0.85rem; margin-top: 10px;">Agents must be online to accept chats</p>
      </div>
    `;
    return;
  }
  
  statusEl.textContent = `${sessions.length} waiting`;
  
  if (sessions.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>No waiting chats</p>
      </div>
    `;
    return;
  }

  // Accept button logic will check agent status

  container.innerHTML = sessions.map(session => {
    const sessionId = session.session_id;
    const shortId = sessionId.substring(0, 8);
    const timeAgo = formatTimeAgo(session.created_at);
    const canAccept = currentAgentId !== null;
    
    return `
      <div class="waiting-chat-card new" data-session-id="${sessionId}">
        <div class="waiting-chat-header">
          <span class="waiting-chat-id">#${shortId}</span>
          <span class="new-badge">🟢 New</span>
        </div>
        <div class="waiting-chat-time">Requested ${timeAgo}</div>
        <button class="accept-btn" 
                onclick="acceptChat('${sessionId}')" 
                ${!canAccept ? 'disabled' : ''}>
          Accept Chat
        </button>
      </div>
    `;
  }).join('');
}

// Accept Chat
async function acceptChat(sessionId) {
  if (!currentAgentId) {
    alert('Please select an agent first');
    return;
  }

  // Verify agent is online
  try {
    const agentsData = await apiCall('agents.php');
    const agents = agentsData.agents || [];
    const currentAgent = agents.find(a => a.id === currentAgentId);
    
    if (!currentAgent || currentAgent.status !== 'online') {
      alert('Please go online first');
      loadAgents(); // Refresh to show current status
      return;
    }
  } catch (error) {
    console.error('Failed to verify agent status:', error);
  }

  try {
    const data = await apiCall('accept.php', {
      method: 'POST',
      body: JSON.stringify({
        session_id: sessionId,
        agent_id: currentAgentId
      })
    });

    if (data.success) {
      currentSessionId = sessionId;
      loadChatHistory(sessionId);
      loadWaitingChats(); // Refresh queue
    }
  } catch (error) {
    if (error.message.includes('409') || error.message.includes('already assigned')) {
      alert('This chat was already taken by another agent');
      loadWaitingChats(); // Refresh to remove it
    } else if (error.message.includes('not online') || error.message.includes('offline')) {
      alert('Agent must be online to accept chats');
      loadAgents(); // Refresh agent status
    } else {
      alert('Failed to accept chat: ' + error.message);
    }
  }
}

// Make acceptChat available globally
window.acceptChat = acceptChat;

// Load Chat History
async function loadChatHistory(sessionId) {
  try {
    const data = await apiCall(`chat-session.php?session_id=${encodeURIComponent(sessionId)}`);
    
    if (!data || !data.messages) {
      throw new Error('Invalid chat data');
    }

    renderChatMessages(data.messages);
    
    // Update last message ID
    if (data.messages.length > 0) {
      lastMessageId = Math.max(...data.messages.map(m => m.id || 0));
    }

    // Show input area
    document.getElementById('chat-input-area').style.display = 'flex';
    
    // Update header
    const header = document.querySelector('.chat-header-info');
    header.innerHTML = `
      <h3>Chat #${sessionId.substring(0, 8)}</h3>
      <span>Connected</span>
    `;

    // Show disconnect button
    document.getElementById('disconnect-btn').style.display = 'block';

    // Start polling for new messages
    startChatPolling(sessionId);
  } catch (error) {
    console.error('Failed to load chat history:', error);
    alert('Failed to load chat: ' + error.message);
  }
}

function renderChatMessages(messages) {
  const container = document.getElementById('chat-messages');
  
  if (messages.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-comments"></i>
        <p>No messages yet</p>
      </div>
    `;
    return;
  }

  container.innerHTML = messages.map(msg => {
    const sender = msg.sender || 'user';
    const time = formatTime(msg.created_at);
    
    return `
      <div class="chat-message ${sender}">
        <div class="chat-message-avatar">
          ${getSenderIcon(sender)}
        </div>
        <div class="chat-message-content">
          ${escapeHtml(msg.message)}
          <div class="chat-message-time">${time}</div>
        </div>
      </div>
    `;
  }).join('');

  // Scroll to bottom
  container.scrollTop = container.scrollHeight;
}

function getSenderIcon(sender) {
  const icons = {
    'user': '<i class="fas fa-user"></i>',
    'agent': '<i class="fas fa-user-tie"></i>',
    'bot': '<i class="fas fa-robot"></i>',
    'system': '<i class="fas fa-info-circle"></i>'
  };
  return icons[sender] || '<i class="fas fa-user"></i>';
}

// Send Message
async function sendMessage() {
  const input = document.getElementById('chat-input');
  const message = input.value.trim();
  
  if (!message || !currentSessionId || !currentAgentId) {
    return;
  }

  const sendBtn = document.getElementById('send-chat-btn');
  sendBtn.disabled = true;

  try {
    await apiCall('send-message.php', {
      method: 'POST',
      body: JSON.stringify({
        session_id: currentSessionId,
        agent_id: currentAgentId,
        message: message
      })
    });

    input.value = '';
    
    // Reload chat to show new message
    loadChatHistory(currentSessionId);
  } catch (error) {
    console.error('Failed to send message:', error);
    alert('Failed to send message: ' + error.message);
  } finally {
    sendBtn.disabled = false;
  }
}

// Polling Functions
function startPolling() {
  // Poll agents every 5 seconds
  pollingIntervals.agents = setInterval(() => {
    loadAgents();
  }, 5000);

  // Poll waiting chats every 2 seconds
  pollingIntervals.queue = setInterval(() => {
    loadWaitingChats();
  }, POLL_INTERVAL);
}

function startChatPolling(sessionId) {
  // Stop existing chat polling
  if (pollingIntervals.chat) {
    clearInterval(pollingIntervals.chat);
  }

  // Poll for new messages every 2 seconds
  pollingIntervals.chat = setInterval(async () => {
    if (!currentSessionId || currentSessionId !== sessionId) {
      clearInterval(pollingIntervals.chat);
      return;
    }

    try {
      const data = await apiCall(`chat-session.php?session_id=${encodeURIComponent(sessionId)}`);
      
      if (data && data.messages) {
        // Find new messages
        const newMessages = data.messages.filter(m => (m.id || 0) > lastMessageId);
        
        if (newMessages.length > 0) {
          // Append new messages
          const container = document.getElementById('chat-messages');
          newMessages.forEach(msg => {
            const sender = msg.sender || 'user';
            const time = formatTime(msg.created_at);
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message ${sender}`;
            messageDiv.innerHTML = `
              <div class="chat-message-avatar">
                ${getSenderIcon(sender)}
              </div>
              <div class="chat-message-content">
                ${escapeHtml(msg.message)}
                <div class="chat-message-time">${time}</div>
              </div>
            `;
            container.appendChild(messageDiv);
            lastMessageId = Math.max(lastMessageId, msg.id || 0);
          });
          
          container.scrollTop = container.scrollHeight;
        }
      }
    } catch (error) {
      console.error('Chat polling error:', error);
    }
  }, POLL_INTERVAL);
}

// Utility Functions
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatTime(datetime) {
  if (!datetime) return '';
  const date = new Date(datetime);
  return date.toLocaleTimeString('en-US', { 
    hour: '2-digit', 
    minute: '2-digit' 
  });
}

function formatTimeAgo(datetime) {
  if (!datetime) return '';
  const date = new Date(datetime);
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  
  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
  
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
  
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
}

// Disconnect Chat
async function disconnectChat() {
  if (!currentSessionId || !currentAgentId) {
    return;
  }

  if (!confirm('Are you sure you want to disconnect from this chat?')) {
    return;
  }

  try {
    await apiCall('disconnect.php', {
      method: 'POST',
      body: JSON.stringify({
        session_id: currentSessionId,
        agent_id: currentAgentId
      })
    });

    // Clear current chat
    currentSessionId = null;
    lastMessageId = 0;

    // Stop chat polling
    if (pollingIntervals.chat) {
      clearInterval(pollingIntervals.chat);
      pollingIntervals.chat = null;
    }

    // Reset UI
    document.getElementById('chat-messages').innerHTML = `
      <div class="empty-state">
        <i class="fas fa-comments"></i>
        <p>No active chat selected</p>
      </div>
    `;

    const header = document.querySelector('.chat-header-info');
    header.innerHTML = `
      <h3>Select a chat to start</h3>
      <span>Accept a chat from the waiting queue</span>
    `;

    document.getElementById('disconnect-btn').style.display = 'none';
    document.getElementById('chat-input-area').style.display = 'none';

    // Refresh waiting chats
    loadWaitingChats();
  } catch (error) {
    console.error('Failed to disconnect chat:', error);
    alert('Failed to disconnect: ' + error.message);
  }
}
