<?php

namespace DatabaseManager\Services;

use mysqli;
use Exception;

/**
 * Table Data Management Service
 */
class TableDataService
{
    private ConnectionService $connectionService;
    private const DEFAULT_PAGE_SIZE = 100;
    
    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }
    
    /**
     * Get table data with pagination and optional filtering
     */
    public function getTableData(
        string $database,
        string $table,
        array $filters = [],
        int $page = 1,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
        $result = [
            'columns' => [],
            'data' => [],
            'totalCount' => 0,
            'currentPage' => $page,
            'totalPages' => 0,
            'pageSize' => $pageSize
        ];
        
        if ($page < 1) {
            $page = 1;
        }
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if (!$conn) {
            return $result;
        }
        
        try {
            $conn->select_db($database);
            
            // Build WHERE clause from filters
            $whereClause = $this->buildWhereClause($conn, $filters);
            
            // Get total row count
            $countResult = $conn->query("SELECT COUNT(*) as total FROM `$table` $whereClause");
            if ($countResult) {
                $countRow = $countResult->fetch_assoc();
                $result['totalCount'] = (int)$countRow['total'];
                $countResult->free();
            }
            
            // Calculate pagination
            $offset = ($page - 1) * $pageSize;
            $result['totalPages'] = (int)ceil($result['totalCount'] / $pageSize);
            
            // Get table data
            $queryResult = $conn->query("SELECT * FROM `$table` $whereClause LIMIT $pageSize OFFSET $offset");
            
            if ($queryResult) {
                // Get column names using fetch_field() in a loop
                $fieldCount = $queryResult->field_count;
                for ($i = 0; $i < $fieldCount; $i++) {
                    $field = $queryResult->fetch_field();
                    if ($field) {
                        $result['columns'][] = $field->name;
                    }
                }
                
                // Reset result pointer to beginning
                $queryResult->data_seek(0);
                
                // Get rows
                while ($row = $queryResult->fetch_assoc()) {
                    $result['data'][] = $row;
                }
                
                $queryResult->free();
            }
            
            $conn->close();
        } catch (Exception $e) {
            throw new Exception('Error fetching table data: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Build WHERE clause from filter array
     */
    private function buildWhereClause(mysqli $conn, array $filters): string
    {
        $conditions = [];
        
        foreach ($filters as $column => $value) {
            if ($value !== '') {
                // Use parameterized approach with proper escaping
                $colEscaped = '`' . str_replace('`', '``', $column) . '`';
                $valEscaped = $conn->real_escape_string($value);
                $conditions[] = "$colEscaped LIKE '%$valEscaped%'";
            }
        }
        
        if (!empty($conditions)) {
            return " WHERE " . implode(" AND ", $conditions);
        }
        
        return '';
    }
    
    /**
     * Add a new row to a table
     */
    public function addRow(string $database, string $table, array $data): array
    {
        $result = ['success' => false, 'error' => null, 'insertId' => null];
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if (!$conn) {
            $result['error'] = 'No database connection';
            return $result;
        }
        
        try {
            $conn->select_db($database);
            
            $columns = array_keys($data);
            $values = array_values($data);
            
            $escapedColumns = array_map(function($col) use ($conn) {
                return '`' . $conn->real_escape_string($col) . '`';
            }, $columns);
            
            $escapedValues = array_map(function($val) use ($conn) {
                if ($val === null || strtoupper(trim($val)) === 'NULL') {
                    return 'NULL';
                } elseif (strtoupper(trim($val)) === 'CURRENT_TIMESTAMP') {
                    return 'CURRENT_TIMESTAMP';
                } else {
                    return "'" . $conn->real_escape_string($val) . "'";
                }
            }, $values);
            
            $sql = "INSERT INTO `$table` (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $escapedValues) . ")";
            
            if ($conn->query($sql)) {
                $result['success'] = true;
                $result['insertId'] = $conn->insert_id;
            } else {
                $result['error'] = 'Error adding row: ' . $conn->error;
            }
            
            $conn->close();
        } catch (Exception $e) {
            $result['error'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Update a row in a table
     */
    public function updateRow(
        string $database,
        string $table,
        array $data,
        array $whereConditions
    ): array {
        $result = ['success' => false, 'error' => null];
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if (!$conn) {
            $result['error'] = 'No database connection';
            return $result;
        }
        
        try {
            $conn->select_db($database);
            
            // Build SET clause
            $setParts = [];
            foreach ($data as $column => $value) {
                $colEscaped = $conn->real_escape_string($column);
                
                if ($value === null || strtoupper(trim($value)) === 'NULL') {
                    $setParts[] = "`$colEscaped` = NULL";
                } elseif (strtoupper(trim($value)) === 'CURRENT_TIMESTAMP') {
                    $setParts[] = "`$colEscaped` = CURRENT_TIMESTAMP";
                } else {
                    $valEscaped = $conn->real_escape_string($value);
                    $setParts[] = "`$colEscaped` = '$valEscaped'";
                }
            }
            
            // Build WHERE clause
            $whereParts = [];
            foreach ($whereConditions as $column => $value) {
                $colEscaped = $conn->real_escape_string($column);
                $valEscaped = $conn->real_escape_string($value);
                $whereParts[] = "`$colEscaped` = '$valEscaped'";
            }
            
            $sql = "UPDATE `$table` SET " . implode(', ', $setParts);
            
            if (!empty($whereParts)) {
                $sql .= " WHERE " . implode(' AND ', $whereParts);
            }
            
            if ($conn->query($sql)) {
                $result['success'] = true;
            } else {
                $result['error'] = 'Error updating row: ' . $conn->error;
            }
            
            $conn->close();
        } catch (Exception $e) {
            $result['error'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Delete a row from a table
     */
    public function deleteRow(
        string $database,
        string $table,
        array $whereConditions
    ): array {
        $result = ['success' => false, 'error' => null];
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if (!$conn) {
            $result['error'] = 'No database connection';
            return $result;
        }
        
        try {
            $conn->select_db($database);
            
            // Build WHERE clause
            $whereParts = [];
            foreach ($whereConditions as $column => $value) {
                $colEscaped = $conn->real_escape_string($column);
                $valEscaped = $conn->real_escape_string($value);
                $whereParts[] = "`$colEscaped` = '$valEscaped'";
            }
            
            $sql = "DELETE FROM `$table`";
            
            if (!empty($whereParts)) {
                $sql .= " WHERE " . implode(' AND ', $whereParts);
            }
            
            if ($conn->query($sql)) {
                $result['success'] = true;
            } else {
                $result['error'] = 'Error deleting row: ' . $conn->error;
            }
            
            $conn->close();
        } catch (Exception $e) {
            $result['error'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
}
