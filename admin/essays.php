<?php
/**
 * 文樞 — 管理：翻譯文章庫管理
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db  = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 驗證失敗。'; $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $fields = [
                trim($_POST['title']      ?? ''),
                trim($_POST['author']     ?? ''),
                trim($_POST['dynasty']    ?? ''),
                trim($_POST['category']   ?? '文言文'),
                trim($_POST['genre']      ?? ''),
                trim($_POST['content']    ?? ''),
                isset($_POST['is_active']) ? 1 : 0,
                (int)($_POST['sort_order'] ?? 0),
            ];
            if (empty($fields[0]) || empty($fields[5])) {
                $msg = '標題和內容不可為空。'; $msgType = 'error';
            } elseif ($action === 'add') {
                $db->prepare('INSERT INTO translate_essays (title, author, dynasty, category, genre, content, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute($fields);
                $msg = '文章已新增。';
            } else {
                $id = (int)$_POST['id'];
                $db->prepare('UPDATE translate_essays SET title=?, author=?, dynasty=?, category=?, genre=?, content=?, is_active=?, sort_order=? WHERE id=?')
                   ->execute([...$fields, $id]);
                $msg = '文章已更新。';
            }
        }
        if ($action === 'delete') {
            $db->prepare('DELETE FROM translate_essays WHERE id = ?')->execute([(int)$_POST['id']]);
            $msg = '文章已刪除。';
        }
    }
}

$essays = $db->query('SELECT * FROM translate_essays ORDER BY sort_order ASC, id ASC')->fetchAll();
$csrfToken  = getCsrfToken();
$pageTitle  = '翻譯文章庫';
$activePage = 'admin';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="page-title"><i class="fas fa-scroll" style="color:var(--accent-color);margin-right:8px;"></i>翻譯文章庫管理</h1>
            <a href="<?= BASE_URL ?>/admin/index.php" style="font-size:0.82rem;color:var(--dark-color);"><i class="fas fa-arrow-left"></i> 返回後台</a>
        </div>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> 新增文章</button>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
    <?php endif; ?>

    <div class="card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:rgba(127,179,213,0.08);font-size:0.82rem;font-weight:700;color:var(--dark-color);">
                    <th style="padding:12px 16px;text-align:left;">標題</th>
                    <th style="padding:12px 16px;text-align:left;">作者</th>
                    <th style="padding:12px 16px;text-align:left;">朝代</th>
                    <th style="padding:12px 16px;text-align:left;">分類</th>
                    <th style="padding:12px 16px;text-align:center;">狀態</th>
                    <th style="padding:12px 16px;text-align:center;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($essays as $es): ?>
                <tr style="border-top:1px solid rgba(127,179,213,0.1);font-size:0.88rem;">
                    <td style="padding:10px 16px;font-weight:600;"><?= e($es['title']) ?></td>
                    <td style="padding:10px 16px;"><?= e($es['author']) ?></td>
                    <td style="padding:10px 16px;"><?= e($es['dynasty']) ?></td>
                    <td style="padding:10px 16px;"><span class="badge badge-primary"><?= e($es['category']) ?></span></td>
                    <td style="padding:10px 16px;text-align:center;">
                        <?= $es['is_active'] ? '<span class="badge badge-success">啟用</span>' : '<span style="color:#aaa;font-size:0.78rem;">停用</span>' ?>
                    </td>
                    <td style="padding:10px 16px;text-align:center;">
                        <div style="display:flex;gap:6px;justify-content:center;">
                            <button class="btn-sm btn-sm-outline" onclick="editEssay(<?= htmlspecialchars(json_encode($es, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('確定刪除此文章？')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $es['id'] ?>">
                                <button type="submit" class="btn-sm btn-sm-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($essays)): ?>
                <tr><td colspan="6" style="padding:32px;text-align:center;color:#aaa;">尚無文章</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="essay-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.5);overflow-y:auto;padding:40px 20px;" onclick="if(event.target===this)closeModal()">
    <div style="max-width:680px;margin:0 auto;background:white;border-radius:var(--border-radius);padding:32px;">
        <h2 id="modal-title" style="font-size:1.1rem;font-weight:700;margin-bottom:20px;"></h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id"     id="form-id"     value="">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">標題 *</label>
                    <input type="text" class="form-input" name="title" id="f-title" required>
                </div>
                <div class="form-group">
                    <label class="form-label">作者</label>
                    <input type="text" class="form-input" name="author" id="f-author">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">朝代</label>
                    <input type="text" class="form-input" name="dynasty" id="f-dynasty">
                </div>
                <div class="form-group">
                    <label class="form-label">分類</label>
                    <input type="text" class="form-input" name="category" id="f-category" value="文言文" placeholder="散文、詩歌…">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">體裁</label>
                <input type="text" class="form-input" name="genre" id="f-genre" placeholder="寫景記遊、論說文…">
            </div>
            <div class="form-group">
                <label class="form-label">原文內容 *</label>
                <textarea class="form-textarea" name="content" id="f-content" rows="8" required></textarea>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" class="form-input" name="sort_order" id="f-sort_order" value="0">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" id="f-is_active" checked> 啟用
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(title) {
    document.getElementById('modal-title').textContent = title || '新增文章';
    document.getElementById('essay-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('essay-modal').style.display = 'none';
    document.body.style.overflow = '';
}
function editEssay(data) {
    document.getElementById('form-action').value   = 'edit';
    document.getElementById('form-id').value       = data.id;
    document.getElementById('f-title').value       = data.title || '';
    document.getElementById('f-author').value      = data.author || '';
    document.getElementById('f-dynasty').value     = data.dynasty || '';
    document.getElementById('f-category').value    = data.category || '文言文';
    document.getElementById('f-genre').value       = data.genre || '';
    document.getElementById('f-content').value     = data.content || '';
    document.getElementById('f-sort_order').value  = data.sort_order || 0;
    document.getElementById('f-is_active').checked = !!parseInt(data.is_active);
    openModal('編輯文章');
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
