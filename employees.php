<?php
require_once 'config.php';
require_once 'layout.php';
requireLogin();

$db = getDB();
$msg = '';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// ─── Handle Actions ──────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $f = $_POST;
    if ($action === 'add') {
        // Auto-generate employee ID
        $last = $db->query("SELECT employee_id FROM employees ORDER BY id DESC LIMIT 1")->fetch();
        $num = $last ? ((int)substr($last['employee_id'], 3)) + 1 : 1;
        $empId = 'EMP' . str_pad($num, 3, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("INSERT INTO employees (employee_id,first_name,last_name,email,phone,gender,date_of_birth,hire_date,department_id,position,employment_type,status,salary,address) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        try {
            $stmt->execute([$empId, $f['first_name'], $f['last_name'], $f['email'], $f['phone'], $f['gender'], $f['date_of_birth'], $f['hire_date'], $f['department_id'] ?: null, $f['position'], $f['employment_type'], $f['status'], (float)$f['salary'], $f['address']]);
            header('Location: employees.php?msg=added');
            exit;
        } catch (Exception $e) {
            $msg = alert('error', 'Error: Email may already exist. ' . $e->getMessage());
        }
    } elseif ($action === 'edit' && $id) {
        $stmt = $db->prepare("UPDATE employees SET first_name=?,last_name=?,email=?,phone=?,gender=?,date_of_birth=?,hire_date=?,department_id=?,position=?,employment_type=?,status=?,salary=?,address=? WHERE id=?");
        try {
            $stmt->execute([$f['first_name'], $f['last_name'], $f['email'], $f['phone'], $f['gender'], $f['date_of_birth'], $f['hire_date'], $f['department_id'] ?: null, $f['position'], $f['employment_type'], $f['status'], (float)$f['salary'], $f['address'], $id]);
            header('Location: employees.php?msg=updated');
            exit;
        } catch (Exception $e) {
            $msg = alert('error', 'Error: ' . $e->getMessage());
        }
    }
} elseif ($action === 'delete' && $id) {
    $db->exec("DELETE FROM employees WHERE id=$id");
    header('Location: employees.php?msg=deleted');
    exit;
}

if (isset($_GET['msg'])) {
    $msgs = ['added' => 'Employee added successfully.', 'updated' => 'Employee updated.', 'deleted' => 'Employee removed.'];
    $msg = alert('success', $msgs[$_GET['msg']] ?? '');
}

// ─── Data ────────────────────────────────────────────────────────────────────
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

// For form (add/edit)
$emp = null;
if ($action === 'edit' && $id) {
    $emp = $db->query("SELECT * FROM employees WHERE id=$id")->fetch();
    if (!$emp) { header('Location: employees.php'); exit; }
}
if ($action === 'view' && $id) {
    $emp = $db->query("SELECT e.*, d.name as dept_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE e.id=$id")->fetch();
    if (!$emp) { header('Location: employees.php'); exit; }
}

