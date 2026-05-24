<?php
/**
 * Database Manager - Refactored Entry Point
 * 
 * This is the main entry point for the Database Manager application.
 * The code has been refactored into a modular architecture with:
 * - Controllers: Handle application logic and request routing
 * - Services: Business logic for database operations
 * - Views: Render HTML templates
 * - Config: Configuration classes
 */

session_start();

// Autoloader for the refactored classes
spl_autoload_register(function ($class) {
    $prefix = 'DatabaseManager\\';
    $baseDir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use DatabaseManager\Config\DatabaseConfig;
use DatabaseManager\Services\ConnectionService;
use DatabaseManager\Controllers\ApplicationController;
use DatabaseManager\Views\LoginView;
use DatabaseManager\Views\DashboardView;

// Initialize configuration and services
$config = new DatabaseConfig('127.0.0.1');
$connectionService = new ConnectionService($config->getHost());
$controller = new ApplicationController($connectionService);

// Handle logout
if (isset($_GET['logout'])) {
    $controller->handleLogout();
    header('Location: index.php');
    exit;
}

// Handle login POST
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $controller->handleLogin($username, $password);
}

// Handle database selection
if (isset($_GET['db'])) {
    $showList = isset($_GET['show_list']) && $_GET['show_list'];
    $controller->selectDatabase($_GET['db'], $showList);
}

// Handle table selection
if (isset($_GET['table'])) {
    $controller->selectTable($_GET['table']);
}

// Check if logged in
$loggedIn = $controller->isLoggedIn();

// Render appropriate view
if (!$loggedIn) {
    $loginView = new LoginView();
    $errors = $controller->getErrors();
    $successes = $controller->getSuccesses();
    $loginView->render(
        !empty($errors) ? $errors[0] : '',
        !empty($successes) ? $successes[0] : ''
    );
} else {
    // Get data for dashboard
    $currentDb = $controller->getCurrentDatabase();
    $currentTable = $controller->getCurrentTable();
    $databases = $controller->getDatabases();
    $tables = $controller->getTables();
    
    // Get table structure and data if table is selected
    $tableStructure = [];
    $tableIndexes = [];
    $tableData = [];
    $tableDataColumns = [];
    $tableRowCount = 0;
    $currentPage = 1;
    $totalPages = 0;
    
    if ($currentTable) {
        $tableStructure = $controller->getTableStructure();
        $tableIndexes = $controller->getTableIndexes();
        
        // Get filters from query string
        $filters = isset($_GET['filter']) && is_array($_GET['filter']) ? $_GET['filter'] : [];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        
        $tableDataResult = $controller->getTableData($filters, $page);
        $tableDataColumns = $tableDataResult['columns'];
        $tableData = $tableDataResult['data'];
        $tableRowCount = $tableDataResult['totalCount'];
        $currentPage = $tableDataResult['currentPage'];
        $totalPages = $tableDataResult['totalPages'];
    }
    
    // Determine which tab to show
    $showTab = isset($_GET['tab']) ? $_GET['tab'] : 'structure';
    
    $dashboardView = new DashboardView();
    $dashboardView->render([
        'currentUser' => $controller->getCurrentUser(),
        'databases' => $databases,
        'tables' => $tables,
        'currentDb' => $currentDb,
        'currentTable' => $currentTable,
        'tableStructure' => $tableStructure,
        'tableIndexes' => $tableIndexes,
        'tableDataColumns' => $tableDataColumns,
        'tableData' => $tableData,
        'tableRowCount' => $tableRowCount,
        'currentPage' => $currentPage,
        'totalPages' => $totalPages,
        'showTab' => $showTab,
        'errors' => $controller->getErrors(),
        'successes' => $controller->getSuccesses()
    ]);
}
