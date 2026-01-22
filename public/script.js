// Navbar functionality
const navbar = document.querySelector('.navbar');
const hamburger = document.querySelector('.hamburger');
const navMenu = document.querySelector('.nav-menu');
const navLinks = document.querySelectorAll('.nav-link');
const navLogo = document.querySelector('.nav-logo');

// Navbar scroll effect
window.addEventListener('scroll', () => {
  if (window.scrollY > 50) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});

// Mobile menu toggle
hamburger.addEventListener('click', () => {
  hamburger.classList.toggle('active');
  navMenu.classList.toggle('active');
});

// Close mobile menu when clicking on a link
navLinks.forEach(link => {
  link.addEventListener('click', () => {
    hamburger.classList.remove('active');
    navMenu.classList.remove('active');
  });
});

// Smooth scrolling for navigation links
navLinks.forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    const targetId = link.getAttribute('href');
    if (targetId.startsWith('#')) {
      const targetSection = document.querySelector(targetId);
      if (targetSection) {
        const offsetTop = targetSection.offsetTop - 80;
        window.scrollTo({
          top: offsetTop,
          behavior: 'smooth'
        });
      }
    }
  });
});

// Logo click scrolls to top
navLogo.addEventListener('click', () => {
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });
  hamburger.classList.remove('active');
  navMenu.classList.remove('active');
});

// Chatbot variables
const chatbotIcon = document.getElementById('chatbot-icon');
const chatbotBox = document.getElementById('chatbot-box');
const closeBtn = document.getElementById('close-chat');
const sendBtn = document.getElementById('send-btn');
const messages = document.getElementById('chatbot-messages');
const userInput = document.getElementById('user-message');

// Session management
const SESSION_STORAGE_KEY = 'digimarker_chat_session_id';

// In-memory chat history for model context
// Structure: [{ role: 'user' | 'assistant', content: '...' }, ...]
let chatHistory = [];

// Agent mode state
let agentMode = {
  active: false,
  connected: false,
  lastMessageId: 0,
  pollInterval: null
};

// Current connected agent name (used for labeling agent messages in the UI)
let currentAgentName = 'Agent';

/**
 * Initialize last message ID from existing messages
 * This ensures we don't miss messages when starting to poll
 */
function initializeLastMessageId() {
  // Get all existing messages and find the highest ID
  // For now, we'll start from 0 and let polling catch up
  // In a production system, you might want to fetch the last message ID from the server
  agentMode.lastMessageId = 0;
}

/**
 * Generate a UUID v4 (RFC 4122 compliant)
 * @returns {string} UUID v4 string
 */
function generateUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

/**
 * Get or create session ID
 * Checks localStorage first, creates new UUID if missing
 * @returns {string} Session ID
 */
function getOrCreateSessionId() {
  let sessionId = localStorage.getItem(SESSION_STORAGE_KEY);
  
  if (!sessionId || sessionId.length < 32) {
    // Generate new UUID v4 session ID
    sessionId = generateUUID();
    localStorage.setItem(SESSION_STORAGE_KEY, sessionId);
  }
  
  return sessionId;
}

