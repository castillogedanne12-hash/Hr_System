<?php
require_once 'config.php';
require_once 'layout.php';
requireLogin();

$db = getDB();
$msg = '';

// Handle mark attendance
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $empId = (int)$_POST['employee_id'];
    $date  = $_POST['date'];
    $status = $_POST['status'];
    $checkIn = $_POST['check_in'] ?? null;
    $checkOut = $_POST['check_out'] ?? null;
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $db->prepare("INSERT INTO attendance (employee_id, date, check_in, check_out, status, notes) VALUES (?,?,?,?,?,?)
        ON CONFLICT(employee_id, date) DO UPDATE SET check_in=excluded.check_in, check_out=excluded.check_out, status=excluded.status, notes=excluded.notes");
    $stmt->execute([$empId, $date, $checkIn ?: null, $checkOut ?: null, $status, $notes]);
    header('Location: attendance.php?msg=saved&date=' . urlencode($date)); exit;
}

if (isset($_GET['msg'])) $msg = alert('success', 'Attendance record saved.');

// Filters
$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterMonth = date('Y-m', strtotime($filterDate));
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$view = $_GET['view'] ?? 'daily'; // daily | monthly

// Employees
$employees = $db->query("SELECT id, first_name, last_name, employee_id as emp_code, department_id FROM employees WHERE status='Active' ORDER BY first_name")->fetchAll();

