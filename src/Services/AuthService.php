<?php

namespace DatabaseManager\Services;

use mysqli;
use Exception;

/**
 * Authentication Service for database users
 */
class AuthService
{
    private ConnectionService $connectionService;
    
    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }
    
    /**
     * Authenticate user with database credentials
     */
    public function authenticate(string $username, string $password): array
    {
        $result = [
            'success' => false,
            'error' => null,
            'connection' => null
        ];
        
        if (empty($username) || empty($password)) {
            $result['error'] = 'Username dan password harus diisi';
            return $result;
        }
        
        try {
            $conn = $this->connectionService->connect($username, $password);
            $result['success'] = true;
            $result['connection'] = $conn;
            return $result;
        } catch (Exception $e) {
            $result['error'] = 'Koneksi gagal: ' . $e->getMessage();
            return $result;
        }
    }
    
    /**
     * Login user and create session
     */
    public function login(string $username, string $password): array
    {
        $result = $this->authenticate($username, $password);
        
        if ($result['success']) {
            // Store username in session for reference
            $_SESSION['db_user'] = $username;
            // Store password temporarily to maintain connection (required for MySQL auth)
            // NOTE: In production, consider using encrypted storage or token-based auth
            $_SESSION['db_pass'] = $password;
            // Also store a session token for additional security
            $_SESSION['db_token'] = bin2hex(random_bytes(32));
            
            if ($result['connection']) {
                $result['connection']->close();
            }
        }
        
        return $result;
    }
    
    /**
     * Check if user is logged in via session
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['db_user']) && 
               isset($_SESSION['db_pass']) && 
               isset($_SESSION['db_token']);
    }
    
    /**
     * Logout user and destroy session - only clear auth-related session data
     */
    public function logout(): void
    {
        // Only clear authentication-related session variables
        unset($_SESSION['db_user']);
        unset($_SESSION['db_pass']);
        unset($_SESSION['db_token']);
        
        // Destroy the session cookie if it exists
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }
    
    /**
     * Get current logged in username
     */
    public function getCurrentUser(): ?string
    {
        return $_SESSION['db_user'] ?? null;
    }
}