// FAQ Data
const chatbotFAQ = {
  "chatbot_faq": [
    {
      "id": 1,
      "question": "Digital Evaluation",
      "answer": "",
      "sub_faqs": [
        {
          "id": 1,
          "question": "What is Digital Evaluation?",
          "answer": "Digital Evaluation is the process of assessing student answer scripts on-screen instead of manually checking physical copies.\n\nBenefits:\n- Accuracy\n- Transparency\n- Faster results"
        },
        {
          "id": 6,
          "question": "Can I evaluate from home?",
          "answer": "Center Evaluation: Most institutions require designated centers.\nRemote Evaluation: Allowed only if officially approved by the Board/University."
        },
        {
          "id": 7,
          "question": "Supported Browsers",
          "answer": "- Google Chrome (Recommended)\n- Mozilla Firefox"
        },
        {
          "id": 24,
          "question": "Minimum Internet Speed",
          "answer": "Stable 20–50 Mbps recommended."
        },
        {
          "id": 25,
          "question": "Can I use a mobile hotspot?",
          "answer": "Not recommended.\nPrefer LAN or strong Wi‑Fi."
        },
        {
          "id": 26,
          "question": "Supported Operating Systems",
          "answer": "- Windows 10/11\n- macOS (latest)\n- Linux (limited support)"
        },
        {
          "id": 29,
          "question": "Recommended Screen Resolution",
          "answer": "Minimum 1366 × 768 pixels."
        },
        {
          "id": 30,
          "question": "Can I see total marks?",
          "answer": "Displayed automatically after marking all questions."
        },
        {
          "id": 31,
          "question": "What does “Session Expire” mean?",
          "answer": "Session ended due to inactivity.\nLog in again to continue."
        },
        {
          "id": 33,
          "question": "Access after office hours?",
          "answer": "Yes, DigiMarker is available 24/7.\nEnsure stable internet."
        }
      ]
    },
    {
      "id": 2,
      "question": "Login & Access",
      "answer": "",
      "sub_faqs": [
        {
          "id": 2,
          "question": "How do I log in to DigiMarker?",
          "answer": "1) Use the username & password sent to your registered email.\n2) Visit the official URL / local server link.\n3) Enter credentials → Click Login."
        },
        {
          "id": 3,
          "question": "How do I access my assigned scripts for evaluation?",
          "answer": "1) After login → Click Start to open Dashboard.\n2) Select the Subject Code with available scripts.\n3) Click Change → Message: “DEFAULT SUBJECT UPDATED SUCCESSFULLY”.\n4) Click Start Marking → Begin evaluation."
        },
        {
          "id": 4,
          "question": "Not Receiving Email or OTP?",
          "answer": "Try these steps:\n- Check your Inbox (OTP emails may go to Spam/Junk)\n- Ensure you are using the registered email ID / mobile number\n- Wait 2–5 minutes (server delay/queue)\n- Ensure internet is stable and inbox isn’t full\n- If still not received, contact Help Desk/Admin"
        },
        {
          "id": 8,
          "question": "Troubleshooting Login Errors",
          "answer": "If login fails:\n- Check username/password carefully\n- Use stable Wi‑Fi or LAN (avoid hotspots)\n- Clear browser cache & cookies, then retry\n- System date/time must be correct\n- If account is locked, contact Help Desk/Admin\n- Ensure firewall/antivirus is not blocked."
        },
        {
          "id": 20,
          "question": "System error: Date & Time",
          "answer": "Cause: Incorrect system clock.\nSolution:\n1) Right-click clock → Change Date/Time\n2) Sync with Internet Time\n3) Save & retry login"
        },
        {
          "id": 28,
          "question": "How to ensure correct Date & Time?",
          "answer": "Sync with Internet Time.\nWrong time may block DigiMarker access."
        },
        {
          "id": 21,
          "question": "Forgot Password?",
          "answer": "Use the Forgot Password option, or contact Help Desk."
        }
      ]
    },
    {
      "id": 3,
      "question": "Marking & Tools",
      "answer": "",
      "sub_faqs": [
        {
          "id": 5,
          "question": "What if the marking panel is not showing after right-click?",
          "answer": "For the marking panel to appear:\n1) First select/click the particular question number in the script panel.\n2) Then right-click on that question area.\n3) The marking panel will pop up (options to enter marks, comments, etc.).\n\nIf you right-click on blank space/outside the question area, the marking panel will not show."
        },
        {
          "id": 9,
          "question": "How do I mark answers?",
          "answer": "1) Select Question Number.\n2) Right-click → Enter marks.\n3) Marks auto-save & totals are auto-calculated."
        },
        {
          "id": 10,
          "question": "Can I change marks?",
          "answer": "Yes:\n- Select the question → Use Undo to remove.\n- Re-enter marks → Auto-saved & re-totaled."
        },
        {
          "id": 11,
          "question": "What if a page is blank?",
          "answer": "Use the Cross-Annotation Tool.\nThe system records it as 0 automatically."
        },
        {
          "id": 12,
          "question": "How is totaling handled?",
          "answer": "DigiMarker auto-calculates totals.\n\nEnsure every question is marked before submission."
        },
        {
          "id": 14,
          "question": "What if I skip a question?",
          "answer": "Warning: “You have not marked all questions and pages.”\n\nReview the script carefully before submission."
        },
        {
          "id": 15,
          "question": "What if the student leaves questions unattempted?",
          "answer": "Always record “0” marks with remark “NA” instead of leaving it blank, so the system registers it as evaluated.\nOtherwise, the system will flag it during submission with a message like:\n“You have not marked these questions.”"
        },
        {
          "id": 36,
          "question": "Annotations Toolbar",
          "answer": "Tools available at the top of the screen:\n- Right Tick – Correct answers\n- Wrong Sign – Incorrect answers\n- Question Mark – Doubtful answers\n- Comment Box – Add notes\n- Rectangle / Circle – Highlight areas\n- Undo / Redo actions"
        },
        {
          "id": 38,
          "question": "Facility to Rotate Answer Sheet",
          "answer": "Rotate scanned answer sheets for best viewing angle.\nEnsures clear visibility, making evaluation smoother & faster."
        },
        {
          "id": 56,
          "question": "How to view QUESTION PAPER or MODEL ANSWER",
          "answer": "When you open the Answer Script, you will see two tabs beside it:\n- QUESTION PAPER → Opens the exam paper.\n- MODEL ANSWER → Opens the official answer key.\n\nHow to view:\n1) Open your answer script in the portal.\n2) On the top/right side, click QUESTION PAPER or MODEL ANSWER.\n3) The file will open."
        }
      ]
    },
    {
      "id": 4,
      "question": "Submission & Rejection",
      "answer": "",
      "sub_faqs": [
        {
          "id": 16,
          "question": "How do I reject an answer sheet?",
          "answer": "1) Click Reject (top-right corner toolbar).\n2) Select reason:\n- Wrong Language\n- Wrong Set\n- Wrong Subject\n- Blurred Pages\n- Incomplete Script"
        },
        {
          "id": 37,
          "question": "Reject Option (Detailed)",
          "answer": "Use Reject (top-right corner) when a script cannot be evaluated.\nSelect valid reason (e.g., Wrong Subject, Blurred Scan, Incomplete Script).\nThe script is removed from dashboard & reassigned by Admin."
        },
        {
          "id": 17,
          "question": "How do I confirm submission?",
          "answer": "Click Submit Evaluation.\nConfirmation: “Sheet Successfully Saved.”\nScript disappears from dashboard."
        },
        {
          "id": 18,
          "question": "Unable to save answer sheet?",
          "answer": "Cause: Poor internet speed.\nSolution:\n- Avoid mobile hotspots\n- Use Wi‑Fi or LAN\n- Restart router if needed"
        },
        {
          "id": 19,
          "question": "Logged out or unable to save?",
          "answer": "Often due to unstable internet.\n- Avoid mobile hotspots\n- Use Wi‑Fi or LAN\n- Restart router if needed"
        },
        {
          "id": 34,
          "question": "Error: “No more sheet showing” – Case 1",
          "answer": "All assigned scripts have been evaluated.\nConfirm with Help Desk if quota is complete."
        },
        {
          "id": 35,
          "question": "Error: “No more sheet showing” – Case 2",
          "answer": "Causes:\n1) Answer scripts assigned to multiple markers → Contact Help Desk to unassign from inactive markers\n2) Question paper marking not completed → Ask Admin to complete setup → Refresh & continue"
        }
      ]
    },
    {
      "id": 5,
      "question": "Technical Issues",
      "answer": "",
      "sub_faqs": [
        {
          "id": 13,
          "question": "System hangs or crashes?",
          "answer": "Auto-save is active.\nRe-login and continue.\nIf issue persists → Contact Help Desk."
        },
        {
          "id": 27,
          "question": "System crash during marking?",
          "answer": "Save work → Restart → Re-login.\nIf scripts are missing → Contact support."
        },
        {
          "id": 32,
          "question": "Error: “Network Not Available”",
          "answer": "Check internet, switch network, and retry login."
        },
        {
          "id": 60,
          "question": "The application is not loading or working slow. What should I do?",
          "answer": "Try the following steps:\n- Refresh the page\n- Clear your browser cache and cookies\n- Try opening the application in a different browser\nIf the issue persists, please contact the Help Desk for support."
        },
        {
          "id": 61,
          "question": "I am getting an error message. What should I do?",
          "answer": "If you encounter an error message:\n- Take a clear screenshot of the error\n- Raise a Help Desk ticket with:\n  - Date and time of the issue\n  - Action being performed when the error occurred\n  - Screenshot of the error message\n\nThis will help the support team resolve your issue faster."
        }
      ]
    },
    {
      "id": 6,
      "question": "Roles & Guidelines",
      "answer": "",
      "sub_faqs": [
        {
          "id": 39,
          "question": "Role of Head Marker in Digital Evaluation",
          "answer": "Acts as second-level evaluator after examiners.\nRe-checks answer sheets for fairness, consistency & quality control."
        },
        {
          "id": 40,
          "question": "Head Marker & Deputy Head Marker Assignment Criteria",
          "answer": "Assigned based on university/institution guidelines.\nSelection depends on subject expertise, experience & academic requirements."
        },
        {
          "id": 41,
          "question": "Role of Deputy Head Marker in Digital Evaluation",
          "answer": "Acts as first-level evaluator after examiners.\nRe-checks answer sheets for fairness, consistency & quality control.\nBlue colour mark/notation indicates the booklet has been rechecked by the Deputy."
        },
        {
          "id": 42,
          "question": "Role of Head Marker in Digital Evaluation (after Deputy Head Marker)",
          "answer": "Acts as second-level evaluator after Deputy Head Marker.\nRe-checks answer sheets for fairness, consistency & quality control.\nRed colour mark/notation indicates the booklet has been rechecked by the Head Marker."
        },
        {
          "id": 43,
          "question": "Do’s & Don’ts for Evaluators",
          "answer": "Do’s:\n- Follow official marking guidelines\n- Maintain confidentiality\n- Use system features correctly (annotations, reject, submit)\n- Report issues promptly\n- Ensure date, time & internet are correct\n\nDon’ts:\n- Do not share login credentials\n- Do not alter marks/data outside DigiMarker\n- Avoid unauthorized devices/networks\n- Do not discuss/share student scripts/marks outside official channels\n- Do not leave the system unattended while logged in"
        },
        {
          "id": 23,
          "question": "Precautions while evaluating",
          "answer": "- Do not share login credentials\n- Don’t leave system unattended\n- Save work frequently\n- Follow official evaluation guidelines"
        }
      ]
    },
    {
      "id": 7,
      "question": "Help Desk & Reports",
      "answer": "",
      "sub_faqs": [
        {
          "id": 22,
          "question": "Whom to contact for technical issues?",
          "answer": "Help Desk at Command Center – First point of contact for all login, access, or technical issues."
        },
        {
          "id": 59,
          "question": "What are the Help Desk working hours?",
          "answer": "The Help Desk is available Monday to Friday, from 9:30 AM to 6:30 PM.\nClosed on weekends and government holidays."
        },
        {
          "id": 57,
          "question": "How to Download Date-wise Checked Booklets Count from evaluator login",
          "answer": "1) Login to the Dashboard.\n2) Go to Date-wise Checked Booklets.\n3) Click Export.\nFile will download with date-wise checked booklets count."
        }
      ]
    }
  ]
};

