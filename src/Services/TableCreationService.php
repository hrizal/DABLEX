<?php

namespace DatabaseManager\Services;

use mysqli;
use Exception;

/**
 * Table Creation and Modification Service
 */
class TableCreationService
{
    private ConnectionService $connectionService;
    
    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }
    
    /**
     * Create a new table
     */
    public function createTable(string $database, string $tableName, array $fields): array
    {
        $result = ['success' => false, 'error' => null];
        
        if (empty($tableName)) {
            $result['error'] = 'Table name must be filled';
            return $result;
        }
        
        if (empty($fields) || !is_array($fields)) {
            $result['error'] = 'At least one field is required';
            return $result;
        }
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if (!$conn) {
            $result['error'] = 'No database connection';
            return $result;
        }
        
        try {
            $conn->select_db($database);
            
            // Build CREATE TABLE SQL
            $fieldDefinitions = [];
            $primaryKeys = [];
            $uniqueKeys = [];
            $indexKeys = [];
            $autoIncrementCount = 0;
            $autoIncrementField = null;
            
            foreach ($fields as $field) {
                if (empty($field['name']) || empty($field['type'])) {
                    continue;
                }
                
                $fieldDef = $this->buildFieldDefinition($conn, $field);
                $fieldDefinitions[] = $fieldDef;
                
                // Track AUTO_INCREMENT fields
                if (!empty($field['extra']) && strtoupper(trim($field['extra'])) === 'AUTO_INCREMENT') {
                    $autoIncrementCount++;
                    $autoIncrementField = $conn->real_escape_string($field['name']);
                    $fieldDefinitions[count($fieldDefinitions) - 1] .= " AUTO_INCREMENT";
                }
                
                // Collect keys
                if (!empty($field['key'])) {
                    $keyType = strtoupper(trim($field['key']));
                    $fieldName = $conn->real_escape_string($field['name']);
                    
                    if ($keyType === 'PRIMARY') {
                        $primaryKeys[] = $fieldName;
                    } elseif ($keyType === 'UNIQUE') {
                        $uniqueKeys[] = $fieldName;
                    } elseif ($keyType === 'INDEX') {
                        $indexKeys[] = $fieldName;
                    }
                }
            }
            
            // Validate AUTO_INCREMENT
            if ($autoIncrementCount > 1) {
                $result['error'] = 'Only one field can have AUTO_INCREMENT';
                return $result;
            } elseif ($autoIncrementCount === 1 && empty($primaryKeys)) {
                // Auto-set PRIMARY KEY if AUTO_INCREMENT exists but no PRIMARY KEY
                $primaryKeys[] = $autoIncrementField;
            }
            
            if (empty($fieldDefinitions)) {
                $result['error'] = 'No valid fields';
                return $result;
            }
            
            // Add PRIMARY KEY constraint
            if (!empty($primaryKeys)) {
                $fieldDefinitions[] = "PRIMARY KEY (`" . implode("`, `", $primaryKeys) . "`)";
            }
            
            // Add UNIQUE KEY constraints
            foreach ($uniqueKeys as $uniqueField) {
                $fieldDefinitions[] = "UNIQUE KEY `{$uniqueField}_unique` (`$uniqueField`)";
            }
            
            // Add INDEX constraints
            foreach ($indexKeys as $indexField) {
                $fieldDefinitions[] = "KEY `{$indexField}_idx` (`$indexField`)";
            }
            
            $sql = "CREATE TABLE `" . $conn->real_escape_string($tableName) . "` (" . implode(", ", $fieldDefinitions) . ")";
            
            if ($conn->query($sql)) {
                $result['success'] = true;
            } else {
                $result['error'] = 'Error creating table: ' . $conn->error;
            }
            
            $conn->close();
        } catch (Exception $e) {
            $result['error'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Build field definition for CREATE/ALTER TABLE
     */
    private function buildFieldDefinition(mysqli $conn, array $field): string
    {
        $fieldName = $conn->real_escape_string($field['name']);
        $fieldType = $conn->real_escape_string($field['type']);
        $fieldLength = isset($field['length']) ? trim($field['length']) : '';
        
        // Determine if type needs length
        $typesWithLength = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'DECIMAL', 'FLOAT', 'DOUBLE'];
        $typeUpper = strtoupper($fieldType);
        $needsLength = false;
        
        foreach ($typesWithLength as $typeWithLen) {
            if (strpos($typeUpper, $typeWithLen) === 0) {
                $needsLength = true;
                break;
            }
        }
        
        // Combine type and length if provided
        if ($needsLength && !empty($fieldLength)) {
            $fieldDef = "`$fieldName` $fieldType(" . $fieldLength . ")";
        } else {
            $fieldDef = "`$fieldName` $fieldType";
        }
        
        // Add NULL/NOT NULL
        if (isset($field['null']) && $field['null'] === 'YES') {
            $fieldDef .= " NULL";
        } else {
            $fieldDef .= " NOT NULL";
        }
        
        // Add DEFAULT value
        if (!empty($field['default'])) {
            $defaultValue = trim($field['default']);
            $defaultValueUpper = strtoupper($defaultValue);
            
            if ($defaultValueUpper === 'NULL') {
                $fieldDef .= " DEFAULT NULL";
            } elseif ($defaultValueUpper === 'CURRENT_TIMESTAMP' || $defaultValueUpper === 'CURRENT_TIMESTAMP()') {
                $fieldDef .= " DEFAULT CURRENT_TIMESTAMP";
            } else {
                $fieldDef .= " DEFAULT '" . $conn->real_escape_string($defaultValue) . "'";
            }
        }
        
        return $fieldDef;
    }
    
    /**
     * Add a new column to a table
     */
    public function addColumn(string $database, string $table, array $field): array
    {
        $result = ['success' => false, 'error' => null];
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if (!$conn) {
            $result['error'] = 'No database connection';
            return $result;
        }
        
        try {
            $conn->select_db($database);
            
            $fieldDef = $this->buildFieldDefinition($conn, $field);
            $fieldName = $conn->real_escape_string($field['name']);
            
            // Handle AUTO_INCREMENT
            if (!empty($field['extra']) && strtoupper(trim($field['extra'])) === 'AUTO_INCREMENT') {
                $fieldDef .= " AUTO_INCREMENT";
            }
            
            $sql = "ALTER TABLE `$table` ADD COLUMN $fieldDef";
            
            if ($conn->query($sql)) {
                $result['success'] = true;
            } else {
                $result['error'] = 'Error adding column: ' . $conn->error;
            }
            
            $conn->close();
        } catch (Exception $e) {
            $result['error'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Modify an existing column
     */
    public function modifyColumn(string $database, string $table, array $field): array
    {
        $result = ['success' => false, 'error' => null];
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if (!$conn) {
            $result['error'] = 'No database connection';
            return $result;
        }
        
        try {
            $conn->select_db($database);
            
            $fieldDef = $this->buildFieldDefinition($conn, $field);
            
            // Handle AUTO_INCREMENT
            if (!empty($field['extra']) && strtoupper(trim($field['extra'])) === 'AUTO_INCREMENT') {
                $fieldDef .= " AUTO_INCREMENT";
            }
            
            $fieldName = $conn->real_escape_string($field['name']);
            $sql = "ALTER TABLE `$table` MODIFY COLUMN $fieldDef";
            
            if ($conn->query($sql)) {
                $result['success'] = true;
            } else {
                $result['error'] = 'Error modifying column: ' . $conn->error;
            }
            
            $conn->close();
        } catch (Exception $e) {
            $result['error'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Delete a column from a table
     */
    public function deleteColumn(string $database, string $table, string $columnName): array
    {
        $result = ['success' => false, 'error' => null];
        
        $conn = $this->connectionService->getConnectionFromSession();
        
        if (!$conn) {
            $result['error'] = 'No database connection';
            return $result;
        }
        
        try {
            $conn->select_db($database);
            
            $sql = "ALTER TABLE `$table` DROP COLUMN `" . $conn->real_escape_string($columnName) . "`";
            
            if ($conn->query($sql)) {
                $result['success'] = true;
            } else {
                $result['error'] = 'Error deleting column: ' . $conn->error;
            }
            
            $conn->close();
        } catch (Exception $e) {
            $result['error'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
}
