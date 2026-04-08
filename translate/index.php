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

<style>
/* ── 翻譯頁專用樣式 ─────────────────────────────────────────────── */
.translation-block {
    margin-bottom: 32px;
    border-bottom: 2px dashed rgba(127,179,213,0.25);
    padding-bottom: 24px;
}
.translation-block:last-child { border-bottom: none; }

.tran-section-title {
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 12px;
    font-size: 1rem;
    font-family: 'Noto Serif TC', serif;
    display: flex; align-items: center; gap: 6px;
}

.original-text {
    font-size: 1rem;
    line-height: 2;
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(174,214,241,0.3), rgba(127,179,213,0.1));
    border-left: 4px solid var(--primary-color);
    border-radius: 6px;
    font-family: 'Noto Serif TC', serif;
    margin-bottom: 14px;
}

.translation-text {
    background: linear-gradient(135deg, rgba(162,217,206,0.2), rgba(118,215,196,0.1));
    padding: 14px 18px;
    border-radius: 6px;
    line-height: 1.8;
    font-size: 0.95rem;
    border-left: 4px solid var(--secondary-color);
    margin-bottom: 14px;
}

.character-breakdown {
    padding: 14px 18px;
    background: linear-gradient(135deg, rgba(169,204,227,0.15), rgba(125,206,160,0.1));
    border-radius: 6px;
    font-size: 0.9rem;
    border-left: 4px solid var(--highlight-color);
}

.character-item {
    margin-bottom: 10px;
    display: flex; align-items: flex-start;
    padding: 6px 0;
    border-bottom: 1px solid rgba(127,179,213,0.1);
}
.character-item:last-child { border-bottom: none; }

/* Click-to-reveal 句子 */
.sentence-container { margin-bottom: 8px; }

.hidden-text {
    background: #ecf0f1;
    color: transparent;
    border-radius: 4px;
    padding: 2px 8px;
    cursor: pointer;
    user-select: none;
    position: relative;
    display: inline-block;
    transition: all 0.25s;
    min-width: 40px;
}
.hidden-text::before {
    content: '點擊顯示';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    color: var(--primary-color);
    font-size: 11px; opacity: 0.7;
    pointer-events: none; white-space: nowrap;
}
.hidden-text:hover { background: rgba(127,179,213,0.2); }
.hidden-text.revealed {
    background: transparent;
    color: inherit;
}
.hidden-text.revealed::before { display: none; }

/* 逐字解釋 */
.character {
    color: var(--primary-color); font-weight: 700;
    cursor: pointer;
    border-bottom: 2px dotted var(--primary-color);
    padding: 2px 4px; border-radius: 3px;
    transition: background 0.2s;
    min-width: 28px; display: inline-block; text-align: center;
    flex-shrink: 0;
}
.character:hover { background: rgba(127,179,213,0.12); }

.explanation {
    display: none;
    padding: 4px 0 4px 12px;
    color: var(--dark-color);
    font-style: italic;
    border-left: 3px solid var(--secondary-color);
    flex: 1;
    margin-left: 8px;
}
.explanation.revealed { display: block; animation: slideDown 0.22s ease; }

@keyframes slideDown {
    from { opacity:0; }
    to   { opacity:1; }
}

/* 常見文言字詞高亮 */
.common-word {
    background: linear-gradient(135deg, rgba(127,179,213,0.22), rgba(162,217,206,0.22));
    padding: 1px 5px;
    border-radius: 4px;
    font-weight: 700;
}

