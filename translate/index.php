<?php
/**
 * 文樞 — 文言翻譯工具頁
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db     = getDB();
$essays = $db->query('SELECT id, title, author, dynasty, category FROM translate_essays WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();

// 按分類分組
$grouped = [];
foreach ($essays as $e) {
    $grouped[$e['category']][] = $e;
}

$pageTitle  = '文言翻譯';
$activePage = 'translate';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-container">
    <h1 class="page-title"><i class="fas fa-language" style="color:var(--primary-color);margin-right:8px;"></i>文言翻譯解析</h1>
    <p class="page-subtitle">選擇預設文章，或直接輸入自訂文言文，AI 將提供逐字解析與白話翻譯</p>

    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">

        <!-- 左側：文章選擇 -->
        <div style="width:260px;flex-shrink:0;">
            <div class="card" style="padding:16px;position:sticky;top:76px;max-height:calc(100vh - 100px);overflow-y:auto;">
                <div style="font-size:0.82rem;font-weight:700;color:var(--dark-color);margin-bottom:12px;">
                    <i class="fas fa-list" style="margin-right:4px;"></i>預設文章
                </div>
                <?php if (empty($essays)): ?>
                <p style="font-size:0.82rem;color:#aaa;">管理員尚未新增文章</p>
                <?php else: ?>
                <?php foreach ($grouped as $category => $group): ?>
                <div style="font-size:0.75rem;font-weight:700;color:var(--primary-color);margin:10px 0 6px;text-transform:uppercase;letter-spacing:1px;">
                    <?= e($category) ?>
                </div>
                <?php foreach ($group as $essay): ?>
                <button class="essay-select-btn" style="display:block;width:100%;text-align:left;background:none;border:none;padding:7px 10px;border-radius:6px;cursor:pointer;font-size:0.84rem;color:var(--dark-color);transition:var(--transition);font-family:inherit;"
                        onclick="selectEssay(<?= json_encode($essay['title'], JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($essay['id']) ?>)"
                        onmouseover="this.style.background='rgba(127,179,213,0.1)';this.style.color='var(--primary-color)'"
                        onmouseout="this.style.background='';this.style.color='var(--dark-color)'"
                        id="essay-btn-<?= $essay['id'] ?>">
                    <?= e($essay['title']) ?>
                    <span style="font-size:0.72rem;color:#aaa;display:block;"><?= e($essay['author']) ?> · <?= e($essay['dynasty']) ?></span>
                </button>
                <?php endforeach; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 右側：翻譯工具 -->
        <div style="flex:1;min-width:0;">

            <!-- 輸入區 -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-title">
                    <i class="fas fa-pen" style="color:var(--primary-color);"></i>
                    輸入文言文
                </div>
                <div class="form-group">
                    <label class="form-label">文章標題（選填）</label>
                    <input type="text" class="form-input" id="essay-title" placeholder="如：記承天寺夜遊">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">文言文原文</label>
                    <textarea class="form-textarea" id="essay-content" rows="6"
                              placeholder="請在此輸入文言文原文，或從左側選擇預設文章…" style="min-height:140px;"></textarea>
                </div>
            </div>

            <button class="btn btn-primary" onclick="doTranslate()" id="translate-btn" style="margin-bottom:20px;">
                <i class="fas fa-magic"></i> AI 翻譯解析
            </button>

            <!-- 結果區 -->
            <div id="result-area" style="display:none;">
                <div class="card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                        <div class="card-title" style="margin-bottom:0;">
                            <i class="fas fa-book" style="color:var(--highlight-color);"></i>
                            翻譯解析結果
                        </div>
                        <span id="cache-badge" class="badge badge-primary" style="display:none;">
                            <i class="fas fa-bolt"></i> 快取
                        </span>
                    </div>
                    <div id="result-content" style="white-space:pre-wrap;font-size:0.9rem;line-height:1.8;color:var(--text-color);"></div>
                </div>
            </div>

            <div id="error-area" class="alert alert-error" style="display:none;"></div>
            <div id="loading-area" style="display:none;text-align:center;padding:40px;">
                <span class="spinner" style="width:32px;height:32px;border-width:3px;"></span>
                <p style="margin-top:12px;color:var(--dark-color);">AI 正在解析，可能需要20-60秒，請耐心等待…</p>
            </div>

        </div><!-- end right -->
    </div><!-- end flex -->
</div>

<script>
const essays = <?= json_encode(
    array_column(
        $db->query('SELECT id, title, content FROM translate_essays WHERE is_active = 1')->fetchAll(),
        null,
        'id'
    ),
    JSON_UNESCAPED_UNICODE
) ?>;

function selectEssay(title, id) {
    document.getElementById('essay-title').value   = title;
    document.getElementById('essay-content').value = essays[id] ? essays[id].content : '';

    // 高亮選中
    document.querySelectorAll('.essay-select-btn').forEach(function(b) {
        b.style.background = '';
        b.style.color = 'var(--dark-color)';
    });
    const btn = document.getElementById('essay-btn-' + id);
    if (btn) { btn.style.background = 'rgba(127,179,213,0.15)'; btn.style.color = 'var(--primary-color)'; }
}

async function doTranslate() {
    const title   = document.getElementById('essay-title').value.trim();
    const content = document.getElementById('essay-content').value.trim();

    if (!content) {
        alert('請先輸入文言文原文');
        return;
    }

    const btn     = document.getElementById('translate-btn');
    const result  = document.getElementById('result-area');
    const loading = document.getElementById('loading-area');
    const errEl   = document.getElementById('error-area');

    btn.disabled   = true;
    btn.innerHTML  = '<span class="spinner"></span> 解析中…';
    result.style.display  = 'none';
    errEl.style.display   = 'none';
    loading.style.display = 'block';

    try {
        const res  = await fetch('<?= BASE_URL ?>/api/translate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, content })
        });
        const data = await res.json();

        loading.style.display = 'none';

        if (data.success) {
            document.getElementById('result-content').textContent = data.content;
            const badge = document.getElementById('cache-badge');
            badge.style.display = data.cached ? 'inline-flex' : 'none';
            result.style.display = 'block';
            result.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            errEl.style.display = 'flex';
            errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.message || '翻譯失敗，請稍後再試');
        }
    } catch (err) {
        loading.style.display = 'none';
        errEl.style.display   = 'flex';
        errEl.innerHTML       = '<i class="fas fa-exclamation-circle"></i> 網絡錯誤，請重試。';
    }

    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-magic"></i> AI 翻譯解析';
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
