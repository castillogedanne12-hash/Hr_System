<?php
// ─── Configuration ───────────────────────────────────────────────────────────
// Move DB outside web root — change this path if needed
define('DB_PATH', dirname(__DIR__) . '/hr_database.sqlite');
define('APP_NAME', 'HRNexus');
define('APP_VERSION', '1.0');

// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_start();

// ─── CSRF Protection ──────────────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        die('Invalid request. <a href="javascript:history.back()">Go back</a>');
    }
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

// ─── Database Connection ──────────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
    }
    return $pdo;
}

// ─── Database Initialization ──────────────────────────────────────────────────
function initDB(): void {
    $db = getDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'hr',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS departments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            manager_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT UNIQUE NOT NULL,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            phone TEXT,
            gender TEXT,
            date_of_birth DATE,
            hire_date DATE NOT NULL,
            department_id INTEGER,
            position TEXT NOT NULL,
            employment_type TEXT DEFAULT 'Full-Time',
            status TEXT DEFAULT 'Active',
            salary REAL DEFAULT 0,
            address TEXT,
            photo TEXT,
            FOREIGN KEY (department_id) REFERENCES departments(id)
        );

        CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            date DATE NOT NULL,
            check_in TIME,
            check_out TIME,
            status TEXT DEFAULT 'Present',
            notes TEXT,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            UNIQUE(employee_id, date)
        );

        CREATE TABLE IF NOT EXISTS leaves (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            leave_type TEXT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days INTEGER NOT NULL,
            reason TEXT,
            status TEXT DEFAULT 'Pending',
            approved_by INTEGER,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id)
        );

        CREATE TABLE IF NOT EXISTS payroll (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            month TEXT NOT NULL,
            year INTEGER NOT NULL,
            basic_salary REAL DEFAULT 0,
            allowances REAL DEFAULT 0,
            deductions REAL DEFAULT 0,
            net_salary REAL DEFAULT 0,
            status TEXT DEFAULT 'Pending',
            paid_at DATETIME,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            UNIQUE(employee_id, month, year)
        );

        CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            priority TEXT DEFAULT 'Normal',
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Seed default admin user
    $check = $db->query("SELECT COUNT(*) as cnt FROM users")->fetch();
    if ($check['cnt'] == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, role) VALUES ('admin', '$hash', 'admin')");
    }

    // Seed departments
    $deptCheck = $db->query("SELECT COUNT(*) as cnt FROM departments")->fetch();
    if ($deptCheck['cnt'] == 0) {
        $departments = [
            ['Engineering', 'Software development and technical operations'],
            ['Human Resources', 'People operations and talent management'],
            ['Finance', 'Financial planning and accounting'],
            ['Marketing', 'Brand, growth, and communications'],
            ['Operations', 'Business operations and logistics'],
            ['Sales', 'Revenue generation and client relations'],
        ];
        $stmt = $db->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
        foreach ($departments as $d) $stmt->execute($d);
    }

    // Seed sample employees
    $empCheck = $db->query("SELECT COUNT(*) as cnt FROM employees")->fetch();
    if ($empCheck['cnt'] == 0) {
        $employees = [
            ['EMP001', 'Sarah', 'Mitchell', 'sarah.mitchell@company.com', '+1-555-0101', 'Female', '1988-03-15', '2020-01-10', 1, 'Senior Engineer', 'Full-Time', 'Active', 95000],
            ['EMP002', 'James', 'Kowalski', 'james.kowalski@company.com', '+1-555-0102', 'Male', '1990-07-22', '2019-06-01', 1, 'DevOps Engineer', 'Full-Time', 'Active', 88000],
            ['EMP003', 'Amara', 'Osei', 'amara.osei@company.com', '+1-555-0103', 'Female', '1993-11-08', '2021-03-15', 2, 'HR Manager', 'Full-Time', 'Active', 72000],
            ['EMP004', 'Lucas', 'Ferreira', 'lucas.ferreira@company.com', '+1-555-0104', 'Male', '1985-05-30', '2018-09-01', 3, 'Financial Analyst', 'Full-Time', 'Active', 78000],
            ['EMP005', 'Priya', 'Nair', 'priya.nair@company.com', '+1-555-0105', 'Female', '1995-02-14', '2022-07-01', 4, 'Marketing Specialist', 'Full-Time', 'Active', 65000],
            ['EMP006', 'David', 'Thompson', 'david.thompson@company.com', '+1-555-0106', 'Male', '1987-09-20', '2020-11-15', 5, 'Operations Manager', 'Full-Time', 'Active', 82000],
            ['EMP007', 'Elena', 'Vasquez', 'elena.vasquez@company.com', '+1-555-0107', 'Female', '1992-04-05', '2021-08-01', 6, 'Sales Executive', 'Full-Time', 'Active', 68000],
            ['EMP008', 'Marcus', 'Chen', 'marcus.chen@company.com', '+1-555-0108', 'Male', '1994-12-18', '2023-01-10', 1, 'Frontend Developer', 'Full-Time', 'Active', 79000],
        ];
        $stmt = $db->prepare("INSERT INTO employees (employee_id, first_name, last_name, email, phone, gender, date_of_birth, hire_date, department_id, position, employment_type, status, salary) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($employees as $e) $stmt->execute($e);

        // Seed attendance for current month
        $today = date('Y-m-d');
        $firstDay = date('Y-m-01');
        $empIds = $db->query("SELECT id FROM employees")->fetchAll(PDO::FETCH_COLUMN);
        $attStmt = $db->prepare("INSERT OR IGNORE INTO attendance (employee_id, date, check_in, check_out, status) VALUES (?, ?, ?, ?, ?)");
        for ($d = strtotime($firstDay); $d <= strtotime($today); $d += 86400) {
            $dow = date('N', $d);
            if ($dow >= 6) continue; // Skip weekends
            $dateStr = date('Y-m-d', $d);
            foreach ($empIds as $empId) {
                $rand = rand(1, 10);
                if ($rand <= 1) {
                    $attStmt->execute([$empId, $dateStr, null, null, 'Absent']);
                } elseif ($rand <= 2) {
                    $attStmt->execute([$empId, $dateStr, '09:' . rand(15,59) . ':00', '17:30:00', 'Late']);
                } else {
                    $attStmt->execute([$empId, $dateStr, '09:0' . rand(0,9) . ':00', '17:' . rand(25,55) . ':00', 'Present']);
                }
            }
        }

        // Seed leaves
        $leaveStmt = $db->prepare("INSERT OR IGNORE INTO leaves (employee_id, leave_type, start_date, end_date, days, reason, status) VALUES (?,?,?,?,?,?,?)");
        $leaveData = [
            [1, 'Annual', '2024-12-23', '2024-12-27', 5, 'Holiday vacation', 'Approved'],
            [2, 'Sick', '2025-01-15', '2025-01-16', 2, 'Flu', 'Approved'],
            [3, 'Personal', '2025-02-10', '2025-02-10', 1, 'Personal matter', 'Approved'],
            [5, 'Annual', '2025-03-03', '2025-03-07', 5, 'Family trip', 'Approved'],
            [4, 'Sick', date('Y-m-d', strtotime('+3 days')), date('Y-m-d', strtotime('+4 days')), 2, 'Medical appointment', 'Pending'],
            [7, 'Annual', date('Y-m-d', strtotime('+7 days')), date('Y-m-d', strtotime('+11 days')), 5, 'Vacation', 'Pending'],
        ];
        foreach ($leaveData as $l) $leaveStmt->execute($l);

        // Seed payroll
        $payStmt = $db->prepare("INSERT OR IGNORE INTO payroll (employee_id, month, year, basic_salary, allowances, deductions, net_salary, status) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($empIds as $empId) {
            $emp = $db->query("SELECT salary FROM employees WHERE id=$empId")->fetch();
            $monthly = $emp['salary'] / 12;
            $allowances = $monthly * 0.15;
            $deductions = $monthly * 0.12;
            $net = $monthly + $allowances - $deductions;
            $payStmt->execute([$empId, 'March', 2025, round($monthly, 2), round($allowances, 2), round($deductions, 2), round($net, 2), 'Paid']);
            $payStmt->execute([$empId, 'April', 2025, round($monthly, 2), round($allowances, 2), round($deductions, 2), round($net, 2), 'Pending']);
        }

        // Seed announcements
        $annStmt = $db->prepare("INSERT INTO announcements (title, content, priority, created_by) VALUES (?,?,?,?)");
        $annStmt->execute(['Q2 Performance Reviews', 'Annual performance review cycle begins next week. All managers please schedule 1:1s.', 'High', 1]);
        $annStmt->execute(['New Health Benefits Package', 'We are upgrading our health insurance effective May 1st. See HR portal for details.', 'Normal', 1]);
        $annStmt->execute(['Office Closure — Memorial Day', 'The office will be closed on Monday, May 26th. Enjoy the long weekend!', 'Normal', 1]);
    }
}

