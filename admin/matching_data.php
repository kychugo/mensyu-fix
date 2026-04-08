<?php
/**
 * 文樞 — 管理：配對遊戲題庫管理
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
                trim($_POST['classical_term'] ?? ''),
                trim($_POST['modern_meaning'] ?? ''),
                trim($_POST['source_essay']   ?? ''),
                trim($_POST['difficulty']     ?? '初級'),
                isset($_POST['is_active']) ? 1 : 0,
            ];
            if (empty($fields[0]) || empty($fields[1])) {
                $msg = '字詞和語譯不可為空。'; $msgType = 'error';
            } elseif ($action === 'add') {
                $db->prepare('INSERT INTO matching_pairs (classical_term, modern_meaning, source_essay, difficulty, is_active) VALUES (?, ?, ?, ?, ?)')
                   ->execute($fields);
                $msg = '題組已新增。';
            } else {
                $id = (int)$_POST['id'];
                $db->prepare('UPDATE matching_pairs SET classical_term=?, modern_meaning=?, source_essay=?, difficulty=?, is_active=? WHERE id=?')
                   ->execute([...$fields, $id]);
                $msg = '題組已更新。';
            }
        }
        if ($action === 'delete') {
            $db->prepare('DELETE FROM matching_pairs WHERE id = ?')->execute([(int)$_POST['id']]);
            $msg = '題組已刪除。';
        }
    }
}

$filterDiff = $_GET['difficulty'] ?? '';
$validDiffs = ['初級','進階','中級','高級','專家'];

if ($filterDiff && in_array($filterDiff, $validDiffs)) {
    $st = $db->prepare('SELECT * FROM matching_pairs WHERE difficulty = ? ORDER BY id ASC');
    $st->execute([$filterDiff]);
} else {
    $st = $db->query('SELECT * FROM matching_pairs ORDER BY difficulty ASC, id ASC');
}
$pairs = $st->fetchAll();

$csrfToken  = getCsrfToken();
$pageTitle  = '配對題庫管理';
$activePage = 'admin';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="page-title"><i class="fas fa-puzzle-piece" style="color:var(--highlight-color);margin-right:8px;"></i>配對遊戲題庫管理</h1>
            <a href="<?= BASE_URL ?>/admin/index.php" style="font-size:0.82rem;color:var(--dark-color);"><i class="fas fa-arrow-left"></i> 返回後台</a>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <select class="form-select" style="width:auto;" onchange="location.href='<?= BASE_URL ?>/admin/matching_data.php?difficulty='+this.value">
                <option value="">全部難度</option>
                <?php foreach ($validDiffs as $d): ?>
                <option value="<?= $d ?>" <?= $filterDiff === $d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> 新增題組</button>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
    <?php endif; ?>

    <div class="alert alert-info" style="font-size:0.85rem;">
        <i class="fas fa-info-circle"></i>
        共 <?= count($pairs) ?> 個題組。遊戲時各難度會從對應難度的題庫隨機抽取。
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:rgba(127,179,213,0.08);font-size:0.82rem;font-weight:700;color:var(--dark-color);">
                    <th style="padding:12px 16px;text-align:left;">文言字詞</th>
                    <th style="padding:12px 16px;text-align:left;">現代語譯</th>
                    <th style="padding:12px 16px;text-align:left;">出處</th>
                    <th style="padding:12px 16px;text-align:center;">難度</th>
                    <th style="padding:12px 16px;text-align:center;">狀態</th>
                    <th style="padding:12px 16px;text-align:center;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pairs as $p): ?>
                <tr style="border-top:1px solid rgba(127,179,213,0.1);font-size:0.88rem;">
                    <td style="padding:10px 16px;font-weight:700;"><?= e($p['classical_term']) ?></td>
                    <td style="padding:10px 16px;"><?= e($p['modern_meaning']) ?></td>
                    <td style="padding:10px 16px;color:var(--dark-color);"><?= e($p['source_essay']) ?></td>
                    <td style="padding:10px 16px;text-align:center;"><span class="badge badge-primary"><?= e($p['difficulty']) ?></span></td>
                    <td style="padding:10px 16px;text-align:center;">
                        <?= $p['is_active'] ? '<span class="badge badge-success">啟用</span>' : '<span style="color:#aaa;font-size:0.78rem;">停用</span>' ?>
                    </td>
                    <td style="padding:10px 16px;text-align:center;">
                        <div style="display:flex;gap:6px;justify-content:center;">
                            <button class="btn-sm btn-sm-outline" onclick="editPair(<?= htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('確定刪除此題組？')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-sm btn-sm-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pairs)): ?>
                <tr><td colspan="6" style="padding:32px;text-align:center;color:#aaa;">尚無題組</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="pair-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.5);overflow-y:auto;padding:40px 20px;" onclick="if(event.target===this)closeModal()">
    <div style="max-width:540px;margin:0 auto;background:white;border-radius:var(--border-radius);padding:32px;">
        <h2 id="modal-title" style="font-size:1.1rem;font-weight:700;margin-bottom:20px;"></h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id"     id="form-id"     value="">

            <div class="form-group">
                <label class="form-label">文言字詞 *</label>
                <input type="text" class="form-input" name="classical_term" id="f-classical_term" required>
            </div>
            <div class="form-group">
                <label class="form-label">現代語譯 *</label>
                <input type="text" class="form-input" name="modern_meaning" id="f-modern_meaning" required>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">出處（選填）</label>
                    <input type="text" class="form-input" name="source_essay" id="f-source_essay" placeholder="如：記承天寺夜遊">
                </div>
                <div class="form-group">
                    <label class="form-label">難度</label>
                    <select class="form-select" name="difficulty" id="f-difficulty">
                        <?php foreach (['初級','進階','中級','高級','專家'] as $d): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
    document.getElementById('modal-title').textContent = title || '新增題組';
    document.getElementById('pair-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('pair-modal').style.display = 'none';
    document.body.style.overflow = '';
}
function editPair(data) {
    document.getElementById('form-action').value          = 'edit';
    document.getElementById('form-id').value              = data.id;
    document.getElementById('f-classical_term').value     = data.classical_term || '';
    document.getElementById('f-modern_meaning').value     = data.modern_meaning || '';
    document.getElementById('f-source_essay').value       = data.source_essay || '';
    document.getElementById('f-difficulty').value         = data.difficulty || '初級';
    document.getElementById('f-is_active').checked        = !!parseInt(data.is_active);
    openModal('編輯題組');
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
