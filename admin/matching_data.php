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
        if ($action === 'bulk_import') {
            $rows      = json_decode($_POST['rows'] ?? '[]', true);
            $defDiff   = trim($_POST['bulk_difficulty'] ?? '初級');
            $validD    = ['初級','進階','中級','高級','專家'];
            if (!in_array($defDiff, $validD)) $defDiff = '初級';
            $imported  = 0;
            $skipped   = 0;
            if (is_array($rows)) {
                $stmt = $db->prepare('INSERT INTO matching_pairs (classical_term, modern_meaning, source_essay, difficulty, is_active) VALUES (?, ?, ?, ?, 1)');
                foreach ($rows as $row) {
                    $term    = trim($row[0] ?? '');
                    $meaning = trim($row[1] ?? '');
                    $source  = trim($row[2] ?? '');
                    $diff    = trim($row[3] ?? '');
                    if (!in_array($diff, $validD)) $diff = $defDiff;
                    if (empty($term) || empty($meaning)) { $skipped++; continue; }
                    $stmt->execute([$term, $meaning, $source, $diff]);
                    $imported++;
                }
            }
            $msg = "批量匯入完成：成功 {$imported} 筆，跳過 {$skipped} 筆。";
            if ($imported === 0) $msgType = 'error';
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
            <button class="btn btn-outline" onclick="openBulkModal()"><i class="fas fa-file-import"></i> 批量匯入</button>
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

// ─── Bulk Import ───────────────────────────────────────────────────────────
let bulkParsedRows = [];

function openBulkModal() {
    document.getElementById('bulk-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    document.getElementById('bulk-preview').style.display = 'none';
    document.getElementById('bulk-paste').value = '';
    document.getElementById('bulk-file').value  = '';
    bulkParsedRows = [];
}
function closeBulkModal() {
    document.getElementById('bulk-modal').style.display = 'none';
    document.body.style.overflow = '';
}

function parseBulkText(text) {
    var lines = text.split(/\r?\n/).filter(function(l){ return l.trim(); });
    var rows = [];
    lines.forEach(function(line) {
        // Skip header-like lines
        if (/^(文言|classical|字詞|term)/i.test(line.trim())) return;
        var cols;
        // Try tab, then comma, then pipe
        if (line.indexOf('\t') > -1)       cols = line.split('\t');
        else if (line.indexOf('|') > -1)   cols = line.split('|').filter(function(c){ return c.trim(); });
        else                                cols = line.split(',');
        // Clean up quotes from CSV
        cols = cols.map(function(c){ return c.trim().replace(/^["']|["']$/g,''); });
        if (cols.length >= 2 && cols[0] && cols[1]) {
            rows.push(cols);
        }
    });
    return rows;
}

function showBulkPreview(rows) {
    bulkParsedRows = rows;
    var container = document.getElementById('bulk-preview');
    if (!rows.length) { container.style.display = 'none'; return; }

    var diffs = ['初級','進階','中級','高級','專家'];
    var html  = '<div style="max-height:340px;overflow-y:auto;margin-top:14px;">';
    html += '<table style="width:100%;border-collapse:collapse;font-size:0.84rem;">';
    html += '<thead><tr style="background:rgba(127,179,213,0.1);">';
    html += '<th style="padding:7px 10px;text-align:left;"><input type="checkbox" id="bulk-check-all" onchange="toggleAllBulk(this.checked)"> 全選</th>';
    html += '<th style="padding:7px 10px;text-align:left;">文言字詞</th>';
    html += '<th style="padding:7px 10px;text-align:left;">現代語譯</th>';
    html += '<th style="padding:7px 10px;text-align:left;">出處</th>';
    html += '<th style="padding:7px 10px;text-align:left;">難度</th>';
    html += '</tr></thead><tbody>';
    rows.forEach(function(row, i) {
        var term    = row[0] || '';
        var meaning = row[1] || '';
        var source  = row[2] || '';
        var diff    = row[3] || '';
        var validDiff = diffs.includes(diff) ? diff : '';
        var diffSel = '<select class="bulk-diff" data-idx="'+i+'" style="border:1px solid #ddd;border-radius:4px;padding:2px 4px;font-size:0.8rem;">';
        diffs.forEach(function(d){ diffSel += '<option value="'+d+'"'+(d===validDiff?' selected':'')+'>'+d+'</option>'; });
        diffSel += '</select>';
        html += '<tr style="border-bottom:1px solid rgba(127,179,213,0.1);">';
        html += '<td style="padding:6px 10px;"><input type="checkbox" class="bulk-row-check" data-idx="'+i+'" checked></td>';
        html += '<td style="padding:6px 10px;font-weight:700;">'+escHtmlBulk(term)+'</td>';
        html += '<td style="padding:6px 10px;">'+escHtmlBulk(meaning)+'</td>';
        html += '<td style="padding:6px 10px;color:#888;">'+escHtmlBulk(source)+'</td>';
        html += '<td style="padding:6px 10px;">'+diffSel+'</td>';
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    html += '<div style="margin-top:12px;font-size:0.83rem;color:var(--dark-color);">共解析 '+rows.length+' 筆，請勾選要匯入的資料。</div>';
    container.innerHTML = html;
    container.style.display = 'block';
}

function toggleAllBulk(checked) {
    document.querySelectorAll('.bulk-row-check').forEach(function(cb){ cb.checked = checked; });
}

function escHtmlBulk(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function parseBulkFile(file) {
    var name = file.name.toLowerCase();
    if (name.endsWith('.xlsx') || name.endsWith('.xls')) {
        if (typeof XLSX === 'undefined') {
            alert('XLSX 解析器未載入，請稍候再試。');
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var wb   = XLSX.read(e.target.result, { type: 'array' });
                var ws   = wb.Sheets[wb.SheetNames[0]];
                var data = XLSX.utils.sheet_to_json(ws, { header: 1 });
                var rows = data.filter(function(r){ return r.length >= 2 && r[0] && r[1]; })
                               .map(function(r){ return r.map(function(c){ return String(c||'').trim(); }); });
                showBulkPreview(rows);
            } catch(err) { alert('XLSX 解析失敗：' + err.message); }
        };
        reader.readAsArrayBuffer(file);
    } else {
        var reader = new FileReader();
        reader.onload = function(e) { showBulkPreview(parseBulkText(e.target.result)); };
        reader.readAsText(file, 'UTF-8');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var pasteArea = document.getElementById('bulk-paste');
    if (pasteArea) {
        pasteArea.addEventListener('input', function(){
            if (this.value.trim()) showBulkPreview(parseBulkText(this.value));
            else document.getElementById('bulk-preview').style.display='none';
        });
    }
    var fileInput = document.getElementById('bulk-file');
    if (fileInput) {
        fileInput.addEventListener('change', function(){
            if (this.files[0]) parseBulkFile(this.files[0]);
        });
    }
});

function submitBulkImport() {
    var selected = [];
    document.querySelectorAll('.bulk-row-check:checked').forEach(function(cb) {
        var idx  = parseInt(cb.dataset.idx);
        var row  = bulkParsedRows[idx] ? bulkParsedRows[idx].slice(0, 4) : [];
        // Override difficulty with the select value
        var diffEl = document.querySelector('.bulk-diff[data-idx="'+idx+'"]');
        if (diffEl && row.length >= 1) row[3] = diffEl.value;
        if (row.length >= 2) selected.push(row);
    });
    if (!selected.length) { alert('請至少勾選一筆資料。'); return; }

    var form     = document.createElement('form');
    form.method  = 'POST';
    form.action  = '';
    var fields   = {
        csrf_token:      '<?= e($csrfToken) ?>',
        action:          'bulk_import',
        rows:            JSON.stringify(selected),
        bulk_difficulty: document.getElementById('bulk-default-diff').value,
    };
    Object.keys(fields).forEach(function(k){
        var inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = k;
        inp.value = fields[k];
        form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
}
</script>

<!-- Bulk Import Modal -->
<div id="bulk-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.5);overflow-y:auto;padding:40px 20px;" onclick="if(event.target===this)closeBulkModal()">
    <div style="max-width:780px;margin:0 auto;background:white;border-radius:var(--border-radius);padding:32px;">
        <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:6px;"><i class="fas fa-file-import" style="color:var(--primary-color);"></i> 批量匯入題組</h2>
        <p style="font-size:0.82rem;color:var(--dark-color);margin-bottom:20px;">
            支援純文字、CSV、TSV、XLSX 格式。每行格式：<code>文言字詞, 現代語譯, 出處（選填）, 難度（選填）</code>
        </p>

        <div class="grid-2" style="gap:16px;margin-bottom:16px;">
            <div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label class="form-label">上傳檔案（TXT / CSV / XLSX）</label>
                    <input type="file" id="bulk-file" class="form-input" accept=".txt,.csv,.tsv,.xlsx,.xls" style="padding:8px;">
                </div>
            </div>
            <div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label class="form-label">預設難度（無難度欄位時使用）</label>
                    <select id="bulk-default-diff" class="form-select">
                        <?php foreach (['初級','進階','中級','高級','專家'] as $d): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:12px;">
            <label class="form-label">或直接貼上文字</label>
            <textarea id="bulk-paste" class="form-textarea" rows="5"
                      placeholder="每行一筆，以逗號、Tab 或 | 分隔&#10;例：&#10;惟，只有&#10;清風，微風，赤壁賦，初級&#10;走，逃跑，廉頗藺相如列傳"></textarea>
        </div>

        <div id="bulk-preview"></div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
            <button type="button" class="btn btn-outline" onclick="closeBulkModal()">取消</button>
            <button type="button" class="btn btn-primary" onclick="submitBulkImport()">
                <i class="fas fa-file-import"></i> 匯入勾選資料
            </button>
        </div>
    </div>
</div>

<!-- SheetJS for XLSX parsing (client-side, admin-only page) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
