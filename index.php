<?php
session_start();

$host = '127.0.0.1';
$error = '';
$success = '';
$tables = [];
$databases = [];
$current_db = '';
$current_table = '';
$table_structure = [];
$table_indexes = [];
$table_data = [];
$table_data_columns = [];
$table_row_count = 0;

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check if user is logged in
$logged_in = isset($_SESSION['db_user']) && isset($_SESSION['db_pass']);

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        try {
            $conn = new mysqli($host, $username, $password);
            if ($conn->connect_error) {
                $error = 'Koneksi gagal: ' . $conn->connect_error;
            } else {
                $_SESSION['db_user'] = $username;
                $_SESSION['db_pass'] = $password;
                $logged_in = true;
                $success = 'Login successful!';
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get database connection
function getConnection() {
    global $host;
    if (!isset($_SESSION['db_user']) || !isset($_SESSION['db_pass'])) {
        return null;
    }
    return new mysqli($host, $_SESSION['db_user'], $_SESSION['db_pass']);
}

// Handle database selection via GET
if ($logged_in && isset($_GET['db'])) {
    $_SESSION['current_db'] = $_GET['db'];
    unset($_SESSION['current_table']);
    // Show table list when clicking database name
    if (isset($_GET['show_list'])) {
        $_SESSION['show_table_list'] = true;
    }
}

// Handle table selection via GET
if ($logged_in && isset($_GET['table'])) {
    $_SESSION['current_table'] = $_GET['table'];
    unset($_SESSION['show_table_list']);
}

$current_db = $_SESSION['current_db'] ?? '';
$current_table = $_SESSION['current_table'] ?? '';
$show_table_list = isset($_SESSION['show_table_list']) && $_SESSION['show_table_list'] && !$current_table;

// Get databases list
if ($logged_in) {
    try {
        $conn = getConnection();
        if ($conn) {
            $result = $conn->query("SHOW DATABASES");
            while ($row = $result->fetch_array()) {
                $databases[] = $row[0];
            }
            $conn->close();
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// No need to get all tables for sidebar anymore

// Get tables list for current database
if ($logged_in && $current_db) {
    try {
        $conn = getConnection();
        if ($conn) {
            $conn->select_db($current_db);
            $result = $conn->query("SHOW TABLES");
            while ($row = $result->fetch_array()) {
                $tables[] = $row[0];
            }
            $conn->close();
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get table structure and indexes
if ($logged_in && $current_db && $current_table) {
    try {
        $conn = getConnection();
        if ($conn) {
            $conn->select_db($current_db);
            
            // Get Structure
            $result = $conn->query("DESCRIBE `$current_table`");
            while ($row = $result->fetch_assoc()) {
                $table_structure[] = $row;
            }
            
            // Get Indexes
            $index_result = $conn->query("SHOW INDEX FROM `$current_table`");
            while ($row = $index_result->fetch_assoc()) {
                $key_name = $row['Key_name'];
                if (!isset($table_indexes[$key_name])) {
                    $table_indexes[$key_name] = [
                        'Key_name' => $key_name,
                        'Non_unique' => $row['Non_unique'],
                        'Columns' => [],
                        'Index_type' => $row['Index_type'] ?? 'BTREE',
                        'Comment' => $row['Comment'] ?? ''
                    ];
                }
                $table_indexes[$key_name]['Columns'][] = $row['Column_name'];
            }
            
            $conn->close();
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle Add Index
if ($logged_in && isset($_POST['add_index']) && $current_db && $current_table) {
    $index_name = $_POST['index_name'] ?? '';
    $index_type = $_POST['index_type'] ?? 'INDEX';
    $index_columns = $_POST['index_columns'] ?? [];
    
    // Filter valid columns
    $valid_columns = [];
    foreach ($index_columns as $col) {
        if (!empty($col['name'])) {
            $col_def = "`" . $conn->real_escape_string($col['name']) . "`";
            if (!empty($col['length'])) {
                $col_def .= "(" . intval($col['length']) . ")";
            }
            $valid_columns[] = $col_def;
        }
    }
    
    if (empty($valid_columns)) {
        $error = 'Select at least one column for index';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                
                $sql = "ALTER TABLE `$current_table` ADD ";
                
                if ($index_type === 'PRIMARY') {
                    $sql .= "PRIMARY KEY";
                } elseif ($index_type === 'UNIQUE') {
                    $sql .= "UNIQUE INDEX";
                } elseif ($index_type === 'FULLTEXT') {
                    $sql .= "FULLTEXT INDEX";
                } else {
                    $sql .= "INDEX";
                }
                
                if ($index_type !== 'PRIMARY' && !empty($index_name)) {
                    $sql .= " `" . $conn->real_escape_string($index_name) . "`";
                }
                
                $sql .= " (" . implode(", ", $valid_columns) . ")";
                
                if ($conn->query($sql)) {
                    $success = "Index added successfully!";
                    header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=struktur");
                    exit;
                } else {
                    $error = 'Error adding index: ' . $conn->error;
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle Delete Index
if ($logged_in && isset($_POST['delete_index']) && $current_db && $current_table) {
    $index_name = $_POST['index_name'] ?? '';
    
    if (empty($index_name)) {
        $error = 'Invalid index name';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                
                $sql = "ALTER TABLE `$current_table` DROP ";
                if ($index_name === 'PRIMARY') {
                    $sql .= "PRIMARY KEY";
                } else {
                    $sql .= "INDEX `" . $conn->real_escape_string($index_name) . "`";
                }
                
                if ($conn->query($sql)) {
                    $success = "Index '$index_name' deleted successfully!";
                    header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=struktur");
                    exit;
                } else {
                    $error = 'Error deleting index: ' . $conn->error;
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get table data and row count
if ($logged_in && $current_db && $current_table) {
    try {
        $conn = getConnection();
        if ($conn) {
            $conn->select_db($current_db);
            
            // Build WHERE clause from filters
            $where_clause = '';
            $filters = isset($_GET['filter']) && is_array($_GET['filter']) ? $_GET['filter'] : [];
            $filter_conditions = [];
            
            if (!empty($filters)) {
                foreach ($filters as $col => $val) {
                    if ($val !== '') {
                        $col_escaped = $conn->real_escape_string($col);
                        $val_escaped = $conn->real_escape_string($val);
                        $filter_conditions[] = "`$col_escaped` LIKE '%$val_escaped%'";
                    }
                }
            }
            
            if (!empty($filter_conditions)) {
                $where_clause = " WHERE " . implode(" AND ", $filter_conditions);
            }

            // Get total row count
            $count_result = $conn->query("SELECT COUNT(*) as total FROM `$current_table` $where_clause");
            if ($count_result) {
                $count_row = $count_result->fetch_assoc();
                $table_row_count = $count_row['total'];
                $count_result->free();
            }
            
            // Get table data with Pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($page < 1) $page = 1;
            $limit = 100;
            $offset = ($page - 1) * $limit;
            $total_pages = ceil($table_row_count / $limit);
            
            $result = $conn->query("SELECT * FROM `$current_table` $where_clause LIMIT $limit OFFSET $offset");
            
            if ($result) {
                // Get column names
                $fields = $result->fetch_fields();
                foreach ($fields as $field) {
                    $table_data_columns[] = $field->name;
                }
                
                // Get rows
                while ($row = $result->fetch_assoc()) {
                    $table_data[] = $row;
                }
                
                $result->free();
            }
            $conn->close();
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle create table
if ($logged_in && isset($_POST['create_table']) && $current_db) {
    $table_name = $_POST['table_name'] ?? '';
    $fields = $_POST['fields'] ?? [];
    
    if (empty($table_name)) {
        $error = 'Table name must be filled';
    } elseif (empty($fields) || !is_array($fields)) {
        $error = 'At least one field is required';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                
                // Build CREATE TABLE SQL
                $field_definitions = [];
                $primary_keys = [];
                $unique_keys = [];
                $index_keys = [];
                $auto_increment_count = 0;
                $auto_increment_field = null;
                
                foreach ($fields as $field) {
                    if (empty($field['name']) || empty($field['type'])) {
                        continue; // Skip invalid fields
                    }
                    
                    $field_name = $conn->real_escape_string($field['name']);
                    $field_type = $conn->real_escape_string($field['type']);
                    $field_length = isset($field['length']) ? trim($field['length']) : '';
                    
                    // Combine type and length if length is provided
                    $types_with_length = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'TEXT', 'BLOB'];
                    $type_upper = strtoupper($field_type);
                    $needs_length = false;
                    foreach ($types_with_length as $type_with_len) {
                        if (strpos($type_upper, $type_with_len) === 0) {
                            $needs_length = true;
                            break;
                        }
                    }
                    
                    if ($needs_length && !empty($field_length)) {
                        $field_def = "`" . $field_name . "` " . $field_type . "(" . $conn->real_escape_string($field_length) . ")";
                    } else {
                        $field_def = "`" . $field_name . "` " . $field_type;
                    }
                    
                    if (isset($field['null']) && $field['null'] === 'YES') {
                        $field_def .= " NULL";
                    } else {
                        $field_def .= " NOT NULL";
                    }
                    
                    if (!empty($field['default']) && strtoupper(trim($field['default'])) !== 'NULL') {
                        $field_def .= " DEFAULT '" . $conn->real_escape_string($field['default']) . "'";
                    } elseif (!empty($field['default']) && strtoupper(trim($field['default'])) === 'NULL') {
                        $field_def .= " DEFAULT NULL";
                    }
                    
                    // Handle AUTO_INCREMENT
                    if (!empty($field['extra']) && strtoupper(trim($field['extra'])) === 'AUTO_INCREMENT') {
                        $auto_increment_count++;
                        $auto_increment_field = $field_name;
                        $field_def .= " AUTO_INCREMENT";
                    }
                    
                    $field_definitions[] = $field_def;
                    
                    // Collect keys
                    if (!empty($field['key'])) {
                        $key_type = strtoupper(trim($field['key']));
                        if ($key_type === 'PRIMARY') {
                            $primary_keys[] = $field_name;
                        } elseif ($key_type === 'UNIQUE') {
                            $unique_keys[] = $field_name;
                        } elseif ($key_type === 'INDEX') {
                            $index_keys[] = $field_name;
                        }
                    }
                }
                
                // Validate AUTO_INCREMENT
                if ($auto_increment_count > 1) {
                    $error = 'Only one field can have AUTO_INCREMENT';
                    $_SESSION['create_table_error'] = $error;
                    header("Location: ?db=" . urlencode($current_db) . "&tab=create");
                    exit;
                } elseif ($auto_increment_count === 1 && empty($primary_keys)) {
                    // Auto-set PRIMARY KEY if AUTO_INCREMENT exists but no PRIMARY KEY
                    $primary_keys[] = $auto_increment_field;
                }
                
                if (empty($field_definitions)) {
                    $error = 'No valid fields';
                    $_SESSION['create_table_error'] = $error;
                    header("Location: ?db=" . urlencode($current_db) . "&tab=create");
                    exit;
                }
                
                // Add PRIMARY KEY constraint
                if (!empty($primary_keys)) {
                    $field_definitions[] = "PRIMARY KEY (`" . implode("`, `", $primary_keys) . "`)";
                }
                
                // Add UNIQUE KEY constraints
                foreach ($unique_keys as $unique_field) {
                    $field_definitions[] = "UNIQUE KEY `" . $unique_field . "_unique` (`" . $unique_field . "`)";
                }
                
                // Add INDEX constraints
                foreach ($index_keys as $index_field) {
                    $field_definitions[] = "KEY `" . $index_field . "_idx` (`" . $index_field . "`)";
                }
                
                $sql = "CREATE TABLE `" . $conn->real_escape_string($table_name) . "` (" . implode(", ", $field_definitions) . ")";
                
                if ($conn->query($sql)) {
                    $success = "Table '$table_name' created successfully!";
                    // Refresh to show new table
                    header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($table_name) . "&tab=struktur");
                    exit;
                } else {
                    $error = 'Error: ' . $conn->error;
                    // Store error in session and redirect back to create tab
                    $_SESSION['create_table_error'] = $error;
                    header("Location: ?db=" . urlencode($current_db) . "&tab=create");
                    exit;
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            // Store error in session and redirect back to create tab
            $_SESSION['create_table_error'] = $error;
            header("Location: ?db=" . urlencode($current_db) . "&tab=create");
            exit;
        }
    }
    
    // Handle validation errors (empty table name or fields)
    if (!empty($error) && $current_db) {
        $_SESSION['create_table_error'] = $error;
        header("Location: ?db=" . urlencode($current_db) . "&tab=create");
        exit;
    }
}

// Handle SQL query
$query_result = null;
$query_columns = [];
$query_error = '';

if ($logged_in && isset($_POST['execute_query'])) {
    $query = $_POST['sql_query'] ?? '';
    
    if (empty($query)) {
        $query_error = 'SQL query cannot be empty';
    } elseif (empty($current_db)) {
        $query_error = 'Please select a database first';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                $result = $conn->query($query);
                
                if ($result === false) {
                    $query_error = 'Error: ' . $conn->error;
                } elseif ($result === true) {
                    $success = 'Query executed successfully!';
                } else {
                    $query_result = [];
                    $query_columns = [];
                    
                    $fields = $result->fetch_fields();
                    foreach ($fields as $field) {
                        $query_columns[] = $field->name;
                    }
                    
                    while ($row = $result->fetch_assoc()) {
                        $query_result[] = $row;
                    }
                    
                    $result->free();
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $query_error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle add field
if ($logged_in && isset($_POST['add_field']) && $current_db && $current_table) {
    $field_name = $_POST['field_name'] ?? '';
    $field_type = $_POST['field_type'] ?? '';
    $field_length = isset($_POST['field_length']) ? trim($_POST['field_length']) : '';
    
    if (empty($field_name) || empty($field_type)) {
        $error = 'Nama field dan type harus diisi';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                
                // Build type with length if provided
                $types_with_length = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'ENUM', 'SET'];
                $type_upper = strtoupper($field_type);
                $needs_length = in_array($type_upper, $types_with_length);
                
                // Format ENUM/SET values if they don't have quotes
                $is_enum_set_formatted = false;
                if (($type_upper === 'ENUM' || $type_upper === 'SET') && !empty($field_length) && strpos($field_length, "'") === false) {
                    $parts = explode(',', $field_length);
                    $quoted_parts = array_map(function($p) use ($conn) {
                        return "'" . $conn->real_escape_string(trim($p)) . "'";
                    }, $parts);
                    $field_length = implode(',', $quoted_parts);
                    $is_enum_set_formatted = true;
                }
                
                if ($needs_length && !empty($field_length)) {
                    $escaped_length = $is_enum_set_formatted ? $field_length : $conn->real_escape_string($field_length);
                    $field_type_full = $field_type . '(' . $escaped_length . ')';
                } else {
                    $field_type_full = $field_type;
                }
                
                $field_null = isset($_POST['field_null']) ? 'NULL' : 'NOT NULL';
                $field_default = $_POST['field_default'] ?? '';
                $field_extra = $_POST['field_extra'] ?? '';
                $field_after = $_POST['field_after'] ?? '';
                
                $sql = "ALTER TABLE `$current_table` ADD COLUMN `$field_name` $field_type_full";
                if ($field_null === 'NULL') {
                    $sql .= " NULL";
                } else {
                    $sql .= " NOT NULL";
                }
                
                if (!empty($field_default)) {
                    $sql .= " DEFAULT " . ($field_default === 'NULL' ? "NULL" : "'$field_default'");
                }
                
                if (!empty($field_extra)) {
                    $sql .= " $field_extra";
                }
                
                if (!empty($field_after) && $field_after !== 'FIRST') {
                    $sql .= " AFTER `$field_after`";
                } elseif ($field_after === 'FIRST') {
                    $sql .= " FIRST";
                }
                
                if ($conn->query($sql)) {
                    $success = "Field '$field_name' added successfully!";
                    // Refresh structure
                    header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=struktur");
                    exit;
                } else {
                    $error = 'Error: ' . $conn->error;
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle edit field
if ($logged_in && isset($_POST['edit_field']) && $current_db && $current_table) {
    $old_field_name = $_POST['old_field_name'] ?? '';
    $field_name = $_POST['field_name'] ?? '';
    $field_type = $_POST['field_type'] ?? '';
    $field_length = isset($_POST['field_length']) ? trim($_POST['field_length']) : '';
    $field_null = isset($_POST['field_null']) ? 'NULL' : 'NOT NULL';
    $field_default = $_POST['field_default'] ?? '';
    $field_extra = $_POST['field_extra'] ?? '';
    
    if (empty($field_name) || empty($field_type) || empty($old_field_name)) {
        $error = 'Nama field dan type harus diisi';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                
                // Combine type and length if length is provided
                $types_with_length = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'ENUM', 'SET'];
                $type_upper = strtoupper($field_type);
                $needs_length = in_array($type_upper, $types_with_length);

                // Format ENUM/SET values if they don't have quotes
                $is_enum_set_formatted = false;
                if (($type_upper === 'ENUM' || $type_upper === 'SET') && !empty($field_length) && strpos($field_length, "'") === false) {
                    $parts = explode(',', $field_length);
                    $quoted_parts = array_map(function($p) use ($conn) {
                        return "'" . $conn->real_escape_string(trim($p)) . "'";
                    }, $parts);
                    $field_length = implode(',', $quoted_parts);
                    $is_enum_set_formatted = true;
                }
                
                if ($needs_length && !empty($field_length)) {
                    $escaped_length = $is_enum_set_formatted ? $field_length : $conn->real_escape_string($field_length);
                    $field_type_full = $field_type . "(" . $escaped_length . ")";
                } else {
                    $field_type_full = $field_type;
                }
                
                $sql = "ALTER TABLE `$current_table` MODIFY COLUMN `$field_name` $field_type_full";
                if ($field_null === 'NULL') {
                    $sql .= " NULL";
                } else {
                    $sql .= " NOT NULL";
                }
                
                if ($field_default !== '' && $field_default !== null) {
                    if (strtoupper(trim($field_default)) === 'NULL') {
                        $sql .= " DEFAULT NULL";
                    } else {
                        $escaped_default = $conn->real_escape_string($field_default);
                        $sql .= " DEFAULT '$escaped_default'";
                    }
                }
                
                if (!empty($field_extra)) {
                    $sql .= " $field_extra";
                }
                
                if ($conn->query($sql)) {
                    $success = "Field '$field_name' updated successfully!";
                    // Refresh structure
                    header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=struktur");
                    exit;
                } else {
                    $error = 'Error: ' . $conn->error;
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle add data
if ($logged_in && isset($_POST['add_data']) && $current_db && $current_table) {
    $data = $_POST['data'] ?? [];
    
    if (empty($data)) {
        $error = 'No data provided';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                
                // Get table structure to validate
                $result = $conn->query("DESCRIBE `$current_table`");
                $fields_info = [];
                while ($row = $result->fetch_assoc()) {
                    $fields_info[$row['Field']] = $row;
                }
                
                // Build INSERT query
                $columns = [];
                $values = [];
                $placeholders = [];
                
                foreach ($data as $field_name => $field_value) {
                    // Skip if field doesn't exist
                    if (!isset($fields_info[$field_name])) {
                        continue;
                    }
                    
                    $field_info = $fields_info[$field_name];
                    
                    // Skip AUTO_INCREMENT fields
                    if (stripos($field_info['Extra'], 'auto_increment') !== false) {
                        continue;
                    }
                    
                    // Handle empty values
                    if ($field_value === '' || $field_value === null) {
                        if ($field_info['Null'] === 'YES') {
                            $columns[] = "`$field_name`";
                            $placeholders[] = "NULL";
                        } elseif (!empty($field_info['Default'])) {
                            // Check if default is CURRENT_TIMESTAMP or similar MySQL functions
                            $default_upper = strtoupper($field_info['Default']);
                            if ($default_upper === 'CURRENT_TIMESTAMP' || $default_upper === 'CURRENT_TIMESTAMP()' || $default_upper === 'NOW()') {
                                // Use MySQL function directly
                                $columns[] = "`$field_name`";
                                $placeholders[] = "CURRENT_TIMESTAMP";
                            } else {
                                // Use default value - skip this field, MySQL will use default
                                continue;
                            }
                        } else {
                            // Required field is empty
                            $error = "Field '$field_name' cannot be empty";
                            $conn->close();
                            header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=data");
                            exit;
                        }
                    } else {
                        $columns[] = "`$field_name`";
                        $escaped_value = $conn->real_escape_string($field_value);
                        $placeholders[] = "'$escaped_value'";
                    }
                }
                
                if (empty($columns)) {
                    $error = 'No valid fields to insert';
                } else {
                    $sql = "INSERT INTO `$current_table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
                    
                    if ($conn->query($sql)) {
                        $success = "Data added successfully!";
                        // Refresh data
                        header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=data");
                        exit;
                    } else {
                        $error = 'Error: ' . $conn->error;
                        header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=data");
                        exit;
                    }
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=data");
            exit;
        }
    }
}

// Handle update data row
if ($logged_in && isset($_POST['update_data_row']) && $current_db && $current_table) {
    $pk_column = $_POST['pk_column'] ?? '';
    $pk_value = $_POST['pk_value'] ?? '';
    $data = $_POST['data'] ?? [];
    
    if (empty($pk_column) || empty($pk_value)) {
        $error = 'Primary Key missing, cannot update data';
    } elseif (empty($data)) {
        $error = 'No data changes';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                
                $set_clauses = [];
                foreach ($data as $field => $value) {
                    // Check if value is NULL (empty string with nullable field logic can be added here if needed)
                    // For now, treat empty string as empty string unless we add specific NULL handling in JS
                    $escaped_value = $conn->real_escape_string($value);
                    $set_clauses[] = "`$field` = '$escaped_value'";
                }
                
                $sql = "UPDATE `$current_table` SET " . implode(", ", $set_clauses) . 
                       " WHERE `$pk_column` = '" . $conn->real_escape_string($pk_value) . "'";
                
                if ($conn->query($sql)) {
                    $success = "Data updated successfully!";
                    header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=data");
                    exit;
                } else {
                    $error = 'Error updating data: ' . $conn->error;
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle delete data row
if ($logged_in && isset($_POST['delete_data_row']) && $current_db && $current_table) {
    $pk_column = $_POST['pk_column'] ?? '';
    $pk_value = $_POST['pk_value'] ?? '';
    
    if (empty($pk_column) || empty($pk_value)) {
        $error = 'Primary Key missing, cannot delete data';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                
                $sql = "DELETE FROM `$current_table` WHERE `$pk_column` = '" . $conn->real_escape_string($pk_value) . "'";
                
                if ($conn->query($sql)) {
                    $success = "Data deleted successfully!";
                    header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=data");
                    exit;
                } else {
                    $error = 'Error deleting data: ' . $conn->error;
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle delete field
if ($logged_in && isset($_POST['delete_field']) && $current_db && $current_table) {
    $field_name = $_POST['field_name'] ?? '';
    
    if (empty($field_name)) {
        $error = 'Nama field harus diisi';
    } else {
        try {
            $conn = getConnection();
            if ($conn) {
                $conn->select_db($current_db);
                $sql = "ALTER TABLE `$current_table` DROP COLUMN `$field_name`";
                
                if ($conn->query($sql)) {
                    $success = "Field '$field_name' deleted successfully!";
                    // Refresh structure
                    header("Location: ?db=" . urlencode($current_db) . "&table=" . urlencode($current_table) . "&tab=struktur");
                    exit;
                } else {
                    $error = 'Error: ' . $conn->error;
                }
                $conn->close();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Active tab
$active_tab = $_GET['tab'] ?? ($current_table ? 'struktur' : 'list');
if (!in_array($active_tab, ['struktur', 'data', 'query', 'create', 'list'])) {
    $active_tab = ($current_table ? 'struktur' : 'list');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Explorer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f0f0f0;
            height: 100vh;
            overflow: hidden;
        }
        
        .top-bar {
            background: #2d2d2d; /* Dark Gray */
            color: #f5f5f5; /* Very Light Gray text */
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #757575; /* Medium Gray border */
        }
        
        .top-bar h1 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .top-bar h1 i {
            font-size: 20px;
        }
        
        .user-info {
            font-size: 13px;
            color: #ecf0f1;
        }
        
        .main-container {
            display: flex;
            height: calc(100vh - 60px);
        }
        
        .sidebar {
            width: 250px;
            background: #f5f5f5; /* Very Light Gray */
            color: #333;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #c0c0c0;
            transition: width 0.3s ease;
            flex-shrink: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar.collapsed {
            width: 0;
            overflow: hidden;
            border: none;
        }
        
        .sidebar-section {
            display: flex;
            flex-direction: column;
            border-bottom: 1px solid #dcdcdc;
        }
        
        .sidebar-section.flex-grow {
            flex-grow: 1;
            overflow-y: auto;
            min-height: 0;
        }
        
        .sidebar-header-section {
            padding: 10px 15px;
            background: #e9ecef;
            color: #495057;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: 1px solid #fff;
            border-bottom: 1px solid #cbd3da;
            text-shadow: 0 1px 0 #fff;
        }
        
        .sidebar-header-section:hover {
            background: #dee2e6;
        }
        
        .db-list, .table-list {
            overflow-y: auto;
            max-height: 300px;
            transition: max-height 0.3s ease;
            background: #f6f7f8;
        }
        
        .sidebar-section.collapsed .db-list {
            max-height: 0;
            overflow: hidden;
        }

        .db-item, .table-item {
            border-bottom: none;
        }
        
        .db-header, .table-link {
            display: block;
            padding: 8px 15px;
            color: #333;
            text-decoration: none;
            transition: all 0.1s;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            border-left: 3px solid transparent;
        }
        
        .db-header:hover, .table-link:hover {
            background: #e0e0e0; /* Light Gray */
            text-decoration: none;
            color: #2d2d2d;
        }
        
        .db-header.active, .table-link.active {
            background: #d0d0d0; /* Slightly darker Light Gray */
            color: #2d2d2d;
            font-weight: 600;
            border-left-color: #757575; /* Medium Gray */
        }

        .table-item i {
            margin-right: 8px;
            font-size: 12px;
            color: #777;
        }
        
        .db-header i {
            color: #757575; /* Medium Gray */
            margin-right: 5px;
        }

        .sidebar-create-btn {
            display: block;
            padding: 10px 15px;
            background: #e9ecef;
            color: #333;
            text-decoration: none;
            border-top: 1px solid #dcdcdc;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
            text-align: center;
        }
        
        .sidebar-create-btn:hover {
            background: #dcdcdc;
            color: #000;
            text-decoration: none;
        }
        
        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 20px;
        }
        
        .table-card {
            padding: 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            transition: all 0.2s;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-card i {
            font-size: 16px;
            color: #757575;
            flex-shrink: 0;
        }
        
        .table-card:hover {
            background: #e0e0e0;
            border-color: #757575;
        }
        
        .table-card.active {
            background: #d0d0d0;
            border-color: #2d2d2d;
            color: #2d2d2d;
            font-weight: 500;
        }
        
        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: margin-left 0.3s ease;
        }
        
        .sidebar.hidden ~ .content-area {
            margin-left: -260px;
        }
        
        .info-bar {
            background: #e9ecef !important;
            padding: 18px 25px !important;
            border-bottom: 2px solid #dee2e6 !important;
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            align-items: flex-start !important;
            font-size: 14px !important;
            color: #555;
            min-height: 80px;
        }
        
        .info-bar-left {
            display: flex !important;
            flex-direction: column !important;
            gap: 10px !important;
            flex: 0 0 auto;
        }
        
        .info-bar .info-item {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 10px !important;
        }
        
        .info-bar .info-label {
            font-weight: 600;
            color: #333;
            min-width: 75px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }
        
        .info-bar .info-label i {
            font-size: 14px;
            color: #666;
        }
        
        .info-bar .info-value {
            color: #2d2d2d; /* Dark Gray */
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .info-bar .info-value:hover {
            color: #555;
            text-decoration: underline;
        }
        
        .info-bar .info-value i {
            font-size: 14px;
        }
        
        .info-bar .info-value.clickable {
            cursor: pointer;
        }
        
        .info-bar-right {
            text-align: right;
            padding-left: 30px;
            border-left: 2px solid #ddd;
            min-width: 140px;
            flex: 0 0 auto;
        }
        
        .row-count {
            white-space: nowrap;
            display: block;
        }
        
        .row-count-number {
            font-size: 32px !important;
            font-weight: 700 !important;
            color: #2d2d2d !important; /* Dark Gray */
            display: block !important;
            line-height: 1.2 !important;
            letter-spacing: -0.5px;
            margin-top: 3px;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-bottom: 2px solid #ddd;
            padding: 0 20px;
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            color: #666;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .tab i {
            font-size: 14px;
        }
        
        .tab:hover {
            color: #333;
            background: #e9ecef;
        }
        
        .tab.active {
            color: #333;
            border-bottom-color: #333;
            font-weight: 600;
        }
        
        .tab-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: white;
        }
        
        .btn {
            padding: 8px 16px;
            border: none; /* Removed border */
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            color: #2d2d2d;
            background: #bdbdbd; /* Darker gray background */
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15); /* Added shadow */
        }
        
        .btn:hover {
            background: #a6a6a6;
            color: #000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transform: translateY(-1px);
        }
        
        /* Resetting specific variables to rely on global .btn style where possible 
           but ensuring they don't break the new rule */
        .btn-primary, .btn-secondary, .btn-danger, .btn-add-premium {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        
        .btn-primary {
            background: #bdbdbd; 
        }
        .btn-primary:hover {
            background: #a6a6a6;
        }
        
        .btn-danger {
            background: #757575; /* Slightly darker for danger/delete to distinguish */
            color: #fff;
        }
        
        .btn-danger:hover {
            background: #555;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            animation: slideDown 0.3s;
        }
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        
        .close-modal:hover,
        .close-modal:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #999;
            box-shadow: 0 0 0 2px rgba(100, 100, 100, 0.2);
        }
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        
        .close-modal:hover,
        .close-modal:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #555;
            box-shadow: 0 0 0 2px rgba(85, 85, 85, 0.2);
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 13px;
        }
        
        input[type="text"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: monospace;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .alert-error {
            background: #f5f5f5;
            color: #2d2d2d;
            border: 1px solid #757575;
            border-left: 4px solid #333;
        }
        
        .alert-success {
            background: #f5f5f5;
            color: #2d2d2d;
            border: 1px solid #757575;
            border-left: 4px solid #757575;
        }
        
        .login-form {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .login-form h1 {
            margin-bottom: 10px;
            color: #2d2d2d;
        }
        
        .host-info {
            color: #666;
            font-size: 12px;
            margin-bottom: 20px;
        }
        
        .structure-table,
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            font-size: 13px;
        }
        
        .structure-table th,
        .structure-table td,
        .data-table th,
        .data-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .structure-table th,
        .data-table th {
            background: #e0e0e0; /* Light Gray */
            font-weight: 600;
            color: #2d2d2d;
            border-color: #c0c0c0;
            vertical-align: middle;
            padding: 12px 10px;
            line-height: 1.4;
        }
        
        .structure-table th:last-child,
        .structure-table td:last-child {
            text-align: center;
            width: 180px;
        }
        
        .field-edit {
            border: 1px solid #757575;
            border-radius: 3px;
            font-size: 13px;
        }
        
        .action-buttons,
        .save-cancel-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .save-cancel-buttons button {
            font-size: 11px;
            padding: 3px 8px;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 15px;
            opacity: 0.4;
            color: #999;
        }
        
        .empty-state-icon i {
            font-size: 64px;
        }
        
        .btn i {
            margin-right: 6px;
        }
        
        .row-count i {
            font-size: 14px;
            margin-right: 5px;
            opacity: 0.7;
        }
        .switch-container {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #666;
        }

        .btn-icon-simple {
            background: none;
            border: none;
            color: #ccc;
            font-size: 14px;
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s;
        }

        .btn-icon-simple:hover {
            color: #999;
        }

        .action-column {
            display: none;
        }

        .btn-add-premium {
            background: #bdbdbd;
            color: #2d2d2d;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-add-premium:hover {
            background: #a6a6a6;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            color: #000;
        }
        
        .btn-add-premium:active {
            transform: translateY(0);
            background: #c0c0c0;
        }


        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #555;
        }

        input:checked + .slider:before {
            transform: translateX(18px);
        }

        .row-clickable {
            cursor: pointer;
        }
        
        .row-clickable:hover {
            background-color: #f5f5f5 !important;
        }
    </style>
</head>
<body>
    <?php if (!$logged_in): ?>
        <div class="login-form">
            <h1>Database Explorer</h1>
            <p class="host-info">Host: 127.0.0.1</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error auto-hide"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success auto-hide"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>MySQL Username</label>
                    <input type="text" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label>MySQL Password</label>
                    <input type="password" name="password" required>
                </div>
                
                <button type="submit" name="login" class="btn btn-primary">Login</button>
            </form>
        </div>
    <?php else: ?>
        <div class="top-bar">
            <div style="display: flex; flex-direction: column;">
                <h1 style="margin: 0; line-height: 1; display: block;">DABLEX</h1>
                <span style="font-size: 10px; opacity: 0.8; font-weight: normal; letter-spacing: 1px;">Database Explorer</span>
            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <div class="user-info">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['db_user']); ?> | <i class="fas fa-server"></i> 127.0.0.1
                </div>
                <a href="?logout=1" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
        
        <div class="main-container">
            <!-- Sidebar -->
            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                
                <!-- Section 1: Databases -->
                <div class="sidebar-section <?php echo $current_db ? 'collapsed' : ''; ?>" id="db-section">
                    <div class="sidebar-header-section" onclick="toggleDbSection()">
                        <span><i class="fas fa-database"></i> Databases</span>
                        <i class="fas fa-chevron-down" id="db-toggle-icon" style="transform: <?php echo $current_db ? 'rotate(-90deg)' : 'rotate(0deg)'; ?>; transition: transform 0.2s;"></i>
                    </div>
                    <div class="db-list">
                        <?php foreach ($databases as $db): ?>
                            <?php $is_active = ($db === $current_db); ?>
                            <div class="db-item">
                                <a href="?db=<?php echo urlencode($db); ?>" class="db-header <?php echo $is_active ? 'active' : ''; ?>">
                                    <i class="fas fa-database" style="opacity: 0.7; margin-right: 5px;"></i>
                                    <?php echo htmlspecialchars($db); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section 2: Tables (Only if DB selected) -->
                <?php if ($current_db): ?>
                <div class="sidebar-section flex-grow">
                    <div class="sidebar-header-section" style="cursor: default;">
                        <span><i class="fas fa-table"></i> Tables</span>
                        <span class="badge" style="background: #e0e0e0; color: #2d2d2d; padding: 2px 6px; font-size: 10px; border-radius: 3px; border: 1px solid #ccc;"><?php echo count($tables); ?></span>
                    </div>
                    <div class="table-list" style="max-height: none;">
                        <?php if (empty($tables)): ?>
                            <div style="padding: 15px; color: #95a5a6; font-size: 12px; text-align: center;">
                                No tables
                            </div>
                        <?php else: ?>
                            <?php foreach ($tables as $table): ?>
                                <?php $is_table_active = ($table === $current_table); ?>
                                <div class="table-item">
                                    <a href="?db=<?php echo urlencode($current_db); ?>&table=<?php echo urlencode($table); ?>&tab=data" class="table-link <?php echo $is_table_active ? 'active' : ''; ?>">
                                        <i class="fas fa-table"></i>
                                        <?php echo htmlspecialchars($table); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- Create Table Button in Sidebar -->
                    <a href="?db=<?php echo urlencode($current_db); ?>&tab=create" class="sidebar-create-btn">
                        <i class="fas fa-plus-circle"></i> Create New Table
                    </a>
                </div>
                <?php endif; ?>

            </div>
            
            <script>
            function toggleDbSection() {
                var section = document.getElementById('db-section');
                var icon = document.getElementById('db-toggle-icon');
                section.classList.toggle('collapsed');
                if (section.classList.contains('collapsed')) {
                    icon.style.transform = 'rotate(-90deg)';
                } else {
                    icon.style.transform = 'rotate(0deg)';
                }
            }
            </script>
            
            <!-- Content Area -->
            <div class="content-area">
                <?php if ($current_db): ?>
                    <!-- Info Bar -->
                    <div class="info-bar" style="display: flex; flex-direction: row; justify-content: space-between; align-items: flex-start;">
                        <div class="info-bar-left" style="display: flex; flex-direction: column; gap: 10px;">
                            <div class="info-item" style="display: flex; flex-direction: row; align-items: center; gap: 10px;">
                                <a href="?db=<?php echo urlencode($current_db); ?>&show_list=1" class="info-value clickable" style="text-decoration: none; font-size: 18px; font-weight: 600;">
                                    <i class="fas fa-database" style="color: #757575;"></i> <?php echo htmlspecialchars($current_db); ?>
                                </a>
                            </div>
                            <div class="info-item" style="display: flex; flex-direction: row; align-items: center; gap: 10px;">
                                <?php if ($current_table): ?>
                                    <span class="info-value" style="font-size: 18px; font-weight: 600;"><i class="fas fa-table" style="color: #757575;"></i> <?php echo htmlspecialchars($current_table); ?></span>
                                <?php else: ?>
                                    <span class="info-value" style="color: #999; font-size: 18px;"><i class="fas fa-table" style="color: #ccc;"></i> -</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-bar-right" style="text-align: right; padding-left: 30px; border-left: 2px solid #ddd; min-width: 140px;">
                            <?php if ($current_table): ?>
                                <div class="row-count">
                                    <div style="font-size: 11px; color: #757575; margin-bottom: 2px;"><i class="fas fa-list-ol"></i> Total Rows</div>
                                    <span class="row-count-number" style="font-size: 20px; font-weight: 700; color: #2d2d2d; display: block; margin-top: 3px;"><?php echo number_format($table_row_count, 0, ',', '.'); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="row-count" style="opacity: 0.5;">
                                    <div style="font-size: 11px; color: #999; margin-bottom: 2px;"><i class="fas fa-list-ol"></i> Total Rows</div>
                                    <span class="row-count-number" style="font-size: 20px; font-weight: 700; color: #999; display: block; margin-top: 3px;">-</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!$current_table && $active_tab !== 'create'): ?>
                        <!-- Menu for List Tables -->
                        <div class="tabs">
                            <a href="?db=<?php echo urlencode($current_db); ?>&tab=query" 
                               class="tab <?php echo $active_tab === 'query' ? 'active' : ''; ?>">
                                <i class="fas fa-code"></i> Query SQL
                            </a>
                        </div>
                    <?php elseif ($active_tab === 'create'): ?>
                        <!-- No Tabs for Create Table -->
                    <?php else: ?>
                        <!-- Tabs for table view -->
                        <div class="tabs">
                            <a href="?db=<?php echo urlencode($current_db); ?>&table=<?php echo urlencode($current_table); ?>&tab=struktur" 
                               class="tab <?php echo $active_tab === 'struktur' ? 'active' : ''; ?>">
                                <i class="fas fa-sitemap"></i> Structure
                            </a>
                            <a href="?db=<?php echo urlencode($current_db); ?>&table=<?php echo urlencode($current_table); ?>&tab=data" 
                               class="tab <?php echo $active_tab === 'data' ? 'active' : ''; ?>">
                                <i class="fas fa-table"></i> Data
                            </a>
                            <a href="?db=<?php echo urlencode($current_db); ?>&tab=query" 
                               class="tab <?php echo $active_tab === 'query' ? 'active' : ''; ?>">
                                <i class="fas fa-code"></i> Query SQL
                            </a>
                            
                            <?php if ($active_tab === 'struktur' || $active_tab === 'data'): ?>
                                <div class="switch-container">
                                    <span>Enable Edit</span>
                                    <label class="switch">
                                        <input type="checkbox" id="editToggle">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="tab-content">
                    <?php if ($error): ?>
                        <div class="alert alert-error auto-hide"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success auto-hide"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($active_tab === 'query'): ?>
                        <h2 style="margin-bottom: 15px; color: #2d2d2d; font-size: 18px;">SQL Query</h2>
                        <?php if (empty($current_db)): ?>
                            <div class="alert alert-error">Please select a database from the sidebar first</div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="form-group">
                                    <label>SQL Query</label>
                                    <textarea name="sql_query" placeholder="SELECT * FROM table_name LIMIT 10" required><?php echo isset($_POST['sql_query']) ? htmlspecialchars($_POST['sql_query']) : ''; ?></textarea>
                                </div>
                                <button type="submit" name="execute_query" class="btn btn-add-premium">Execute Query</button>
                            </form>
                            <?php if ($query_error): ?>
                                <div class="alert alert-error auto-hide" style="margin-top: 20px;">
                                    <?php echo htmlspecialchars($query_error); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($query_result !== null && !empty($query_result)): ?>
                                <div style="overflow-x: auto; margin-top: 20px;">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <?php foreach ($query_columns as $col): ?>
                                                    <th><?php echo htmlspecialchars($col); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($query_result as $row): ?>
                                                <tr>
                                                    <?php foreach ($query_columns as $col): ?>
                                                        <td><?php echo htmlspecialchars($row[$col] ?? ''); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p style="margin-top: 10px; color: #666; font-size: 12px;">Total: <?php echo count($query_result); ?> baris</p>
                            <?php elseif ($query_result !== null && empty($query_result)): ?>
                                <p style="margin-top: 20px; color: #999;">Query executed successfully, no data returned.</p>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php elseif ($active_tab === 'create'): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="margin: 0; color: #2d2d2d; font-size: 18px;">Create New Table</h2>
                            <div style="display: flex; align-items: center;">
                                <input type="text" id="new-table-name" placeholder="Table Name" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; margin-right: 10px; width: 200px;">
                                <button type="button" onclick="submitCreateTable()" class="btn btn-add-premium">
                                    <i class="fas fa-plus-circle"></i> Create Table
                                </button>
                            </div>
                        </div>
                        <?php if (empty($current_db)): ?>
                            <div class="alert alert-error">Please select a database from the sidebar first</div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-error auto-hide" style="margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="POST" id="createTableForm">
                                <input type="hidden" name="table_name" id="table_name_input">
                                <table class="structure-table" id="create-table-fields">
                                    <thead><tr><th>Field</th><th>Type</th><th>Length</th><th>Null</th><th>Default</th><th>Key</th><th>Extra</th><th>Aksi</th></tr></thead>
                                    <tbody id="create-table-fields-body"><!-- Fields --></tbody>
                                </table>
                                <div style="margin-top: 20px;">
                                    <button type="button" class="btn btn-add-premium" onclick="addNewTableFieldRow()">
                                        <i class="fas fa-plus"></i> Add Field
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                        

                    
                    <?php elseif (($active_tab === 'list' || $show_table_list) && $current_db && !$current_table): ?>
                        <div class="empty-state" style="padding: 100px 20px;">
                            <div class="empty-state-icon" style="font-size: 64px; margin-bottom: 20px; color: #dfe6e9;"><i class="fas fa-table"></i></div>
                            <h2 style="color: #2d2d2d; font-size: 18px;">Database: <?php echo htmlspecialchars($current_db); ?></h2>
                            <p style="color: #7f8c8d; font-size: 16px;">Please select a table from the sidebar menu to view data.</p>
                            <p style="color: #bdc3c7; font-size: 13px; margin-top: 10px;">Total: <?php echo count($tables); ?> Tables</p>
                        </div>
                    
                    <?php elseif ($active_tab === 'struktur' && $current_table): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="margin: 0; color: #2d2d2d; font-size: 18px;">Table Structure</h2>
                            <button class="btn btn-add-premium" id="add-structure-field-btn" onclick="addNewFieldRow()" style="display: none;">
                                <i class="fas fa-plus"></i> Add Field
                            </button>
                        </div>
                        <?php if (!empty($table_structure)): ?>
                            <table class="structure-table">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Type</th>
                                        <th>Null</th>
                                        <th>Key</th>
                                        <th>Default</th>
                                        <th>Extra</th>
                                        <th class="action-column">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table_structure as $field): ?>
                                        <tr id="row-<?php echo htmlspecialchars($field['Field']); ?>" onclick="handleRowClick(event, '<?php echo htmlspecialchars($field['Field']); ?>')">
                                            <td>
                                                <span class="field-display" id="field-name-<?php echo htmlspecialchars($field['Field']); ?>">
                                                    <strong><?php echo htmlspecialchars($field['Field']); ?></strong>
                                                </span>
                                                <input type="text" name="field_name" value="<?php echo htmlspecialchars($field['Field']); ?>" 
                                                       class="field-edit" id="edit-field-name-<?php echo htmlspecialchars($field['Field']); ?>" 
                                                       style="display: none; width: 100%; padding: 5px;">
                                            </td>
                                            <td>
                                                <span class="field-display" id="field-type-<?php echo htmlspecialchars($field['Field']); ?>">
                                                    <?php echo htmlspecialchars($field['Type']); ?>
                                                </span>
                                                <div class="field-edit" id="edit-field-type-<?php echo htmlspecialchars($field['Field']); ?>" style="display: none;">
                                                    <?php
                                                    // Parse type and length
                                                    $type_value = $field['Type'];
                                                    $parsed_type = '';
                                                    $parsed_length = '';
                                                    if (preg_match('/^(\w+)\(([^)]+)\)$/', $type_value, $matches)) {
                                                        $parsed_type = $matches[1];
                                                        $parsed_length = $matches[2];
                                                    } else {
                                                        $parsed_type = $type_value;
                                                    }
                                                    
                                                    // Clean ENUM/SET values for display (remove quotes)
                                                    if (strtoupper($parsed_type) === 'ENUM' || strtoupper($parsed_type) === 'SET') {
                                                        $parsed_length = str_replace("','", ",", $parsed_length);
                                                        $parsed_length = str_replace("'", "", $parsed_length);
                                                    }
                                                    $types_with_length = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'ENUM', 'SET'];
                                                    $needs_length = in_array(strtoupper($parsed_type), $types_with_length);
                                                    ?>
                                                    <select name="field_type" 
                                                            style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px; margin-bottom: 8px; display: block;"
                                                            onchange="updateEditFieldLengthInput('<?php echo htmlspecialchars($field['Field']); ?>')">
                                                        <option value="">Select Type</option>
                                                        <option value="VARCHAR" <?php echo strtoupper($parsed_type) === 'VARCHAR' ? 'selected' : ''; ?>>VARCHAR</option>
                                                        <option value="CHAR" <?php echo strtoupper($parsed_type) === 'CHAR' ? 'selected' : ''; ?>>CHAR</option>
                                                        <option value="INT" <?php echo strtoupper($parsed_type) === 'INT' ? 'selected' : ''; ?>>INT</option>
                                                        <option value="BIGINT" <?php echo strtoupper($parsed_type) === 'BIGINT' ? 'selected' : ''; ?>>BIGINT</option>
                                                        <option value="SMALLINT" <?php echo strtoupper($parsed_type) === 'SMALLINT' ? 'selected' : ''; ?>>SMALLINT</option>
                                                        <option value="TINYINT" <?php echo strtoupper($parsed_type) === 'TINYINT' ? 'selected' : ''; ?>>TINYINT</option>
                                                        <option value="MEDIUMINT" <?php echo strtoupper($parsed_type) === 'MEDIUMINT' ? 'selected' : ''; ?>>MEDIUMINT</option>
                                                        <option value="DECIMAL" <?php echo strtoupper($parsed_type) === 'DECIMAL' ? 'selected' : ''; ?>>DECIMAL</option>
                                                        <option value="FLOAT" <?php echo strtoupper($parsed_type) === 'FLOAT' ? 'selected' : ''; ?>>FLOAT</option>
                                                        <option value="DOUBLE" <?php echo strtoupper($parsed_type) === 'DOUBLE' ? 'selected' : ''; ?>>DOUBLE</option>
                                                        <option value="DATE" <?php echo strtoupper($parsed_type) === 'DATE' ? 'selected' : ''; ?>>DATE</option>
                                                        <option value="DATETIME" <?php echo strtoupper($parsed_type) === 'DATETIME' ? 'selected' : ''; ?>>DATETIME</option>
                                                        <option value="TIMESTAMP" <?php echo strtoupper($parsed_type) === 'TIMESTAMP' ? 'selected' : ''; ?>>TIMESTAMP</option>
                                                        <option value="TIME" <?php echo strtoupper($parsed_type) === 'TIME' ? 'selected' : ''; ?>>TIME</option>
                                                        <option value="YEAR" <?php echo strtoupper($parsed_type) === 'YEAR' ? 'selected' : ''; ?>>YEAR</option>
                                                        <option value="TEXT" <?php echo strtoupper($parsed_type) === 'TEXT' ? 'selected' : ''; ?>>TEXT</option>
                                                        <option value="TINYTEXT" <?php echo strtoupper($parsed_type) === 'TINYTEXT' ? 'selected' : ''; ?>>TINYTEXT</option>
                                                        <option value="MEDIUMTEXT" <?php echo strtoupper($parsed_type) === 'MEDIUMTEXT' ? 'selected' : ''; ?>>MEDIUMTEXT</option>
                                                        <option value="LONGTEXT" <?php echo strtoupper($parsed_type) === 'LONGTEXT' ? 'selected' : ''; ?>>LONGTEXT</option>
                                                        <option value="BLOB" <?php echo strtoupper($parsed_type) === 'BLOB' ? 'selected' : ''; ?>>BLOB</option>
                                                        <option value="TINYBLOB" <?php echo strtoupper($parsed_type) === 'TINYBLOB' ? 'selected' : ''; ?>>TINYBLOB</option>
                                                        <option value="MEDIUMBLOB" <?php echo strtoupper($parsed_type) === 'MEDIUMBLOB' ? 'selected' : ''; ?>>MEDIUMBLOB</option>
                                                        <option value="LONGBLOB" <?php echo strtoupper($parsed_type) === 'LONGBLOB' ? 'selected' : ''; ?>>LONGBLOB</option>
                                                        <option value="BOOLEAN" <?php echo strtoupper($parsed_type) === 'BOOLEAN' ? 'selected' : ''; ?>>BOOLEAN</option>
                                                        <option value="JSON" <?php echo strtoupper($parsed_type) === 'JSON' ? 'selected' : ''; ?>>JSON</option>
                                                        <option value="ENUM" <?php echo strtoupper($parsed_type) === 'ENUM' ? 'selected' : ''; ?>>ENUM</option>
                                                        <option value="SET" <?php echo strtoupper($parsed_type) === 'SET' ? 'selected' : ''; ?>>SET</option>
                                                    </select>
                                                    <input type="text" name="field_length" 
                                                           id="edit-field-length-<?php echo htmlspecialchars($field['Field']); ?>"
                                                           value="<?php echo htmlspecialchars($parsed_length); ?>"
                                                           placeholder="255" 
                                                           style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px; display: <?php echo $needs_length ? 'block' : 'none'; ?>;">
                                                </div>
                                            </td>
                                            <td>
                                                <span class="field-display" id="field-null-<?php echo htmlspecialchars($field['Field']); ?>">
                                                    <?php echo htmlspecialchars($field['Null']); ?>
                                                </span>
                                                <select name="field_null" class="field-edit" id="edit-field-null-<?php echo htmlspecialchars($field['Field']); ?>" 
                                                        style="display: none; width: 100%; padding: 5px;">
                                                    <option value="YES" <?php echo $field['Null'] === 'YES' ? 'selected' : ''; ?>>YES</option>
                                                    <option value="NO" <?php echo $field['Null'] === 'NO' ? 'selected' : ''; ?>>NO</option>
                                                </select>
                                            </td>
                                            <td><?php echo htmlspecialchars($field['Key']); ?></td>
                                            <td>
                                                <span class="field-display" id="field-default-<?php echo htmlspecialchars($field['Field']); ?>">
                                                    <?php echo htmlspecialchars($field['Default'] ?? 'NULL'); ?>
                                                </span>
                                                <input type="text" name="field_default" value="<?php echo htmlspecialchars($field['Default'] ?? ''); ?>" 
                                                       class="field-edit" id="edit-field-default-<?php echo htmlspecialchars($field['Field']); ?>" 
                                                       placeholder="NULL" style="display: none; width: 100%; padding: 5px;">
                                            </td>
                                            <td>
                                                <span class="field-display" id="field-extra-<?php echo htmlspecialchars($field['Field']); ?>">
                                                    <?php echo htmlspecialchars($field['Extra']); ?>
                                                </span>
                                                <select name="field_extra" class="field-edit" id="edit-field-extra-<?php echo htmlspecialchars($field['Field']); ?>" 
                                                        style="display: none; width: 100%; padding: 5px;">
                                                    <option value="">-</option>
                                                    <option value="AUTO_INCREMENT" <?php echo $field['Extra'] === 'auto_increment' ? 'selected' : ''; ?>>AUTO_INCREMENT</option>
                                                </select>
                                            </td>

                                            <td class="action-column">
                                                <div class="action-buttons" id="actions-<?php echo htmlspecialchars($field['Field']); ?>">
                                                    <!-- Edit button removed -->
                                                    <button type="button" class="btn-icon-simple" onclick="deleteField('<?php echo htmlspecialchars($field['Field']); ?>')" title="Delete Field">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="save-cancel-buttons" id="save-cancel-<?php echo htmlspecialchars($field['Field']); ?>" style="display: none;">
                                                    <button type="button" class="action-btn action-btn-edit" onclick="saveEdit('<?php echo htmlspecialchars($field['Field']); ?>')">
                                                        <i class="fas fa-save"></i> Save
                                                    </button>
                                                    <button type="button" class="action-btn" onclick="cancelEdit('<?php echo htmlspecialchars($field['Field']); ?>')" style="background: #999; color: white;">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <!-- New field row (hidden by default) -->
                                    <tr id="new-field-row" style="display: none; background: #f8f9fa;">
                                        <td>
                                            <input type="text" name="new_field_name" id="new-field-name" 
                                                   placeholder="field_name" style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;">
                                        </td>
                                        <td>
                                            <select name="new_field_type" id="new-field-type" 
                                                    style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px; margin-bottom: 5px;"
                                                    onchange="updateNewFieldLengthInput()">
                                                <option value="">Select Type</option>
                                                <option value="VARCHAR">VARCHAR</option>
                                                <option value="CHAR">CHAR</option>
                                                <option value="INT">INT</option>
                                                <option value="BIGINT">BIGINT</option>
                                                <option value="SMALLINT">SMALLINT</option>
                                                <option value="TINYINT">TINYINT</option>
                                                <option value="MEDIUMINT">MEDIUMINT</option>
                                                <option value="DECIMAL">DECIMAL</option>
                                                <option value="FLOAT">FLOAT</option>
                                                <option value="DOUBLE">DOUBLE</option>
                                                <option value="DATE">DATE</option>
                                                <option value="DATETIME">DATETIME</option>
                                                <option value="TIMESTAMP">TIMESTAMP</option>
                                                <option value="TIME">TIME</option>
                                                <option value="YEAR">YEAR</option>
                                                <option value="TEXT">TEXT</option>
                                                <option value="TINYTEXT">TINYTEXT</option>
                                                <option value="MEDIUMTEXT">MEDIUMTEXT</option>
                                                <option value="LONGTEXT">LONGTEXT</option>
                                                <option value="BLOB">BLOB</option>
                                                <option value="TINYBLOB">TINYBLOB</option>
                                                <option value="MEDIUMBLOB">MEDIUMBLOB</option>
                                                <option value="LONGBLOB">LONGBLOB</option>
                                                <option value="BOOLEAN">BOOLEAN</option>
                                                <option value="JSON">JSON</option>
                                                <option value="ENUM">ENUM</option>
                                                <option value="SET">SET</option>
                                            </select>
                                            <input type="text" name="new_field_length" id="new-field-length" 
                                                   placeholder="255" 
                                                   style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px; display: none;">
                                        </td>
                                        <td>
                                            <select name="new_field_null" id="new-field-null" style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;">
                                                <option value="YES">YES</option>
                                                <option value="NO">NO</option>
                                            </select>
                                        </td>
                                        <td>-</td>
                                        <td>
                                            <input type="text" name="new_field_default" id="new-field-default" 
                                                   placeholder="NULL" style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;">
                                        </td>
                                        <td>
                                            <select name="new_field_extra" id="new-field-extra" style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;">
                                                <option value="">-</option>
                                                <option value="AUTO_INCREMENT">AUTO_INCREMENT</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="save-cancel-buttons">
                                                <button type="button" class="action-btn action-btn-edit" onclick="saveStructureField()">
                                                    <i class="fas fa-save"></i> Save
                                                </button>

                                                <button type="button" class="action-btn" onclick="cancelNewField()" style="background: #999; color: white;">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        <!-- INDEX MANAGEMENT SECTION -->
                        <div style="margin-top: 40px; margin-bottom: 20px;">
                            <h3 style="color: #2d2d2d; border-bottom: 2px solid #eee; padding-bottom: 10px;">Table Indexes</h3>
                            
                            <?php if (!empty($table_indexes)): ?>
                                <table class="structure-table" style="margin-top: 15px;">
                                    <thead>
                                        <tr>
                                            <th>Key Name</th>
                                            <th>Type</th>
                                            <th>Unique</th>
                                            <th>Columns</th>
                                            <th>Comment</th>
                                            <th class="action-column">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($table_indexes as $idx): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($idx['Key_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($idx['Index_type']); ?></td>
                                                <td>
                                                    <?php if ($idx['Non_unique'] == 0): ?>
                                                        <span class="badge badge-success">Unique</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(implode(', ', $idx['Columns'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($idx['Comment']); ?></td>
                                                <td class="action-cell">
                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this index?');" style="display:inline;">
                                                        <input type="hidden" name="delete_index" value="1">
                                                        <input type="hidden" name="index_name" value="<?php echo htmlspecialchars($idx['Key_name']); ?>">
                                                        <button type="submit" class="btn-icon-simple js-index-delete-btn" title="Delete Index" style="display: none;">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="color: #666; margin-top: 10px;">No indexes on this table yet.</p>
                            <?php endif; ?>
                            
                            <!-- ADD INDEX FORM -->
                            <div class="card" id="add-index-card" style="margin-top: 25px; padding: 20px; border: 1px solid #e0e0e0; background: #fff; border-radius: 8px; display: none;">
                                <h4 style="margin-top: 0; margin-bottom: 15px; color: #2d2d2d;">Add New Index</h4>
                                <form method="POST" id="addIndexForm">
                                    <input type="hidden" name="add_index" value="1">
                                    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                                        <div style="flex: 1;">
                                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Index Name (Optional)</label>
                                            <input type="text" name="index_name" placeholder="idx_name" 
                                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Index Type</label>
                                            <select name="index_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                                <option value="INDEX">INDEX</option>
                                                <option value="UNIQUE">UNIQUE</option>
                                                <option value="PRIMARY">PRIMARY</option>
                                                <option value="FULLTEXT">FULLTEXT</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Index Columns</label>
                                    <div id="index-columns-container">
                                        <!-- Dynamic Index Columns -->
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; margin-bottom: 20px;">
                                        <button type="button" class="btn btn-add-premium" onclick="addIndexColumnRow()">
                                            <i class="fas fa-plus"></i> Add Column
                                        </button>
                                        
                                        <button type="submit" class="btn btn-add-premium">
                                            <i class="fas fa-save"></i> Save Index
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-sitemap"></i></div>
                                <p>No table structure</p>
                            </div>
                        <?php endif; ?>
                    
                    <?php elseif ($active_tab === 'data' && $current_table): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="margin: 0; color: #2d2d2d; font-size: 18px;">Table Data</h2>
                            <div style="display: flex; gap: 10px;">
                                <button class="btn btn-add-premium" onclick="openAddDataModal()">
                                    <i class="fas fa-plus"></i> Add Data
                                </button>
                                <button class="btn btn-add-premium" onclick="toggleSearch()" style="background: #e0e0e0; color: #2d2d2d;">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($table_data) || !empty($filters)): ?>

                            <?php 
                            // Find Primary Key
                            $pk_column = '';
                            foreach ($table_structure as $field) {
                                if ($field['Key'] === 'PRI') {
                                    $pk_column = $field['Field'];
                                    break;
                                }
                            }
                            ?>
                            <div class="table-responsive" style="overflow-x: auto; max-width: 100%; border: 1px solid #ddd; border-radius: 4px; max-height: 75vh;">
                                <table class="data-table" style="margin-top: 0; min-width: 100%;">
                                    <thead style="position: sticky; top: 0; background: #f5f5f5; z-index: 10;">
                                        <tr>
                                            <?php foreach ($table_data_columns as $col): ?>
                                                <th><?php echo htmlspecialchars($col); ?></th>
                                            <?php endforeach; ?>
                                            <th class="action-column">Aksi</th>
                                        </tr>
                                        <!-- Filter Row -->
                                        <tr id="filter-row" style="display: <?php echo !empty($filters) ? 'table-row' : 'none'; ?>; background: #e3f2fd;">
                                            <?php foreach ($table_data_columns as $col): ?>
                                                <td style="padding: 5px;">
                                                    <input type="text" 
                                                           class="filter-input" 
                                                           data-col="<?php echo htmlspecialchars($col); ?>"
                                                           value="<?php echo isset($filters[$col]) ? htmlspecialchars($filters[$col]) : ''; ?>"
                                                           placeholder="Filter..." 
                                                           style="width: 100%; padding: 4px; border: 1px solid #757575; border-radius: 3px; font-size: 12px;">
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="action-column"></td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($table_data)): ?>
                                            <tr>
                                                <td colspan="<?php echo count($table_data_columns) + 1; ?>" style="text-align: center; padding: 30px; color: #999;">
                                                    <i class="fas fa-search" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                                    No data matches the search filter.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($table_data as $i => $row): ?>
                                                <?php 
                                                // Determine Row Identifier (PK or Index)
                                                $row_id = $pk_column && isset($row[$pk_column]) ? $row[$pk_column] : 'idx-' . $i;
                                            $row_id_safe = htmlspecialchars($row_id);
                                            ?>
                                            <tr id="data-row-<?php echo $row_id_safe; ?>" 
                                                onclick="handleDataRowClick(event, '<?php echo $row_id_safe; ?>', '<?php echo htmlspecialchars(json_encode($row)); ?>')">
                                                
                                                <?php foreach ($table_data_columns as $col): ?>
                                                    <td>
                                                        <span class="data-display" id="data-disp-<?php echo $row_id_safe; ?>-<?php echo htmlspecialchars($col); ?>">
                                                            <?php echo htmlspecialchars($row[$col] ?? ''); ?>
                                                        </span>
                                                        <input type="text" 
                                                               class="data-edit" 
                                                               id="data-edit-<?php echo $row_id_safe; ?>-<?php echo htmlspecialchars($col); ?>"
                                                               name="data[<?php echo htmlspecialchars($col); ?>]"
                                                               value="<?php echo htmlspecialchars($row[$col] ?? ''); ?>"
                                                               style="display: none; width: 100%; min-width: 80px; padding: 4px; border: 1px solid #757575; border-radius: 3px;">
                                                    </td>
                                                <?php endforeach; ?>
                                                
                                                <td class="action-column">
                                                    <div class="action-buttons" id="data-actions-<?php echo $row_id_safe; ?>">
                                                        <button type="button" class="btn-icon-simple" onclick="deleteDataRow('<?php echo $row_id_safe; ?>')" title="Delete Row">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    <div class="save-cancel-buttons" id="data-save-cancel-<?php echo $row_id_safe; ?>" style="display: none; flex-direction: column; gap: 5px;">
                                                        <button type="button" class="action-btn action-btn-edit" onclick="saveDataEdit('<?php echo $row_id_safe; ?>')">
                                                            <i class="fas fa-save"></i>
                                                        </button>
                                                        <button type="button" class="action-btn" onclick="cancelDataEdit('<?php echo $row_id_safe; ?>')" style="background: #999; color: white;">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination Controls -->
                            <div class="pagination-controls" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                                <div class="pagination-info" style="font-size: 13px; color: #666;">
                                    Page <strong><?php echo $page; ?></strong> of <strong><?php echo max(1, $total_pages); ?></strong>
                                    (Total: <?php echo number_format($table_row_count); ?> Rows)
                                </div>
                                <div class="pagination-buttons" style="display: flex; gap: 5px;">
                                    <?php 
                                    // Build query string for pagination preserving filters
                                    $query_params = $_GET;
                                    $query_params['db'] = $current_db;
                                    $query_params['table'] = $current_table;
                                    $query_params['tab'] = 'data';
                                    ?>
                                    
                                    <?php if ($page > 1): ?>
                                        <?php $query_params['page'] = $page - 1; ?>
                                        <a href="?<?php echo http_build_query($query_params); ?>" class="btn btn-add-premium" style="font-size: 12px; padding: 6px 12px;">
                                            <i class="fas fa-chevron-left"></i> Prev
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-add-premium" style="font-size: 12px; padding: 6px 12px; opacity: 0.5; cursor: not-allowed;" disabled>
                                            <i class="fas fa-chevron-left"></i> Prev
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <?php $query_params['page'] = $page + 1; ?>
                                        <a href="?<?php echo http_build_query($query_params); ?>" class="btn btn-add-premium" style="font-size: 12px; padding: 6px 12px;">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-add-premium" style="font-size: 12px; padding: 6px 12px; opacity: 0.5; cursor: not-allowed;" disabled>
                                            Next <i class="fas fa-chevron-right"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Hidden input to store PK Column Name for JS -->
                            <input type="hidden" id="pk-column-name" value="<?php echo htmlspecialchars($pk_column); ?>">
                            
                            <p style="margin-top: 15px; color: #666; font-size: 12px;">
                                Showing <?php echo count($table_data); ?> rows (max 100). 
                                <?php if (!$pk_column): ?>
                                    <span style="color: orange;"><i class="fas fa-exclamation-triangle"></i> This table has no Primary Key. Editing may not be accurate.</span>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-database"></i></div>
                                <p>Tidak ada data dalam tabel</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Add Data Modal -->
                        <div id="addDataModal" class="modal" style="display: none;">
                            <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #757575; padding-bottom: 10px;">
                                    <h2 style="margin: 0; color: #2d2d2d;"><i class="fas fa-plus-circle"></i> Add Data</h2>
                                    <span class="close-modal" onclick="closeAddDataModal()" style="cursor: pointer; font-size: 28px; font-weight: bold; color: #999;">&times;</span>
                                </div>
                                <form id="addDataForm" method="POST">
                                    <input type="hidden" name="add_data" value="1">
                                    <div id="addDataFields">
                                        <?php if (!empty($table_structure)): ?>
                                            <?php foreach ($table_structure as $field): ?>
                                                <?php
                                                // Skip AUTO_INCREMENT fields
                                                if (stripos($field['Extra'], 'auto_increment') !== false) {
                                                    continue;
                                                }
                                                
                                                $field_name = $field['Field'];
                                                $field_type = $field['Type'];
                                                $field_null = $field['Null'];
                                                $field_default = $field['Default'];
                                                
                                                // Parse type
                                                $type_upper = strtoupper($field_type);
                                                $is_enum = preg_match('/^ENUM\((.*)\)$/i', $field_type, $enum_matches);
                                                $is_set = preg_match('/^SET\((.*)\)$/i', $field_type, $set_matches);
                                                $is_boolean = in_array($type_upper, ['BOOLEAN', 'BOOL', 'TINYINT(1)']);
                                                $is_text = in_array($type_upper, ['TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB']);
                                                $is_number = preg_match('/^(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT|DECIMAL|FLOAT|DOUBLE|NUMERIC)/i', $field_type);
                                                $is_date = in_array($type_upper, ['DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR']);
                                                
                                                // Get field length for input width
                                                $field_length = 0;
                                                if (preg_match('/\((\d+)\)/', $field_type, $length_matches)) {
                                                    $field_length = (int)$length_matches[1];
                                                }
                                                $input_width = min(max($field_length * 8, 150), 500);
                                                ?>
                                                <div class="form-group" style="margin-bottom: 15px;">
                                                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                                        <?php echo htmlspecialchars($field_name); ?>
                                                        <?php if ($field_null === 'NO' && empty($field_default)): ?>
                                                            <span style="color: #555;">*</span>
                                                        <?php endif; ?>
                                                        <span style="font-size: 11px; color: #666; font-weight: normal;">(<?php echo htmlspecialchars($field_type); ?>)</span>
                                                    </label>
                                                    
                                                    <?php if ($is_enum || $is_set): ?>
                                                        <?php
                                                        $values_str = $is_enum ? $enum_matches[1] : $set_matches[1];
                                                        $values = array_map(function($v) {
                                                            return trim($v, "'\"");
                                                        }, explode(',', $values_str));
                                                        ?>
                                                        <select name="data[<?php echo htmlspecialchars($field_name); ?>]" 
                                                                class="form-input"
                                                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                                                <?php echo ($field_null === 'NO' && empty($field_default)) ? 'required' : ''; ?>>
                                                            <option value="">-- Select --</option>
                                                            <?php foreach ($values as $val): ?>
                                                                <option value="<?php echo htmlspecialchars($val); ?>" 
                                                                        <?php echo ($field_default === $val) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($val); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php elseif ($is_boolean): ?>
                                                        <select name="data[<?php echo htmlspecialchars($field_name); ?>]" 
                                                                class="form-input"
                                                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                                                <?php echo ($field_null === 'NO' && empty($field_default)) ? 'required' : ''; ?>>
                                                            <option value="">-- Select --</option>
                                                            <option value="1" <?php echo ($field_default === '1' || $field_default === 'true') ? 'selected' : ''; ?>>True</option>
                                                            <option value="0" <?php echo ($field_default === '0' || $field_default === 'false') ? 'selected' : ''; ?>>False</option>
                                                        </select>
                                                    <?php elseif ($is_text): ?>
                                                        <textarea name="data[<?php echo htmlspecialchars($field_name); ?>]" 
                                                                  class="form-input"
                                                                  rows="4"
                                                                  style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;"
                                                                  <?php echo ($field_null === 'NO' && empty($field_default)) ? 'required' : ''; ?>><?php echo htmlspecialchars($field_default ?? ''); ?></textarea>
                                                    <?php elseif ($is_date): ?>
                                                        <?php
                                                        $input_type = 'text';
                                                        if ($type_upper === 'DATE') $input_type = 'date';
                                                        elseif ($type_upper === 'DATETIME' || $type_upper === 'TIMESTAMP') $input_type = 'datetime-local';
                                                        elseif ($type_upper === 'TIME') $input_type = 'time';
                                                        elseif ($type_upper === 'YEAR') $input_type = 'number';
                                                        
                                                        // Handle CURRENT_TIMESTAMP and other MySQL functions
                                                        $date_value = '';
                                                        if (!empty($field_default) && strtoupper($field_default) !== 'CURRENT_TIMESTAMP' && strtoupper($field_default) !== 'CURRENT_TIMESTAMP()' && strtoupper($field_default) !== 'NOW()') {
                                                            $date_value = htmlspecialchars($field_default);
                                                        }
                                                        ?>
                                                        <input type="<?php echo $input_type; ?>" 
                                                               name="data[<?php echo htmlspecialchars($field_name); ?>]" 
                                                               class="form-input"
                                                               value="<?php echo $date_value; ?>"
                                                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                                               <?php echo ($field_null === 'NO' && empty($field_default)) ? 'required' : ''; ?>>
                                                    <?php elseif ($is_number): ?>
                                                        <input type="number" 
                                                               step="<?php echo (strpos($type_upper, 'DECIMAL') !== false || strpos($type_upper, 'FLOAT') !== false || strpos($type_upper, 'DOUBLE') !== false) ? 'any' : '1'; ?>"
                                                               name="data[<?php echo htmlspecialchars($field_name); ?>]" 
                                                               class="form-input"
                                                               value="<?php echo htmlspecialchars($field_default ?? ''); ?>"
                                                               style="width: <?php echo $input_width; ?>px; max-width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                                               <?php echo ($field_null === 'NO' && empty($field_default)) ? 'required' : ''; ?>>
                                                    <?php else: ?>
                                                        <input type="text" 
                                                               name="data[<?php echo htmlspecialchars($field_name); ?>]" 
                                                               class="form-input"
                                                               value="<?php echo htmlspecialchars($field_default ?? ''); ?>"
                                                               maxlength="<?php echo $field_length > 0 ? $field_length : ''; ?>"
                                                               style="width: <?php echo $input_width > 0 ? $input_width : '100%'; ?>px; max-width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                                               <?php echo ($field_null === 'NO' && empty($field_default)) ? 'required' : ''; ?>>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($field_default) && $field_default !== 'NULL'): ?>
                                                        <small style="color: #666; font-size: 11px;">
                                                            Default: <?php 
                                                            if (strtoupper($field_default) === 'CURRENT_TIMESTAMP' || strtoupper($field_default) === 'CURRENT_TIMESTAMP()' || strtoupper($field_default) === 'NOW()') {
                                                                echo 'Current Timestamp';
                                                            } else {
                                                                echo htmlspecialchars($field_default);
                                                            }
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                                        <button type="button" class="btn btn-secondary" onclick="closeAddDataModal()" style="padding: 10px 20px;">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                        <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    

                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-database"></i></div>
                                <p>Select a database and table from the sidebar to begin</p>
                            </div>
                        <?php endif; ?>
                </div>
            </div>
        </div>
        
    <?php endif; ?>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            
            sidebar.classList.toggle('hidden');
            
            if (sidebar.classList.contains('hidden')) {
                sessionStorage.setItem('sidebarHidden', 'true');
            } else {
                sessionStorage.setItem('sidebarHidden', 'false');
            }
        }
        
        // Restore sidebar state from sessionStorage
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarHidden = sessionStorage.getItem('sidebarHidden') === 'true';
            if (sidebarHidden) {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.add('hidden');
            }
            
            // Auto-hide error and success messages after 5 seconds
            const autoHideMessages = document.querySelectorAll('.auto-hide');
            autoHideMessages.forEach(function(message) {
                setTimeout(function() {
                    message.style.transition = 'opacity 0.5s ease-out';
                    message.style.opacity = '0';
                    setTimeout(function() {
                        message.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
        
        // Store original values for cancel
        const originalValues = {};
        
        let isEditEnabled = false;

        function handleRowClick(event, fieldName) {
            if (!isEditEnabled) return;
            
            // Prevent triggering when clicking on interactive elements
            if (event.target.tagName === 'BUTTON' || 
                event.target.tagName === 'INPUT' || 
                event.target.tagName === 'SELECT' || 
                event.target.tagName === 'A' ||
                event.target.closest('button') ||
                event.target.closest('.field-edit') ||
                event.target.closest('.action-buttons') ||
                event.target.closest('.save-cancel-buttons')) {
                return;
            }
            
            // Check if already editing this field
            const editNameInput = document.getElementById('edit-field-name-' + fieldName);
            if (editNameInput && editNameInput.style.display !== 'none') {
                return; // Already editing
            }
            
            startEdit(fieldName);
        }
        
        // Data Table Inline Edit Functions
        function handleDataRowClick(event, rowId, rowData) {
            if (!isEditEnabled) return;
            
            // Prevent triggering when clicking on interactive elements
            if (event.target.tagName === 'BUTTON' || 
                event.target.tagName === 'INPUT' || 
                event.target.tagName === 'SELECT' || 
                event.target.tagName === 'A' ||
                event.target.closest('button') ||
                event.target.closest('.data-edit') ||
                event.target.closest('.action-buttons') ||
                event.target.closest('.save-cancel-buttons')) {
                return;
            }
            
            // Check if already editing this row
            const dataActions = document.getElementById('data-actions-' + rowId);
            if (dataActions && dataActions.style.display === 'none') {
                return; // Already editing
            }
            
            startDataEdit(rowId);
        }
        
        function startDataEdit(rowId) {
            // Hide display spans, show inputs
            const row = document.getElementById('data-row-' + rowId);
            const displays = row.querySelectorAll('.data-display');
            const edits = row.querySelectorAll('.data-edit');
            
            displays.forEach(el => el.style.display = 'none');
            edits.forEach(el => el.style.display = 'block');
            
            // Toggle buttons
            document.getElementById('data-actions-' + rowId).style.display = 'none';
            document.getElementById('data-save-cancel-' + rowId).style.display = 'flex';
        }
        
        function cancelDataEdit(rowId) {
            // Show display spans, hide inputs
            const row = document.getElementById('data-row-' + rowId);
            const displays = row.querySelectorAll('.data-display');
            const edits = row.querySelectorAll('.data-edit');
            
            displays.forEach(el => el.style.display = 'inline');
            edits.forEach(el => el.style.display = 'none');
            
            // Toggle buttons
            document.getElementById('data-actions-' + rowId).style.display = 'flex';
            document.getElementById('data-save-cancel-' + rowId).style.display = 'none';
        }
        
        function saveDataEdit(rowId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const row = document.getElementById('data-row-' + rowId);
            const edits = row.querySelectorAll('.data-edit');
            
            edits.forEach(input => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = input.name;
                hiddenInput.value = input.value;
                form.appendChild(hiddenInput);
            });
            
            // Add PK info
            const pkColumn = document.getElementById('pk-column-name').value;
            if (pkColumn) {
                const pkInput = document.createElement('input');
                pkInput.type = 'hidden';
                pkInput.name = 'pk_column';
                pkInput.value = pkColumn;
                form.appendChild(pkInput);
                
                const pkValue = rowId; // Assuming rowId is the PK value
                const pkValueInput = document.createElement('input');
                pkValueInput.type = 'hidden';
                pkValueInput.name = 'pk_value';
                pkValueInput.value = pkValue;
                form.appendChild(pkValueInput);
            }
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'update_data_row';
            actionInput.value = '1';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteDataRow(rowId) {
            if (confirm('Are you sure you want to delete this data?\n\nThis action cannot be undone!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const pkColumn = document.getElementById('pk-column-name').value;
                if (pkColumn) {
                    const pkInput = document.createElement('input');
                    pkInput.type = 'hidden';
                    pkInput.name = 'pk_column';
                    pkInput.value = pkColumn;
                    form.appendChild(pkInput);
                    
                    const pkValue = rowId;
                    const pkValueInput = document.createElement('input');
                    pkValueInput.type = 'hidden';
                    pkValueInput.name = 'pk_value';
                    pkValueInput.value = pkValue;
                    form.appendChild(pkValueInput);
                }
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'delete_data_row';
                actionInput.value = '1';
                form.appendChild(actionInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Add new field row functions
        function addNewFieldRow() {
            const newRow = document.getElementById('new-field-row');
            if (newRow) {
                newRow.style.display = 'table-row';
                document.getElementById('new-field-name').focus();
            }
        }
        
        function cancelNewField() {
            const newRow = document.getElementById('new-field-row');
            if (newRow) {
                newRow.style.display = 'none';
                // Clear inputs
                document.getElementById('new-field-name').value = '';
                document.getElementById('new-field-type').value = '';
                document.getElementById('new-field-length').value = '';
                document.getElementById('new-field-length').style.display = 'none';
                document.getElementById('new-field-null').value = 'YES';
                document.getElementById('new-field-default').value = '';
                document.getElementById('new-field-extra').value = '';
            }
        }
        
        function updateNewFieldLengthInput() {
            const typeSelect = document.getElementById('new-field-type');
            const lengthInput = document.getElementById('new-field-length');
            
            if (!typeSelect || !lengthInput) return;
            
            const typesWithLength = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'ENUM', 'SET'];
            const selectedType = typeSelect.value.toUpperCase();
            
            if (typesWithLength.includes(selectedType)) {
                lengthInput.style.display = 'block';
                if (selectedType === 'VARCHAR' || selectedType === 'CHAR') {
                    lengthInput.placeholder = '255';
                } else if (selectedType === 'INT' || selectedType === 'BIGINT' || selectedType === 'SMALLINT' || selectedType === 'TINYINT' || selectedType === 'MEDIUMINT') {
                    lengthInput.placeholder = '11';
                } else if (selectedType === 'DECIMAL' || selectedType === 'FLOAT' || selectedType === 'DOUBLE') {
                    lengthInput.placeholder = '10,2';
                } else if (selectedType === 'ENUM' || selectedType === 'SET') {
                    lengthInput.placeholder = "'a','b','c'";
                }
            } else {
                lengthInput.style.display = 'none';
                lengthInput.value = '';
            }
        }
        
        function updateEditFieldLengthInput(fieldName) {
            const row = document.getElementById('row-' + fieldName);
            if (!row) return;
            
            const typeSelect = row.querySelector('select[name="field_type"]');
            const lengthInput = document.getElementById('edit-field-length-' + fieldName);
            
            if (!typeSelect || !lengthInput) return;
            
            const typesWithLength = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'ENUM', 'SET'];
            const selectedType = typeSelect.value.toUpperCase();
            
            if (typesWithLength.includes(selectedType)) {
                lengthInput.style.display = 'block';
                if (selectedType === 'VARCHAR' || selectedType === 'CHAR') {
                    lengthInput.placeholder = '255';
                } else if (selectedType === 'INT' || selectedType === 'BIGINT' || selectedType === 'SMALLINT' || selectedType === 'TINYINT' || selectedType === 'MEDIUMINT') {
                    lengthInput.placeholder = '11';
                } else if (selectedType === 'DECIMAL' || selectedType === 'FLOAT' || selectedType === 'DOUBLE') {
                    lengthInput.placeholder = '10,2';
                } else if (selectedType === 'ENUM' || selectedType === 'SET') {
                    lengthInput.placeholder = "'a','b','c'";
                }
            } else {
                lengthInput.style.display = 'none';
                lengthInput.value = '';
            }
        }
        
        function saveStructureField() {
            // Scope to the specific row to avoid ID conflicts
            const row = document.getElementById('new-field-row');
            if (!row) return;
            
            const fieldNameInput = row.querySelector('input[name="new_field_name"]');
            const fieldTypeInput = row.querySelector('select[name="new_field_type"]');
            
            const fieldName = fieldNameInput ? fieldNameInput.value.trim() : '';
            const fieldType = fieldTypeInput ? fieldTypeInput.value.trim() : '';
            
            // DEBUG: Show what we found
            // alert('Debug: Name=[' + fieldName + '] Type=[' + fieldType + '] RowFound=' + (row ? 'YES' : 'NO'));
            
            if (!fieldName || !fieldType) {
                alert('Field name and type must be filled!\nDetected: Name="' + fieldName + '", Type="' + fieldType + '"');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const hiddenFieldNameInput = document.createElement('input');
            hiddenFieldNameInput.type = 'hidden';
            hiddenFieldNameInput.name = 'field_name';
            hiddenFieldNameInput.value = fieldName;
            form.appendChild(hiddenFieldNameInput);
            
            const typeSelect = row.querySelector('select[name="new_field_type"]');
            const lengthInput = row.querySelector('input[name="new_field_length"]');
            
            const hiddenFieldTypeInput = document.createElement('input');
            hiddenFieldTypeInput.type = 'hidden';
            hiddenFieldTypeInput.name = 'field_type';
            hiddenFieldTypeInput.value = typeSelect ? typeSelect.value : '';
            form.appendChild(hiddenFieldTypeInput);
            
            const fieldLengthInput = document.createElement('input');
            fieldLengthInput.type = 'hidden';
            fieldLengthInput.name = 'field_length';
            fieldLengthInput.value = lengthInput ? lengthInput.value : '';
            form.appendChild(fieldLengthInput);
            
            const fieldNullInput = document.createElement('input');
            fieldNullInput.type = 'hidden';
            fieldNullInput.name = 'field_null';
            const nullSelect = row.querySelector('select[name="new_field_null"]');
            fieldNullInput.value = nullSelect ? (nullSelect.value === 'YES' ? '1' : '') : '';
            form.appendChild(fieldNullInput);
            
            const fieldDefaultInput = document.createElement('input');
            fieldDefaultInput.type = 'hidden';
            fieldDefaultInput.name = 'field_default';
            fieldDefaultInput.value = row.querySelector('input[name="new_field_default"]').value;
            form.appendChild(fieldDefaultInput);
            
            const fieldExtraInput = document.createElement('input');
            fieldExtraInput.type = 'hidden';
            fieldExtraInput.name = 'field_extra';
            fieldExtraInput.value = row.querySelector('select[name="new_field_extra"]').value;
            form.appendChild(fieldExtraInput);
            
            const addFieldInput = document.createElement('input');
            addFieldInput.type = 'hidden';
            addFieldInput.name = 'add_field';
            addFieldInput.value = '1';
            form.appendChild(addFieldInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Inline edit functions
        function startEdit(fieldName) {
            // Store original values
            const typeSelect = document.querySelector('#edit-field-type-' + fieldName + ' select[name="field_type"]');
            const lengthInput = document.getElementById('edit-field-length-' + fieldName);
            originalValues[fieldName] = {
                name: document.getElementById('edit-field-name-' + fieldName).value,
                type: typeSelect ? typeSelect.value : '',
                length: lengthInput ? lengthInput.value : '',
                null: document.getElementById('edit-field-null-' + fieldName).value,
                default: document.getElementById('edit-field-default-' + fieldName).value,
                extra: document.getElementById('edit-field-extra-' + fieldName).value
            };
            
            // Hide display, show edit inputs
            document.getElementById('field-name-' + fieldName).style.display = 'none';
            document.getElementById('field-type-' + fieldName).style.display = 'none';
            document.getElementById('field-null-' + fieldName).style.display = 'none';
            document.getElementById('field-default-' + fieldName).style.display = 'none';
            document.getElementById('field-extra-' + fieldName).style.display = 'none';
            
            document.getElementById('edit-field-name-' + fieldName).style.display = 'block';
            document.getElementById('edit-field-type-' + fieldName).style.display = 'block';
            document.getElementById('edit-field-null-' + fieldName).style.display = 'block';
            document.getElementById('edit-field-default-' + fieldName).style.display = 'block';
            document.getElementById('edit-field-extra-' + fieldName).style.display = 'block';
            
            // Hide action buttons, show save/cancel
            document.getElementById('actions-' + fieldName).style.display = 'none';
            document.getElementById('save-cancel-' + fieldName).style.display = 'flex';
        }
        
        function cancelEdit(fieldName) {
            // Restore original values
            if (originalValues[fieldName]) {
                document.getElementById('edit-field-name-' + fieldName).value = originalValues[fieldName].name;
                document.getElementById('edit-field-type-' + fieldName).value = originalValues[fieldName].type;
                document.getElementById('edit-field-null-' + fieldName).value = originalValues[fieldName].null;
                document.getElementById('edit-field-default-' + fieldName).value = originalValues[fieldName].default;
                document.getElementById('edit-field-extra-' + fieldName).value = originalValues[fieldName].extra;
            }
            
            // Show display, hide edit inputs
            document.getElementById('field-name-' + fieldName).style.display = 'inline';
            document.getElementById('field-type-' + fieldName).style.display = 'inline';
            document.getElementById('field-null-' + fieldName).style.display = 'inline';
            document.getElementById('field-default-' + fieldName).style.display = 'inline';
            document.getElementById('field-extra-' + fieldName).style.display = 'inline';
            
            document.getElementById('edit-field-name-' + fieldName).style.display = 'none';
            document.getElementById('edit-field-type-' + fieldName).style.display = 'none';
            document.getElementById('edit-field-null-' + fieldName).style.display = 'none';
            document.getElementById('edit-field-default-' + fieldName).style.display = 'none';
            document.getElementById('edit-field-extra-' + fieldName).style.display = 'none';
            
            // Show action buttons, hide save/cancel
            document.getElementById('actions-' + fieldName).style.display = 'flex';
            document.getElementById('save-cancel-' + fieldName).style.display = 'none';
        }
        
        function saveEdit(fieldName) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const oldFieldNameInput = document.createElement('input');
            oldFieldNameInput.type = 'hidden';
            oldFieldNameInput.name = 'old_field_name';
            oldFieldNameInput.value = fieldName;
            form.appendChild(oldFieldNameInput);
            
            const fieldNameInput = document.createElement('input');
            fieldNameInput.type = 'hidden';
            fieldNameInput.name = 'field_name';
            fieldNameInput.value = document.getElementById('edit-field-name-' + fieldName).value;
            form.appendChild(fieldNameInput);
            
            const typeSelect = document.querySelector('#edit-field-type-' + fieldName + ' select[name="field_type"]');
            const lengthInput = document.getElementById('edit-field-length-' + fieldName);
            
            const fieldTypeInput = document.createElement('input');
            fieldTypeInput.type = 'hidden';
            fieldTypeInput.name = 'field_type';
            fieldTypeInput.value = typeSelect ? typeSelect.value : '';
            form.appendChild(fieldTypeInput);
            
            const fieldLengthInput = document.createElement('input');
            fieldLengthInput.type = 'hidden';
            fieldLengthInput.name = 'field_length';
            fieldLengthInput.value = lengthInput ? lengthInput.value : '';
            form.appendChild(fieldLengthInput);
            
            const fieldNullInput = document.createElement('input');
            fieldNullInput.type = 'hidden';
            fieldNullInput.name = 'field_null';
            fieldNullInput.value = document.getElementById('edit-field-null-' + fieldName).value === 'YES' ? '1' : '';
            form.appendChild(fieldNullInput);
            
            const fieldDefaultInput = document.createElement('input');
            fieldDefaultInput.type = 'hidden';
            fieldDefaultInput.name = 'field_default';
            fieldDefaultInput.value = document.getElementById('edit-field-default-' + fieldName).value;
            form.appendChild(fieldDefaultInput);
            
            const fieldExtraInput = document.createElement('input');
            fieldExtraInput.type = 'hidden';
            fieldExtraInput.name = 'field_extra';
            fieldExtraInput.value = document.getElementById('edit-field-extra-' + fieldName).value;
            form.appendChild(fieldExtraInput);
            
            const editFieldInput = document.createElement('input');
            editFieldInput.type = 'hidden';
            editFieldInput.name = 'edit_field';
            editFieldInput.value = '1';
            form.appendChild(editFieldInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteField(fieldName) {
            if (confirm('Are you sure you want to delete field "' + fieldName + '"?\n\nThis action cannot be undone!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const fieldNameInput = document.createElement('input');
                fieldNameInput.type = 'hidden';
                fieldNameInput.name = 'field_name';
                fieldNameInput.value = fieldName;
                form.appendChild(fieldNameInput);
                
                const deleteFieldInput = document.createElement('input');
                deleteFieldInput.type = 'hidden';
                deleteFieldInput.name = 'delete_field';
                deleteFieldInput.value = '1';
                form.appendChild(deleteFieldInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Initialize index functions if we are in structure tab
        <?php if ($active_tab === 'struktur' && !empty($table_structure)): ?>
            const dbTableFields = <?php echo json_encode(array_column($table_structure, 'Field')); ?>;
            let indexColCounter = 0;
            
            function addIndexColumnRow() {
                const container = document.getElementById('index-columns-container');
                if (!container) return;
                
                const rowId = 'index-col-row-' + indexColCounter;
                const div = document.createElement('div');
                div.id = rowId;
                div.style.display = 'flex';
                div.style.gap = '10px';
                div.style.marginBottom = '10px';
                div.style.alignItems = 'center';
                
                let optionsHtml = '<option value="">Select Column</option>';
                dbTableFields.forEach(field => {
                    optionsHtml += `<option value="${field}">${field}</option>`;
                });
                
                div.innerHTML = `
                    <div style="flex: 2;">
                        <select name="index_columns[${indexColCounter}][name]" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                            ${optionsHtml}
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <input type="number" name="index_columns[${indexColCounter}][length]" placeholder="Length (Opt)" 
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <button type="button" class="btn-icon-simple" onclick="removeIndexColumnRow(${indexColCounter})" style="color: #555;">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                
                container.appendChild(div);
                indexColCounter++;
            }
            
            function removeIndexColumnRow(id) {
                const row = document.getElementById('index-col-row-' + id);
                if (row) row.remove();
            }
            
            // Add first row by default
            document.addEventListener('DOMContentLoaded', function() {
                if(document.getElementById('index-columns-container')) {
                    addIndexColumnRow();
                }
            });
        <?php endif; ?>

        // Create table functions
        let fieldCounter = 0;
        
        function addNewTableFieldRow() {
            const tbody = document.getElementById('create-table-fields-body');
            if (!tbody) return;
            
            const row = document.createElement('tr');
            row.id = 'create-field-row-' + fieldCounter;
            row.style.background = '#f8f9fa';
            
            row.innerHTML = `
                <td>
                    <input type="text" name="fields[${fieldCounter}][name]" 
                           placeholder="field_name" 
                           oninput="this.value = this.value.replace(/ /g, '_')"
                           style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;">
                </td>
                <td>
                    <select name="fields[${fieldCounter}][type]" 
                            style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;"
                            onchange="updateCreateTableLengthInput(${fieldCounter})">
                        <option value="">Select Type</option>
                        <option value="VARCHAR">VARCHAR</option>
                        <option value="CHAR">CHAR</option>
                        <option value="INT">INT</option>
                        <option value="BIGINT">BIGINT</option>
                        <option value="SMALLINT">SMALLINT</option>
                        <option value="TINYINT">TINYINT</option>
                        <option value="MEDIUMINT">MEDIUMINT</option>
                        <option value="DECIMAL">DECIMAL</option>
                        <option value="FLOAT">FLOAT</option>
                        <option value="DOUBLE">DOUBLE</option>
                        <option value="DATE">DATE</option>
                        <option value="DATETIME">DATETIME</option>
                        <option value="TIMESTAMP">TIMESTAMP</option>
                        <option value="TIME">TIME</option>
                        <option value="YEAR">YEAR</option>
                        <option value="TEXT">TEXT</option>
                        <option value="TINYTEXT">TINYTEXT</option>
                        <option value="MEDIUMTEXT">MEDIUMTEXT</option>
                        <option value="LONGTEXT">LONGTEXT</option>
                        <option value="BLOB">BLOB</option>
                        <option value="TINYBLOB">TINYBLOB</option>
                        <option value="MEDIUMBLOB">MEDIUMBLOB</option>
                        <option value="LONGBLOB">LONGBLOB</option>
                        <option value="BOOLEAN">BOOLEAN</option>
                        <option value="JSON">JSON</option>
                        <option value="ENUM">ENUM</option>
                        <option value="SET">SET</option>
                    </select>
                </td>
                <td>
                    <input type="text" name="fields[${fieldCounter}][length]" 
                           id="create-field-length-${fieldCounter}"
                           placeholder="255" 
                           style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px; display: none;">
                </td>
                <td>
                    <select name="fields[${fieldCounter}][null]" 
                            style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;">
                        <option value="YES">YES</option>
                        <option value="NO">NO</option>
                    </select>
                </td>
                <td>
                    <input type="text" name="fields[${fieldCounter}][default]" 
                           placeholder="NULL" 
                           style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;">
                </td>
                <td>
                    <select name="fields[${fieldCounter}][key]" 
                            style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;">
                        <option value="">-</option>
                        <option value="PRIMARY">PRIMARY</option>
                        <option value="UNIQUE">UNIQUE</option>
                        <option value="INDEX">INDEX</option>
                    </select>
                </td>
                <td>
                    <select name="fields[${fieldCounter}][extra]" 
                            style="width: 100%; padding: 5px; border: 1px solid #757575; border-radius: 3px;"
                            onchange="handleAutoIncrementChange(${fieldCounter})">
                        <option value="">-</option>
                        <option value="AUTO_INCREMENT">AUTO_INCREMENT</option>
                    </select>
                </td>
                <td>
                    <button type="button" class="btn-icon-simple" onclick="removeTableFieldRow(${fieldCounter})" title="Delete Row">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
            
            // Set defaults for the first row (id, INT, PRIMARY, AUTO_INCREMENT)
            if (fieldCounter === 0) {
                const nameInput = row.querySelector('input[name*="[name]"]');
                const typeSelect = row.querySelector('select[name*="[type]"]');
                const nullSelect = row.querySelector('select[name*="[null]"]');
                const keySelect = row.querySelector('select[name*="[key]"]');
                const extraSelect = row.querySelector('select[name*="[extra]"]');
                
                if (nameInput) nameInput.value = 'id';
                if (typeSelect) {
                    typeSelect.value = 'INT';
                    // Trigger length update
                    updateCreateTableLengthInput(fieldCounter);
                }
                if (nullSelect) nullSelect.value = 'NO';
                if (keySelect) keySelect.value = 'PRIMARY';
                if (extraSelect) extraSelect.value = 'AUTO_INCREMENT';
            }
            
            fieldCounter++;
        }
        
        function removeTableFieldRow(index) {
            const row = document.getElementById('create-field-row-' + index);
            if (row) {
                row.remove();
            }
        }
        
        function updateCreateTableLengthInput(index) {
            const row = document.getElementById('create-field-row-' + index);
            if (!row) return;
            
            const typeSelect = row.querySelector('select[name*="[type]"]');
            const lengthInput = document.getElementById('create-field-length-' + index);
            
            if (!typeSelect || !lengthInput) return;
            
            const typesWithLength = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'ENUM', 'SET'];
            const selectedType = typeSelect.value.toUpperCase();
            
            if (typesWithLength.includes(selectedType)) {
                lengthInput.style.display = 'block';
                if (selectedType === 'VARCHAR' || selectedType === 'CHAR') {
                    lengthInput.placeholder = '255';
                } else if (selectedType === 'INT' || selectedType === 'BIGINT' || selectedType === 'SMALLINT' || selectedType === 'TINYINT' || selectedType === 'MEDIUMINT') {
                    lengthInput.placeholder = '11';
                } else if (selectedType === 'DECIMAL' || selectedType === 'FLOAT' || selectedType === 'DOUBLE') {
                    lengthInput.placeholder = '10,2';
                } else if (selectedType === 'ENUM' || selectedType === 'SET') {
                    lengthInput.placeholder = "'a','b','c'";
                }
            } else {
                lengthInput.style.display = 'none';
                lengthInput.value = '';
            }
        }
        
        function handleAutoIncrementChange(index) {
            const row = document.getElementById('create-field-row-' + index);
            if (!row) return;
            
            const extraSelect = row.querySelector('select[name*="[extra]"]');
            const keySelect = row.querySelector('select[name*="[key]"]');
            
            if (extraSelect && keySelect) {
                if (extraSelect.value === 'AUTO_INCREMENT') {
                    // Auto-set PRIMARY KEY if AUTO_INCREMENT is selected
                    if (keySelect.value === '') {
                        keySelect.value = 'PRIMARY';
                    }
                }
            }
        }
        
        function submitCreateTable() {
            const tableName = document.getElementById('new-table-name').value.trim();
            if (!tableName) {
                alert('Nama tabel harus diisi!');
                document.getElementById('new-table-name').focus();
                return;
            }
            
            const tbody = document.getElementById('create-table-fields-body');
            const rows = tbody.querySelectorAll('tr');
            
            if (rows.length === 0) {
                alert('At least one field is required!');
                return;
            }
            
            // Validate fields
            let hasValidField = false;
            rows.forEach(row => {
                const nameInput = row.querySelector('input[name*="[name]"]');
                const typeSelect = row.querySelector('select[name*="[type]"]');
                if (nameInput && typeSelect && nameInput.value.trim() && typeSelect.value.trim()) {
                    hasValidField = true;
                }
            });
            
            if (!hasValidField) {
                alert('At least one field with valid name and type is required!');
                return;
            }
            
            document.getElementById('table_name_input').value = tableName;
            
            // Add hidden input for create_table
            const createTableInput = document.createElement('input');
            createTableInput.type = 'hidden';
            createTableInput.name = 'create_table';
            createTableInput.value = '1';
            document.getElementById('createTableForm').appendChild(createTableInput);
            
            document.getElementById('createTableForm').submit();
        }
        
        // Handle type input for phpMyAdmin style form
        function updateTypeInput() {
            const typeSelect = document.getElementById('field_type_select');
            const lengthInput = document.getElementById('field_type_length');
            const lengthValue = document.getElementById('field_length');
            const customTypeInput = document.getElementById('field_type_custom');
            
            if (!typeSelect || !lengthInput || !lengthValue || !customTypeInput) return;
            
            const typesWithLength = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'ENUM', 'SET'];
            const selectedType = typeSelect.value.toUpperCase();
            
            if (typesWithLength.includes(selectedType)) {
                lengthInput.style.display = 'block';
                lengthValue.placeholder = selectedType === 'DECIMAL' ? '10,2' : '255';
                if (selectedType === 'ENUM' || selectedType === 'SET') lengthValue.placeholder = "'a','b','c'";
            } else {
                lengthInput.style.display = 'none';
                lengthValue.value = '';
                lengthValue.placeholder = '';
            }
            
            // Update custom type
            if (lengthValue.value && typesWithLength.includes(selectedType)) {
                customTypeInput.value = selectedType + '(' + lengthValue.value + ')';
            } else {
                customTypeInput.value = selectedType;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const lengthValue = document.getElementById('field_length');
            const typeSelect = document.getElementById('field_type_select');
            const customTypeInput = document.getElementById('field_type_custom');
            
            if (lengthValue && typeSelect && customTypeInput) {
                lengthValue.addEventListener('input', function() {
                    const selectedType = typeSelect.value.toUpperCase();
                    const typesWithLength = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'DECIMAL', 'FLOAT', 'DOUBLE'];
                    
                    if (this.value && typesWithLength.includes(selectedType)) {
                        customTypeInput.value = selectedType + '(' + this.value + ')';
                    } else if (!this.value) {
                        customTypeInput.value = selectedType;
                    }
                });
                
                if (typeSelect) {
                    typeSelect.addEventListener('change', updateTypeInput);
                    updateTypeInput(); // Initialize
                }
            }
        });
        
        // Add    // Search Functionality
    function toggleSearch() {
        const row = document.getElementById('filter-row');
        if (row.style.display === 'none') {
            row.style.display = 'table-row';
        } else {
            row.style.display = 'none';
        }
    }
    
    // Use event delegation for filter inputs or attach on load
    document.querySelectorAll('.filter-input').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitSearch();
            }
        });
    });
    
    function submitSearch() {
        const inputs = document.querySelectorAll('.filter-input');
        const params = new URLSearchParams(window.location.search);
        
        // Reset page to 1 on new search
        params.set('page', 1);
        
        let hasFilter = false;
        inputs.forEach(input => {
            const col = input.getAttribute('data-col');
            const val = input.value.trim();
            if (val) {
                params.set(`filter[${col}]`, val);
                hasFilter = true;
            } else {
                params.delete(`filter[${col}]`);
            }
        });
        
        window.location.href = '?' + params.toString();
    }

    // Modal Handling
    function openModal(modalId) {
            const modal = document.getElementById('addDataModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }
        
        // Add Data Modal Functions
        function openAddDataModal() {
            const modal = document.getElementById('addDataModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }
        
        function closeAddDataModal() {
            const modal = document.getElementById('addDataModal');
            if (modal) {
                modal.style.display = 'none';
                // Reset form
                const form = document.getElementById('addDataForm');
                if (form) {
                    form.reset();
                }
            }
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('addDataModal');
            if (event.target == modal) {
                closeAddDataModal();
            }
        });

        // Initialize edit toggle state if exists
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('editToggle');
            if (toggle) {
                toggle.checked = false; // Reset to disabled on load
                
                // Handle Add Field Button Visibility
                const addFieldBtn = document.getElementById('add-structure-field-btn');
                
                // Handle Index Management Visibility
                const addIndexCard = document.getElementById('add-index-card');
                const indexDeleteBtns = document.querySelectorAll('.js-index-delete-btn');
                
                toggle.addEventListener('change', function() {
                    const isChecked = this.checked;
                    isEditEnabled = isChecked;
                    
                    if (addFieldBtn) {
                        addFieldBtn.style.display = isChecked ? 'flex' : 'none';
                    }
                    
                    if (addIndexCard) {
                        addIndexCard.style.display = isChecked ? 'block' : 'none';
                    }
                    
                    if (indexDeleteBtns.length > 0) {
                        indexDeleteBtns.forEach(btn => {
                            btn.style.display = isChecked ? 'inline-block' : 'none';
                        });
                    }
                    
                    if (indexDeleteBtns.length > 0) {
                        indexDeleteBtns.forEach(btn => {
                            btn.style.display = isChecked ? 'inline-block' : 'none';
                        });
                    }

                    // Toggle Action Column
                    const actionColumns = document.querySelectorAll('.action-column');
                    actionColumns.forEach(col => {
                        col.style.display = isChecked ? 'table-cell' : 'none';
                    });
                    
                    const rows = document.querySelectorAll('.structure-table tbody tr');
                    rows.forEach(row => {
                        if (isChecked) {
                            row.classList.add('row-clickable');
                        } else {
                            row.classList.remove('row-clickable');
                        }
                    });
                    
                    // Reload if disabled to reset view
                    if (!isChecked) {
                        // Optional: reload to clear any inline edits in progress
                        // location.reload(); 
                        // Actually, let's just hide everything, no need to reload forcefully unless desired.
                        // User previous code had reload, but maybe better just to hide?
                        // "malah diem aja" -> maybe reload was breaking it or preventing view?
                        // Let's keep it simple: just toggle visibility.
                    }
                });
            }
            
            // Initialize Create Table with 1 row if empty
            try {
                const createTableBody = document.getElementById('create-table-fields-body');
                if (createTableBody && createTableBody.children.length === 0) {
                    if (typeof addNewTableFieldRow === 'function') {
                        addNewTableFieldRow();
                    } else {
                        console.error('addNewTableFieldRow function not found');
                    }
                }
            } catch (e) {
                console.error('Error initializing create table fields:', e);
            }
        });
    </script>
</body>
</html>