if ($view === 'daily') {
    // Daily attendance
    $where = ["a.date = '$filterDate'", "e.status='Active'"];
    $params = [];
    if ($search) { $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; }
    if ($filterStatus) { $where[] = "a.status=?"; $params[] = $filterStatus; }

    $stmt = $db->prepare("
        SELECT e.id, e.employee_id as emp_code, e.first_name, e.last_name, a.check_in, a.check_out, a.status, a.notes
        FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = '$filterDate'
        WHERE " . implode(' AND ', $where) . "
        ORDER BY e.first_name
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Summary
    $summary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Not Marked' => 0];
    foreach ($records as $r) {
        if (!$r['status']) $summary['Not Marked']++;
        else $summary[$r['status']] = ($summary[$r['status']] ?? 0) + 1;
    }
} else {
    // Monthly report
    $stmt = $db->prepare("
        SELECT e.employee_id as emp_code, e.first_name, e.last_name,
            SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status='Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN a.status='Late' THEN 1 ELSE 0 END) as late,
            COUNT(a.id) as total_marked
        FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND strftime('%Y-%m', a.date) = ?
        WHERE e.status='Active'
        GROUP BY e.id ORDER BY e.first_name
    ");
    $stmt->execute([$filterMonth]);
    $monthlyRecords = $stmt->fetchAll();

    // Working days in month
    $workDays = 0;
    $firstDay = strtotime($filterMonth . '-01');
    $lastDay = strtotime(date('Y-m-t', $firstDay));
    for ($d = $firstDay; $d <= $lastDay; $d += 86400) {
        if (date('N', $d) < 6) $workDays++;
    }
}

ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <h2>Attendance</h2>
        <p>Track and manage employee attendance</p>
    </div>
    <div class="flex gap-2">
        <a href="attendance.php?view=daily" class="btn <?= $view=='daily' ? 'btn-primary' : 'btn-outline' ?>">Daily View</a>
        <a href="attendance.php?view=monthly" class="btn <?= $view=='monthly' ? 'btn-primary' : 'btn-outline' ?>">Monthly Report</a>
        <button class="btn btn-outline" onclick="document.getElementById('markModal').classList.add('open')">+ Mark Attendance</button>
    </div>
</div>
<?= $msg ?>

<?php if ($view === 'daily'): ?>
<!-- Summary stats -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <div class="stat-card success"><div class="stat-label">Present</div><div class="stat-value"><?= $summary['Present'] ?></div></div>
    <div class="stat-card danger"><div class="stat-label">Absent</div><div class="stat-value"><?= $summary['Absent'] ?></div></div>
    <div class="stat-card" style=""><div class="stat-label">Late</div><div class="stat-value" style="color:var(--warning)"><?= $summary['Late'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Not Marked</div><div class="stat-value" style="color:var(--text3)"><?= $summary['Not Marked'] ?></div></div>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="filter-bar">
            <input type="hidden" name="view" value="daily">
            <input type="date" name="date" value="<?= h($filterDate) ?>">
            <input type="text" name="search" placeholder="Search employee…" value="<?= h($search) ?>" class="search-input">
            <select name="status">
                <option value="">All Status</option>
                <?php foreach (['Present','Absent','Late'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus==$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline">Filter</button>
        </form>
        <div style="font-size:0.85rem;color:var(--text3)"><?= date('l, F j, Y', strtotime($filterDate)) ?></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Employee</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
                <td>
                    <div class="emp-cell">
                        <div class="avatar" style="background:<?= getAvatarColor($r['first_name']) ?>"><?= getInitials($r['first_name'], $r['last_name']) ?></div>
                        <div class="emp-info">
                            <div class="emp-name"><?= h($r['first_name'].' '.$r['last_name']) ?></div>
                            <div class="emp-id"><?= h($r['emp_code']) ?></div>
                        </div>
                    </div>
                </td>
                <td><?= $r['check_in'] ? substr($r['check_in'],0,5) : '<span style="color:var(--text3)">—</span>' ?></td>
                <td><?= $r['check_out'] ? substr($r['check_out'],0,5) : '<span style="color:var(--text3)">—</span>' ?></td>
                <td>
                    <?php
                    if ($r['check_in'] && $r['check_out']) {
                        $diff = strtotime($r['check_out']) - strtotime($r['check_in']);
                        echo floor($diff/3600) . 'h ' . floor(($diff%3600)/60) . 'm';
                    } else { echo '<span style="color:var(--text3)">—</span>'; }
                    ?>
                </td>
                <td><?= $r['status'] ? statusBadge($r['status']) : '<span class="badge badge-secondary">Not Marked</span>' ?></td>
                <td style="color:var(--text3);font-size:0.8rem"><?= h($r['notes'] ?? '—') ?></td>
                <td>
                    <button class="btn btn-outline btn-sm" onclick="openEdit(<?= $r['id'] ?>,'<?= $r['emp_code'] ?>','<?= h($r['first_name'].' '.$r['last_name']) ?>','<?= $filterDate ?>','<?= $r['check_in']??'' ?>','<?= $r['check_out']??'' ?>','<?= $r['status']??'Present' ?>','<?= h($r['notes']??'') ?>')">✎</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: // Monthly report ?>
<div class="card">
    <div class="card-header">
        <form method="GET" class="filter-bar">
            <input type="hidden" name="view" value="monthly">
            <input type="month" name="date" value="<?= $filterMonth ?>">
            <button type="submit" class="btn btn-outline">View</button>
        </form>
        <div style="font-size:0.85rem;color:var(--text3)"><?= date('F Y', strtotime($filterMonth.'-01')) ?> · <?= $workDays ?> working days</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Employee</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Late</th>
                <th>Attendance %</th>
                <th>Performance</th>
            </tr></thead>
            <tbody>
            <?php foreach ($monthlyRecords as $r): ?>
            <?php $pct = $workDays > 0 ? round(($r['present'] + $r['late']) / $workDays * 100) : 0; ?>
            <tr>
                <td>
                    <div class="emp-cell">
                        <div class="avatar" style="background:<?= getAvatarColor($r['first_name']) ?>"><?= getInitials($r['first_name'], $r['last_name']) ?></div>
                        <div class="emp-info">
                            <div class="emp-name"><?= h($r['first_name'].' '.$r['last_name']) ?></div>
                            <div class="emp-id"><?= h($r['emp_code']) ?></div>
                        </div>
                    </div>
                </td>
                <td><span style="color:var(--success);font-weight:600"><?= $r['present'] ?></span></td>
                <td><span style="color:var(--danger);font-weight:600"><?= $r['absent'] ?></span></td>
                <td><span style="color:var(--warning);font-weight:600"><?= $r['late'] ?></span></td>
                <td><span style="font-weight:700"><?= $pct ?>%</span></td>
                <td style="width:140px">
                    <div class="progress">
                        <div class="progress-bar <?= $pct>=90?'success':($pct>=70?'':'danger') ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Mark Attendance Modal -->
<div class="modal-overlay" id="markModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700" id="modalTitle">Mark Attendance</h3>
            <button class="modal-close" onclick="document.getElementById('markModal').classList.remove('open')">✕</button>
        </div>
        <form method="POST" action="attendance.php?view=<?= $view ?>
                <?= csrfField() ?>">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px">
                    <label>Employee</label>
                    <select name="employee_id" id="modalEmpId" required>
                        <option value="">— Select Employee —</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= h($e['first_name'].' '.$e['last_name']) ?> (<?= h($e['emp_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Date</label>
                    <input type="date" name="date" id="modalDate" value="<?= $filterDate ?>" required>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                    <div class="form-group">
                        <label>Check In</label>
                        <input type="time" name="check_in" id="modalCheckIn">
                    </div>
                    <div class="form-group">
                        <label>Check Out</label>
                        <input type="time" name="check_out" id="modalCheckOut">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Status</label>
                    <select name="status" id="modalStatus" required>
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Late">Late</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" id="modalNotes" placeholder="Optional note">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('markModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Attendance</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, empCode, name, date, checkIn, checkOut, status, notes) {
    document.getElementById('modalTitle').textContent = 'Edit — ' + name;
    document.getElementById('modalDate').value = date;
    document.getElementById('modalCheckIn').value = checkIn ? checkIn.substring(0,5) : '';
    document.getElementById('modalCheckOut').value = checkOut ? checkOut.substring(0,5) : '';
    document.getElementById('modalStatus').value = status || 'Present';
    document.getElementById('modalNotes').value = notes || '';
    // Find employee by code
    const sel = document.getElementById('modalEmpId');
    for (let o of sel.options) {
        if (o.text.includes(empCode)) { o.selected = true; break; }
    }
    document.getElementById('markModal').classList.add('open');
}
</script>
<?php
$content = ob_get_clean();
renderLayout('Attendance', 'attendance', $content);
?>
