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
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $priority= $_POST['priority'] ?? 'Normal';
    $userId  = currentUser()['id'];

    if ($action === 'add') {
        $db->prepare("INSERT INTO announcements (title, content, priority, created_by) VALUES (?,?,?,?)")->execute([$title, $content, $priority, $userId]);
        header('Location: announcements.php?msg=added'); exit;
    } elseif ($action === 'edit' && $id) {
        $db->prepare("UPDATE announcements SET title=?, content=?, priority=? WHERE id=?")->execute([$title, $content, $priority, $id]);
        header('Location: announcements.php?msg=updated'); exit;
    }
} elseif ($action === 'delete' && $id) {
    $db->exec("DELETE FROM announcements WHERE id=$id");
    header('Location: announcements.php?msg=deleted'); exit;
}

if (isset($_GET['msg'])) {
    $msgs = ['added' => 'Announcement posted.', 'updated' => 'Announcement updated.', 'deleted' => 'Announcement removed.'];
    $msg = alert('success', $msgs[$_GET['msg']] ?? '');
}

$ann = null;
if (in_array($action, ['edit']) && $id) $ann = $db->query("SELECT * FROM announcements WHERE id=$id")->fetch();
$announcements = $db->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();

ob_start();

if ($action === 'add' || ($action === 'edit' && $ann)) {
    $isEdit = $action === 'edit';
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h2><?= $isEdit ? 'Edit Announcement' : 'Post Announcement' ?></h2>
        </div>
        <a href="announcements.php" class="btn btn-outline">← Back</a>
    </div>
    <div class="card" style="max-width:620px">
        <div class="card-body">
            <form method="POST" action="announcements.php?action=<?= $action ?>
                <?= csrfField() ?><?= $isEdit ? "&id=$id" : '' ?>">
                <div class="form-group" style="margin-bottom:16px">
                    <label>Title *</label>
                    <input type="text" name="title" value="<?= h($ann['title'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:16px">
                    <label>Priority</label>
                    <select name="priority">
                        <?php foreach (['Normal','High','Low'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($ann['priority'] ?? 'Normal')==$p?'selected':'' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:22px">
                    <label>Content *</label>
                    <textarea name="content" style="min-height:140px" required><?= h($ann['content'] ?? '') ?></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary"><?= $isEdit ? '✓ Update' : '+ Post' ?></button>
                    <a href="announcements.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php
} else {
    ?>
    <div class="page-header">
        <div class="page-header-left">
            <h2>Announcements</h2>
            <p><?= count($announcements) ?> active announcements</p>
        </div>
        <a href="announcements.php?action=add" class="btn btn-primary">+ Post Announcement</a>
    </div>
    <?= $msg ?>
    <div style="display:flex;flex-direction:column;gap:16px">
        <?php foreach ($announcements as $a): ?>
        <div class="card">
            <div class="card-body">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
                    <div style="flex:1">
                        <div class="flex items-center gap-2" style="margin-bottom:8px;flex-wrap:wrap">
                            <?php if ($a['priority'] === 'High'): ?>
                            <span class="badge badge-danger">HIGH PRIORITY</span>
                            <?php elseif ($a['priority'] === 'Low'): ?>
                            <span class="badge badge-secondary">LOW</span>
                            <?php endif; ?>
                            <span style="font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700"><?= h($a['title']) ?></span>
                        </div>
                        <p style="color:var(--text2);font-size:0.87rem;line-height:1.65;margin-bottom:10px"><?= nl2br(h($a['content'])) ?></p>
                        <div style="font-size:0.75rem;color:var(--text3)">Posted on <?= formatDate($a['created_at']) ?></div>
                    </div>
                    <div class="flex gap-2" style="flex-shrink:0">
                        <a href="announcements.php?action=edit&id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">✎ Edit</a>
                        <a href="announcements.php?action=delete&id=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this announcement?')">✕</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">◉</div>
            <h3>No announcements yet</h3>
            <p>Post your first company-wide announcement</p>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
$content = ob_get_clean();
renderLayout('Announcements', 'announcements', $content);
?>
