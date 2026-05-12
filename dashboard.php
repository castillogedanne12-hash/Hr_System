<?php
require_once 'config.php';
require_once 'layout.php';
requireLogin();

$db = getDB();

// KPIs
$totalEmp    = $db->query("SELECT COUNT(*) as c FROM employees WHERE status='Active'")->fetch()['c'];
$totalDepts  = $db->query("SELECT COUNT(*) as c FROM departments")->fetch()['c'];
$pendingLeaves = $db->query("SELECT COUNT(*) as c FROM leaves WHERE status='Pending'")->fetch()['c'];
$pendingPayroll = $db->query("SELECT COUNT(*) as c FROM payroll WHERE status='Pending'")->fetch()['c'];

// Attendance today
$today = date('Y-m-d');
$todayAtt = $db->query("SELECT status, COUNT(*) as cnt FROM attendance WHERE date='$today' GROUP BY status")->fetchAll();
$attMap = [];
foreach ($todayAtt as $a) $attMap[$a['status']] = $a['cnt'];
$presentToday = $attMap['Present'] ?? 0;
$absentToday  = $attMap['Absent'] ?? 0;
$lateToday    = $attMap['Late'] ?? 0;

// Department distribution
$deptDist = $db->query("
    SELECT d.name, COUNT(e.id) as cnt
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id AND e.status='Active'
    GROUP BY d.id ORDER BY cnt DESC
")->fetchAll();

// Monthly attendance for chart (last 7 working days)
$last7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dow = date('N', strtotime($d));
    if ($dow >= 6) continue;
    $row = $db->query("SELECT 
        SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) as late
        FROM attendance WHERE date='$d'")->fetch();
    $last7[] = ['date' => date('M d', strtotime($d)), 'present' => (int)$row['present'], 'absent' => (int)$row['absent'], 'late' => (int)$row['late']];
}

