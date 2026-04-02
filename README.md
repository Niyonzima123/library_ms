# University Library Management System

A comprehensive web-based Library Management System built with PHP, MySQL, Bootstrap 5, and JavaScript. Designed for universities and educational institutions to manage library operations digitally.

## Features

### Admin Panel
- Full dashboard with statistics and analytics
- Manage Books, Authors, Categories, Departments, Classes
- Issue and Return books with fine calculation
- E-Book management and uploads
- Student approval and promotion system
- Manage Librarians with shift tracking
- Reports: Defaulter List, Activity Log, Student Activities
- Reservations management
- Export reports to CSV, Print support
- Activity logging with IP and device tracking

### Librarian Panel
- Book and Student management
- Issue/Return book operations
- Shift control (auto-open/close on login/logout)
- Reports and analytics
- E-Book management

### Student Portal
- Browse book catalog with search and filters
- Request to borrow and return books
- Read e-books online with note-taking sidebar
- Manage personal notes (inline document view)
- Upload and manage documents
- Set goals, plan events, calendar management
- View library staff availability and hours
- Book popularity stats (views, downloads, times issued)
- Reading list / book cart

## Tech Stack
- **Backend:** PHP 8.x
- **Database:** MySQL (MariaDB)
- **Frontend:** Bootstrap 5, Bootstrap Icons, JavaScript
- **Server:** XAMPP (Apache)

## Installation

1. Install [XAMPP](https://www.apachefriends.org/) and start Apache + MySQL
2. Clone this repository into `C:\xampp\htdocs\` (Windows) or `/opt/lampp/htdocs/` (Linux)
3. Open phpMyAdmin at `http://localhost/phpmyadmin`
4. Create a new database named `lms_db`
5. Import the file `config/database_schema.sql`
6. Access the system at `http://localhost/library_ms`

## Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@library.edu | admin@1234 |
| Librarian | librarian@library.edu | librarian@1234 |
| Student | john@library.edu | student@1234 |

## Project Structure

```
library_ms/
├── admin/              # Admin panel pages
├── student/            # Student portal pages
├── librarian/          # Librarian dashboard
├── config/             # Database config and schema
├── includes/           # Shared PHP includes (auth, header, footer)
├── assets/
│   ├── css/            # Stylesheets
│   └── js/             # JavaScript files
├── uploads/
│   ├── covers/         # Book cover images
│   ├── ebooks/         # E-book files
│   └── documents/      # Student documents
├── index.php           # Landing page
├── login.php           # Login page
└── register.php        # Student registration
```

## License

This project is for educational purposes.
