<?php
// ============================================================
// includes/db.php – Database Connection
// ============================================================

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHAR);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ];
        
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('Database Error: ' . $e->getMessage());
            } else {
                die('Service temporarily unavailable.');
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

function db() {
    return Database::getInstance()->getConnection();
}

function query($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetch($sql, $params = []) {
    return query($sql, $params)->fetch();
}

function fetchAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

function insert($table, $data) {
    $keys = array_keys($data);
    $placeholders = ':' . implode(', :', $keys);
    $sql = "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES ($placeholders)";
    $stmt = db()->prepare($sql);
    $stmt->execute($data);
    return db()->lastInsertId();
}

function update($table, $data, $where, $whereParams = []) {
    $set = [];
    foreach ($data as $key => $value) {
        $set[] = "$key = :$key";
    }
    $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";
    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge($data, $whereParams));
    return $stmt->rowCount();
}

function delete($table, $where, $params = []) {
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}
?>