/* Star 收藏 */
.star-btn {
    background: none; border: none; cursor: pointer;
    font-size: 1rem; color: #ccc; padding: 0 4px 0 8px;
    transition: color 0.2s; line-height: 1;
    flex-shrink: 0;
}
.star-btn:hover { color: #f39c12; }
.star-btn.starred { color: #f39c12; }

/* 收藏列表面板 */
.starred-words-panel {
    position: fixed; bottom: 80px; right: 20px;
    width: 260px; max-height: 320px; overflow-y: auto;
    background: white; border-radius: var(--border-radius);
    box-shadow: var(--shadow-heavy);
    z-index: 200; display: none;
}
.starred-words-panel.open { display: block; animation: panelFadeIn 0.22s; }
@keyframes panelFadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }

.starred-panel-header {
    background: var(--gradient-primary); color: white;
    padding: 10px 14px;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    font-weight: 700; font-size: 0.9rem;
    display: flex; align-items: center; justify-content: space-between;
}
.starred-item {
    padding: 8px 14px; border-bottom: 1px solid rgba(127,179,213,0.12);
    font-size: 0.85rem; display: flex; align-items: center; gap: 6px;
}
.starred-item:last-child { border-bottom: none; }
.starred-word-char { font-weight: 700; color: var(--primary-color); flex-shrink: 0; }
.starred-word-def  { color: var(--dark-color); flex: 1; }

/* 收藏浮動按鈕 */
.starred-fab {
    position: fixed; bottom: 24px; right: 20px;
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--gradient-primary); color: white;
    border: none; cursor: pointer; font-size: 1.1rem;
    box-shadow: var(--shadow-medium); z-index: 201;
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.2s, box-shadow 0.2s;
}
.starred-fab:hover { transform: scale(1.1); box-shadow: var(--shadow-heavy); }
.fab-badge {
    position: absolute; top: -4px; right: -4px;
    width: 18px; height: 18px; border-radius: 50%;
    background: #e74c3c; color: white; font-size: 0.65rem;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
}
</style>

<div class="page-container">
    <h1 class="page-title"><i class="fas fa-language" style="color:var(--primary-color);margin-right:8px;"></i>文言翻譯解析</h1>
    <p class="page-subtitle">選擇預設文章，或直接輸入自訂文言文，AI 將提供逐字解析（語譯預設隱藏，點擊顯示）</p>

    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">

        <!-- 左側：文章選擇 -->
        <div style="width:240px;flex-shrink:0;">
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
                              placeholder="請在此輸入文言文原文，或從左側選擇預設文章…"
                              style="min-height:140px;font-family:'Noto Serif TC',serif;font-size:1rem;line-height:1.9;"></textarea>
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
                <button class="btn btn-primary" onclick="doTranslate()" id="translate-btn">
                    <i class="fas fa-magic"></i> AI 翻譯解析
                </button>
                <button class="btn btn-outline" onclick="clearAll()">
                    <i class="fas fa-eraser"></i> 清除
                </button>
            </div>

            <!-- 結果區 -->
            <div id="result-area" style="display:none;">
                <div class="card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                        <div class="card-title" style="margin-bottom:0;">
                            <i class="fas fa-book" style="color:var(--highlight-color);"></i>
                            翻譯解析結果
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <span id="cache-badge" class="badge badge-primary" style="display:none;">
                                <i class="fas fa-bolt"></i> 快取
                            </span>
                            <button class="btn btn-outline" onclick="revealAll()" style="padding:5px 14px;font-size:0.82rem;">
                                <i class="fas fa-eye"></i> 全部顯示
                            </button>
                        </div>
                    </div>
                    <div id="result-content" style="font-size:0.92rem;line-height:1.8;color:var(--text-color);"></div>
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

<!-- 收藏浮動按鈕 -->
<button class="starred-fab" id="starred-fab" onclick="toggleStarPanel()" title="我的收藏字詞" style="position:fixed;">
    <i class="fas fa-star"></i>
    <span class="fab-badge" id="starred-count" style="display:none;">0</span>
</button>

<!-- 收藏面板 -->
<div class="starred-words-panel" id="starred-panel">
    <div class="starred-panel-header">
        <span><i class="fas fa-star" style="margin-right:6px;"></i>收藏字詞</span>
        <button onclick="toggleStarPanel()" style="background:none;border:none;color:white;cursor:pointer;font-size:1.1rem;line-height:1;">×</button>
    </div>
    <div id="starred-list"></div>
    <div id="starred-empty" style="padding:16px;font-size:0.85rem;color:#aaa;text-align:center;">
        尚無收藏字詞<br><small>點擊解析結果中的 ☆ 即可收藏</small>
    </div>
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

// ── 星號收藏（localStorage）──────────────────────────────────────────────
let starredWords = JSON.parse(localStorage.getItem('mensyu_starred_words') || '{}');

function saveStarred() {
    localStorage.setItem('mensyu_starred_words', JSON.stringify(starredWords));
    updateStarCount();
}

function updateStarCount() {
    const count = Object.keys(starredWords).length;
    const badge = document.getElementById('starred-count');
    badge.textContent = count;
    badge.style.display = count > 0 ? 'flex' : 'none';
    renderStarPanel();
}

