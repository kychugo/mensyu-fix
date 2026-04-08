<?php
/**
 * 文樞 — 管理：文學導師管理
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
                'name'           => trim($_POST['name'] ?? ''),
                'dynasty'        => trim($_POST['dynasty'] ?? ''),
                'description'    => trim($_POST['description'] ?? ''),
                'background'     => trim($_POST['background'] ?? ''),
                'personality'    => trim($_POST['personality'] ?? ''),
                'language_style' => trim($_POST['language_style'] ?? ''),
                'avatar_url'     => trim($_POST['avatar_url'] ?? ''),
                'gradient_class' => trim($_POST['gradient_class'] ?? 'gradient-primary'),
                'is_active'      => isset($_POST['is_active']) ? 1 : 0,
                'sort_order'     => (int)($_POST['sort_order'] ?? 0),
            ];
            if (empty($fields['name'])) {
                $msg = '姓名不可為空。'; $msgType = 'error';
            } elseif ($action === 'add') {
                $db->prepare('INSERT INTO tutors (name, dynasty, description, background, personality, language_style, avatar_url, gradient_class, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute(array_values($fields));
                $msg = '導師「' . $fields['name'] . '」已新增。';
            } else {
                $id = (int)$_POST['id'];
                $db->prepare('UPDATE tutors SET name=?, dynasty=?, description=?, background=?, personality=?, language_style=?, avatar_url=?, gradient_class=?, is_active=?, sort_order=? WHERE id=?')
                   ->execute([...array_values($fields), $id]);
                $msg = '導師已更新。';
            }
        }

        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $db->prepare('DELETE FROM tutors WHERE id = ?')->execute([$id]);
            $msg = '導師已刪除。';
        }
    }
}

$tutors     = getActiveTutors() ?: $db->query('SELECT * FROM tutors ORDER BY sort_order ASC, id ASC')->fetchAll();
$csrfToken  = getCsrfToken();
$pageTitle  = '導師管理';
$activePage = 'admin';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="page-title"><i class="fas fa-user-tie" style="color:var(--primary-color);margin-right:8px;"></i>文學導師管理</h1>
            <a href="<?= BASE_URL ?>/admin/index.php" style="font-size:0.82rem;color:var(--dark-color);"><i class="fas fa-arrow-left"></i> 返回後台</a>
        </div>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> 新增導師</button>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <?php foreach ($tutors as $tutor): ?>
        <div class="card" style="display:flex;gap:14px;align-items:flex-start;">
            <div style="width:52px;height:52px;border-radius:50%;background:var(--gradient-primary);overflow:hidden;flex-shrink:0;">
                <?php if (!empty($tutor['avatar_url'])): ?>
                <img src="<?= e($tutor['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;"><?= e($tutor['name']) ?> <span style="font-size:0.78rem;color:var(--dark-color);"><?= e($tutor['dynasty']) ?></span></div>
                <div style="font-size:0.82rem;color:var(--dark-color);margin:4px 0;"><?= e(mb_substr($tutor['description'] ?? '', 0, 60)) ?></div>
                <div style="font-size:0.75rem;color:#aaa;">排序: <?= $tutor['sort_order'] ?> · <?= $tutor['is_active'] ? '<span style="color:var(--success-color)">啟用</span>' : '<span style="color:var(--error-color)">停用</span>' ?></div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0;">
                <button class="btn-sm btn-sm-outline" onclick="editTutor(<?= htmlspecialchars(json_encode($tutor, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                    <i class="fas fa-edit"></i>
                </button>
                <form method="POST" style="display:inline;" onsubmit="return confirm('確定刪除此導師？相關關卡及動態將一併刪除。')">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action"     value="delete">
                    <input type="hidden" name="id"         value="<?= $tutor['id'] ?>">
                    <button type="submit" class="btn-sm btn-sm-danger"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 新增 / 編輯 Modal -->
<div id="tutor-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.5);overflow-y:auto;padding:40px 20px;" onclick="if(event.target===this)closeModal()">
    <div style="max-width:640px;margin:0 auto;background:white;border-radius:var(--border-radius);padding:32px;">
        <h2 id="modal-title" style="font-size:1.1rem;font-weight:700;margin-bottom:20px;"></h2>
        <form method="POST" id="tutor-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action"     id="form-action" value="add">
            <input type="hidden" name="id"         id="form-id"     value="">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">姓名 *</label>
                    <input type="text" class="form-input" name="name" id="f-name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">朝代</label>
                    <input type="text" class="form-input" name="dynasty" id="f-dynasty">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">簡介</label>
                <textarea class="form-textarea" name="description" id="f-description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">歷史背景（顯示於導師介紹）</label>
                <textarea class="form-textarea" name="background" id="f-background" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">性格設定（AI Prompt 用）</label>
                <textarea class="form-textarea" name="personality" id="f-personality" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">語言風格（AI Prompt 用）</label>
                <textarea class="form-textarea" name="language_style" id="f-language_style" rows="2"></textarea>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">頭像 URL</label>
                    <input type="url" class="form-input" name="avatar_url" id="f-avatar_url" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label class="form-label">漸層樣式</label>
                    <select class="form-select" name="gradient_class" id="f-gradient_class">
                        <option value="gradient-primary">藍色（gradient-primary）</option>
                        <option value="gradient-secondary">綠色（gradient-secondary）</option>
                        <option value="gradient-accent">淡藍（gradient-accent）</option>
                    </select>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">排序（數字小在前）</label>
                    <input type="number" class="form-input" name="sort_order" id="f-sort_order" value="0">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.9rem;">
                        <input type="checkbox" name="is_active" id="f-is_active" checked> 啟用
                    </label>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(title) {
    document.getElementById('modal-title').textContent = title || '新增文學導師';
    document.getElementById('tutor-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('tutor-modal').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('tutor-form').reset();
    document.getElementById('form-action').value = 'add';
    document.getElementById('form-id').value     = '';
}
function editTutor(data) {
    document.getElementById('form-action').value       = 'edit';
    document.getElementById('form-id').value           = data.id;
    document.getElementById('f-name').value            = data.name || '';
    document.getElementById('f-dynasty').value         = data.dynasty || '';
    document.getElementById('f-description').value     = data.description || '';
    document.getElementById('f-background').value      = data.background || '';
    document.getElementById('f-personality').value     = data.personality || '';
    document.getElementById('f-language_style').value  = data.language_style || '';
    document.getElementById('f-avatar_url').value      = data.avatar_url || '';
    document.getElementById('f-gradient_class').value  = data.gradient_class || 'gradient-primary';
    document.getElementById('f-sort_order').value      = data.sort_order || 0;
    document.getElementById('f-is_active').checked     = !!parseInt(data.is_active);
    openModal('編輯文學導師');
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
