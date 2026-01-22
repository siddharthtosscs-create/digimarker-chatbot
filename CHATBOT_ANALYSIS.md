## DigiMarker Chatbot – Detailed Architecture & Gemini Integration

This document explains **how the DigiMarker chatbot is built**, how it **answers user questions and FAQ “tags”**, and **how the Gemini API is used behind the scenes**.

---

## 1. High‑Level Overview

- **Frontend (browser)**
  - Renders the **chat widget UI** (`index.php` + `styles.css`).
  - Handles **FAQ category browsing** and **chat input/output** (`script.js`).
  - Sends the user’s message to the backend via `fetch('api/chat.php', ...)`.

- **Backend (PHP)**
  - `api/chat.php` is the **main chat endpoint**.
  - Validates the request, loads FAQ context from `data/chatbot_faq.json` (for FAQ mode), builds a **prompt**, and calls **Google Gemini** via HTTP.
  - Parses Gemini’s response and returns a simple JSON: `{ "answer": "..." }`.

- **Gemini API (Google Generative Language)**
  - Receives a structured **generateContent** request.
  - Uses the **FAQ context + user question** to generate a natural language answer.
  - Returns the answer text in a structured JSON format.

The chatbot therefore has **two main “brains”**:
1. A **local FAQ navigation layer** (predefined questions/answers).
2. A **Gemini‑powered answer layer** (LLM response based on the FAQ JSON + user question).

---

## 2. Frontend Chatbot: UI, FAQ Tags, and User Messages

### 2.1. Chat Widget Placement (`index.php`)

- The chat UI is added at the bottom of the page:
  - `#chatbot-icon`: small floating button that opens the chat.
  - `#chatbot-box`: the chat window with:
    - Header (`DigiMarker Assistant`, phone button).
    - Messages area (`#chatbot-messages`).
    - Input bar with text field + send button (`#chatbot-input`).

This HTML hosts the **visual container** for the chatbot. All behavior is implemented in `script.js`.

### 2.2. Local FAQ Data and “Tags” (`script.js`)

- At the top of the chatbot section in `script.js`, there is a large JavaScript object:
  - `const chatbotFAQ = { "chatbot_faq": [ ... ] };`
  - Structure:
    - **Top‑level categories** with:
      - `id`
      - `question` → this is the **category title / tag**, e.g. `"Login & Access"`, `"Marking & Tools"`, `"Technical Issues"`.
      - `answer` (sometimes empty)
      - `sub_faqs`: an array of detailed Q&A entries.
    - Each `sub_faq` has:
      - `id`
      - `question` → the specific question (FAQ tag the user sees).
      - `answer` → the full answer text.

These act as the **FAQ “tags”** that the user can click, independent of Gemini. They are rendered purely on the frontend.

### 2.3. Rendering FAQ Categories and Sub‑FAQs

- **State tracking:** `currentView` keeps track of whether the user is seeing:
  - `type: 'categories'` → list of FAQ categories.
  - `type: 'sub-faqs'` → list of questions inside a category.
  - `type: 'answer'` → a single answer.

- **Key functions:**
  - `renderFAQCategories()`
    - Loops over `chatbotFAQ.chatbot_faq`.
    - For each category:
      - Chooses an icon with `getCategoryIcon(question)`.
      - Creates a clickable **category card**.
      - On click:
        - If `sub_faqs` exist → shows **sub‑FAQ list**.
        - If only `answer` exists → directly shows the **answer**.
  - `renderSubFAQs(category)`
    - Displays all `sub_faqs` of a selected category as clickable items.
    - On click of a sub‑FAQ:
      - Shows the answer via `showFAQAnswer(question, answer)`.
  - `showFAQAnswer(question, answer)`
    - Renders the answer text inside a bot message bubble.
    - Uses `formatAnswerText()` → converts `\n` to `<br>` and escapes HTML.
  - `showFAQCategories()` and `goBack()`
    - Implement the **Back** navigation between:
      - Answer → Sub‑FAQs → Categories.

- **Initial behavior:**
  - When the widget loads, the greeting message in `index.php` includes an empty `.faq-container`.
  - After a `setTimeout`, `showFAQCategories()` populates this container with the clickable FAQ categories.

**Important:** This **FAQ browsing UX is fully local**. Clicking FAQ “tags” does not call Gemini. It simply shows pre‑written answers from `script.js`.

### 2.4. Sending a Free‑Text Chat Message

- Elements:
  - `sendBtn` → the send button.
  - `userInput` → text field for the user message.

