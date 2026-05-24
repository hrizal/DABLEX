<?php

namespace DatabaseManager\Services;

use mysqli;
use Exception;

/**
 * Database Connection Service
 */
class ConnectionService
{
    private string $host;
    
    public function __construct(string $host)
    {
        $this->host = $host;
    }
    
    /**
     * Create a new database connection
     * @throws Exception
     */
    public function connect(string $username, string $password): mysqli
    {
        $conn = new mysqli($this->host, $username, $password);
        
        if ($conn->connect_error) {
            throw new Exception('Connection failed: ' . $conn->connect_error);
        }
        
        return $conn;
    }
    
    /**
     * Get connection from session credentials
     * Note: This requires the user to have stored credentials in session.
     * For better security, consider using a token-based re-authentication system.
     */
    public function getConnectionFromSession(): ?mysqli
    {
        if (!isset($_SESSION['db_user']) || !isset($_SESSION['db_pass'])) {
            return null;
        }
        
        try {
            return $this->connect($_SESSION['db_user'], $_SESSION['db_pass']);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get connection using provided credentials
     */
    public function connectWithCredentials(string $username, string $password): mysqli
    {
        return $this->connect($username, $password);
    }
}
