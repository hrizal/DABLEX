<?php

namespace DatabaseManager\Services;

use mysqli;
use Exception;

/**
 * Database Management Service
 */
class DatabaseService
{
    private ConnectionService $connectionService;
    
    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }
    
    /**
     * Get list of all databases
     */
    public function getDatabases(): array
    {
        $databases = [];
        $conn = $this->connectionService->getConnectionFromSession();
        
        if ($conn) {
            try {
                $result = $conn->query("SHOW DATABASES");
                if ($result) {
                    while ($row = $result->fetch_array()) {
                        $databases[] = $row[0];
                    }
                    $result->free();
                }
                $conn->close();
            } catch (Exception $e) {
                throw new Exception('Error fetching databases: ' . $e->getMessage());
            }
        }
        
        return $databases;
    }
    
    /**
     * Get list of tables in a database
     */
    public function getTables(string $database): array
    {
        $tables = [];
        $conn = $this->connectionService->getConnectionFromSession();
        
        if ($conn) {
            try {
                $conn->select_db($database);
                $result = $conn->query("SHOW TABLES");
                if ($result) {
                    while ($row = $result->fetch_array()) {
                        $tables[] = $row[0];
                    }
                    $result->free();
                }
                $conn->close();
            } catch (Exception $e) {
                throw new Exception('Error fetching tables: ' . $e->getMessage());
            }
        }
        
        return $tables;
    }
    
    /**
     * Select a database
     */
    public function selectDatabase(string $database): bool
    {
        $_SESSION['current_db'] = $database;
        unset($_SESSION['current_table']);
        unset($_SESSION['show_table_list']);
        return true;
    }
    
    /**
     * Show table list for a database
     */
    public function showTableList(bool $show = true): void
    {
        $_SESSION['show_table_list'] = $show;
    }
    
    /**
     * Select a table
     */
    public function selectTable(string $table): void
    {
        $_SESSION['current_table'] = $table;
        unset($_SESSION['show_table_list']);
    }
    
    /**
     * Get current selected database
     */
    public function getCurrentDatabase(): ?string
    {
        return $_SESSION['current_db'] ?? null;
    }
    
    /**
     * Get current selected table
     */
    public function getCurrentTable(): ?string
    {
        return $_SESSION['current_table'] ?? null;
    }
}
