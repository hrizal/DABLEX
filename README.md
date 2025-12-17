![GitHub stars](https://img.shields.io/github/stars/hrizal/DABLEX)
![GitHub forks](https://img.shields.io/github/forks/hrizal/DABLEX)
![GitHub issues](https://img.shields.io/github/issues/hrizal/DABLEX)

# DABLEX - Database Explorer

A lightweight, modern MySQL database management tool with a clean grayscale UI design.

![Version](https://img.shields.io/badge/version-0.1-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)

## Features

### 🗄️ Database Management
- **Multi-database Support**: Browse and switch between multiple databases
- **Table Explorer**: Hierarchical sidebar navigation for easy table access
- **Quick Search**: Filter tables and databases instantly

### 📊 Table Operations
- **Structure Management**: View and edit table structure with inline editing
- **Data Viewing**: Browse table data with pagination
- **CRUD Operations**: Create, read, update, and delete records
- **Index Management**: Add, view, and delete table indexes

### 🔧 Advanced Features
- **SQL Query Execution**: Run custom SQL queries with result display
- **Table Creation**: Create new tables with custom field definitions
- **Field Types**: Support for all MySQL data types (VARCHAR, INT, TEXT, ENUM, etc.)
- **Auto-increment**: Configure auto-increment fields
- **Primary Keys**: Set primary keys and unique constraints

### 🎨 User Interface
- **Modern Grayscale Theme**: Clean, professional 4-tone grayscale design
- **Responsive Layout**: Works on desktop and tablet devices
- **Inline Editing**: Edit data and structure without page reloads
- **Visual Feedback**: Hover effects and shadows for better UX

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.2 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- PDO MySQL extension enabled

### Quick Start

1. **Clone or download** this repository:
   ```bash
   git clone https://github.com/hrizal/dablex.git
   cd dablex
   ```

2. **Configure database connection** (optional):
   Edit `index.php` and modify the connection settings if needed:
   ```php
   $host = '127.0.0.1';
   $port = 3306;
   ```

3. **Start the application**:
   
   **Option A: Using PHP built-in server**
   ```bash
   php -S localhost:8000
   ```
   Then open `http://localhost:8000` in your browser.

   **Option B: Using Apache/Nginx**
   - Place files in your web root directory
   - Access via your configured virtual host

4. **Login**:
   - Enter your MySQL username and password
   - Default: `root` (password may be empty for local development)

## Usage

### Basic Workflow

1. **Login** with your MySQL credentials
2. **Select a database** from the sidebar
3. **Choose a table** to view its structure and data
4. **Perform operations**:
   - View/edit table structure
   - Browse and modify data
   - Execute custom SQL queries
   - Create new tables
   - Manage indexes

### Keyboard Shortcuts

- Click on any row to edit (when edit mode is enabled)
- Use Tab to navigate between fields
- Press Escape to cancel editing

### Tips

- Use the **Filter** feature in the Data tab to search specific records
- The **Total Rows** counter shows the current table size
- All delete operations require confirmation to prevent accidental data loss
- SQL queries are executed in the context of the selected database

## Security Considerations

⚠️ **Important**: This tool is designed for **local development** and **trusted environments** only.

### Security Recommendations:

1. **Never expose to public internet** without additional authentication
2. **Use strong MySQL passwords**
3. **Restrict MySQL user permissions** to necessary databases only
4. **Enable HTTPS** if deploying on a network
5. **Consider IP whitelisting** in production environments
6. **Regular backups** before performing bulk operations

## Screenshots

<img width="1118" height="682" alt="Screenshot 2025-12-17 213344" src="https://github.com/user-attachments/assets/cb5d80c4-8253-4605-aef8-616e81f15b32" />

<img width="1118" height="678" alt="Screenshot 2025-12-17 213410" src="https://github.com/user-attachments/assets/c053f829-f3e1-4f5d-bdd8-a0cea38aeed7" />

<img width="1120" height="677" alt="Screenshot 2025-12-17 213419" src="https://github.com/user-attachments/assets/be509325-4c9a-4ee4-8892-9491187dbb88" />

<img width="1124" height="669" alt="Screenshot 2025-12-17 213427" src="https://github.com/user-attachments/assets/fb31f197-0198-474a-9eb0-45051394109d" />

<img width="1122" height="649" alt="Screenshot 2025-12-17 213437" src="https://github.com/user-attachments/assets/4dd5d8b7-4646-48ae-8e94-e13dd1eac413" />


## Technology Stack

- **Backend**: PHP (PDO for database connections)
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **Database**: MySQL/MariaDB
- **Icons**: Font Awesome 6
- **Architecture**: Single-file application (no dependencies)

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Known Limitations

- Single-file architecture (all code in one file)
- No user management system
- No query history
- Limited to MySQL/MariaDB databases
- No export/import functionality (planned for future versions)

## Roadmap

- [ ] Multi-user support with role-based access
- [ ] Export data (CSV, JSON, SQL)
- [ ] Import data from files
- [ ] Query history and favorites
- [ ] Dark mode toggle
- [ ] PostgreSQL support
- [ ] Relationship visualization

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

**DABLEX Version 0.1**  
Created by **Rizal** with **Antigravity**

---

### Support

If you find this tool useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 📖 Improving documentation

---

**Made with ❤️ for developers who need a simple, fast database explorer**