// Store current view state
let currentView = {
  type: 'categories', // 'categories', 'sub-faqs', 'answer'
  category: null,
  question: null,
  answer: null
};

// Function to clear current FAQ view
function clearCurrentFAQView() {
  const allBotMessages = messages.querySelectorAll('.message.bot');
  allBotMessages.forEach((msg, index) => {
    if (index === 0) {
      // For the first message (initial greeting), clear the FAQ container but keep the message
      const faqContainer = msg.querySelector('.faq-container');
      if (faqContainer) {
        faqContainer.innerHTML = '';
      }
    } else {
      // Remove all other FAQ-related messages
      const hasFAQ = msg.querySelector('.faq-container, .faq-answer, .faq-sub-item');
      if (hasFAQ) {
        msg.remove();
      }
    }
  });
}

// Icon mapping for FAQ categories
const categoryIcons = {
  "Digital Evaluation Overview": "fa-chart-bar",
  "Login and Access": "fa-lock",
  "Marking Process": "fa-pencil",
  "Submission and Rejection": "fa-redo",
  "Technical & System Issues": "fa-exclamation-triangle",
  "FTP, Upload, and Sync": "fa-upload",
  "Head/Deputy Marker Roles": "fa-user",
  "Miscellaneous": "fa-file-alt"
};

