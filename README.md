# HRNexus — Full HR Management System

A complete, production-ready Human Resources Management System built with PHP and SQLite.

## Features

| Module | Description |
|--------|-------------|
| 🔐 **Authentication** | Secure login with session management |
| 📊 **Dashboard** | KPI cards, attendance chart, leave requests, announcements |
| 👥 **Employees** | Full CRUD — add, edit, delete, view profile with attendance history |
| 🏢 **Departments** | Manage departments with headcount overview |
| ⏰ **Attendance** | Daily marking, monthly reports, check-in/out tracking |
| 🌴 **Leave Management** | Apply, approve/reject leaves with multi-type support |
| 💰 **Payroll** | Generate payroll, mark paid, view payslips, bulk processing |
| 📢 **Announcements** | Company-wide communications with priority levels |

## Requirements

- PHP 7.4+ with PDO and SQLite3 extensions
- A web server (Apache, Nginx, or PHP built-in server)

## Quick Start

### Option 1: PHP Built-in Server (Easiest)
```bash
cd hr_system
php -S localhost:8080
```
Then open: http://localhost:8080

### Option 2: XAMPP / WAMP
Place the `hr_system` folder in `htdocs` (XAMPP) or `www` (WAMP), then access:
http://localhost/hr_system

### Option 3: Apache/Nginx
Point your document root to the `hr_system` directory.

## Login Credentials

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `admin123` |

## File Structure

```
hr_system/
├── index.php          # Login page
├── dashboard.php      # Main dashboard
├── employees.php      # Employee management
├── departments.php    # Department management
├── attendance.php     # Attendance tracking
├── leave.php          # Leave management
├── payroll.php        # Payroll processing
├── announcements.php  # Company announcements
├── logout.php         # Session logout
├── config.php         # DB config + helpers
├── layout.php         # Shared sidebar layout + CSS
└── hr_database.sqlite # Auto-created on first run
```

## Database

The SQLite database (`hr_database.sqlite`) is **auto-created** on first run with:
- 1 admin user
- 6 departments
- 8 sample employees
- 30 days of attendance data
- Sample leaves and payroll records

## Customization

- **Salary breakdowns**: Edit allowance/deduction percentages in `config.php` (initDB) and `payroll.php`
- **Leave types**: Edit the `$leaveTypes` array in `leave.php`
- **Branding**: Change `APP_NAME` in `config.php`
- **Colors**: CSS variables are at the top of `layout.php` (`getCommonCSS()`)

## Security Notes

For production use:
1. Change the default admin password immediately
2. Move `hr_database.sqlite` outside the web root and update `DB_PATH` in `config.php`
3. Add CSRF protection to all forms
4. Use HTTPS
5. Set proper file permissions on the SQLite database

---
Built with PHP + SQLite · No external dependencies required
