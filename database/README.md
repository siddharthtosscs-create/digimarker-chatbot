# Database Setup for DigiMarker Chatbot

This directory contains the database schema for chat session persistence.

## Quick Setup

### 1. Create Database

```sql
CREATE DATABASE digimarker_chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Run Schema

Execute the SQL script to create the required tables:

```bash
mysql -u your_username -p digimarker_chat < schema.sql
```

Or import via phpMyAdmin:
- Select your database
- Go to "Import" tab
- Choose `schema.sql` file
- Click "Go"

### 3. Configure Database Credentials

Update `config/config.local.php` with your database credentials:

```php
return [
  'GEMINI_API_KEY' => 'your_gemini_key',
  'DB_HOST' => 'localhost',
  'DB_NAME' => 'digimarker_chat',
  'DB_USER' => 'your_db_username',
  'DB_PASS' => 'your_db_password',
  'DB_CHARSET' => 'utf8mb4',
];
```

## Tables

### `chat_sessions`
Stores chat session metadata:
- `id`: Auto-increment primary key
- `session_id`: Unique session identifier (UUID v4)
- `status`: Session status ('active' or 'closed')
- `created_at`: Session creation timestamp
- `updated_at`: Last update timestamp

### `chat_messages`
Stores all chat messages:
- `id`: Auto-increment primary key
- `session_id`: Links to chat_sessions.session_id
- `sender`: Message sender ('user', 'bot', or 'system')
- `message`: Message content (TEXT)
- `source`: Message source ('faq', 'gemini', or 'system')
- `created_at`: Message timestamp

## Notes

- The chatbot will continue to work even if the database is not configured (graceful degradation)
- Session IDs are generated client-side and stored in browser localStorage
- All database operations use prepared statements for security
- Foreign key constraint ensures referential integrity