// Function to get icon for category
function getCategoryIcon(question) {
  // Try exact match first
  if (categoryIcons[question]) {
    return categoryIcons[question];
  }
  // Try partial matches
  if (question.toLowerCase().includes("digital evaluation") || question.toLowerCase().includes("evaluation")) {
    return "fa-chart-bar";
  }
  if (question.toLowerCase().includes("login") || question.toLowerCase().includes("access")) {
    return "fa-lock";
  }
  if (question.toLowerCase().includes("marking") || question.toLowerCase().includes("mark")) {
    return "fa-pencil";
  }
  if (question.toLowerCase().includes("revaluation") || question.toLowerCase().includes("reject") || question.toLowerCase().includes("submission")) {
    return "fa-redo";
  }
  if (question.toLowerCase().includes("upload") || question.toLowerCase().includes("ftp") || question.toLowerCase().includes("sync")) {
    return "fa-upload";
  }
  if (question.toLowerCase().includes("technical") || question.toLowerCase().includes("system") || question.toLowerCase().includes("issue") || question.toLowerCase().includes("error")) {
    return "fa-exclamation-triangle";
  }
  if (question.toLowerCase().includes("result") || question.toLowerCase().includes("report")) {
    return "fa-file-alt";
  }
  if (question.toLowerCase().includes("download")) {
    return "fa-download";
  }
  if (question.toLowerCase().includes("role") || question.toLowerCase().includes("head") || question.toLowerCase().includes("deputy") || question.toLowerCase().includes("marker")) {
    return "fa-user";
  }
  // Default icon
  return "fa-question-circle";
}

// Function to render FAQ categories
function renderFAQCategories() {
  const container = document.createElement('div');
  container.className = 'faq-container';
  
  chatbotFAQ.chatbot_faq.forEach(category => {
    const card = document.createElement('div');
    card.className = 'faq-category-card';
    const iconClass = getCategoryIcon(category.question);
    card.innerHTML = `
      <i class="fas ${iconClass}"></i>
      <span>${category.question}</span>
    `;
    card.addEventListener('click', () => {
      if (category.sub_faqs && category.sub_faqs.length > 0) {
        // Clear current view and show sub-FAQs
        clearCurrentFAQView();
        currentView = { type: 'sub-faqs', category: category };
        renderSubFAQs(category);
      } else if (category.answer) {
        // Clear current view and show answer
        clearCurrentFAQView();
        currentView = { type: 'answer', question: category.question, answer: category.answer };
        showFAQAnswer(category.question, category.answer);
      }
    });
    container.appendChild(card);
  });
  
  return container;
}

