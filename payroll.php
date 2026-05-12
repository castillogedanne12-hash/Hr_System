<?php
require_once 'config.php';
require_once 'layout.php';
requireLogin();

$db = getDB();
$msg = '';
$action = $_GET['action'] ?? 'list';

$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$currentMonth = date('F');
$currentYear  = (int)date('Y');

// Handle generate payroll for month
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate"])) {
    verifyCsrf();
    $genMonth = $_POST['gen_month'];
    $genYear  = (int)$_POST['gen_year'];
    $employees = $db->query("SELECT * FROM employees WHERE status='Active'")->fetchAll();
    $stmt = $db->prepare("INSERT OR IGNORE INTO payroll (employee_id, month, year, basic_salary, allowances, deductions, net_salary, status) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($employees as $e) {
        $monthly = $e['salary'] / 12;
        $allowances = round($monthly * 0.15, 2);
        $deductions = round($monthly * 0.12, 2);
        $net = round($monthly + $allowances - $deductions, 2);
        $stmt->execute([$e['id'], $genMonth, $genYear, round($monthly, 2), $allowances, $deductions, $net, 'Pending']);
    }
    header('Location: payroll.php?msg=generated&month=' . urlencode($genMonth) . '&year=' . $genYear); exit;
}

// Handle mark as paid
if ($action === 'pay' && $_GET['id']) {
    $id = (int)$_GET['id'];
    $db->exec("UPDATE payroll SET status='Paid', paid_at=CURRENT_TIMESTAMP WHERE id=$id");
    header('Location: payroll.php?msg=paid&month=' . urlencode($_GET['month'] ?? $currentMonth) . '&year=' . ($_GET['year'] ?? $currentYear)); exit;
}
if ($action === 'payall') {
    $m = $_GET['month'] ?? $currentMonth;
    $y = (int)($_GET['year'] ?? $currentYear);
    $db->exec("UPDATE payroll SET status='Paid', paid_at=CURRENT_TIMESTAMP WHERE month='$m' AND year=$y AND status='Pending'");
    header('Location: payroll.php?msg=paidall&month=' . urlencode($m) . '&year=' . $y); exit;
}

if (isset($_GET['msg'])) {
    $msgs = ['generated' => 'Payroll generated successfully.', 'paid' => 'Payment marked as paid.', 'paidall' => 'All pending payments marked as paid.'];
    $msg = alert('success', $msgs[$_GET['msg']] ?? '');
}

// Filters
$filterMonth = $_GET['month'] ?? $currentMonth;
$filterYear  = (int)($_GET['year'] ?? $currentYear);
$filterStatus = $_GET['status'] ?? '';

// Payroll records
$where = ["p.month='$filterMonth' AND p.year=$filterYear"];
$params = [];
if ($filterStatus) { $where[] = "p.status=?"; $params[] = $filterStatus; }
$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT p.*, e.first_name, e.last_name, e.employee_id as emp_code, e.position, d.name as dept_name
    FROM payroll p
    JOIN employees e ON e.id = p.employee_id
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE $whereStr
    ORDER BY e.first_name
");
$stmt->execute($params);
$payroll = $stmt->fetchAll();