- Event flow (`sendBtn.addEventListener('click', ...)`):
  1. Read `text = userInput.value.trim();`.
  2. If empty → return.
  3. Append a **user message bubble** to `#chatbot-messages`.
  4. Clear the input.
  5. Append a **“Typing…” bot message** as a loading indicator.
  6. Disable the send button (`sendBtn.disabled = true`).
  7. Call `askChatApi(text)` (this is where Gemini comes in).
  8. On success:
     - Remove “Typing…” message.
     - If `answer` is non‑empty → call `displayBotAnswer(answer)` to show Gemini’s response.
     - If `answer` is empty → show a fallback message and call `showFAQCategories()` again.
  9. On error:
     - Remove “Typing…” message.
     - Show a bot message saying the chat API could not be reached, including the error message.
  10. Finally, re‑enable the send button.

- **Enter key support:**
  - `userInput.addEventListener('keypress', (e) => { if(e.key === 'Enter') sendBtn.click(); });`

So from the browser’s perspective:
> User types something → frontend sends JSON to `api/chat.php` → waits for `answer` → displays it in the chat bubble.

---

## 3. Frontend → Backend: `askChatApi` Call

The function `askChatApi(question)` is the bridge from the browser to the Gemini‑powered backend.

- Implementation summary:
  - Sends a **POST** request to `api/chat.php`:
    - Headers: `{ 'Content-Type': 'application/json' }`.
    - Body:
      ```json
      { "question": "<user text>" }
      ```
    - No explicit `mode` is sent from the frontend, so the backend uses the default `"faq"` mode.
  - Waits for JSON response and parses it.
  - If `resp.ok` is false (non‑2xx):
    - Tries to read `data.error` or `data.message` from the JSON and throws an Error.
  - If successful:
    - Expects `data.answer` to be a string and returns it.

Frontend does **not** know anything about Gemini; it only knows:
- Endpoint: `api/chat.php`
- Request: `{ question }`
- Response: `{ answer }`

All AI logic is hidden behind `api/chat.php`.

---

## 4. Backend Chat Endpoint: `api/chat.php`

`api/chat.php` is designed as a **reusable, environment‑friendly chat API** with detailed diagnostics for hosting issues.

### 4.1. Request Format and Basic Validation

- **Expected HTTP method:** `POST` (anything else → `405 Method not allowed`).
- **CORS headers:** configured based on `DIGIMARKER_CHAT_ALLOWED_ORIGINS` environment variable.
- **Optional API key protection:**
  - If `DIGIMARKER_CHAT_API_KEY` is set:
    - Requests must send header `X-API-Key` matching this value.
    - Otherwise: `401 Unauthorized`.

- **Request body:**
  ```json
  {
    "question": "string",           // required
    "mode": "faq" | "general",      // optional (defaults to "faq")
    "history": [                    // optional
      { "role": "user", "content": "..." },
      { "role": "assistant", "content": "..." }
    ]
  }
  ```

- **Validation logic:**
  - Parses JSON → if invalid → `400 Invalid JSON body.`
  - `question`:
    - Must be non‑empty string.
    - If missing/empty → `400 Missing question.`
    - Truncated to a max of 2000 characters for safety.
  - `mode`:
    - Allowed: `"faq"` or `"general"`.
    - If anything else → `400 Invalid mode. Use "faq" or "general".`

### 4.2. Gemini API Key Configuration

- The API key is **never exposed to the frontend**. It is read only on the server:
  1. Environment variable: `GEMINI_API_KEY`.
  2. Local config file: `api/config.local.php` (returned array with `'GEMINI_API_KEY' => '...'`).

- If no valid key is found:
  - Responds with `500` and a detailed JSON describing:
    - Whether the config file exists and is readable.
    - Whether environment variable is set.
    - Hints on how to configure the key.

### 4.3. FAQ Context Loading (for `mode === "faq"`)

For FAQ mode, `api/chat.php` builds a **text context** from the server‑side JSON file `data/chatbot_faq.json`. This is **separate from the frontend’s `chatbotFAQ` object**, but structured similarly.

- It tries multiple possible paths to locate `chatbot_faq.json` (to handle different hosting setups):
  - `../data/chatbot_faq.json` relative to `api/chat.php`.
  - Paths based on `DOCUMENT_ROOT`.
  - Other relative combinations.

- If none of the paths work:
  - Returns `500` with detailed diagnostics of each tested path.

- Once file is found:
  - Reads it and decodes JSON.
  - Validates that:
    - JSON parses correctly.
    - `chatbot_faq` array is present.
  - If invalid → `500` with JSON error details.

- Then builds a **plain text FAQ context string**:
  - For each item in `chatbot_faq`:
    - Adds `Q: <question>\nA: <answer>` lines.
    - Also iterates over `sub_faqs` to add similar lines.
  - All blocks are joined with blank lines.
  - Long context strings are truncated to ~20,000 characters.

