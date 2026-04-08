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
/* ── 翻譯結果格式 ─────────────────────────────────────── */
.translation-block {
    margin-bottom: 28px;
    border-bottom: 2px dashed rgba(127,179,213,0.25);
    padding-bottom: 20px;
}
.translation-block:last-child { border-bottom: none; }
.block-label {
    font-weight: 700; font-size: 0.85rem; color: var(--primary-color);
    margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
}
.original-text {
    font-size: 1rem; line-height: 2;
    padding: 14px 16px;
    background: linear-gradient(135deg, rgba(174,214,241,0.25), rgba(173,214,241,0.15));
    border-left: 4px solid var(--primary-color);
    border-radius: 0 8px 8px 0;
    font-family: 'Noto Serif TC', serif;
    margin-bottom: 12px;
}
.translation-text {
    background: linear-gradient(135deg, rgba(162,217,206,0.18), rgba(118,215,196,0.08));
    padding: 14px 16px;
    border-left: 4px solid var(--secondary-color);
    border-radius: 0 8px 8px 0;
    line-height: 1.8;
    font-size: 0.9rem;
}
.character-breakdown {
    margin-top: 12px;
    padding: 14px 16px;
    background: linear-gradient(135deg, rgba(169,204,227,0.15), rgba(125,206,160,0.08));
    border-left: 4px solid var(--highlight-color);
    border-radius: 0 8px 8px 0;
    font-size: 0.88rem;
}
/* click-to-reveal 句子 */
.sentence-container { margin-bottom: 6px; }
.hidden-text {
    background: #ecf0f1; color: transparent;
    border-radius: 4px; padding: 3px 8px;
    transition: var(--transition); user-select: none;
    position: relative; cursor: pointer; display: inline-block;
}
.hidden-text::before {
    content: '點擊顯示'; position: absolute;
    top: 50%; left: 50%; transform: translate(-50%, -50%);
    color: var(--primary-color); font-size: 11px; opacity: 0.7;
    pointer-events: none;
}
.hidden-text:hover { background: rgba(127,179,213,0.2); }
.hidden-text.revealed { background: transparent; color: inherit; }
.hidden-text.revealed::before { display: none; }
/* character item */
.character-item { margin-bottom: 8px; display: flex; align-items: flex-start; padding: 5px 0; border-bottom: 1px solid rgba(127,179,213,0.1); }
.character-item:last-child { border-bottom: none; }
.character {
    color: var(--primary-color); font-weight: 700;
    cursor: pointer; border-bottom: 2px dotted var(--primary-color);
    padding: 2px 4px; border-radius: 3px; transition: var(--transition);
    flex-shrink: 0;
}
.character:hover { background: rgba(127,179,213,0.12); }
.char-explanation {
    display: none; padding-left: 10px; color: var(--dark-color);
    font-style: italic; flex: 1;
}
.char-explanation.revealed { display: block; }
/* common-word 星詞 */
.common-word {
    background: linear-gradient(135deg, rgba(127,179,213,0.2), rgba(162,217,206,0.2));
    padding: 1px 5px; border-radius: 4px; font-weight: 700;
    cursor: pointer; position: relative;
}
.common-word .star-hint {
    display: none; position: absolute; top: -28px; left: 50%;
    transform: translateX(-50%); background: var(--dark-color); color: white;
    padding: 3px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap;
    z-index: 10;
}
.common-word:hover .star-hint { display: block; }
/* star vocabulary panel */
.star-panel {
    position: sticky; top: 76px;
    max-height: calc(100vh - 100px); overflow-y: auto;
}
.star-word-item {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 8px 10px; border-radius: 6px;
    background: rgba(127,179,213,0.06); margin-bottom: 6px; font-size: 0.84rem;
}
.star-word-item .word { font-weight: 700; color: var(--primary-color); }
.star-word-item .meaning { color: var(--dark-color); font-size: 0.78rem; }
.star-remove { background: none; border: none; color: #ccc; cursor: pointer; font-size: 0.75rem; padding: 2px; }
.star-remove:hover { color: var(--error-color); }
/* floating original (mobile) */
.floating-original {
    display: none; position: fixed;
    background: var(--light-color); border: 2px solid var(--primary-color);
    border-radius: var(--border-radius); box-shadow: var(--shadow-heavy);
    z-index: 1000;
}
@media (min-width: 1025px) {
    .floating-original { top: 50%; right: 20px; transform: translateY(-50%); width: 320px; max-height: 65vh; overflow-y: auto; }
}
@media (max-width: 768px) {
    .floating-original { top: 0; left: 0; right: 0; width: 100%; max-width: none; border-radius: 0 0 var(--border-radius) var(--border-radius); border-top: none; overflow: hidden; min-height: 120px; }
}
.floating-original.show { display: block; }
.floating-header {
    background: var(--gradient-primary); color: white; padding: 12px 16px;
    display: flex; justify-content: space-between; align-items: center;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}
@media (max-width: 768px) { .floating-header { border-radius: 0; } }
.floating-content { padding: 16px; overflow-y: auto; font-family: 'Noto Serif TC', serif; font-size: 0.95rem; line-height: 1.9; }
.reopen-floating { position: fixed; top: 70px; right: 16px; background: var(--gradient-primary); color: white; border: none; border-radius: 50%; width: 44px; height: 44px; font-size: 1.1rem; cursor: pointer; box-shadow: var(--shadow-medium); z-index: 999; display: none; transition: var(--transition); }
@media (max-width: 768px) { .reopen-floating.show { display: flex; align-items: center; justify-content: center; } }
</style>

<div class="page-container">
    <h1 class="page-title"><i class="fas fa-language" style="color:var(--primary-color);margin-right:8px;"></i>文言翻譯解析</h1>
    <p class="page-subtitle">選擇預設文章，或直接輸入自訂文言文，AI 將提供逐字解析與白話翻譯</p>

    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">

        <!-- 左側：文章選擇 + 詞庫 -->
        <div style="width:240px;flex-shrink:0;">
            <div class="star-panel">
                <div class="card" style="padding:14px;margin-bottom:16px;">
                    <div style="font-size:0.82rem;font-weight:700;color:var(--dark-color);margin-bottom:10px;">
                        <i class="fas fa-list" style="margin-right:4px;"></i>預設文章
                    </div>
                    <?php if (empty($essays)): ?>
                    <p style="font-size:0.82rem;color:#aaa;">管理員尚未新增文章</p>
                    <?php else: ?>
                    <?php foreach ($grouped as $category => $group): ?>
                    <div style="font-size:0.72rem;font-weight:700;color:var(--primary-color);margin:8px 0 5px;text-transform:uppercase;letter-spacing:1px;">
                        <?= e($category) ?>
                    </div>
                    <?php foreach ($group as $essay): ?>
                    <button class="essay-select-btn" style="display:block;width:100%;text-align:left;background:none;border:none;padding:6px 9px;border-radius:6px;cursor:pointer;font-size:0.83rem;color:var(--dark-color);transition:var(--transition);font-family:inherit;"
                            onclick="selectEssay(<?= json_encode($essay['title'], JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($essay['id']) ?>)"
                            onmouseover="this.style.background='rgba(127,179,213,0.1)';this.style.color='var(--primary-color)'"
                            onmouseout="this.style.background='';this.style.color='var(--dark-color)'"
                            id="essay-btn-<?= $essay['id'] ?>">
                        <?= e($essay['title']) ?>
                        <span style="font-size:0.71rem;color:#aaa;display:block;"><?= e($essay['author']) ?> · <?= e($essay['dynasty']) ?></span>
                    </button>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- 我的星詞庫 -->
                <div class="card" style="padding:14px;" id="star-panel">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <div style="font-size:0.82rem;font-weight:700;color:var(--dark-color);">
                            <i class="fas fa-star" style="color:var(--warning-color);margin-right:4px;"></i>我的詞庫
                        </div>
                        <button onclick="clearAllStars()" style="background:none;border:none;color:#ccc;cursor:pointer;font-size:0.75rem;" title="清空詞庫">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div id="star-list">
                        <p style="font-size:0.78rem;color:#aaa;" id="star-empty">點擊結果中的粗體詞彙可加入詞庫</p>
                    </div>
                </div>
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
                              placeholder="請在此輸入文言文原文，或從左側選擇預設文章…" style="min-height:140px;font-family:'Noto Serif TC',serif;"></textarea>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
                <button class="btn btn-primary" onclick="doTranslate()" id="translate-btn">
                    <i class="fas fa-magic"></i> AI 翻譯解析
                </button>
                <button class="btn btn-outline" onclick="revealAll()" id="reveal-all-btn" style="display:none;">
                    <i class="fas fa-eye"></i> 顯示全部翻譯
                </button>
                <button class="btn" onclick="clearAll()" style="background:rgba(231,76,60,0.1);color:var(--error-color);border:1.5px solid rgba(231,76,60,0.2);">
                    <i class="fas fa-eraser"></i> 清除
                </button>
            </div>

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
                    <div id="result-content"></div>
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

<!-- 懸浮式原文（手機版） -->
<div id="floating-original" class="floating-original">
    <div class="floating-header">
        <span style="font-weight:600;font-size:0.95rem;">原文</span>
        <button id="floating-toggle" style="background:none;border:none;color:white;font-size:1.1rem;cursor:pointer;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:var(--transition);" onclick="toggleFloating()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="floating-content" id="floating-text"></div>
</div>
<button id="reopen-floating" class="reopen-floating" title="開啟原文" onclick="reopenFloating()">
    <i class="fas fa-scroll"></i>
</button>

<script>
const essays = <?= json_encode(
    array_column(
        $db->query('SELECT id, title, content FROM translate_essays WHERE is_active = 1')->fetchAll(),
        null,
        'id'
    ),
    JSON_UNESCAPED_UNICODE
) ?>;

let currentOriginalTexts = [];
let floatingEnabled = localStorage.getItem('floatingEnabled') !== 'false';
let starredWords = JSON.parse(localStorage.getItem('starredWords') || '[]');

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

// ── 翻譯 ─────────────────────────────────────────────────────────────────
async function doTranslate() {
    const title   = document.getElementById('essay-title').value.trim();
    const content = document.getElementById('essay-content').value.trim();
    if (!content) { alert('請先輸入文言文原文'); return; }

    const btn     = document.getElementById('translate-btn');
    const result  = document.getElementById('result-area');
    const loading = document.getElementById('loading-area');
    const errEl   = document.getElementById('error-area');
    const revBtn  = document.getElementById('reveal-all-btn');

    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> 解析中…';
    result.style.display  = 'none';
    errEl.style.display   = 'none';
    loading.style.display = 'block';
    revBtn.style.display  = 'none';

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
            revBtn.style.display = 'inline-flex';
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

// ── 顯示格式化結果 ────────────────────────────────────────────────────────
function displayFormattedResult(text) {
    // 移除 think 標籤
    text = text.replace(/<think>[\s\S]*?<\/think>/g, '');
    text = cleanTextFormatting(text);

    const blocks = splitTranslationBlocks(text);
    let html = '';
    currentOriginalTexts = [];

    for (const block of blocks) {
        if (block.type === 'original') {
            const fmtOrig = formatOriginalText(block.content);
            currentOriginalTexts.push(fmtOrig);
            html += `<div class="translation-block">
<div class="block-label"><i class="fas fa-scroll"></i> 原文</div>
<div class="original-text">${fmtOrig}</div>`;
        } else if (block.type === 'translation') {
            html += `<div class="block-label"><i class="fas fa-language"></i> 語譯</div>
<div class="translation-text">${formatTranslationText(block.content)}</div>`;
        } else if (block.type === 'breakdown') {
            html += `<div class="block-label" style="margin-top:10px;"><i class="fas fa-search"></i> 逐字解釋</div>
<div class="character-breakdown">${formatCharacterBreakdown(block.content)}</div>
</div>`;
        }
    }

    document.getElementById('result-content').innerHTML = html;
    setupClickToReveal();
    setupCommonWordStars();
    setupCharacterClick();

    // 顯示懸浮原文（手機版）
    if (currentOriginalTexts.length > 0 && isMobile()) {
        document.getElementById('floating-text').innerHTML = currentOriginalTexts.join('<br><br>');
        if (floatingEnabled) showFloating();
    }
}

// ── 分割翻譯塊 ────────────────────────────────────────────────────────────
function splitTranslationBlocks(text) {
    const blocks = [];
    const lines = text.split('\n');
    let current = null;
    for (const rawLine of lines) {
        const line = rawLine.trim();
        if (line.startsWith('原文：') || line === '原文') {
            if (current) blocks.push(current);
            current = { type: 'original', content: '' };
            const c = line.replace(/^原文：?\s*/, '');
            if (c) current.content += c + '\n';
        } else if (line.startsWith('語譯：') || line === '語譯') {
            if (current) blocks.push(current);
            current = { type: 'translation', content: '' };
            const c = line.replace(/^語譯：?\s*/, '');
            if (c) current.content += c + '\n';
        } else if (line.startsWith('逐字解釋：') || line === '逐字解釋') {
            if (current) blocks.push(current);
            current = { type: 'breakdown', content: '' };
            const c = line.replace(/^逐字解釋：?\s*/, '');
            if (c) current.content += c + '\n';
        } else if (current) {
            current.content += rawLine + '\n';
        }
    }
    if (current) blocks.push(current);
    return blocks;
}

function cleanTextFormatting(text) {
    text = text.replace(/^\*+\s*$/gm, '');
    text = text.replace(/^\*+/, '').replace(/\*+$/, '');
    text = text.replace(/\n{3,}/g, '\n\n');
    text = text.replace(/^-{3,}\s*$/gm, '');
    return text.trim();
}

function cleanSectionText(text) {
    return text.trim().replace(/^：+/, '').replace(/：+$/, '').replace(/^\*+/, '').replace(/\*+$/, '');
}

function formatOriginalText(text) {
    text = cleanSectionText(text);
    return text.replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<span class="common-word">$1<span class="star-hint">⭐ 加入詞庫</span></span>');
}

function formatTranslationText(text) {
    text = cleanSectionText(text);
    return text.replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<span class="common-word">$1<span class="star-hint">⭐ 加入詞庫</span></span>');
}

function formatCharacterBreakdown(text) {
    text = cleanSectionText(text);
    text = text.replace(/\*\*(.*?)\*\*/g, '<span class="common-word">$1<span class="star-hint">⭐ 加入詞庫</span></span>');
    const lines = text.split('\n').filter(l => l.trim());
    let html = '';
    for (let line of lines) {
        line = line.replace(/^\*+/, '').replace(/\*+$/, '').trim();
        const parts = line.split(/:|：/);
        if (parts.length >= 2) {
            const ch   = parts[0].trim();
            const expl = parts.slice(1).join(':').trim();
            if (/[\u4e00-\u9fff]/.test(ch)) {
                html += `<div class="character-item">
<span class="character" data-word="${ch}" data-meaning="${expl.replace(/"/g,'&quot;')}">${ch}</span>
<span class="char-explanation">：${expl}</span>
</div>`;
            }
        } else if (line.trim()) {
            html += `<div style="font-size:0.85rem;color:var(--dark-color);margin-bottom:4px;">${line}</div>`;
        }
    }
    return html;
}

// ── 點擊顯示句子翻譯 ──────────────────────────────────────────────────────
function setupClickToReveal() {
    const transDivs = document.querySelectorAll('.translation-text');
    transDivs.forEach(div => {
        const raw = div.innerHTML.split('<br>').filter(s => s.trim());
        let html2 = '';
        raw.forEach(s => {
            if (s.trim()) {
                html2 += `<div class="sentence-container"><span class="hidden-text">${s.trim()}</span></div>`;
            }
        });
        div.innerHTML = html2;
        div.querySelectorAll('.hidden-text').forEach(el => {
            el.addEventListener('click', () => el.classList.toggle('revealed'));
        });
    });
}

// ── 點擊逐字解釋 ─────────────────────────────────────────────────────────
function setupCharacterClick() {
    document.querySelectorAll('.character').forEach(ch => {
        ch.addEventListener('click', function() {
            const expl = this.nextElementSibling;
            if (expl && expl.classList.contains('char-explanation')) {
                expl.classList.toggle('revealed');
            }
        });
    });
}

// ── 星詞庫功能 ────────────────────────────────────────────────────────────
function setupCommonWordStars() {
    document.querySelectorAll('.common-word').forEach(el => {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            const word    = this.textContent.replace('⭐ 加入詞庫', '').trim();
            const meaning = this.dataset.meaning || '常見文言詞彙';
            addStar(word, meaning, this);
        });
    });
}

