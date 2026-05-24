<?php

namespace DatabaseManager\Config;

/**
 * Database Configuration Class
 */
class DatabaseConfig
{
    private const DEFAULT_HOST = '127.0.0.1';
    private const DEFAULT_PORT = 3306;
    
    private string $host;
    private int $port;
    
    public function __construct(string $host = self::DEFAULT_HOST, int $port = self::DEFAULT_PORT)
    {
        $this->host = $host;
        $this->port = $port;
    }
    
    public function getHost(): string
    {
        return $this->host;
    }
    
    public function getPort(): int
    {
        return $this->port;
    }
    
    public function getFullHost(): string
    {
        return $this->host . ':' . $this->port;
    }
}
