<?php
/**
 * 文樞 — 管理：關卡內容管理
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
                (int)($_POST['tutor_id']      ?? 0),
                (int)($_POST['level_number']  ?? 1),
                trim($_POST['difficulty']     ?? '初級'),
                trim($_POST['essay_title']    ?? ''),
                trim($_POST['essay_author']   ?? ''),
                trim($_POST['essay_content']  ?? ''),
                trim($_POST['notes']          ?? ''),
                isset($_POST['is_active']) ? 1 : 0,
            ];
            if (empty($fields[3]) || empty($fields[5])) {
                $msg = '標題和內容不可為空。'; $msgType = 'error';
            } elseif ($action === 'add') {
                $db->prepare('INSERT INTO levels (tutor_id, level_number, difficulty, essay_title, essay_author, essay_content, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute($fields);
                $msg = '關卡已新增。';
            } else {
                $id = (int)$_POST['id'];
                $db->prepare('UPDATE levels SET tutor_id=?, level_number=?, difficulty=?, essay_title=?, essay_author=?, essay_content=?, notes=?, is_active=? WHERE id=?')
                   ->execute([...$fields, $id]);
                $msg = '關卡已更新。';
            }
        }

        if ($action === 'delete') {
            $db->prepare('DELETE FROM levels WHERE id = ?')->execute([(int)$_POST['id']]);
            $msg = '關卡已刪除。';
        }
    }
}

// 篩選
$filterTutor = (int)($_GET['tutor_id'] ?? 0);

$tutors = $db->query('SELECT id, name FROM tutors ORDER BY sort_order ASC, id ASC')->fetchAll();

if ($filterTutor) {
    $st = $db->prepare('SELECT l.*, t.name AS tutor_name FROM levels l JOIN tutors t ON l.tutor_id = t.id WHERE l.tutor_id = ? ORDER BY l.level_number ASC');
    $st->execute([$filterTutor]);
} else {
    $st = $db->query('SELECT l.*, t.name AS tutor_name FROM levels l JOIN tutors t ON l.tutor_id = t.id ORDER BY t.sort_order ASC, l.level_number ASC');
}
$levels = $st->fetchAll();

$csrfToken  = getCsrfToken();
$pageTitle  = '關卡管理';
$activePage = 'admin';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="page-title"><i class="fas fa-layer-group" style="color:var(--highlight-color);margin-right:8px;"></i>關卡內容管理</h1>
            <a href="<?= BASE_URL ?>/admin/index.php" style="font-size:0.82rem;color:var(--dark-color);"><i class="fas fa-arrow-left"></i> 返回後台</a>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <select class="form-select" style="width:auto;" onchange="location.href='<?= BASE_URL ?>/admin/levels.php?tutor_id='+this.value">
                <option value="0">全部導師</option>
                <?php foreach ($tutors as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $filterTutor === $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> 新增關卡</button>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
    <?php endif; ?>

    <div class="card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:rgba(127,179,213,0.08);font-size:0.82rem;font-weight:700;color:var(--dark-color);">
                    <th style="padding:12px 16px;text-align:left;">導師</th>
                    <th style="padding:12px 16px;text-align:left;">關</th>
                    <th style="padding:12px 16px;text-align:left;">難度</th>
                    <th style="padding:12px 16px;text-align:left;">標題</th>
                    <th style="padding:12px 16px;text-align:left;">作者</th>
                    <th style="padding:12px 16px;text-align:center;">狀態</th>
                    <th style="padding:12px 16px;text-align:center;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($levels as $lv): ?>
                <tr style="border-top:1px solid rgba(127,179,213,0.1);font-size:0.88rem;">
                    <td style="padding:10px 16px;font-weight:600;"><?= e($lv['tutor_name']) ?></td>
                    <td style="padding:10px 16px;"><?= $lv['level_number'] ?></td>
                    <td style="padding:10px 16px;"><span class="badge badge-primary"><?= e($lv['difficulty']) ?></span></td>
                    <td style="padding:10px 16px;"><?= e($lv['essay_title']) ?></td>
                    <td style="padding:10px 16px;"><?= e($lv['essay_author']) ?></td>
                    <td style="padding:10px 16px;text-align:center;">
                        <?= $lv['is_active'] ? '<span class="badge badge-success">啟用</span>' : '<span style="color:#aaa;font-size:0.78rem;">停用</span>' ?>
                    </td>
                    <td style="padding:10px 16px;text-align:center;">
                        <div style="display:flex;gap:6px;justify-content:center;">
                            <button class="btn-sm btn-sm-outline" onclick="editLevel(<?= htmlspecialchars(json_encode($lv, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('確定刪除此關卡？用戶進度將一併刪除。')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $lv['id'] ?>">
                                <button type="submit" class="btn-sm btn-sm-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($levels)): ?>
                <tr><td colspan="7" style="padding:32px;text-align:center;color:#aaa;">尚無關卡</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="level-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.5);overflow-y:auto;padding:40px 20px;" onclick="if(event.target===this)closeModal()">
    <div style="max-width:680px;margin:0 auto;background:white;border-radius:var(--border-radius);padding:32px;">
        <h2 id="modal-title" style="font-size:1.1rem;font-weight:700;margin-bottom:20px;"></h2>
        <form method="POST" id="level-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id"     id="form-id"     value="">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">文學導師 *</label>
                    <select class="form-select" name="tutor_id" id="f-tutor_id" required>
                        <?php foreach ($tutors as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">關卡編號 *</label>
                    <input type="number" class="form-input" name="level_number" id="f-level_number" min="1" value="1" required>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">難度</label>
                    <select class="form-select" name="difficulty" id="f-difficulty">
                        <?php foreach (['初級','進階','中級','高級','專家'] as $d): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">文章作者</label>
                    <input type="text" class="form-input" name="essay_author" id="f-essay_author">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">文章標題 *</label>
                <input type="text" class="form-input" name="essay_title" id="f-essay_title" required>
            </div>
            <div class="form-group">
                <label class="form-label">文章原文 *</label>
                <textarea class="form-textarea" name="essay_content" id="f-essay_content" rows="6" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">教學備注</label>
                <textarea class="form-textarea" name="notes" id="f-notes" rows="2"></textarea>
            </div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:16px;">
                <input type="checkbox" name="is_active" id="f-is_active" checked> 啟用
            </label>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(title) {
    document.getElementById('modal-title').textContent = title || '新增關卡';
    document.getElementById('level-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('level-modal').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('level-form').reset();
    document.getElementById('form-action').value = 'add';
    document.getElementById('form-id').value     = '';
}
function editLevel(data) {
    document.getElementById('form-action').value       = 'edit';
    document.getElementById('form-id').value           = data.id;
    document.getElementById('f-tutor_id').value        = data.tutor_id;
    document.getElementById('f-level_number').value    = data.level_number;
    document.getElementById('f-difficulty').value      = data.difficulty;
    document.getElementById('f-essay_author').value    = data.essay_author || '';
    document.getElementById('f-essay_title').value     = data.essay_title || '';
    document.getElementById('f-essay_content').value   = data.essay_content || '';
    document.getElementById('f-notes').value           = data.notes || '';
    document.getElementById('f-is_active').checked     = !!parseInt(data.is_active);
    openModal('編輯關卡');
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
