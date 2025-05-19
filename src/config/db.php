<?php
// Disable error reporting for file downloads
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/db_error.log');

// Database configuration
$db_host = 'localhost';
$db_name = 'nexinvent';
$db_user = 'root';
$db_pass = '';

// Global PDO connection
$pdo = null;

// Function to get database connection
function getDBConnection() {
    global $pdo, $db_host, $db_name, $db_user, $db_pass;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $db_user, $db_pass, $options);
            
        } catch(PDOException $e) {
            error_log("Connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }
    
    return $pdo;
}

// Utility function to fetch all rows
function fetchAll($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Database error in fetchAll: " . $e->getMessage() . "\nSQL: $sql\nParams: " . print_r($params, true));
        throw new Exception("Database error occurred while fetching records");
    }
}

// Utility function to fetch a single row
function fetchRow($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Database error in fetchRow: " . $e->getMessage() . "\nSQL: $sql\nParams: " . print_r($params, true));
        throw new Exception("Database error occurred while fetching record");
    }
}

// Utility function to fetch a single value
function fetchValue($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : 0;
    } catch(PDOException $e) {
        error_log("Database error in fetchValue: " . $e->getMessage() . "\nSQL: $sql\nParams: " . print_r($params, true));
        throw new Exception("Database error occurred while fetching value");
    }
}

// Utility function to execute an SQL statement
function execute($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch(PDOException $e) {
        error_log("Database error in execute: " . $e->getMessage() . "\nSQL: $sql\nParams: " . print_r($params, true));
        throw new Exception("Database error occurred while executing statement");
    }
}

// Utility function to get last inserted ID
function lastInsertId() {
    try {
        $pdo = getDBConnection();
        return $pdo->lastInsertId();
    } catch(PDOException $e) {
        error_log("Database error in lastInsertId: " . $e->getMessage());
        throw new Exception("Database error occurred while getting last insert ID");
    }
}

// Helper function to insert data
function insert($table, $data) {
    try {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO $table (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        return $pdo->lastInsertId();
    } catch(PDOException $e) {
        error_log("Insert Error: " . $e->getMessage() . "\nTable: $table\nData: " . print_r($data, true));
        throw new Exception("Database error occurred while inserting data");
    }
}

// Helper function to update data
function update($table, $data, $where, $whereParams = []) {
    try {
        $fields = [];
        $values = [];
        
        foreach($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE $table SET " . implode(',', $fields) . " WHERE $where";
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($values, $whereParams));
        return $stmt->rowCount();
    } catch(PDOException $e) {
        error_log("Update Error: " . $e->getMessage() . "\nTable: $table\nData: " . print_r($data, true) . "\nWhere: $where");
        throw new Exception("Database error occurred while updating data");
    }
}

// Helper function to delete data
function delete($table, $where, $whereParams = []) {
    try {
        $sql = "DELETE FROM $table WHERE $where";
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($whereParams);
        return $stmt->rowCount();
    } catch(PDOException $e) {
        error_log("Delete Error: " . $e->getMessage() . "\nTable: $table\nWhere: $where");
        throw new Exception("Database error occurred while deleting data");
    }
}

// Helper function to get user notifications
function getUserNotifications($userId, $userRole, $limit = 10) {
    $sql = "SELECT * FROM notifications 
            WHERE for_role = ? AND is_read = 0 
            ORDER BY created_at DESC LIMIT ?";
    return fetchAll($sql, [$userRole, $limit]);
}

// Helper function to get dashboard statistics
function getDashboardStats($startDate = null, $endDate = null) {
    if (!$startDate) $startDate = date('Y-m-d', strtotime('-30 days'));
    if (!$endDate) $endDate = date('Y-m-d');
    
    $sql = "SELECT * FROM dashboard_stats 
            WHERE stat_date BETWEEN ? AND ? 
            ORDER BY stat_date DESC";
    return fetchAll($sql, [$startDate, $endDate]);
}

// Helper function to record attendance
function recordAttendance($employeeId, $status = 'present', $notes = '') {
    $data = [
        'employee_id' => $employeeId,
        'date' => date('Y-m-d'),
        'time_in' => date('Y-m-d H:i:s'),
        'status' => $status,
        'notes' => $notes
    ];
    return insert('attendance', $data);
}

// Helper function to update stock
function updateStock($productId, $quantity, $type, $userId, $referenceId = null, $notes = '') {
    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();
        
        // Insert stock movement
        $movement = [
            'product_id' => $productId,
            'user_id' => $userId,
            'quantity' => $quantity,
            'type' => $type,
            'reference_id' => $referenceId,
            'notes' => $notes
        ];
        insert('stock_movements', $movement);
        
        // Update product quantity
        $sql = "UPDATE products SET 
                in_stock_quantity = in_stock_quantity + ?,
                updated_by = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE product_id = ?";
        executeQuery($sql, [$quantity, $userId, $productId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Stock Update Error: " . $e->getMessage());
        throw $e;
    }
}

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/../../logs';
if (!file_exists($logsDir)) {
    mkdir($logsDir, 0777, true);
}