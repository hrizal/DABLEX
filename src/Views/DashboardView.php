<?php

namespace DatabaseManager\Views;

/**
 * Dashboard View Component
 */
class DashboardView
{
    public function render(array $data): void
    {
        extract($data);
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Database Manager</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
                
                /* Top Bar */
                .top-bar { background: #2d3e50; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; }
                .user-info { display: flex; align-items: center; gap: 15px; }
                .btn-danger { background: #dc3545; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; }
                
                /* Main Container */
                .main-container { display: flex; height: calc(100vh - 50px); }
                
                /* Sidebar */
                .sidebar { width: 280px; background: white; border-right: 1px solid #ddd; overflow-y: auto; }
                .sidebar-section { border-bottom: 1px solid #eee; }
                .sidebar-header-section { padding: 15px; background: #f8f9fa; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-weight: 600; }
                .db-list { padding: 10px; }
                .db-item { margin-bottom: 5px; }
                .db-header { display: block; padding: 10px; border-radius: 4px; text-decoration: none; color: #333; }
                .db-header:hover { background: #f0f0f0; }
                .db-header.active { background: #e3f2fd; color: #1976d2; }
                .table-list { padding-left: 20px; }
                .table-item a { display: block; padding: 8px 10px; color: #666; text-decoration: none; font-size: 13px; }
                .table-item a:hover { background: #f0f0f0; }
                .table-item a.active { background: #e3f2fd; color: #1976d2; }
                
                /* Content Area */
                .content { flex: 1; padding: 20px; overflow-y: auto; }
                
                /* Alerts */
                .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; }
                .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                
                /* Tables */
                table { width: 100%; border-collapse: collapse; background: white; }
                th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #f8f9fa; font-weight: 600; }
                tr:hover { background: #f5f5f5; }
                
                /* Tabs */
                .tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
                .tab-btn { padding: 10px 20px; border: none; background: none; cursor: pointer; font-size: 14px; color: #666; }
                .tab-btn.active { color: #007bff; border-bottom: 2px solid #007bff; margin-bottom: -12px; }
                
                /* Buttons */
                .btn { padding: 8px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
                .btn-primary { background: #007bff; color: white; }
                .btn-secondary { background: #6c757d; color: white; }
                
                /* Pagination */
                .pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
                .pagination a { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; }
                .pagination a.active { background: #007bff; color: white; border-color: #007bff; }
                
                /* Forms */
                .form-group { margin-bottom: 15px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
                .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
                
                .badge { background: #e0e0e0; color: #2d2d2d; padding: 2px 6px; font-size: 10px; border-radius: 3px; }
            </style>
        </head>
        <body>
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="logo">
                    <strong>Database Manager</strong>
                </div>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($currentUser); ?> | <i class="fas fa-server"></i> 127.0.0.1</span>
                    <a href="?logout=1" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <!-- Main Container -->
            <div class="main-container">
                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Databases Section -->
                    <div class="sidebar-section">
                        <div class="sidebar-header-section">
                            <span><i class="fas fa-database"></i> Databases</span>
                        </div>
                        <div class="db-list">
                            <?php foreach ($databases as $db): ?>
                                <div class="db-item">
                                    <a href="?db=<?php echo urlencode($db); ?>" class="db-header <?php echo $db === $currentDb ? 'active' : ''; ?>">
                                        <i class="fas fa-database"></i> <?php echo htmlspecialchars($db); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Tables Section -->
                    <?php if ($currentDb): ?>
                    <div class="sidebar-section">
                        <div class="sidebar-header-section">
                            <span><i class="fas fa-table"></i> Tables</span>
                            <span class="badge"><?php echo count($tables); ?></span>
                        </div>
                        <div class="table-list">
                            <?php foreach ($tables as $table): ?>
                                <div class="table-item">
                                    <a href="?db=<?php echo urlencode($currentDb); ?>&table=<?php echo urlencode($table); ?>" 
                                       class="<?php echo $table === $currentTable ? 'active' : ''; ?>">
                                        <i class="fas fa-table"></i> <?php echo htmlspecialchars($table); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Content Area -->
                <div class="content">
                    <?php if (!empty($errors)): ?>
                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($successes)): ?>
                        <?php foreach ($successes as $success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Table Structure Tab -->
                    <?php if ($currentTable && $showTab === 'structure'): ?>
                        <h2>Table Structure: <?php echo htmlspecialchars($currentTable); ?></h2>
                        
                        <div class="tabs">
                            <button class="tab-btn active">Structure</button>
                            <a href="?db=<?php echo urlencode($currentDb); ?>&table=<?php echo urlencode($currentTable); ?>&tab=data" class="tab-btn">Data</a>
                        </div>
                        
                        <h3>Columns</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Type</th>
                                    <th>Null</th>
                                    <th>Key</th>
                                    <th>Default</th>
                                    <th>Extra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableStructure as $column): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($column['Field']); ?></td>
                                        <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                        <td><?php echo htmlspecialchars($column['Null']); ?></td>
                                        <td><?php echo htmlspecialchars($column['Key']); ?></td>
                                        <td><?php echo htmlspecialchars($column['Default'] ?? 'NULL'); ?></td>
                                        <td><?php echo htmlspecialchars($column['Extra']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <h3 style="margin-top: 30px;">Indexes</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Key Name</th>
                                    <th>Columns</th>
                                    <th>Unique</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableIndexes as $index): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($index['Key_name']); ?></td>
                                        <td><?php echo htmlspecialchars(implode(', ', $index['Columns'])); ?></td>
                                        <td><?php echo $index['Non_unique'] ? 'No' : 'Yes'; ?></td>
                                        <td><?php echo htmlspecialchars($index['Index_type']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <!-- Table Data Tab -->
                    <?php if ($currentTable && $showTab === 'data'): ?>
                        <h2>Table Data: <?php echo htmlspecialchars($currentTable); ?></h2>
                        
                        <div class="tabs">
                            <a href="?db=<?php echo urlencode($currentDb); ?>&table=<?php echo urlencode($currentTable); ?>&tab=structure" class="tab-btn">Structure</a>
                            <button class="tab-btn active">Data</button>
                        </div>
                        
                        <p>Total rows: <?php echo number_format($tableRowCount); ?></p>
                        
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach ($tableDataColumns as $column): ?>
                                        <th><?php echo htmlspecialchars($column); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData as $row): ?>
                                    <tr>
                                        <?php foreach ($tableDataColumns as $column): ?>
                                            <td><?php echo htmlspecialchars($row[$column] ?? ''); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?db=<?php echo urlencode($currentDb); ?>&table=<?php echo urlencode($currentTable); ?>&tab=data&page=<?php echo $i; ?>" 
                                       class="<?php echo $i === $currentPage ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- No table selected -->
                    <?php if (!$currentTable && $currentDb): ?>
                        <h2>Database: <?php echo htmlspecialchars($currentDb); ?></h2>
                        <p>Select a table from the sidebar to view its structure and data.</p>
                    <?php endif; ?>
                    
                    <!-- No database selected -->
                    <?php if (!$currentDb): ?>
                        <h2>Welcome to Database Manager</h2>
                        <p>Select a database from the sidebar to get started.</p>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
