<?php
/**
 * Database Connections & Environment Config Management
 * Centralizes MySQL, MongoDB, and Redis connections.
 */

// Loader for .env configuration files
function loadEnvironmentVariables($dirPath) {
    $envPath = rtrim($dirPath, '/') . '/.env';
    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split on first '='
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);

            // Strip quotes if any
            $val = trim($val, "\"'");

            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Load env variables from workspace root
loadEnvironmentVariables(dirname(__DIR__, 2));

class Database {
    private static $mysqlInstance = null;
    private static $mongoInstance = null;
    private static $redisInstance = null;

    /**
     * Get MySQL Connection (PDO)
     */
    public static function getMySQL() {
        if (self::$mysqlInstance === null) {
            $mysqlUrl = getenv('MYSQL_URL') ?: getenv('DB_URL');
            if (!empty($mysqlUrl)) {
                $dbparts = parse_url($mysqlUrl);
                $host = isset($dbparts['host']) ? $dbparts['host'] : '127.0.0.1';
                $port = isset($dbparts['port']) ? $dbparts['port'] : '3306';
                $user = isset($dbparts['user']) ? $dbparts['user'] : 'root';
                $password = isset($dbparts['pass']) ? $dbparts['pass'] : '';
                $dbName = isset($dbparts['path']) ? ltrim($dbparts['path'], '/') : 'guvi_internship';
            } else {
                $host = getenv('DB_HOST') ?: '127.0.0.1';
                $dbName = getenv('DB_NAME') ?: 'guvi_internship';
                $user = getenv('DB_USER') ?: 'root';
                $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
                $port = getenv('DB_PORT') ?: '3306';
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$mysqlInstance = new PDO($dsn, $user, $password, $options);
            } catch (PDOException $e) {
                // Ensure credentials aren't leaked in typical stack trace
                throw new RuntimeException("Database connection failed. Please check configurations.");
            }
        }
        return self::$mysqlInstance;
    }

    /**
     * Get MongoDB Connection Manager (native driver)
     */
    public static function getMongo() {
        if (self::$mongoInstance === null) {
            $uri = getenv('MONGO_URI');
            if (empty($uri)) {
                $host = getenv('MONGO_HOST') ?: '127.0.0.1';
                $port = getenv('MONGO_PORT') ?: '27017';
                $user = getenv('MONGO_USER');
                $pass = getenv('MONGO_PASS');
                
                $authString = "";
                if (!empty($user) && !empty($pass)) {
                    $authString = "{$user}:" . urlencode($pass) . "@";
                }

                $uri = "mongodb://{$authString}{$host}:{$port}";
            }
            
            try {
                self::$mongoInstance = new MongoDB\Driver\Manager($uri);
            } catch (Exception $e) {
                throw new RuntimeException("No-SQL Database connection failure.");
            }
        }
        return self::$mongoInstance;
    }

    /**
     * Get Redis Connection (native client class)
     */
    public static function getRedis() {
        if (self::$redisInstance === null) {
            $redisUrl = getenv('REDIS_URL');
            if (!empty($redisUrl)) {
                if (preg_match('/^redis:\/\/(?:([^:]*):([^@]*)@)?([^:\/]+)(?::([0-9]+))?(?:[\/|\?].*)?$/', $redisUrl, $matches)) {
                    $host = !empty($matches[3]) ? $matches[3] : '127.0.0.1';
                    $port = !empty($matches[4]) ? (int)$matches[4] : 6379;
                    $auth = !empty($matches[2]) ? $matches[2] : '';
                } else {
                    $host = '127.0.0.1';
                    $port = 6379;
                    $auth = '';
                }
            } else {
                $host = getenv('REDIS_HOST') ?: '127.0.0.1';
                $port = (int)(getenv('REDIS_PORT') ?: 6379);
                $auth = getenv('REDIS_AUTH');
            }

            try {
                $redis = new Redis();
                $redis->connect($host, $port);
                
                if (!empty($auth)) {
                    $redis->auth($auth);
                }

                self::$redisInstance = $redis;
            } catch (Exception $e) {
                throw new RuntimeException("In-memory cache connection failure.");
            }
        }
        return self::$redisInstance;
    }
}