// Recent employees
$recentEmps = $db->query("
    SELECT e.*, d.name as dept_name
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    ORDER BY e.id DESC LIMIT 5
")->fetchAll();

// Upcoming leaves
$upcomingLeaves = $db->query("
    SELECT l.*, e.first_name, e.last_name, e.employee_id as emp_code
    FROM leaves l
    JOIN employees e ON e.id = l.employee_id
    WHERE l.status='Pending'
    ORDER BY l.applied_at DESC LIMIT 5
")->fetchAll();

// Announcements
$announcements = $db->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3")->fetchAll();

// Payroll summary
$payTotal = $db->query("SELECT SUM(net_salary) as total FROM payroll WHERE status='Pending' AND year=2025 AND month='April'")->fetch()['total'] ?? 0;

ob_start();
?>
<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card accent">
        <div class="stat-icon">◎</div>
        <div class="stat-label">Active Employees</div>
        <div class="stat-value"><?= $totalEmp ?></div>
        <div class="stat-sub"><?= $totalDepts ?> departments</div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon">◷</div>
        <div class="stat-label">Present Today</div>
        <div class="stat-value"><?= $presentToday ?></div>
        <div class="stat-sub"><?= $lateToday ?> late · <?= $absentToday ?> absent</div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon">◈</div>
        <div class="stat-label">Pending Leaves</div>
        <div class="stat-value"><?= $pendingLeaves ?></div>
        <div class="stat-sub">Awaiting approval</div>
    </div>
    <div class="stat-card info">
        <div class="stat-icon">◆</div>
        <div class="stat-label">Payroll Due</div>
        <div class="stat-value"><?= formatCurrency($payTotal) ?></div>
        <div class="stat-sub"><?= $pendingPayroll ?> employees pending</div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid-2 mb-4">
    <!-- Attendance Chart -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Attendance Trend</div>
                <div class="card-subtitle">Last working days</div>
            </div>
        </div>
        <div class="card-body">
            <canvas id="attChart" height="200"></canvas>
        </div>
    </div>

    <!-- Department Distribution -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Headcount by Department</div>
                <div class="card-subtitle">Active employees</div>
            </div>
        </div>
        <div class="card-body">
            <?php foreach ($deptDist as $d): ?>
            <?php $pct = $totalEmp > 0 ? round($d['cnt'] / $totalEmp * 100) : 0; ?>
            <div style="margin-bottom:14px">
                <div class="flex justify-between items-center" style="margin-bottom:5px">
                    <span style="font-size:0.83rem;font-weight:500"><?= h($d['name']) ?></span>
                    <span style="font-size:0.78rem;color:var(--text3)"><?= $d['cnt'] ?> <span style="color:var(--text3)">(<?= $pct ?>%)</span></span>
                </div>
                <div class="progress">
                    <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="grid-2">
    <!-- Pending Leaves -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Leave Requests</div>
                <div class="card-subtitle">Awaiting your review</div>
            </div>
            <a href="leave.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <?php if (empty($upcomingLeaves)): ?>
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon">◈</div>
                <h3>No pending requests</h3>
                <p>All leave requests are handled</p>
            </div>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($upcomingLeaves as $l): ?>
                <tr>
                    <td>
                        <div class="emp-cell">
                            <div class="avatar" style="background:<?= getAvatarColor($l['first_name']) ?>">
                                <?= getInitials($l['first_name'], $l['last_name']) ?>
                            </div>
                            <div class="emp-info">
                                <div class="emp-name"><?= h($l['first_name'].' '.$l['last_name']) ?></div>
                                <div class="emp-id"><?= h($l['emp_code']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= h($l['leave_type']) ?></td>
                    <td><?= $l['days'] ?></td>
                    <td><?= statusBadge($l['status']) ?></td>
                    <td>
                        <a href="leave.php?action=approve&id=<?= $l['id'] ?>" class="btn btn-success btn-sm">✓</a>
                        <a href="leave.php?action=reject&id=<?= $l['id'] ?>" class="btn btn-danger btn-sm">✗</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right column -->
    <div style="display:flex;flex-direction:column;gap:20px">
        <!-- Recent Employees -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Recently Added</div>
                <a href="employees.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <div class="card-body" style="padding:12px 16px">
                <?php foreach ($recentEmps as $e): ?>
                <div class="flex items-center gap-3" style="padding:8px 0;border-bottom:1px solid var(--border)">
                    <div class="avatar" style="background:<?= getAvatarColor($e['first_name']) ?>">
                        <?= getInitials($e['first_name'], $e['last_name']) ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:0.85rem;font-weight:600"><?= h($e['first_name'].' '.$e['last_name']) ?></div>
                        <div style="font-size:0.75rem;color:var(--text3)"><?= h($e['position']) ?> · <?= h($e['dept_name'] ?? '—') ?></div>
                    </div>
                    <?= statusBadge($e['status']) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Announcements -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Announcements</div>
                <a href="announcements.php" class="btn btn-outline btn-sm">Manage</a>
            </div>
            <div class="card-body" style="padding:12px 16px">
                <?php foreach ($announcements as $ann): ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--border)">
                    <div class="flex items-center gap-2" style="margin-bottom:4px">
                        <?php if ($ann['priority'] === 'High'): ?>
                        <span class="badge badge-danger" style="font-size:0.6rem">HIGH</span>
                        <?php endif; ?>
                        <span style="font-size:0.85rem;font-weight:600"><?= h($ann['title']) ?></span>
                    </div>
                    <div style="font-size:0.78rem;color:var(--text3)"><?= h(substr($ann['content'], 0, 80)) ?>…</div>
                    <div style="font-size:0.7rem;color:var(--text3);margin-top:4px"><?= formatDate($ann['created_at']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode(array_column($last7, 'date')) ?>;
const presentData = <?= json_encode(array_column($last7, 'present')) ?>;
const absentData  = <?= json_encode(array_column($last7, 'absent')) ?>;
const lateData    = <?= json_encode(array_column($last7, 'late')) ?>;

Chart.defaults.color = '#9BA3BA';
Chart.defaults.borderColor = '#2A2F3F';

new Chart(document.getElementById('attChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label: 'Present', data: presentData, backgroundColor: 'rgba(34,197,94,0.7)', borderRadius: 4 },
            { label: 'Late',    data: lateData,    backgroundColor: 'rgba(245,158,11,0.7)', borderRadius: 4 },
            { label: 'Absent',  data: absentData,  backgroundColor: 'rgba(239,68,68,0.7)', borderRadius: 4 },
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: {
            x: { stacked: true, grid: { display: false } },
            y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
</script>
<?php
$content = ob_get_clean();
renderLayout('Dashboard', 'dashboard', $content);
?>