function renderStarPanel() {
    const list  = document.getElementById('starred-list');
    const empty = document.getElementById('starred-empty');
    const keys  = Object.keys(starredWords);
    if (keys.length === 0) {
        list.innerHTML = '';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    list.innerHTML = keys.map(k => {
        const safeChar = k.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        const safeDef  = starredWords[k].replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        return `<div class="starred-item">
            <span class="starred-word-char">${safeChar}</span>
            <span class="starred-word-def">${safeDef}</span>
            <button onclick="unstar(this)" data-char="${safeChar}"
              style="background:none;border:none;color:#e74c3c;cursor:pointer;font-size:0.8rem;flex-shrink:0;">✕</button>
        </div>`;
    }).join('');
}

function toggleStar(btn) {
    const ch  = btn.dataset.char;
    const def = btn.dataset.def;
    if (starredWords[ch]) {
        delete starredWords[ch];
        btn.classList.remove('starred');
        btn.title = '收藏此字';
    } else {
        starredWords[ch] = def;
        btn.classList.add('starred');
        btn.title = '取消收藏';
    }
    saveStarred();
}

function unstar(btn) {
    const ch = btn.dataset.char;
    delete starredWords[ch];
    saveStarred();
    document.querySelectorAll('.star-btn[data-char="' + ch + '"]').forEach(b => {
        b.classList.remove('starred');
        b.title = '收藏此字';
    });
}

function toggleStarPanel() {
    document.getElementById('starred-panel').classList.toggle('open');
}

// ── 文章選擇 ─────────────────────────────────────────────────────────────
function selectEssay(title, id) {
    document.getElementById('essay-title').value   = title;
    document.getElementById('essay-content').value = essays[id] ? essays[id].content : '';
    document.querySelectorAll('.essay-select-btn').forEach(b => {
        b.style.background = '';
        b.style.color = 'var(--dark-color)';
    });
    const btn = document.getElementById('essay-btn-' + id);
    if (btn) { btn.style.background = 'rgba(127,179,213,0.15)'; btn.style.color = 'var(--primary-color)'; }
}

// ── 清除 ──────────────────────────────────────────────────────────────────
function clearAll() {
    document.getElementById('essay-title').value   = '';
    document.getElementById('essay-content').value = '';
    document.getElementById('result-area').style.display  = 'none';
    document.getElementById('error-area').style.display   = 'none';
    document.getElementById('result-content').innerHTML   = '';
    document.querySelectorAll('.essay-select-btn').forEach(b => {
        b.style.background = '';
        b.style.color = 'var(--dark-color)';
    });
}

// ── 全部顯示 ─────────────────────────────────────────────────────────────
function revealAll() {
    document.querySelectorAll('.hidden-text').forEach(el => el.classList.add('revealed'));
    document.querySelectorAll('.explanation').forEach(el  => el.classList.add('revealed'));
}

// ── 翻譯 ─────────────────────────────────────────────────────────────────
async function doTranslate() {
    const title   = document.getElementById('essay-title').value.trim();
    const content = document.getElementById('essay-content').value.trim();

    if (!content) { alert('請先輸入文言文原文'); return; }

    const btn     = document.getElementById('translate-btn');
    const result  = document.getElementById('result-area');
    const loading = document.getElementById('loading-area');
    const errEl   = document.getElementById('error-area');

    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> 解析中…';
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
            displayFormattedResult(data.content);
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

// ── 格式化輸出 ────────────────────────────────────────────────────────────
function displayFormattedResult(text) {
    text = text.replace(/<think>[\s\S]*?<\/think>/g, '');
    text = cleanTextFormatting(text);
    const blocks = splitTranslationBlocks(text);
    let html = '';

    blocks.forEach(block => {
        if (block.type === 'original') {
            html += '<div class="translation-block">'
                  + '<div class="tran-section-title"><i class="fas fa-scroll"></i> 原文</div>'
                  + '<div class="original-text">' + formatOriginalText(block.content) + '</div>';
        } else if (block.type === 'translation') {
            html += '<div class="tran-section-title"><i class="fas fa-language"></i> 語譯 <small style="font-weight:400;color:#aaa;font-size:0.78rem;">（點擊灰色文字以顯示）</small></div>'
                  + '<div class="translation-text">' + formatTranslationText(block.content) + '</div>';
        } else if (block.type === 'breakdown') {
            html += '<div class="character-breakdown">'
                  + '<div class="tran-section-title" style="margin-bottom:10px;"><i class="fas fa-search"></i> 逐字解釋 <small style="font-weight:400;color:#aaa;font-size:0.78rem;">（點擊字元展開解釋，☆ 收藏）</small></div>'
                  + formatCharacterBreakdown(block.content)
                  + '</div></div>';
        }
    });

    document.getElementById('result-content').innerHTML = html;
    setupClickToReveal();
    setupWordExplanations();
}

function splitTranslationBlocks(text) {
    const blocks = [];
    const lines  = text.split('\n');
    let cur = null;

    lines.forEach(rawLine => {
        const line = rawLine.trim();
        if (/^原文[：:\s]*$/.test(line) || /^原文[：:]/.test(line)) {
            if (cur) blocks.push(cur);
            cur = { type: 'original', content: '' };
            const extra = line.replace(/^原文[：:]?\s*/, '');
            if (extra) cur.content += extra + '\n';
        } else if (/^語譯[：:\s]*$/.test(line) || /^語譯[：:]/.test(line)) {
            if (cur) blocks.push(cur);
            cur = { type: 'translation', content: '' };
            const extra = line.replace(/^語譯[：:]?\s*/, '');
            if (extra) cur.content += extra + '\n';
        } else if (/^逐字解釋[：:\s]*$/.test(line) || /^逐字解釋[：:]/.test(line)) {
            if (cur) blocks.push(cur);
            cur = { type: 'breakdown', content: '' };
            const extra = line.replace(/^逐字解釋[：:]?\s*/, '');
            if (extra) cur.content += extra + '\n';
        } else if (cur) {
            cur.content += rawLine + '\n';
        }
    });

    if (cur) blocks.push(cur);
    return blocks;
}

function cleanTextFormatting(text) {
    text = text.replace(/^\*+\s*$/gm, '');
    text = text.replace(/^\*+/, '').replace(/\*+$/, '');
    text = text.replace(/\n{3,}/g, '\n\n');
    text = text.replace(/^-{3,}\s*$/gm, '');
    return text.trim();
}

function escapeHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function formatOriginalText(text) {
    return cleanSectionText(text)
        .replace(/\n/g, '<br>')
        .replace(/\*\*(.*?)\*\*/g, '<span class="common-word">$1</span>');
}

function formatTranslationText(text) {
    const lines = cleanSectionText(text).split('\n').filter(l => l.trim());
    return lines.map(line => {
        const safe = escapeHtml(line.trim()).replace(/\*\*(.*?)\*\*/g, '<span class="common-word">$1</span>');
        return '<div class="sentence-container"><span class="hidden-text">' + safe + '</span></div>';
    }).join('');
}

function formatCharacterBreakdown(text) {
    const clean = cleanSectionText(text);
    const lines  = clean.split('\n').filter(l => l.trim());
    let html = '';
    lines.forEach(line => {
        line = line.replace(/^\*+/, '').replace(/\*+$/, '').trim();
        const parts = line.split(/[:：]/);
        if (parts.length >= 2) {
            const ch  = parts[0].trim();
            const def = parts.slice(1).join(':').trim();
            if (/[\u4e00-\u9fff]/.test(ch)) {
                const safeCh  = escapeHtml(ch);
                const safeDef = escapeHtml(def).replace(/\*\*(.*?)\*\*/g, '<span class="common-word">$1</span>');
                const starred = !!starredWords[ch];
                html += '<div class="character-item">'
                      + '<span class="character">' + safeCh + '</span>'
                      + '<span class="explanation">：' + safeDef + '</span>'
                      + '<button class="star-btn' + (starred ? ' starred' : '') + '" '
                      + 'data-char="' + safeCh + '" data-def="' + escapeHtml(def) + '" '
                      + 'onclick="toggleStar(this)" '
                      + 'title="' + (starred ? '取消收藏' : '收藏此字') + '">★</button>'
                      + '</div>';
            }
        } else if (line.trim()) {
            html += '<div>' + escapeHtml(line) + '</div>';
        }
    });
    return html;
}

function cleanSectionText(text) {
    return text.trim()
               .replace(/^[：:]+/, '')
               .replace(/[：:]+$/, '')
               .replace(/^\*+/, '')
               .replace(/\*+$/, '');
}

function setupClickToReveal() {
    document.querySelectorAll('.hidden-text').forEach(el => {
        el.addEventListener('click', function() { this.classList.toggle('revealed'); });
    });
}

function setupWordExplanations() {
    document.querySelectorAll('.character').forEach(ch => {
        ch.addEventListener('click', function() {
            const exp = this.nextElementSibling;
            if (exp && exp.classList.contains('explanation')) {
                exp.classList.toggle('revealed');
            }
        });
    });
    updateStarCount();
}

// Init
updateStarCount();
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
