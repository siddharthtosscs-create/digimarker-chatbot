<?php
declare(strict_types=1);

/**
 * Database - PDO-based database connection helper
 * Provides secure database access using prepared statements
 */
class Database
{
  private static ?PDO $connection = null;
  private static array $config = [];

  /**
   * Initialize database configuration from config file or environment
   */
  private static function loadConfig(): void
  {
    if (!empty(self::$config)) {
      return;
    }

    // Try to load from config file
    $configFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.local.php';
    if (is_file($configFile)) {
      try {
        $loaded = require $configFile;
        if (is_array($loaded) && isset($loaded['DB_HOST'])) {
          self::$config = [
            'host' => (string)($loaded['DB_HOST'] ?? 'localhost'),
            'dbname' => (string)($loaded['DB_NAME'] ?? ''),
            'username' => (string)($loaded['DB_USER'] ?? ''),
            'password' => (string)($loaded['DB_PASS'] ?? ''),
            'charset' => (string)($loaded['DB_CHARSET'] ?? 'utf8mb4'),
          ];
          return;
        }
      } catch (Throwable $e) {
        error_log("Database config load error: " . $e->getMessage());
      }
    }

    // Fallback to environment variables
    self::$config = [
      'host' => (string)(getenv('DB_HOST') ?: 'localhost'),
      'dbname' => (string)(getenv('DB_NAME') ?: ''),
      'username' => (string)(getenv('DB_USER') ?: ''),
      'password' => (string)(getenv('DB_PASS') ?: ''),
      'charset' => (string)(getenv('DB_CHARSET') ?: 'utf8mb4'),
    ];
  }

  /**
   * Get PDO connection (singleton pattern)
   * @throws PDOException if connection fails
   */
  public static function getConnection(): PDO
  {
    if (self::$connection !== null) {
      return self::$connection;
    }

    self::loadConfig();

    if (empty(self::$config['dbname']) || empty(self::$config['username'])) {
      throw new PDOException('Database configuration is incomplete. Set DB_HOST, DB_NAME, DB_USER, and DB_PASS in config/config.local.php or environment variables.');
    }

    $dsn = sprintf(
      'mysql:host=%s;dbname=%s;charset=%s',
      self::$config['host'],
      self::$config['dbname'],
      self::$config['charset']
    );

    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
      PDO::ATTR_PERSISTENT => false,
    ];

    try {
      self::$connection = new PDO(
        $dsn,
        self::$config['username'],
        self::$config['password'],
        $options
      );
    } catch (PDOException $e) {
      error_log("Database connection failed: " . $e->getMessage());
      throw $e;
    }

    return self::$connection;
  }

  /**
   * Test database connection
   * @return bool True if connection successful
   */
  public static function testConnection(): bool
  {
    try {
      self::getConnection();
      return true;
    } catch (PDOException $e) {
      return false;
    }
  }

  /**
   * Close database connection
   */
  public static function closeConnection(): void
  {
    self::$connection = null;
  }
}

