<?php
require_once 'config.php';
require_once 'layout.php';
requireLogin();

$db = getDB();
$msg = '';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($action === 'add') {
        $db->prepare("INSERT INTO departments (name, description) VALUES (?,?)")->execute([$name, $desc]);
        header('Location: departments.php?msg=added'); exit;
    } elseif ($action === 'edit' && $id) {
        $db->prepare("UPDATE departments SET name=?, description=? WHERE id=?")->execute([$name, $desc, $id]);
        header('Location: departments.php?msg=updated'); exit;
    }
} elseif ($action === 'delete' && $id) {
    $db->exec("DELETE FROM departments WHERE id=$id");
    header('Location: departments.php?msg=deleted'); exit;
}

if (isset($_GET['msg'])) {
    $msgs = ['added' => 'Department added.', 'updated' => 'Department updated.', 'deleted' => 'Department removed.'];
    $msg = alert('success', $msgs[$_GET['msg']] ?? '');
}

$dept = null;
if (in_array($action, ['edit'])) {
    $dept = $db->query("SELECT * FROM departments WHERE id=$id")->fetch();
}

// All departments with employee counts
$departments = $db->query("
    SELECT d.*, COUNT(e.id) as emp_count
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id AND e.status='Active'
    GROUP BY d.id ORDER BY d.name
")->fetchAll();

ob_start();

if ($action === 'add' || ($action === 'edit' && $dept)) {
    $isEdit = $action === 'edit';
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h2><?= $isEdit ? 'Edit Department' : 'Add Department' ?></h2>
        </div>
        <a href="departments.php" class="btn btn-outline">← Back</a>
    </div>
    <div class="card" style="max-width:520px">
        <div class="card-body">
            <form method="POST" action="departments.php?action=<?= $action ?>
                <?= csrfField() ?><?= $isEdit ? "&id=$id" : '' ?>">
                <div class="form-group" style="margin-bottom:18px">
                    <label>Department Name *</label>
                    <input type="text" name="name" value="<?= h($dept['name'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:24px">
                    <label>Description</label>
                    <textarea name="description"><?= h($dept['description'] ?? '') ?></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary"><?= $isEdit ? '✓ Update' : '+ Add Department' ?></button>
                    <a href="departments.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php
} else {
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h2>Departments</h2>
            <p><?= count($departments) ?> departments configured</p>
        </div>
        <a href="departments.php?action=add" class="btn btn-primary">+ Add Department</a>
    </div>
    <?= $msg ?>
    <div class="grid-3" style="gap:16px">
        <?php foreach ($departments as $d): ?>
        <div class="card">
            <div class="card-body">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
                    <div style="width:44px;height:44px;background:var(--accent-dim);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--accent)">◫</div>
                    <div class="flex gap-2">
                        <a href="departments.php?action=edit&id=<?= $d['id'] ?>" class="btn btn-outline btn-sm">✎</a>
                        <a href="departments.php?action=delete&id=<?= $d['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this department?')">✕</a>
                    </div>
                </div>
                <div style="font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:4px"><?= h($d['name']) ?></div>
                <div style="font-size:0.8rem;color:var(--text3);margin-bottom:14px"><?= h($d['description'] ?: 'No description') ?></div>
                <div class="divider"></div>
                <div class="flex items-center gap-2 mt-4">
                    <span style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:var(--accent)"><?= $d['emp_count'] ?></span>
                    <span style="font-size:0.78rem;color:var(--text3)">active employees</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}

$content = ob_get_clean();
renderLayout('Departments', 'departments', $content);
?>