function addStar(word, meaning, el) {
    if (starredWords.find(w => w.word === word)) {
        showStarToast('已在詞庫中：' + word);
        return;
    }
    starredWords.push({ word, meaning });
    localStorage.setItem('starredWords', JSON.stringify(starredWords));
    renderStarList();
    showStarToast('已加入詞庫：' + word + ' ⭐');
    if (el) { el.style.background = 'linear-gradient(135deg, rgba(243,156,18,0.3),rgba(241,196,15,0.2))'; }
}

function removeStar(word) {
    starredWords = starredWords.filter(w => w.word !== word);
    localStorage.setItem('starredWords', JSON.stringify(starredWords));
    renderStarList();
}

function clearAllStars() {
    if (!starredWords.length || confirm('確定清空詞庫？')) {
        starredWords = [];
        localStorage.setItem('starredWords', '[]');
        renderStarList();
    }
}

function renderStarList() {
    const list = document.getElementById('star-list');
    const empty = document.getElementById('star-empty');
    if (!list) return;
    if (starredWords.length === 0) {
        if (empty) empty.style.display = 'block';
        // Remove all items except empty msg
        list.querySelectorAll('.star-word-item').forEach(el => el.remove());
        return;
    }
    if (empty) empty.style.display = 'none';
    list.querySelectorAll('.star-word-item').forEach(el => el.remove());
    starredWords.forEach(item => {
        const div = document.createElement('div');
        div.className = 'star-word-item';
        div.innerHTML = `<div><div class="word">⭐ ${item.word}</div><div class="meaning">${item.meaning}</div></div>
<button class="star-remove" title="移除"><i class="fas fa-times"></i></button>`;
        div.querySelector('.star-remove').onclick = () => removeStar(item.word);
        list.appendChild(div);
    });
}