This text is what Gemini receives as **FAQ knowledge base** in `faq` mode.

### 4.4. System Instruction and Conversation History

- **System instruction (`systemInstruction` field in Gemini payload):**
  - For `mode === "faq"`:
    - Tells Gemini:
      - *“Answer ONLY using the provided FAQ context.”*
      - If the answer is not in context, reply exactly:
        - `"I don't have that information in the DigiMarker FAQ."`
      - Keep the answer short and step‑by‑step where needed.
  - For `mode === "general"`:
    - More open‑ended, but still:
      - Be accurate and concise.
      - If unsure, ask a clarifying question.

- **History usage:**
  - If `history` is provided:
    - Takes the **last 10 turns**.
    - Each turn must have `role` in `["user", "assistant"]` and non‑empty `content`.
    - Each `content` is truncated to 5000 characters.
    - These turns are converted to Gemini **content objects** and sent before the current question.

### 4.5. Building the User Content for Gemini

- In `faq` mode:
  - `userText` is constructed as:
    ```text
    FAQ CONTEXT:
    <all the Q/A pairs from chatbot_faq.json>

    USER QUESTION:
    <user's question>
    ```
  - This means Gemini **sees both the FAQ corpus and the user’s query** in a single message.

- In `general` mode:
  - `userText` is simply:
    ```
    <user's question>
    ```

- Then a final `contents` array is built for Gemini:
  - (`history` turns, if any)
  - + one last content with:
    - `role: "user"`
    - `parts: [{ "text": userText }]`

### 4.6. Model Selection Strategy

- `DIGIMARKER_GEMINI_MODEL` can be set via:
  - Environment variable.
  - Or `config.local.php`.
- If set, that exact model ID is used.
- Otherwise, the code tries a list of **candidate model IDs**, such as:
  - `gemini-2.0-flash`
  - `gemini-2.0-flash-lite`
  - `gemini-1.5-flash-latest`
  - `gemini-1.5-pro-latest`
  - …etc.

The API attempts each candidate in order until one works or it runs out of options.

### 4.7. Gemini Request Payload

The final payload sent to Gemini looks like:

```json
{
  "contents": [
    // optional history messages...
    {
      "role": "user",
      "parts": [{ "text": "FAQ CONTEXT:\n...\n\nUSER QUESTION:\n..." }]
    }
  ],
  "systemInstruction": {
    "parts": [
      { "text": "You are DigiMarker Assistant. Answer ONLY using the provided FAQ context. ..." }
    ]
  },
  "generationConfig": {
    "temperature": 0.2,
    "maxOutputTokens": 500
  }
}
```

- `temperature`:
  - Lower (0.2) in FAQ mode → more deterministic, less creative.
  - Slightly higher in general mode (0.4) for more natural responses.
- `maxOutputTokens` limits the length of the response.

### 4.8. Calling the Gemini API (`callGemini` function)

- Endpoint pattern:
  - `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key=<API_KEY>`
- Uses PHP cURL:
  - `CURLOPT_POST`, `CURLOPT_RETURNTRANSFER`, JSON headers and body.
  - SSL settings with CA bundle detection where possible.
  - Timeouts and redirect handling.
  - Custom `User-Agent: DigiMarker-ChatBot/1.0`.

- Error handling:
  - If cURL fails:
    - Returns detailed diagnostics:
      - `curl_errno`, `curl_errno_name`, `details`, and suggestions for common hosting issues (SSL, firewall, DNS, timeout).
  - If HTTP status is not 2xx:
    - Returns an error object with `type: "http"`, `status`, and raw body.
  - If response JSON does not contain a valid `candidates[0].content.parts[0].text`:
    - Treats it as an **empty** response and returns `type: "empty"`.

- Successful case:
  - Extracts the text string from first candidate and returns:
    - `{ "ok": true, "text": "<answer text>", "model": "<model id>" }`.

### 4.9. Returning the Final Answer to the Frontend

- The main loop in `chat.php` tries each candidate model:
  - On first successful result (`ok === true`):
    - Sends JSON:
      ```json
      { "answer": "<Gemini answer text>" }
      ```
    - and exits.
  - If model 404s (not found for this key):
    - Tries the next candidate.
  - For transport, empty, or other errors:
    - Responds with `502` and a JSON describing the error.
  - If no model works:
    - Responds with `502` and `"No supported Gemini model found for this API key."`.

The frontend `askChatApi()` receives this `{ answer }`, and `displayBotAnswer()` renders it in the chat.

---

## 5. Models Endpoint: `api/models.php` (optional helper)

`api/models.php` is a separate endpoint that can be used to **inspect which Gemini models are available** for your API key.