// Function to render sub-FAQs
function renderSubFAQs(category) {
  const botMsg = document.createElement('div');
  botMsg.className = 'message bot';
  botMsg.innerHTML = `
    <div class="message-avatar">
      <i class="fas fa-robot"></i>
    </div>
    <div class="message-content">
      <div class="faq-title">${category.question}</div>
      <div class="faq-container">
        ${category.sub_faqs.map(sub => `
          <div class="faq-sub-item" data-answer="${sub.answer.replace(/"/g, '&quot;')}">
            <span>${sub.question}</span>
            <i class="fas fa-arrow-right"></i>
          </div>
        `).join('')}
        <button class="faq-back-btn" onclick="goBack()">
          <i class="fas fa-arrow-left"></i> Back
        </button>
      </div>
    </div>
  `;
  messages.appendChild(botMsg);
  
  // Add click handlers for sub-FAQs
  botMsg.querySelectorAll('.faq-sub-item').forEach(item => {
    item.addEventListener('click', () => {
      const answer = item.getAttribute('data-answer');
      const question = item.querySelector('span').textContent;
      // Clear current view and show answer
      clearCurrentFAQView();
      currentView = { type: 'answer', question: question, answer: answer, category: category };
      showFAQAnswer(question, answer);
    });
  });
  
  messages.scrollTop = messages.scrollHeight;
}

// Function to show FAQ answer
function showFAQAnswer(question, answer) {
  const botMsg = document.createElement('div');
  botMsg.className = 'message bot';
  botMsg.innerHTML = `
    <div class="message-avatar">
      <i class="fas fa-robot"></i>
    </div>
    <div class="message-content">
      <div class="faq-title">${question}</div>
      <div class="faq-answer">${formatAnswerText(answer)}</div>
      <button class="faq-back-btn" onclick="goBack()">
        <i class="fas fa-arrow-left"></i> Back
      </button>
    </div>
  `;
  messages.appendChild(botMsg);
  messages.scrollTop = messages.scrollHeight;
}

// Function to go back
window.goBack = function() {
  clearCurrentFAQView();
  
  if (currentView.type === 'answer' && currentView.category) {
    // If we're viewing an answer and have a category, go back to sub-FAQs
    currentView = { type: 'sub-faqs', category: currentView.category };
    renderSubFAQs(currentView.category);
  } else {
    // Otherwise, go back to categories
    currentView = { type: 'categories' };
    showFAQCategories();
  }
};

// Function to show FAQ categories
window.showFAQCategories = function() {
  clearCurrentFAQView();
  currentView = { type: 'categories' };
  
  // Check if initial message exists and restore categories there
  const initialMessage = messages.querySelector('.message.bot:first-child');
  if (initialMessage) {
    let initialFAQContainer = initialMessage.querySelector('.faq-container');
    
    // If container doesn't exist, create it
    if (!initialFAQContainer) {
      const messageContent = initialMessage.querySelector('.message-content');
      if (messageContent) {
        initialFAQContainer = document.createElement('div');
        initialFAQContainer.className = 'faq-container';
        messageContent.appendChild(initialFAQContainer);
      }
    }
    
    // Populate with categories
    if (initialFAQContainer) {
      initialFAQContainer.innerHTML = '';
      const categoriesContainer = renderFAQCategories();
      // Move all cards to initial container
      while (categoriesContainer.firstChild) {
        initialFAQContainer.appendChild(categoriesContainer.firstChild);
      }
      
      // Scroll to top
      messages.scrollTop = 0;
      return;
    }
  }
  
  // If no initial message, create a new one
  const botMsg = document.createElement('div');
  botMsg.className = 'message bot';
  const messageContent = document.createElement('div');
  messageContent.className = 'message-content';
  
  
  const categoriesContainer = renderFAQCategories();
  messageContent.appendChild(categoriesContainer);
  
  const avatar = document.createElement('div');
  avatar.className = 'message-avatar';
  avatar.innerHTML = '<i class="fas fa-robot"></i>';
  
  botMsg.appendChild(avatar);
  botMsg.appendChild(messageContent);
  messages.appendChild(botMsg);
  
  messages.scrollTop = messages.scrollHeight;
};

// Initialize with FAQ categories in the initial message
setTimeout(() => {
  const initialContainer = messages.querySelector('.faq-container');
  if (initialContainer) {
    const categories = renderFAQCategories();
    initialContainer.innerHTML = categories.innerHTML;
    
    // Attach event listeners
    initialContainer.querySelectorAll('.faq-category-card').forEach((card, index) => {
      card.addEventListener('click', () => {
        const category = chatbotFAQ.chatbot_faq[index];
        if (category.sub_faqs && category.sub_faqs.length > 0) {
          // Clear current view and show sub-FAQs
          clearCurrentFAQView();
          currentView = { type: 'sub-faqs', category: category };
          renderSubFAQs(category);
        } else if (category.answer) {
          // Clear current view and show answer
          clearCurrentFAQView();
          currentView = { type: 'answer', question: category.question, answer: category.answer };
          showFAQAnswer(category.question, category.answer);
        }
      });
    });
  }
}, 100);

