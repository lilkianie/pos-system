<?php
require_once __DIR__ . '/config.php';

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $name = DB_NAME;
    private $conn;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->name};charset=utf8mb4",
                $this->user,
                $this->pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            // Don't die immediately - let the page handle the error
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your database configuration and ensure the database 'pos_system' exists. Error: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            throw $e;
        }
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollback() {
        return $this->conn->rollback();
    }
}