- HTTP method: `GET`.
- Uses the same CORS + optional `X-API-Key` protection as `chat.php`.
- Reads `GEMINI_API_KEY` from environment or `config.local.php`.
- Calls:
  - `https://generativelanguage.googleapis.com/v1beta/models?key=<API_KEY>`.
- Filters models that support `"generateContent"` and returns:
  ```json
  {
    "models": [
      {
        "id": "gemini-1.5-flash",
        "name": "models/gemini-1.5-flash",
        "supportedGenerationMethods": ["generateContent", ...]
      },
      ...
    ],
    "count": <number>
  }
  ```

This endpoint is mostly for **diagnostics / configuration**, not used directly by the chat widget.

---

## 6. How the Chatbot Answers “Tags” vs. Free‑Text Chats

### 6.1. FAQ “Tags” (Click‑Based)

- When a user clicks on a **FAQ category** or a **question** in the chat interface:
  - The answer is directly read from the **frontend’s `chatbotFAQ` object in `script.js`**.
  - No API call is made.
  - The answer is fully deterministic and exactly what you wrote in the JavaScript.

So these FAQ interactions are **static** and **Gemini is not involved** in rendering those predefined answers.

### 6.2. Free‑Text Chats (Type a Question)

- When the user **types a question** (for example: “How do I log in to DigiMarker?”):
  1. Frontend calls `askChatApi(text)` → POST to `api/chat.php`.
  2. Backend:
     - Uses `mode = "faq"` by default:
       - Loads `data/chatbot_faq.json` and constructs a big FAQ context.
       - Builds a prompt “FAQ CONTEXT + USER QUESTION”.
       - Sets a strict system instruction to only answer from this context.
     - Calls Gemini’s `generateContent` with this payload.
  3. Gemini:
     - Reads the context, finds the most relevant Q&A, and generates a **natural language answer** summarizing that information.
  4. Backend:
     - Extracts Gemini’s answer text into `answer`.
     - Returns `{ "answer": "..." }` to the frontend.
  5. Frontend:
     - Displays this `answer` in a bot bubble.

So free‑text chat is **Gemini‑driven**, but **constrained** by your FAQ JSON in `data/chatbot_faq.json` when in FAQ mode.

---

## 7. Role of the Gemini API – Conceptual Summary

Gemini’s role can be summarized as:

- **Input it receives (FAQ mode):**
  - A large chunk of text containing:
    - All your FAQ questions and answers.
    - The specific question the user asked.
  - A system message instructing it to:
    - Answer only from that FAQ information.
    - Say it doesn’t know if the context doesn’t contain the answer.

- **Processing:**
  - Uses a large language model to:
    - Understand the user’s question semantically (“meaning‑based”, not just keyword).
    - Find the most relevant Q&A entries from the provided FAQ context.
    - Compose a short, user‑friendly answer.

- **Output:**
  - A single answer string, which the backend wraps into `{ "answer": "<text>" }`.

In other words:
> **FAQ JSON + System Instructions + User Question → Gemini → Natural‑language answer returned to the chat UI.**

---

## 8. Where to Adjust Behavior

- **Edit FAQ content (both click‑based and Gemini context):**
  - For frontend click FAQ:
    - Update `chatbotFAQ` in `script.js`.
  - For Gemini context:
    - Update `data/chatbot_faq.json`.

- **Control model and response style:**
  - Set `DIGIMARKER_GEMINI_MODEL` to a specific model ID.
  - Adjust `generationConfig.temperature` and `maxOutputTokens` in `api/chat.php`.
  - Change the `systemInstruction` text to enforce stricter or looser behavior.

- **Control modes (FAQ vs general):**
  - Extend the frontend `askChatApi` call to send `"mode": "general"` when you want open‑ended answers.
  - Backend already supports `"general"` mode; only the prompt and temperature differ.

---

## 9. End‑to‑End Flow Recap

1. **User opens site** → Sees chat icon.
2. **User clicks icon** → `#chatbot-box` opens with greeting + FAQ categories.
3. **User interacts:**
   - If they click a FAQ “tag” → local `script.js` shows hardcoded answer (no Gemini).
   - If they type a question → frontend posts JSON `{ question }` to `api/chat.php`.
4. **Backend (`api/chat.php`):**
   - Validates request and loads Gemini API key.
   - In FAQ mode: loads `data/chatbot_faq.json` and builds FAQ context.
   - Builds Gemini payload (history + systemInstruction + userText).
   - Calls Gemini `generateContent` and extracts the answer.
   - Returns `{ answer }` to the browser.
5. **Frontend:**
   - Receives `answer` and renders it as a bot message.

This is the **complete pipeline** of how the DigiMarker chatbot is built and how the Gemini API helps answer both structured FAQ tags and free‑text chat questions.






