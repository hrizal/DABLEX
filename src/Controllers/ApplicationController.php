<?php

namespace DatabaseManager\Controllers;

use DatabaseManager\Services\AuthService;
use DatabaseManager\Services\ConnectionService;
use DatabaseManager\Services\DatabaseService;
use DatabaseManager\Services\TableStructureService;
use DatabaseManager\Services\TableDataService;
use DatabaseManager\Services\TableCreationService;

/**
 * Main Application Controller
 */
class ApplicationController
{
    private AuthService $authService;
    private DatabaseService $databaseService;
    private TableStructureService $structureService;
    private TableDataService $dataService;
    private TableCreationService $creationService;
    
    private array $errors = [];
    private array $successes = [];
    
    public function __construct(ConnectionService $connectionService)
    {
        $this->authService = new AuthService($connectionService);
        $this->databaseService = new DatabaseService($connectionService);
        $this->structureService = new TableStructureService($connectionService);
        $this->dataService = new TableDataService($connectionService);
        $this->creationService = new TableCreationService($connectionService);
    }
    
    /**
     * Handle login request
     */
    public function handleLogin(string $username, string $password): void
    {
        $result = $this->authService->login($username, $password);
        
        if ($result['success']) {
            $this->successes[] = 'Login successful!';
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Handle logout request
     */
    public function handleLogout(): void
    {
        $this->authService->logout();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        return $this->authService->isLoggedIn();
    }
    
    /**
     * Get current username
     */
    public function getCurrentUser(): ?string
    {
        return $this->authService->getCurrentUser();
    }
    
    /**
     * Handle database selection
     */
    public function selectDatabase(string $database, bool $showTableList = false): void
    {
        $this->databaseService->selectDatabase($database);
        $this->databaseService->showTableList($showTableList);
    }
    
    /**
     * Handle table selection
     */
    public function selectTable(string $table): void
    {
        $this->databaseService->selectTable($table);
    }
    
    /**
     * Get list of databases
     */
    public function getDatabases(): array
    {
        try {
            return $this->databaseService->getDatabases();
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            return [];
        }
    }
    
    /**
     * Get list of tables for current database
     */
    public function getTables(): array
    {
        $database = $this->databaseService->getCurrentDatabase();
        
        if (!$database) {
            return [];
        }
        
        try {
            return $this->databaseService->getTables($database);
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            return [];
        }
    }
    
    /**
     * Get current database name
     */
    public function getCurrentDatabase(): ?string
    {
        return $this->databaseService->getCurrentDatabase();
    }
    
    /**
     * Get current table name
     */
    public function getCurrentTable(): ?string
    {
        return $this->databaseService->getCurrentTable();
    }
    
    /**
     * Should show table list
     */
    public function shouldShowTableList(): bool
    {
        return isset($_SESSION['show_table_list']) && $_SESSION['show_table_list'] && !$this->getCurrentTable();
    }
    
    /**
     * Get table structure
     */
    public function getTableStructure(): array
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            return [];
        }
        
        try {
            return $this->structureService->getTableStructure($database, $table);
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            return [];
        }
    }
    
    /**
     * Get table indexes
     */
    public function getTableIndexes(): array
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            return [];
        }
        
        try {
            return $this->structureService->getTableIndexes($database, $table);
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            return [];
        }
    }
    
    /**
     * Get table data with pagination
     */
    public function getTableData(array $filters = [], int $page = 1): array
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            return [
                'columns' => [],
                'data' => [],
                'totalCount' => 0,
                'currentPage' => $page,
                'totalPages' => 0,
                'pageSize' => 100
            ];
        }
        
        try {
            return $this->dataService->getTableData($database, $table, $filters, $page);
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            return [
                'columns' => [],
                'data' => [],
                'totalCount' => 0,
                'currentPage' => $page,
                'totalPages' => 0,
                'pageSize' => 100
            ];
        }
    }
    
    /**
     * Add index to table
     */
    public function addIndex(string $indexName, string $indexType, array $columns): void
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            $this->errors[] = 'No database or table selected';
            return;
        }
        
        $result = $this->structureService->addIndex($database, $table, $indexName, $indexType, $columns);
        
        if ($result['success']) {
            $this->successes[] = 'Index added successfully!';
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Delete index from table
     */
    public function deleteIndex(string $indexName): void
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            $this->errors[] = 'No database or table selected';
            return;
        }
        
        $result = $this->structureService->deleteIndex($database, $table, $indexName);
        
        if ($result['success']) {
            $this->successes[] = "Index '$indexName' deleted successfully!";
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Create a new table
     */
    public function createTable(string $tableName, array $fields): void
    {
        $database = $this->getCurrentDatabase();
        
        if (!$database) {
            $this->errors[] = 'No database selected';
            return;
        }
        
        $result = $this->creationService->createTable($database, $tableName, $fields);
        
        if ($result['success']) {
            $this->successes[] = "Table '$tableName' created successfully!";
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Add column to table
     */
    public function addColumn(array $field): void
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            $this->errors[] = 'No database or table selected';
            return;
        }
        
        $result = $this->creationService->addColumn($database, $table, $field);
        
        if ($result['success']) {
            $this->successes[] = 'Column added successfully!';
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Modify column in table
     */
    public function modifyColumn(array $field): void
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            $this->errors[] = 'No database or table selected';
            return;
        }
        
        $result = $this->creationService->modifyColumn($database, $table, $field);
        
        if ($result['success']) {
            $this->successes[] = 'Column modified successfully!';
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Delete column from table
     */
    public function deleteColumn(string $columnName): void
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            $this->errors[] = 'No database or table selected';
            return;
        }
        
        $result = $this->creationService->deleteColumn($database, $table, $columnName);
        
        if ($result['success']) {
            $this->successes[] = 'Column deleted successfully!';
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Add row to table
     */
    public function addRow(array $data): void
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            $this->errors[] = 'No database or table selected';
            return;
        }
        
        $result = $this->dataService->addRow($database, $table, $data);
        
        if ($result['success']) {
            $this->successes[] = 'Row added successfully!';
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Update row in table
     */
    public function updateRow(array $data, array $whereConditions): void
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            $this->errors[] = 'No database or table selected';
            return;
        }
        
        $result = $this->dataService->updateRow($database, $table, $data, $whereConditions);
        
        if ($result['success']) {
            $this->successes[] = 'Row updated successfully!';
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Delete row from table
     */
    public function deleteRow(array $whereConditions): void
    {
        $database = $this->getCurrentDatabase();
        $table = $this->getCurrentTable();
        
        if (!$database || !$table) {
            $this->errors[] = 'No database or table selected';
            return;
        }
        
        $result = $this->dataService->deleteRow($database, $table, $whereConditions);
        
        if ($result['success']) {
            $this->successes[] = 'Row deleted successfully!';
        } else {
            $this->errors[] = $result['error'];
        }
    }
    
    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get all success messages
     */
    public function getSuccesses(): array
    {
        return $this->successes;
    }
    
    /**
     * Clear messages
     */
    public function clearMessages(): void
    {
        $this->errors = [];
        $this->successes = [];
    }
}