// Check session status and initialize agent mode if needed
async function checkSessionStatus() {
  const sessionId = getOrCreateSessionId();
  if (!sessionId) {
    return;
  }

  try {
    // Check session status via chat API (it will return session status)
    const resp = await fetch('api/chat.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ 
        question: '', // Empty question just to check status
        session_id: sessionId,
        history: []
      }),
    });

    if (resp.ok) {
      const data = await resp.json();
      
      // Check if session is in agent mode
      if (data.agent_requested === true) {
        // Session is requesting or connected to agent
        // Reset state for fresh connection
        agentMode.active = true;
        agentMode.connected = false;
        agentMode.lastMessageId = 0; // Reset to avoid showing old messages
        showConnectingToAgent();
        startAgentPolling();
      } else {
        // Session is in normal bot mode
        agentMode.active = false;
        agentMode.connected = false;
        agentMode.lastMessageId = 0; // Reset
        stopAgentPolling();
      }
    }
  } catch (err) {
    // Silently fail - assume normal bot mode
    agentMode.active = false;
    agentMode.connected = false;
    agentMode.lastMessageId = 0; // Reset
  }
}

// Open chatbot
chatbotIcon.addEventListener('click', () => {
  chatbotBox.style.display = 'flex';
  userInput.focus();
  
  // Ensure session ID exists when chatbot opens
  getOrCreateSessionId();
  
  // Check session status to initialize agent mode correctly
  checkSessionStatus();
});

// Close chatbot
closeBtn.addEventListener('click', () => {
  chatbotBox.style.display = 'none';
});

function escapeHtml(text) {
  return String(text || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function formatAnswerText(answer) {
  const safe = escapeHtml(answer);
  return safe.replace(/\n/g, '<br>');
}

// Function to display bot answer
function displayBotAnswer(answer) {
  const botMsg = document.createElement('div');
  botMsg.className = 'message bot';
  botMsg.innerHTML = `
    <div class="message-avatar">
      <i class="fas fa-robot"></i>
    </div>
    <div class="message-content">
      <div class="faq-answer">${formatAnswerText(answer)}</div>
    </div>
  `;
  messages.appendChild(botMsg);
  messages.scrollTop = messages.scrollHeight;
}

async function askChatApi(question) {
  // Don't call Gemini if agent mode is active
  if (agentMode.active) {
    return { answer: '', agent_requested: false };
  }

  // Get or create session ID for this chat request
  const sessionId = getOrCreateSessionId();
  
  const resp = await fetch('api/chat.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ 
      question,
      session_id: sessionId,
      history: chatHistory
    }),
  });

  let data = null;
  try {
    data = await resp.json();
  } catch (parseError) {
    // If JSON parse fails, try to get error message from response text
    const text = await resp.text().catch(() => '');
    throw new Error(`Invalid response from server: ${text || 'Unknown error'}`);
  }

  if (!resp.ok) {
    const errMsg =
      (data && (data.error || data.message)) ||
      `Request failed (${resp.status})`;
    throw new Error(errMsg);
  }

  // Ensure data is an object
  if (!data || typeof data !== 'object') {
    throw new Error('Invalid response format from server');
  }

  const answer = (data.answer && typeof data.answer === 'string') ? data.answer : '';
  const agentRequested = (data.agent_requested === true);
  return { answer, agent_requested: agentRequested };
}

/**
 * Display agent message with distinct UI
 */
function displayAgentMessage(message) {
  const agentMsg = document.createElement('div');
  agentMsg.className = 'message agent';
  agentMsg.innerHTML = `
    <div class="message-avatar">
      <i class="fas fa-user-tie"></i>
    </div>
    <div class="message-content">
      <div class="agent-label">${escapeHtml(currentAgentName || 'Agent')}</div>
      <div class="faq-answer">${formatAnswerText(message)}</div>
    </div>
  `;
  messages.appendChild(agentMsg);
  messages.scrollTop = messages.scrollHeight;
}

/**
 * Display system message to user
 */
function displaySystemMessage(message) {
  const systemMsg = document.createElement('div');
  systemMsg.className = 'message system';
  systemMsg.innerHTML = `
    <div class="message-avatar">
      <i class="fas fa-info-circle"></i>
    </div>
    <div class="message-content">
      <div class="faq-answer">${formatAnswerText(message)}</div>
    </div>
  `;
  messages.appendChild(systemMsg);
  messages.scrollTop = messages.scrollHeight;
}

/**
 * Show "Connecting to agent..." message
 * This was originally a temporary UI message. We now rely on the
 * system message stored in the database, so this function should
 * NOT render any visible message to avoid duplicates.
 */
function showConnectingToAgent() {
  // Keep the function for compatibility, but do nothing visually.
  // The real "Connecting you to an agent..." message will come from
  // the backend as a system message and be displayed via polling.
}

