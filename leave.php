<?php
require_once 'config.php';
require_once 'layout.php';
requireLogin();

$db = getDB();
$msg = '';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle actions
if ($action === 'approve' && $id) {
    $db->exec("UPDATE leaves SET status='Approved' WHERE id=$id");
    header('Location: leave.php?msg=approved'); exit;
} elseif ($action === 'reject' && $id) {
    $db->exec("UPDATE leaves SET status='Rejected' WHERE id=$id");
    header('Location: leave.php?msg=rejected'); exit;
} elseif ($action === 'delete' && $id) {
    $db->exec("DELETE FROM leaves WHERE id=$id");
    header('Location: leave.php?msg=deleted'); exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $empId = (int)$_POST['employee_id'];
    $type  = $_POST['leave_type'];
    $start = $_POST['start_date'];
    $end   = $_POST['end_date'];
    $reason= trim($_POST['reason'] ?? '');

    // Calculate working days
    $days = 0;
    for ($d = strtotime($start); $d <= strtotime($end); $d += 86400) {
        if (date('N', $d) < 6) $days++;
    }
    if ($days > 0) {
        $stmt = $db->prepare("INSERT INTO leaves (employee_id, leave_type, start_date, end_date, days, reason, status) VALUES (?,?,?,?,?,?,'Pending')");
        $stmt->execute([$empId, $type, $start, $end, $days, $reason]);
        header('Location: leave.php?msg=applied'); exit;
    } else {
        $msg = alert('error', 'Invalid date range — no working days selected.');
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['applied' => 'Leave application submitted.', 'approved' => 'Leave approved.', 'rejected' => 'Leave rejected.', 'deleted' => 'Leave record removed.'];
    $msg = alert('success', $msgs[$_GET['msg']] ?? '');
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterType   = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = "l.status=?"; $params[] = $filterStatus; }
if ($filterType)   { $where[] = "l.leave_type=?"; $params[] = $filterType; }
if ($search) { $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; }
$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT l.*, e.first_name, e.last_name, e.employee_id as emp_code, d.name as dept_name
    FROM leaves l
    JOIN employees e ON e.id = l.employee_id
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE $whereStr
    ORDER BY l.applied_at DESC
");
$stmt->execute($params);
$leaves = $stmt->fetchAll();

// Stats
$stats = $db->query("SELECT status, COUNT(*) as cnt FROM leaves GROUP BY status")->fetchAll();
$statsMap = [];
foreach ($stats as $s) $statsMap[$s['status']] = $s['cnt'];

$employees = $db->query("SELECT id, first_name, last_name, employee_id as emp_code FROM employees WHERE status='Active' ORDER BY first_name")->fetchAll();
$leaveTypes = ['Annual', 'Sick', 'Personal', 'Maternity', 'Paternity', 'Unpaid', 'Emergency'];

ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <h2>Leave Management</h2>
        <p><?= count($leaves) ?> total requests</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('applyModal').classList.add('open')">+ Apply Leave</button>
</div>
<?= $msg ?>

<!-- Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <div class="stat-card accent"><div class="stat-label">Total Applied</div><div class="stat-value"><?= array_sum($statsMap) ?></div></div>
    <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value" style="color:var(--warning)"><?= $statsMap['Pending'] ?? 0 ?></div></div>
    <div class="stat-card success"><div class="stat-label">Approved</div><div class="stat-value"><?= $statsMap['Approved'] ?? 0 ?></div></div>
    <div class="stat-card danger"><div class="stat-label">Rejected</div><div class="stat-value"><?= $statsMap['Rejected'] ?? 0 ?></div></div>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="filter-bar">
            <input type="text" name="search" placeholder="Search employee…" value="<?= h($search) ?>" class="search-input">
            <select name="status">
                <option value="">All Status</option>
                <?php foreach (['Pending','Approved','Rejected'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus==$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type">
                <option value="">All Types</option>
                <?php foreach ($leaveTypes as $t): ?>
                <option value="<?= $t ?>" <?= $filterType==$t?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline">Filter</button>
            <a href="leave.php" class="btn btn-outline">Reset</a>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Leave Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Days</th>
                <th>Reason</th>
                <th>Applied</th>
                <th>Status</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($leaves)): ?>
            <tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon">◈</div><h3>No leave requests found</h3></div></td></tr>
            <?php else: ?>
            <?php foreach ($leaves as $l): ?>
            <tr>
                <td>
                    <div class="emp-cell">
                        <div class="avatar" style="background:<?= getAvatarColor($l['first_name']) ?>"><?= getInitials($l['first_name'], $l['last_name']) ?></div>
                        <div class="emp-info">
                            <div class="emp-name"><?= h($l['first_name'].' '.$l['last_name']) ?></div>
                            <div class="emp-id"><?= h($l['emp_code']) ?></div>
                        </div>
                    </div>
                </td>
                <td style="color:var(--text3);font-size:0.82rem"><?= h($l['dept_name'] ?? '—') ?></td>
                <td><span class="badge badge-info"><?= h($l['leave_type']) ?></span></td>
                <td><?= formatDate($l['start_date']) ?></td>
                <td><?= formatDate($l['end_date']) ?></td>
                <td><strong><?= $l['days'] ?></strong></td>
                <td style="color:var(--text3);font-size:0.8rem;max-width:160px"><?= h(substr($l['reason'] ?? '—', 0, 50)) ?></td>
                <td style="color:var(--text3);font-size:0.78rem"><?= formatDate($l['applied_at']) ?></td>
                <td><?= statusBadge($l['status']) ?></td>
                <td>
                    <div class="flex gap-2">
                    <?php if ($l['status'] === 'Pending'): ?>
                        <a href="leave.php?action=approve&id=<?= $l['id'] ?>" class="btn btn-success btn-sm" title="Approve">✓</a>
                        <a href="leave.php?action=reject&id=<?= $l['id'] ?>" class="btn btn-danger btn-sm" title="Reject">✗</a>
                    <?php endif; ?>
                        <a href="leave.php?action=delete&id=<?= $l['id'] ?>" class="btn btn-outline btn-sm" title="Delete" onclick="return confirm('Delete this record?')">🗑</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Apply Leave Modal -->
<div class="modal-overlay" id="applyModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700">Apply for Leave</h3>
            <button class="modal-close" onclick="document.getElementById('applyModal').classList.remove('open')">✕</button>
        </div>
        <form method="POST" action="leave.php">
                <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px">
                    <label>Employee *</label>
                    <select name="employee_id" required>
                        <option value="">— Select Employee —</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= h($e['first_name'].' '.$e['last_name']) ?> (<?= h($e['emp_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Leave Type *</label>
                    <select name="leave_type" required>
                        <?php foreach ($leaveTypes as $t): ?>
                        <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>End Date *</label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" placeholder="Briefly describe the reason for leave…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('applyModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Application</button>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
renderLayout('Leave Management', 'leave', $content);
?>
