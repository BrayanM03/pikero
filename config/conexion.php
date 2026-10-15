<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

class Database {
    private $pdo;

    public function __construct(
        $host = null,
        $dbname = null,
        $user = null,
        $pass = null
    ) {
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile) && class_exists('Dotenv\\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->safeLoad();
        }

        $host = $host ?? ($_ENV['DB_HOST'] ?? 'localhost');
        $dbname = $dbname ?? ($_ENV['DB_NAME'] ?? 'pikero');
        $user = $user ?? ($_ENV['DB_USER'] ?? 'root');
        $pass = $pass ?? ($_ENV['DB_PASS'] ?? 'root');

        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $this->pdo = new PDO($dsn, $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die(json_encode(['estado' => 'error', 'mensaje' => $e->getMessage()]));
        }
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function insert($table, $data) {
        $cols = implode(", ", array_keys($data));
        $vals = implode(", ", array_fill(0, count($data), "?"));
        $sql = "INSERT INTO $table ($cols) VALUES ($vals)";
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $params = []) {
        $fields = implode(" = ?, ", array_keys($data)) . " = ?";
        $sql = "UPDATE $table SET $fields WHERE $where";
        $stmt = $this->query($sql, array_merge(array_values($data), $params));
        return $stmt->rowCount();
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params);
    }

    public function select($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count($table, $where = "1", $params = []) {
        $sql = "SELECT COUNT(*) AS total FROM $table WHERE $where";
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function sum($table, $col, $where = "1", $params = []) {
        $sql = "SELECT SUM($col) AS total FROM $table WHERE $where";
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function cancel($table, $id_reg = []) {
        $data = ['estatus' => 0];
        return $this->update($table, $data, 'id = ?', [$id_reg]);
    }

    public function uncancel($table, $id_reg = []) {
        $data = ['estatus' => 1];
        return $this->update($table, $data, 'id = ?', [$id_reg]);
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }
}
