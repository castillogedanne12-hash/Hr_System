<?php
// layout.php – call renderLayout($pageTitle, $activeNav, $content)
function getCommonCSS(): string {
    return <<<CSS
    @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap');

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --bg: #0D0F14;
        --bg2: #13161E;
        --bg3: #1A1E29;
        --bg4: #222738;
        --border: #2A2F3F;
        --border2: #333A50;
        --text: #E8EAF0;
        --text2: #9BA3BA;
        --text3: #6B7490;
        --accent: #F0A500;
        --accent2: #FFB830;
        --accent-dim: rgba(240,165,0,0.12);
        --danger: #EF4444;
        --danger-dim: rgba(239,68,68,0.12);
        --success: #22C55E;
        --success-dim: rgba(34,197,94,0.12);
        --warning: #F59E0B;
        --warning-dim: rgba(245,158,11,0.12);
        --info: #3B82F6;
        --info-dim: rgba(59,130,246,0.12);
        --sidebar-w: 260px;
        --radius: 12px;
        --radius-sm: 8px;
        --shadow: 0 4px 24px rgba(0,0,0,0.4);
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.25);
        --transition: 0.2s ease;
    }

    html { font-size: 15px; }
    body {
        font-family: 'DM Sans', sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        display: flex;
        line-height: 1.6;
        overflow-x: hidden;
    }

    /* ─── Scrollbar ─────────────────── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--bg2); }
    ::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 99px; }

    /* ─── Sidebar ───────────────────── */
    .sidebar {
        width: var(--sidebar-w);
        min-height: 100vh;
        background: var(--bg2);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0; left: 0; bottom: 0;
        z-index: 100;
        transition: transform var(--transition);
    }
    .sidebar-logo {
        padding: 24px 20px 16px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .logo-icon {
        width: 36px; height: 36px;
        background: var(--accent);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 900;
        color: #000;
        font-family: 'Syne', sans-serif;
        flex-shrink: 0;
    }
    .logo-text { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.15rem; color: var(--text); letter-spacing: -0.02em; }
    .logo-sub { font-size: 0.68rem; color: var(--text3); letter-spacing: 0.08em; text-transform: uppercase; }

    .sidebar-nav { flex: 1; padding: 12px 0; overflow-y: auto; }
    .nav-section { padding: 16px 20px 6px; }
    .nav-section-label { font-size: 0.65rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text3); font-weight: 600; }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 20px;
        color: var(--text2);
        text-decoration: none;
        font-size: 0.88rem;
        font-weight: 500;
        border-radius: 0;
        transition: all var(--transition);
        position: relative;
    }
    .nav-link:hover { color: var(--text); background: rgba(255,255,255,0.04); }
    .nav-link.active {
        color: var(--accent);
        background: var(--accent-dim);
    }
    .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 3px;
        background: var(--accent);
        border-radius: 0 3px 3px 0;
    }
    .nav-icon { font-size: 1rem; width: 20px; text-align: center; opacity: 0.8; }
    .nav-badge {
        margin-left: auto;
        background: var(--accent);
        color: #000;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 99px;
    }

    .sidebar-footer {
        padding: 16px 20px;
        border-top: 1px solid var(--border);
    }
    .sidebar-user {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: background var(--transition);
    }
    .sidebar-user:hover { background: var(--bg3); }
    .user-avatar-sm {
        width: 34px; height: 34px;
        border-radius: 50%;
        background: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        color: #000;
        flex-shrink: 0;
    }
    .user-info-sm { flex: 1; min-width: 0; }
    .user-name-sm { font-size: 0.82rem; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role-sm { font-size: 0.7rem; color: var(--text3); }

    /* ─── Main Content ───────────────── */
    .main-wrapper {
        flex: 1;
        margin-left: var(--sidebar-w);
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
    .topbar {
        height: 60px;
        background: var(--bg2);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        padding: 0 28px;
        gap: 16px;
        position: sticky;
        top: 0;
        z-index: 50;
    }
    .topbar-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.05rem; letter-spacing: -0.02em; flex: 1; }
    .topbar-actions { display: flex; align-items: center; gap: 10px; }
    .topbar-date { font-size: 0.8rem; color: var(--text3); }

    .topbar-btn {
        width: 34px; height: 34px;
        border: 1px solid var(--border2);
        background: var(--bg3);
        color: var(--text2);
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition);
        text-decoration: none;
        font-size: 0.9rem;
    }
    .topbar-btn:hover { background: var(--bg4); color: var(--text); border-color: var(--border2); }

    .page-content { padding: 24px 28px; flex: 1; }

    /* ─── Typography ─────────────────── */
    h1, h2, h3, h4 { font-family: 'Syne', sans-serif; letter-spacing: -0.02em; }

    /* ─── Cards ──────────────────────── */
    .card {
        background: var(--bg2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
    }
    .card-header {
        padding: 18px 22px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .card-title { font-size: 0.95rem; font-weight: 700; font-family: 'Syne', sans-serif; }
    .card-subtitle { font-size: 0.78rem; color: var(--text3); margin-top: 2px; }
    .card-body { padding: 22px; }
    .card-footer { padding: 14px 22px; border-top: 1px solid var(--border); }

    /* ─── Stat Cards ─────────────────── */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card {
        background: var(--bg2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px 22px;
        position: relative;
        overflow: hidden;
        transition: border-color var(--transition);
    }
    .stat-card:hover { border-color: var(--border2); }
    .stat-card::after {
        content: '';
        position: absolute;
        top: 0; right: 0;
        width: 80px; height: 80px;
        border-radius: 50%;
        opacity: 0.06;
        transform: translate(30%, -30%);
    }
    .stat-card.accent::after { background: var(--accent); }
    .stat-card.success::after { background: var(--success); }
    .stat-card.danger::after { background: var(--danger); }
    .stat-card.info::after { background: var(--info); }

    .stat-label { font-size: 0.73rem; color: var(--text3); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; margin-bottom: 8px; }
    .stat-value { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; line-height: 1; margin-bottom: 6px; }
    .stat-card.accent .stat-value { color: var(--accent); }
    .stat-card.success .stat-value { color: var(--success); }
    .stat-card.danger .stat-value { color: var(--danger); }
    .stat-card.info .stat-value { color: var(--info); }
    .stat-sub { font-size: 0.75rem; color: var(--text3); }
    .stat-icon { font-size: 1.5rem; margin-bottom: 12px; }

    /* ─── Tables ─────────────────────── */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
        padding: 11px 16px;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text3);
        background: var(--bg3);
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }
    tbody td {
        padding: 13px 16px;
        border-bottom: 1px solid var(--border);
        font-size: 0.85rem;
        vertical-align: middle;
    }
    tbody tr:hover { background: rgba(255,255,255,0.02); }
    tbody tr:last-child td { border-bottom: none; }

    /* ─── Badges ─────────────────────── */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 99px;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    .badge-success { background: var(--success-dim); color: var(--success); }
    .badge-danger  { background: var(--danger-dim);  color: var(--danger); }
    .badge-warning { background: var(--warning-dim); color: var(--warning); }
    .badge-info    { background: var(--info-dim);    color: var(--info); }
    .badge-secondary { background: rgba(255,255,255,0.08); color: var(--text2); }
    .badge-accent  { background: var(--accent-dim); color: var(--accent); }

    /* ─── Buttons ────────────────────── */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        border-radius: var(--radius-sm);
        font-size: 0.83rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all var(--transition);
        text-decoration: none;
        white-space: nowrap;
        font-family: 'DM Sans', sans-serif;
    }
    .btn-primary { background: var(--accent); color: #000; }
    .btn-primary:hover { background: var(--accent2); }
    .btn-outline { background: transparent; color: var(--text2); border: 1px solid var(--border2); }
    .btn-outline:hover { background: var(--bg3); color: var(--text); }
    .btn-danger { background: var(--danger-dim); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }
    .btn-danger:hover { background: var(--danger); color: #fff; }
    .btn-success { background: var(--success-dim); color: var(--success); border: 1px solid rgba(34,197,94,0.3); }
    .btn-success:hover { background: var(--success); color: #fff; }
    .btn-sm { padding: 5px 12px; font-size: 0.78rem; }
    .btn-icon { padding: 7px; }

    /* ─── Forms ──────────────────────── */
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
    .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }
    label { font-size: 0.78rem; font-weight: 600; color: var(--text2); text-transform: uppercase; letter-spacing: 0.06em; }
    input, select, textarea {
        background: var(--bg3);
        border: 1px solid var(--border2);
        color: var(--text);
        padding: 9px 13px;
        border-radius: var(--radius-sm);
        font-size: 0.88rem;
        font-family: 'DM Sans', sans-serif;
        transition: border-color var(--transition);
        width: 100%;
    }
    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-dim);
    }
    select option { background: var(--bg3); }
    textarea { resize: vertical; min-height: 90px; }

    /* ─── Alerts ─────────────────────── */
    .alert {
        padding: 12px 16px;
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-icon { font-size: 1rem; flex-shrink: 0; }
    .alert-success { background: var(--success-dim); color: var(--success); border: 1px solid rgba(34,197,94,0.25); }
    .alert-error   { background: var(--danger-dim);  color: var(--danger);  border: 1px solid rgba(239,68,68,0.25); }
    .alert-info    { background: var(--info-dim);    color: var(--info);    border: 1px solid rgba(59,130,246,0.25); }
    .alert-warning { background: var(--warning-dim); color: var(--warning); border: 1px solid rgba(245,158,11,0.25); }

    /* ─── Avatar ─────────────────────── */
    .avatar {
        width: 36px; height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        flex-shrink: 0;
        color: #000;
    }

    /* ─── Employee name cell ──────────── */
    .emp-cell { display: flex; align-items: center; gap: 10px; }
    .emp-info .emp-name { font-weight: 600; font-size: 0.88rem; }
    .emp-info .emp-id   { font-size: 0.72rem; color: var(--text3); }

    /* ─── Search & filter bar ─────────── */
    .filter-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .filter-bar input, .filter-bar select {
        background: var(--bg3);
        border: 1px solid var(--border2);
        color: var(--text);
        padding: 8px 13px;
        border-radius: var(--radius-sm);
        font-size: 0.83rem;
    }
    .search-input { min-width: 220px; }

    /* ─── Page Header ────────────────── */
    .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 12px; }
    .page-header-left h2 { font-size: 1.4rem; font-weight: 800; }
    .page-header-left p { color: var(--text3); font-size: 0.83rem; margin-top: 3px; }

    /* ─── Grid layouts ───────────────── */
    .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .gap-4 { gap: 16px; }

    /* ─── Misc ───────────────────────── */
    .text-muted { color: var(--text3); }
    .text-sm { font-size: 0.8rem; }
    .mt-4 { margin-top: 16px; }
    .mb-4 { margin-bottom: 16px; }
    .flex { display: flex; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 12px; }
    .fw-600 { font-weight: 600; }
    a { color: inherit; }

    .divider { height: 1px; background: var(--border); margin: 16px 0; }

    /* ─── Pagination ─────────────────── */
    .pagination { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .page-link {
        min-width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--radius-sm);
        font-size: 0.8rem; font-weight: 600;
        color: var(--text2);
        background: var(--bg3);
        border: 1px solid var(--border);
        text-decoration: none;
        transition: all var(--transition);
        padding: 0 8px;
    }
    .page-link:hover { background: var(--bg4); color: var(--text); }
    .page-link.active { background: var(--accent); color: #000; border-color: var(--accent); }
    .page-link.disabled { opacity: 0.4; pointer-events: none; }

    /* ─── Modal ──────────────────────── */
    .modal-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.7);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal {
        background: var(--bg2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        width: 100%;
        max-width: 520px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow);
    }
    .modal-header { padding: 20px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .modal-body { padding: 22px; }
    .modal-footer { padding: 16px 22px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
    .modal-close { background: none; border: none; color: var(--text3); cursor: pointer; font-size: 1.2rem; transition: color var(--transition); }
    .modal-close:hover { color: var(--text); }

    /* ─── Progress bar ───────────────── */
    .progress { background: var(--bg4); border-radius: 99px; height: 6px; overflow: hidden; }
    .progress-bar { height: 100%; border-radius: 99px; background: var(--accent); transition: width 0.6s ease; }
    .progress-bar.success { background: var(--success); }
    .progress-bar.danger { background: var(--danger); }
    .progress-bar.info { background: var(--info); }

    /* ─── Empty state ────────────────── */
    .empty-state { text-align: center; padding: 48px 24px; }
    .empty-state-icon { font-size: 3rem; margin-bottom: 14px; opacity: 0.4; }
    .empty-state h3 { font-size: 1rem; color: var(--text2); margin-bottom: 6px; }
    .empty-state p { font-size: 0.83rem; color: var(--text3); }

    /* ─── Responsive ─────────────────── */
    @media (max-width: 768px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.open { transform: translateX(0); }
        .main-wrapper { margin-left: 0; }
        .form-grid, .form-grid-3 { grid-template-columns: 1fr; }
        .grid-2, .grid-3 { grid-template-columns: 1fr; }
        .stat-grid { grid-template-columns: repeat(2, 1fr); }
        .page-content { padding: 16px; }
    }
    CSS;
}

function renderLayout(string $pageTitle, string $activeNav, string $content): void {
    $user = currentUser();
    $initials = strtoupper(substr($user['username'] ?? 'A', 0, 2));
    $today = date('l, F j, Y');

    // Get pending leaves count for badge
    $db = getDB();
    $pendingLeaves = $db->query("SELECT COUNT(*) as cnt FROM leaves WHERE status='Pending'")->fetch()['cnt'];
    $css = getCommonCSS();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= h($pageTitle) ?> — <?= APP_NAME ?></title>
        <style><?= $css ?></style>
    </head>
    <body>
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <div class="logo-icon">HR</div>
                <div>
                    <div class="logo-text"><?= APP_NAME ?></div>
                    <div class="logo-sub">People Platform</div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section"><span class="nav-section-label">Overview</span></div>
                <a href="dashboard.php" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">⬡</span> Dashboard
                </a>

                <div class="nav-section"><span class="nav-section-label">People</span></div>
                <a href="employees.php" class="nav-link <?= $activeNav === 'employees' ? 'active' : '' ?>">
                    <span class="nav-icon">◎</span> Employees
                </a>
                <a href="departments.php" class="nav-link <?= $activeNav === 'departments' ? 'active' : '' ?>">
                    <span class="nav-icon">◫</span> Departments
                </a>

                <div class="nav-section"><span class="nav-section-label">Time & Leave</span></div>
                <a href="attendance.php" class="nav-link <?= $activeNav === 'attendance' ? 'active' : '' ?>">
                    <span class="nav-icon">◷</span> Attendance
                </a>
                <a href="leave.php" class="nav-link <?= $activeNav === 'leave' ? 'active' : '' ?>">
                    <span class="nav-icon">◈</span> Leave Management
                    <?php if ($pendingLeaves > 0): ?>
                    <span class="nav-badge"><?= $pendingLeaves ?></span>
                    <?php endif; ?>
                </a>

                <div class="nav-section"><span class="nav-section-label">Finance</span></div>
                <a href="payroll.php" class="nav-link <?= $activeNav === 'payroll' ? 'active' : '' ?>">
                    <span class="nav-icon">◆</span> Payroll
                </a>

                <div class="nav-section"><span class="nav-section-label">Admin</span></div>
                <a href="announcements.php" class="nav-link <?= $activeNav === 'announcements' ? 'active' : '' ?>">
                    <span class="nav-icon">◉</span> Announcements
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user" onclick="window.location='logout.php'">
                    <div class="user-avatar-sm"><?= $initials ?></div>
                    <div class="user-info-sm">
                        <div class="user-name-sm"><?= h($user['username'] ?? 'Admin') ?></div>
                        <div class="user-role-sm"><?= h(ucfirst($user['role'] ?? 'Administrator')) ?></div>
                    </div>
                    <span style="color:var(--text3);font-size:0.8rem;">⎋</span>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="main-wrapper">
            <header class="topbar">
                <button class="topbar-btn" onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none" id="menu-btn">☰</button>
                <div class="topbar-title"><?= h($pageTitle) ?></div>
                <div class="topbar-actions">
                    <span class="topbar-date"><?= $today ?></span>
                    <a href="logout.php" class="topbar-btn" title="Logout">⎋</a>
                </div>
            </header>
            <main class="page-content">
                <?= $content ?>
            </main>
        </div>

        <script>
        // Show mobile menu btn
        if (window.innerWidth <= 768) document.getElementById('menu-btn').style.display = 'flex';
        window.addEventListener('resize', () => {
            document.getElementById('menu-btn').style.display = window.innerWidth <= 768 ? 'flex' : 'none';
        });
        // Close sidebar on outside click (mobile)
        document.addEventListener('click', e => {
            const s = document.getElementById('sidebar');
            if (window.innerWidth <= 768 && !s.contains(e.target) && !document.getElementById('menu-btn').contains(e.target)) {
                s.classList.remove('open');
            }
        });
        </script>
    </body>
    </html>
    <?php
}
?>