// Summary
$summary = $db->prepare("SELECT 
    SUM(net_salary) as total_net,
    SUM(basic_salary) as total_basic,
    SUM(allowances) as total_allow,
    SUM(deductions) as total_ded,
    SUM(CASE WHEN status='Paid' THEN net_salary ELSE 0 END) as paid_total,
    SUM(CASE WHEN status='Pending' THEN net_salary ELSE 0 END) as pending_total,
    COUNT(*) as total_emp,
    SUM(CASE WHEN status='Paid' THEN 1 ELSE 0 END) as paid_count,
    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending_count
    FROM payroll WHERE month=? AND year=?");
$summary->execute([$filterMonth, $filterYear]);
$sum = $summary->fetch();

ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <h2>Payroll</h2>
        <p><?= h($filterMonth) ?> <?= $filterYear ?></p>
    </div>
    <div class="flex gap-2">
        <?php if (!empty($payroll) && ($sum['pending_count'] > 0)): ?>
        <a href="payroll.php?action=payall&month=<?= urlencode($filterMonth) ?>&year=<?= $filterYear ?>" class="btn btn-success" onclick="return confirm('Mark all pending as paid?')">✓ Pay All Pending</a>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="document.getElementById('generateModal').classList.add('open')">⚙ Generate Payroll</button>
    </div>
</div>
<?= $msg ?>

<!-- Month Selector -->
<div class="card mb-4" style="margin-bottom:20px">
    <div class="card-body" style="padding:14px 22px">
        <form method="GET" class="filter-bar">
            <select name="month">
                <?php foreach ($months as $m): ?>
                <option value="<?= $m ?>" <?= $filterMonth==$m?'selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year">
                <?php for ($y = $currentYear; $y >= $currentYear - 3; $y--): ?>
                <option value="<?= $y ?>" <?= $filterYear==$y?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="Paid" <?= $filterStatus=='Paid'?'selected':'' ?>>Paid</option>
                <option value="Pending" <?= $filterStatus=='Pending'?'selected':'' ?>>Pending</option>
            </select>
            <button type="submit" class="btn btn-outline">View</button>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<?php if (!empty($payroll)): ?>
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <div class="stat-card accent">
        <div class="stat-label">Total Payroll</div>
        <div class="stat-value" style="font-size:1.4rem"><?= formatCurrency($sum['total_net'] ?? 0) ?></div>
        <div class="stat-sub"><?= $sum['total_emp'] ?> employees</div>
    </div>
    <div class="stat-card success">
        <div class="stat-label">Paid</div>
        <div class="stat-value" style="font-size:1.4rem"><?= formatCurrency($sum['paid_total'] ?? 0) ?></div>
        <div class="stat-sub"><?= $sum['paid_count'] ?> employees</div>
    </div>
    <div class="stat-card danger">
        <div class="stat-label">Pending</div>
        <div class="stat-value" style="font-size:1.4rem"><?= formatCurrency($sum['pending_total'] ?? 0) ?></div>
        <div class="stat-sub"><?= $sum['pending_count'] ?> employees</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Deductions</div>
        <div class="stat-value" style="font-size:1.4rem;color:var(--text2)"><?= formatCurrency($sum['total_ded'] ?? 0) ?></div>
        <div class="stat-sub">Allowances: <?= formatCurrency($sum['total_allow'] ?? 0) ?></div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <?php if (empty($payroll)): ?>
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-state-icon">◆</div>
            <h3>No payroll for <?= h($filterMonth) ?> <?= $filterYear ?></h3>
            <p>Generate payroll to get started</p>
            <button class="btn btn-primary" style="margin-top:14px" onclick="document.getElementById('generateModal').classList.add('open')">⚙ Generate Now</button>
        </div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Basic Salary</th>
                <th>Allowances</th>
                <th>Deductions</th>
                <th>Net Salary</th>
                <th>Status</th>
                <th>Paid On</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($payroll as $p): ?>
            <tr>
                <td>
                    <div class="emp-cell">
                        <div class="avatar" style="background:<?= getAvatarColor($p['first_name']) ?>"><?= getInitials($p['first_name'], $p['last_name']) ?></div>
                        <div class="emp-info">
                            <div class="emp-name"><?= h($p['first_name'].' '.$p['last_name']) ?></div>
                            <div class="emp-id"><?= h($p['emp_code']) ?> · <?= h($p['position']) ?></div>
                        </div>
                    </div>
                </td>
                <td style="color:var(--text3);font-size:0.82rem"><?= h($p['dept_name'] ?? '—') ?></td>
                <td><?= formatCurrency($p['basic_salary']) ?></td>
                <td><span style="color:var(--success)"><?= formatCurrency($p['allowances']) ?></span></td>
                <td><span style="color:var(--danger)">-<?= formatCurrency($p['deductions']) ?></span></td>
                <td><strong style="font-family:'Syne',sans-serif;font-size:0.95rem"><?= formatCurrency($p['net_salary']) ?></strong></td>
                <td><?= statusBadge($p['status']) ?></td>
                <td style="font-size:0.78rem;color:var(--text3)"><?= $p['paid_at'] ? formatDate($p['paid_at']) : '—' ?></td>
                <td>
                    <div class="flex gap-2">
                    <?php if ($p['status'] === 'Pending'): ?>
                        <a href="payroll.php?action=pay&id=<?= $p['id'] ?>&month=<?= urlencode($filterMonth) ?>&year=<?= $filterYear ?>" class="btn btn-success btn-sm">✓ Pay</a>
                    <?php endif; ?>
                        <button class="btn btn-outline btn-sm" onclick="showSlip(<?= htmlspecialchars(json_encode($p)) ?>)">⎙ Slip</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Generate Modal -->
<div class="modal-overlay" id="generateModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700">Generate Payroll</h3>
            <button class="modal-close" onclick="document.getElementById('generateModal').classList.remove('open')">✕</button>
        </div>
        <form method="POST" action="payroll.php">
                <?= csrfField() ?>
            <div class="modal-body">
                <p style="color:var(--text3);font-size:0.85rem;margin-bottom:16px">Generate payroll records for all active employees based on their annual salary. Existing records won't be overwritten.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="form-group">
                        <label>Month</label>
                        <select name="gen_month">
                            <?php foreach ($months as $m): ?>
                            <option value="<?= $m ?>" <?= $m==$currentMonth?'selected':'' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <select name="gen_year">
                            <?php for ($y = $currentYear; $y >= $currentYear - 2; $y--): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top:14px;padding:12px;background:var(--accent-dim);border-radius:8px;font-size:0.8rem;color:var(--text2)">
                    ⚡ Salary breakdown: 100% basic + 15% allowances − 12% deductions = 103% net pay
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('generateModal').classList.remove('open')">Cancel</button>
                <button type="submit" name="generate" value="1" class="btn btn-primary">⚙ Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Payslip Modal -->
<div class="modal-overlay" id="slipModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 style="font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700">Pay Slip</h3>
            <button class="modal-close" onclick="document.getElementById('slipModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body" id="slipContent"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('slipModal').classList.remove('open')">Close</button>
            <button type="button" class="btn btn-primary" onclick="window.print()">⎙ Print</button>
        </div>
    </div>
</div>

<script>
function showSlip(p) {
    const net = parseFloat(p.net_salary).toLocaleString('en-US', {style:'currency', currency:'USD'});
    const basic = parseFloat(p.basic_salary).toLocaleString('en-US', {style:'currency', currency:'USD'});
    const allow = parseFloat(p.allowances).toLocaleString('en-US', {style:'currency', currency:'USD'});
    const ded = parseFloat(p.deductions).toLocaleString('en-US', {style:'currency', currency:'USD'});
    document.getElementById('slipContent').innerHTML = `
        <div style="text-align:center;margin-bottom:20px">
            <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800">HRNexus</div>
            <div style="color:var(--text3);font-size:0.8rem">PAY SLIP — ${p.month} ${p.year}</div>
        </div>
        <div style="background:var(--bg3);border-radius:8px;padding:16px;margin-bottom:16px">
            <div style="font-weight:700;margin-bottom:4px">${p.first_name} ${p.last_name}</div>
            <div style="color:var(--text3);font-size:0.8rem">${p.emp_code} · ${p.position}</div>
        </div>
        <table style="width:100%;font-size:0.85rem">
            <tr><td style="padding:8px 0;color:var(--text3)">Basic Salary</td><td style="text-align:right;font-weight:600">${basic}</td></tr>
            <tr><td style="padding:8px 0;color:var(--success)">+ Allowances</td><td style="text-align:right;color:var(--success);font-weight:600">${allow}</td></tr>
            <tr><td style="padding:8px 0;color:var(--danger)">− Deductions</td><td style="text-align:right;color:var(--danger);font-weight:600">${ded}</td></tr>
            <tr style="border-top:1px solid var(--border)">
                <td style="padding:12px 0 0;font-weight:700;font-family:'Syne',sans-serif">NET SALARY</td>
                <td style="padding:12px 0 0;text-align:right;font-weight:800;font-size:1.1rem;color:var(--accent);font-family:'Syne',sans-serif">${net}</td>
            </tr>
        </table>
        <div style="margin-top:16px;padding:10px;background:var(--${p.status==='Paid'?'success':'warning'}-dim);border-radius:6px;text-align:center;font-size:0.8rem;font-weight:600;color:var(--${p.status==='Paid'?'success':'warning'})">
            ${p.status === 'Paid' ? '✓ PAID' : '⏳ PENDING PAYMENT'}
        </div>`;
    document.getElementById('slipModal').classList.add('open');
}
</script>
<?php
$content = ob_get_clean();
renderLayout('Payroll', 'payroll', $content);
?>