/**
 * Update connecting message to show agent connected
 */
function showAgentConnected() {
  const connectingMsg = document.getElementById('agent-connecting-msg');
  // We no longer want to show a separate "Agent connected" banner to the user.
  // If a temporary "connecting" message exists, remove it and let the
  // normal system/agent messages from the backend be shown via polling.
  if (connectingMsg) {
    connectingMsg.remove();
  }
}

/**
 * Poll for new agent messages
 * IMPORTANT: Only polls if session status is agent_connected or agent_requested
 */
async function pollForAgentMessages() {
  if (!agentMode.active) {
    return;
  }

  const sessionId = getOrCreateSessionId();
  if (!sessionId) {
    return;
  }

  try {
    const resp = await fetch(`api/chat/poll.php?session_id=${encodeURIComponent(sessionId)}&last_message_id=${agentMode.lastMessageId}`);
    if (!resp.ok) {
      // If poll fails, check if session is still in agent mode
      // If not, stop polling
      return;
    }

    const data = await resp.json();
    if (data && Array.isArray(data.messages)) {
      for (const msg of data.messages) {
        // Update lastMessageId for all messages to track position
        agentMode.lastMessageId = Math.max(agentMode.lastMessageId, msg.id || 0);
        
        if (msg.sender === 'agent') {
          displayAgentMessage(msg.message);
          // Mark as connected when first agent message arrives
          if (!agentMode.connected) {
            agentMode.connected = true;
            showAgentConnected();
          }
        } else if (msg.sender === 'system') {
          // Remove temporary "connecting" message if we get the real system message
          if (msg.message.includes('Connecting you to an agent') || msg.message.includes('Connecting')) {
            const existingConnecting = document.getElementById('agent-connecting-msg');
            if (existingConnecting) {
              existingConnecting.remove();
            }
          }
          
          // Display system messages to user
          displaySystemMessage(msg.message);
          
          // Check for agent connected status in system messages
          // Only show "Agent connected" handling if message contains "Agent" and "has connected"
          if (msg.message.includes('Agent') && msg.message.includes('has connected')) {
            // Try to extract agent name from pattern: "Agent John Doe has connected..."
            const match = msg.message.match(/Agent\s+(.+?)\s+has connected/i);
            if (match && match[1]) {
              currentAgentName = match[1].trim();
            } else {
              currentAgentName = 'Agent';
            }
            agentMode.connected = true;
            showAgentConnected();
          }
          // Check for agent disconnection - reset agent mode so user can continue with bot/AI
          if (msg.message.includes('disconnected') || msg.message.includes('continue chatting') || msg.message.includes('continue with')) {
            agentMode.active = false;
            agentMode.connected = false;
            currentAgentName = 'Agent'; // Reset label after disconnect
            stopAgentPolling();
            // Reset lastMessageId so next connection starts fresh
            agentMode.lastMessageId = 0;
          }
        }
      }
    }
  } catch (err) {
    // Silently fail - polling will retry
    // Don't log unless in debug mode
  }
}

/**
 * Get the maximum message ID from messages already displayed in the DOM
 * This helps avoid showing duplicate messages when reconnecting
 */
function getMaxMessageIdFromDOM() {
  // We don't store message IDs in DOM, so we'll use a different approach:
  // Track the timestamp of the last message we've seen
  // For now, return 0 and let the first poll establish the baseline
  return 0;
}

/**
 * Start polling for agent messages
 * Initializes lastMessageId properly to avoid showing old messages on reconnect
 */
async function startAgentPolling() {
  if (agentMode.pollInterval) {
    clearInterval(agentMode.pollInterval);
  }
  
  // When starting agent mode, we need to establish a baseline
  // Make an initial poll to get current messages and set lastMessageId to the max
  // This ensures we only get NEW messages going forward, not old ones
  const sessionId = getOrCreateSessionId();
  if (sessionId) {
    try {
      // First poll: get current state and find max message ID
      // We use lastMessageId=0 to get all current messages, but we only use them to establish baseline
      const resp = await fetch(`api/chat/poll.php?session_id=${encodeURIComponent(sessionId)}&last_message_id=0`);
      if (resp.ok) {
        const data = await resp.json();
        if (data && Array.isArray(data.messages) && data.messages.length > 0) {
          // Set lastMessageId to the max ID from this initial poll
          // This ensures subsequent polls only get NEW messages that come after this point
          agentMode.lastMessageId = Math.max(...data.messages.map(m => m.id || 0), 0);
          
          // Check if agent is already connected based on system messages
          // But don't display these messages - they're already in the chat history
          for (const msg of data.messages) {
            if (msg.sender === 'system') {
              // Check for agent connected status - update state but don't show duplicate message
              if (msg.message.includes('Agent') && msg.message.includes('has connected')) {
                agentMode.connected = true;
                // Only show "Agent connected" if we haven't shown it yet
                const existingConnecting = document.getElementById('agent-connecting-msg');
                if (existingConnecting) {
                  showAgentConnected();
                }
              }
            } else if (msg.sender === 'agent') {
              // Agent message exists - mark as connected
              if (!agentMode.connected) {
                agentMode.connected = true;
                showAgentConnected();
              }
            }
          }
        } else {
          // No messages yet, start from 0
          agentMode.lastMessageId = 0;
        }
      } else {
        // Poll failed, start from 0
        agentMode.lastMessageId = 0;
      }
    } catch (err) {
      // Error on initial poll, start from 0
      agentMode.lastMessageId = 0;
    }
  } else {
    agentMode.lastMessageId = 0;
  }
  
  // Poll every 2 seconds for NEW messages only
  agentMode.pollInterval = setInterval(pollForAgentMessages, 2000);
}

