<?php

namespace DatabaseManager\Services;

use mysqli;
use Exception;

/**
 * Table Structure and Index Management Service
 */
class TableStructureService
{
    private ConnectionService $connectionService;
    
    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }
    
    /**
     * Get table structure (columns)
     */
    public function getTableStructure(string $database, string $table): array
    {
        $structure = [];
        $conn = $this->connectionService->getConnectionFromSession();
        
        if ($conn) {
            try {
                $conn->select_db($database);
                $result = $conn->query("DESCRIBE `$table`");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $structure[] = $row;
                    }
                    $result->free();
                }
                $conn->close();
            } catch (Exception $e) {
                throw new Exception('Error fetching table structure: ' . $e->getMessage());
            }
        }
        
        return $structure;
    }
    
    /**
     * Get table indexes
     */
    public function getTableIndexes(string $database, string $table): array
    {
        $indexes = [];
        $conn = $this->connectionService->getConnectionFromSession();
        
        if ($conn) {
            try {
                $conn->select_db($database);
                $indexResult = $conn->query("SHOW INDEX FROM `$table`");
                
                if ($indexResult) {
                    while ($row = $indexResult->fetch_assoc()) {
                        $keyName = $row['Key_name'];
                        
                        if (!isset($indexes[$keyName])) {
                            $indexes[$keyName] = [
                                'Key_name' => $keyName,
                                'Non_unique' => $row['Non_unique'],
                                'Columns' => [],
                                'Index_type' => $row['Index_type'] ?? 'BTREE',
                                'Comment' => $row['Comment'] ?? ''
                            ];
                        }
                        
                        $indexes[$keyName]['Columns'][] = $row['Column_name'];
                    }
                    $indexResult->free();
                }
                
                $conn->close();
            } catch (Exception $e) {
                throw new Exception('Error fetching table indexes: ' . $e->getMessage());
            }
        }
        
        return $indexes;
    }
    
    /**
     * Add a new index to a table
     */
    public function addIndex(
        string $database,
        string $table,
        string $indexName,
        string $indexType,
        array $columns
    ): array {
        $result = ['success' => false, 'error' => null];
        
        // Filter valid columns - collect column definitions first
        $validColumns = [];
        foreach ($columns as $col) {
            if (!empty($col['name'])) {
                $colDef = "`" . $col['name'] . "`";
                
                if (!empty($col['length'])) {
                    $colDef .= "(" . intval($col['length']) . ")";
                }
                
                $validColumns[] = $colDef;
            }
        }
        
        if (empty($validColumns)) {
            $result['error'] = 'Select at least one column for index';
            return $result;
        }
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if (!$conn) {
            $result['error'] = 'No database connection';
            return $result;
        }
        
        try {
            $conn->select_db($database);
            
            $sql = "ALTER TABLE `$table` ADD ";
            
            switch ($indexType) {
                case 'PRIMARY':
                    $sql .= "PRIMARY KEY";
                    break;
                case 'UNIQUE':
                    $sql .= "UNIQUE INDEX";
                    break;
                case 'FULLTEXT':
                    $sql .= "FULLTEXT INDEX";
                    break;
                default:
                    $sql .= "INDEX";
            }
            
            if ($indexType !== 'PRIMARY' && !empty($indexName)) {
                $sql .= " `" . $conn->real_escape_string($indexName) . "`";
            }
            
            $sql .= " (" . implode(", ", $validColumns) . ")";
            
            if ($conn->query($sql)) {
                $result['success'] = true;
            } else {
                $result['error'] = 'Error adding index: ' . $conn->error;
            }
            
            $conn->close();
        } catch (Exception $e) {
            $result['error'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Delete an index from a table
     */
    public function deleteIndex(string $database, string $table, string $indexName): array
    {
        $result = ['success' => false, 'error' => null];
        
        if (empty($indexName)) {
            $result['error'] = 'Invalid index name';
            return $result;
        }
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if ($conn) {
            try {
                $conn->select_db($database);
                
                $sql = "ALTER TABLE `$table` DROP ";
                
                if ($indexName === 'PRIMARY') {
                    $sql .= "PRIMARY KEY";
                } else {
                    $sql .= "INDEX `" . $conn->real_escape_string($indexName) . "`";
                }
                
                if ($conn->query($sql)) {
                    $result['success'] = true;
                } else {
                    $result['error'] = 'Error deleting index: ' . $conn->error;
                }
                
                $conn->close();
            } catch (Exception $e) {
                $result['error'] = 'Error: ' . $e->getMessage();
            }
        }
        
        return $result;
    }
}