// ─── Auth Helpers ─────────────────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

// ─── Utility Helpers ──────────────────────────────────────────────────────────
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $amount): string {
    return '$' . number_format($amount, 2);
}

function formatDate(string $date): string {
    return date('M d, Y', strtotime($date));
}

function getInitials(string $first, string $last): string {
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
}

function getAvatarColor(string $name): string {
    $colors = ['#E8A87C', '#85C1E9', '#82E0AA', '#F1948A', '#BB8FCE', '#76D7C4', '#F8C471', '#AED6F1'];
    return $colors[crc32($name) % count($colors)];
}

function statusBadge(string $status): string {
    $map = [
        'Active' => 'badge-success',
        'Inactive' => 'badge-danger',
        'Present' => 'badge-success',
        'Absent' => 'badge-danger',
        'Late' => 'badge-warning',
        'Approved' => 'badge-success',
        'Pending' => 'badge-warning',
        'Rejected' => 'badge-danger',
        'Paid' => 'badge-success',
        'Full-Time' => 'badge-info',
        'Part-Time' => 'badge-warning',
        'Contract' => 'badge-secondary',
    ];
    $cls = $map[$status] ?? 'badge-secondary';
    return "<span class=\"badge $cls\">$status</span>";
}

function alert(string $type, string $msg): string {
    $icon = $type === 'success' ? '✓' : ($type === 'error' ? '✗' : 'ℹ');
    return "<div class=\"alert alert-$type\"><span class=\"alert-icon\">$icon</span> $msg</div>";
}

initDB();
?>