// List with filters & pagination
$search = trim($_GET['search'] ?? '');
$filterDept = (int)($_GET['dept'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;

$where = ['1=1'];
$params = [];
if ($search) { $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.employee_id LIKE ? OR e.position LIKE ?)"; $s = "%$search%"; $params = array_merge($params, [$s,$s,$s,$s,$s]); }
if ($filterDept) { $where[] = "e.department_id=?"; $params[] = $filterDept; }
if ($filterStatus) { $where[] = "e.status=?"; $params[] = $filterStatus; }
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) as c FROM employees e WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetch()['c'];
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$listStmt = $db->prepare("SELECT e.*, d.name as dept_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE $whereStr ORDER BY e.id DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$employees = $listStmt->fetchAll();

// ─── Render ───────────────────────────────────────────────────────────────────
ob_start();

// VIEW PROFILE
if ($action === 'view' && $emp) {
    // Get recent attendance
    $recentAtt = $db->query("SELECT * FROM attendance WHERE employee_id={$emp['id']} ORDER BY date DESC LIMIT 7")->fetchAll();
    // Get leaves
    $empLeaves = $db->query("SELECT * FROM leaves WHERE employee_id={$emp['id']} ORDER BY start_date DESC LIMIT 5")->fetchAll();
    // Attendance this month
    $monthAtt = $db->query("SELECT status, COUNT(*) as cnt FROM attendance WHERE employee_id={$emp['id']} AND strftime('%Y-%m', date)=strftime('%Y-%m','now') GROUP BY status")->fetchAll();
    $attMonthMap = [];
    foreach ($monthAtt as $a) $attMonthMap[$a['status']] = $a['cnt'];
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h2><?= h($emp['first_name'].' '.$emp['last_name']) ?></h2>
            <p><?= h($emp['position']) ?> · <?= h($emp['dept_name'] ?? 'No Dept') ?></p>
        </div>
        <div class="flex gap-2">
            <a href="employees.php?action=edit&id=<?= $emp['id'] ?>" class="btn btn-outline">✎ Edit</a>
            <a href="employees.php" class="btn btn-outline">← Back</a>
        </div>
    </div>
    <div class="grid-2">
        <div style="display:flex;flex-direction:column;gap:20px">
            <div class="card">
                <div class="card-body" style="text-align:center;padding:32px">
                    <div class="avatar" style="width:72px;height:72px;font-size:1.5rem;background:<?= getAvatarColor($emp['first_name']) ?>;margin:0 auto 14px">
                        <?= getInitials($emp['first_name'], $emp['last_name']) ?>
                    </div>
                    <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800"><?= h($emp['first_name'].' '.$emp['last_name']) ?></div>
                    <div style="color:var(--text3);font-size:0.85rem;margin:4px 0 12px"><?= h($emp['employee_id']) ?></div>
                    <?= statusBadge($emp['status']) ?> <?= statusBadge($emp['employment_type']) ?>
                </div>
                <div style="padding:0 22px 22px">
                    <?php $fields = [['◎ Email', $emp['email']], ['◷ Phone', $emp['phone']], ['◈ Gender', $emp['gender']], ['◆ Hire Date', formatDate($emp['hire_date'])], ['◫ Department', $emp['dept_name'] ?? '—'], ['◉ Position', $emp['position']], ['$ Salary', formatCurrency($emp['salary'])]]; ?>
                    <?php foreach ($fields as [$lbl, $val]): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border)">
                        <span style="font-size:0.78rem;color:var(--text3)"><?= $lbl ?></span>
                        <span style="font-size:0.83rem;font-weight:500"><?= h($val ?: '—') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Month attendance mini -->
            <div class="card">
                <div class="card-header"><div class="card-title">This Month</div></div>
                <div class="card-body">
                    <div class="stat-grid" style="grid-template-columns:repeat(3,1fr)">
                        <div style="text-align:center"><div style="font-size:1.4rem;font-weight:800;color:var(--success)"><?= $attMonthMap['Present'] ?? 0 ?></div><div style="font-size:0.7rem;color:var(--text3)">Present</div></div>
                        <div style="text-align:center"><div style="font-size:1.4rem;font-weight:800;color:var(--danger)"><?= $attMonthMap['Absent'] ?? 0 ?></div><div style="font-size:0.7rem;color:var(--text3)">Absent</div></div>
                        <div style="text-align:center"><div style="font-size:1.4rem;font-weight:800;color:var(--warning)"><?= $attMonthMap['Late'] ?? 0 ?></div><div style="font-size:0.7rem;color:var(--text3)">Late</div></div>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:20px">
            <!-- Recent attendance -->
            <div class="card">
                <div class="card-header"><div class="card-title">Recent Attendance</div></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentAtt as $a): ?>
                        <tr>
                            <td><?= formatDate($a['date']) ?></td>
                            <td><?= $a['check_in'] ?? '—' ?></td>
                            <td><?= $a['check_out'] ?? '—' ?></td>
                            <td><?= statusBadge($a['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentAtt)): ?><tr><td colspan="4" style="text-align:center;color:var(--text3);padding:24px">No records</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Leaves -->
            <div class="card">
                <div class="card-header"><div class="card-title">Leave History</div></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Type</th><th>Start</th><th>End</th><th>Days</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($empLeaves as $l): ?>
                        <tr>
                            <td><?= h($l['leave_type']) ?></td>
                            <td><?= formatDate($l['start_date']) ?></td>
                            <td><?= formatDate($l['end_date']) ?></td>
                            <td><?= $l['days'] ?></td>
                            <td><?= statusBadge($l['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($empLeaves)): ?><tr><td colspan="5" style="text-align:center;color:var(--text3);padding:24px">No leaves</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ADD / EDIT FORM
elseif ($action === 'add' || ($action === 'edit' && $emp)) {
    $isEdit = $action === 'edit';
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h2><?= $isEdit ? 'Edit Employee' : 'Add New Employee' ?></h2>
            <p><?= $isEdit ? 'Update employee information' : 'Enter the details for the new hire' ?></p>
        </div>
        <a href="employees.php" class="btn btn-outline">← Back</a>
    </div>
    <?= $msg ?>
    <div class="card">
        <div class="card-header"><div class="card-title">Personal & Employment Details</div></div>
        <div class="card-body">
            <form method="POST" action="employees.php?action=<?= $action ?>
                <?= csrfField() ?><?= $isEdit ? "&id=$id" : '' ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" value="<?= h($emp['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" value="<?= h($emp['last_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" value="<?= h($emp['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?= h($emp['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">— Select —</option>
                            <?php foreach (['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($emp['gender'] ?? '') == $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?= h($emp['date_of_birth'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Hire Date *</label>
                        <input type="date" name="hire_date" value="<?= h($emp['hire_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id">
                            <option value="">— None —</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($emp['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position / Title *</label>
                        <input type="text" name="position" value="<?= h($emp['position'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Annual Salary ($)</label>
                        <input type="number" name="salary" value="<?= h($emp['salary'] ?? '0') ?>" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Employment Type</label>
                        <select name="employment_type">
                            <?php foreach (['Full-Time','Part-Time','Contract','Intern'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($emp['employment_type'] ?? 'Full-Time') == $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach (['Active','Inactive','On Leave','Terminated'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($emp['status'] ?? 'Active') == $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Address</label>
                        <textarea name="address"><?= h($emp['address'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="divider"></div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary"><?= $isEdit ? '✓ Update Employee' : '+ Add Employee' ?></button>
                    <a href="employees.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// LIST
else {
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h2>Employees</h2>
            <p><?= $total ?> total records</p>
        </div>
        <a href="employees.php?action=add" class="btn btn-primary">+ Add Employee</a>
    </div>
    <?= $msg ?>
    <div class="card">
        <div class="card-header">
            <form method="GET" class="filter-bar" style="flex-wrap:wrap">
                <input class="search-input" type="text" name="search" placeholder="Search name, email, position…" value="<?= h($search) ?>">
                <select name="dept">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filterDept == $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <?php foreach (['Active','Inactive','On Leave','Terminated'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline">Filter</button>
                <a href="employees.php" class="btn btn-outline">Reset</a>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Type</th>
                    <th>Hire Date</th>
                    <th>Salary</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (empty($employees)): ?>
                <tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">◎</div><h3>No employees found</h3><p>Try adjusting your search filters</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($employees as $e): ?>
                <tr>
                    <td>
                        <div class="emp-cell">
                            <div class="avatar" style="background:<?= getAvatarColor($e['first_name']) ?>">
                                <?= getInitials($e['first_name'], $e['last_name']) ?>
                            </div>
                            <div class="emp-info">
                                <div class="emp-name"><?= h($e['first_name'].' '.$e['last_name']) ?></div>
                                <div class="emp-id"><?= h($e['employee_id']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= h($e['dept_name'] ?? '—') ?></td>
                    <td><?= h($e['position']) ?></td>
                    <td><?= statusBadge($e['employment_type']) ?></td>
                    <td><?= formatDate($e['hire_date']) ?></td>
                    <td><?= formatCurrency($e['salary']) ?></td>
                    <td><?= statusBadge($e['status']) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <a href="employees.php?action=view&id=<?= $e['id'] ?>" class="btn btn-outline btn-sm" title="View">◎</a>
                            <a href="employees.php?action=edit&id=<?= $e['id'] ?>" class="btn btn-outline btn-sm" title="Edit">✎</a>
                            <a href="employees.php?action=delete&id=<?= $e['id'] ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Delete this employee?')">✕</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <div class="card-footer flex justify-between items-center">
            <span class="text-muted text-sm">Showing <?= ($offset + 1) ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?></span>
            <div class="pagination">
                <a href="?p=<?= $page-1 ?>&search=<?= urlencode($search) ?>&dept=<?= $filterDept ?>&status=<?= urlencode($filterStatus) ?>" class="page-link <?= $page==1 ? 'disabled' : '' ?>">←</a>
                <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
                <a href="?p=<?= $i ?>&search=<?= urlencode($search) ?>&dept=<?= $filterDept ?>&status=<?= urlencode($filterStatus) ?>" class="page-link <?= $i==$page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?p=<?= $page+1 ?>&search=<?= urlencode($search) ?>&dept=<?= $filterDept ?>&status=<?= urlencode($filterStatus) ?>" class="page-link <?= $page==$pages ? 'disabled' : '' ?>">→</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

$content = ob_get_clean();
renderLayout('Employees', 'employees', $content);
?>