/**
 * Stop polling for agent messages
 */
function stopAgentPolling() {
  if (agentMode.pollInterval) {
    clearInterval(agentMode.pollInterval);
    agentMode.pollInterval = null;
  }
}

// Send message
sendBtn.addEventListener('click', async () => {
  const text = userInput.value.trim();
  if(text === '') return;

  // Add user message
  const userMsg = document.createElement('div');
  userMsg.className = 'message user';
  userMsg.innerHTML = `
    <div class="message-avatar">
      <i class="fas fa-user"></i>
    </div>
    <div class="message-content">${text}</div>
  `;
  messages.appendChild(userMsg);

  // Update chat history with user message
  chatHistory.push({
    role: 'user',
    content: text
  });

  // Clear input
  userInput.value = '';

  // Scroll to bottom
  messages.scrollTop = messages.scrollHeight;

  // Show a lightweight "typing" indicator while we call the API
  const typingMsg = document.createElement('div');
  typingMsg.className = 'message bot';
  typingMsg.innerHTML = `
    <div class="message-avatar">
      <i class="fas fa-robot"></i>
    </div>
    <div class="message-content">
      <div class="faq-answer">Typing...</div>
    </div>
  `;
  messages.appendChild(typingMsg);
  messages.scrollTop = messages.scrollHeight;

  // Prevent double-send while awaiting response
  sendBtn.disabled = true;

  try {
    // If agent mode is active, still send message to backend for logging
    // but don't expect a Gemini response
    if (agentMode.active) {
      // Send message to backend for logging (it will be logged but Gemini won't be called)
      const sessionId = getOrCreateSessionId();
      try {
        await fetch('api/chat.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ 
            question: text,
            session_id: sessionId,
            history: [] // Don't send history in agent mode
          }),
        });
      } catch (err) {
        // Silently fail - message logging is non-critical
      }
      
      typingMsg.remove();
      // Just wait for agent response via polling
      return;
    }

    const result = await askChatApi(text);
    typingMsg.remove();

    // Ensure result is an object
    if (!result || typeof result !== 'object') {
      throw new Error('Invalid response from chat API');
    }

    // Check if this is an agent request
    if (result.agent_requested === true) {
      // Reset state for fresh agent connection
      agentMode.active = true;
      agentMode.connected = false;
      agentMode.lastMessageId = 0; // Reset to avoid showing old messages
      showConnectingToAgent();
      startAgentPolling();
      
      // Still display the answer message
      if (result.answer) {
        displayBotAnswer(result.answer);
      }
      return;
    }

    const answer = result.answer ? result.answer.trim() : '';
    if (answer) {
      displayBotAnswer(answer);
      // Update chat history with assistant response
      chatHistory.push({
        role: 'assistant',
        content: answer
      });
    } else {
      const botMsg = document.createElement('div');
      botMsg.className = 'message bot';
      botMsg.innerHTML = `
        <div class="message-avatar">
          <i class="fas fa-robot"></i>
        </div>
        <div class="message-content">
          <div>I couldn't find a specific answer to your question. Here are some topics that might help:</div>
        </div>
      `;
      messages.appendChild(botMsg);
      showFAQCategories();
    }
  } catch (err) {
    typingMsg.remove();
    const botMsg = document.createElement('div');
    botMsg.className = 'message bot';
    botMsg.innerHTML = `
      <div class="message-avatar">
        <i class="fas fa-robot"></i>
      </div>
      <div class="message-content">
        <div>Sorry — I couldn't reach the chat API. ${escapeHtml(err && err.message ? err.message : 'Unknown error')}</div>
      </div>
    `;
    messages.appendChild(botMsg);
    messages.scrollTop = messages.scrollHeight;
  } finally {
    sendBtn.disabled = false;
  }
});

// Send message on Enter
userInput.addEventListener('keypress', (e) => {
  if(e.key === 'Enter') sendBtn.click();
});

// Initialize session ID on page load (for persistence across page refresh)
getOrCreateSessionId();

// Initialize last message ID
initializeLastMessageId();