function showStarToast(msg) {
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;right:20px;background:var(--dark-color);color:white;padding:10px 18px;border-radius:8px;font-size:0.85rem;z-index:2000;box-shadow:var(--shadow-medium);transition:opacity 0.3s;';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 2000);
}

// ── 顯示全部翻譯 ─────────────────────────────────────────────────────────
function revealAll() {
    document.querySelectorAll('.hidden-text').forEach(el => el.classList.add('revealed'));
    document.querySelectorAll('.char-explanation').forEach(el => el.classList.add('revealed'));
}

// ── 清除 ─────────────────────────────────────────────────────────────────
function clearAll() {
    document.getElementById('essay-title').value = '';
    document.getElementById('essay-content').value = '';
    document.getElementById('result-content').innerHTML = '';
    document.getElementById('result-area').style.display = 'none';
    document.getElementById('error-area').style.display = 'none';
    document.getElementById('reveal-all-btn').style.display = 'none';
    currentOriginalTexts = [];
    hideFloating();
    document.querySelectorAll('.essay-select-btn').forEach(b => { b.style.background = ''; b.style.color = 'var(--dark-color)'; });
}

// ── 懸浮原文 ─────────────────────────────────────────────────────────────
function isMobile() { return window.innerWidth <= 1024; }
function showFloating() {
    document.getElementById('floating-original').classList.add('show');
    document.getElementById('reopen-floating').classList.remove('show');
}
function hideFloating() {
    document.getElementById('floating-original').classList.remove('show');
    if (currentOriginalTexts.length > 0 && isMobile()) {
        document.getElementById('reopen-floating').classList.add('show');
    }
}
function toggleFloating() {
    const el = document.getElementById('floating-original');
    if (el.classList.contains('show')) hideFloating();
    else showFloating();
}
function reopenFloating() {
    document.getElementById('reopen-floating').classList.remove('show');
    showFloating();
}

// ── 初始化 ────────────────────────────────────────────────────────────────
renderStarList();
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>

