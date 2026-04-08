<?php
/**
 * 文樞 — 文言配對遊戲
 * 雙列卡片配對：文言字詞 ↔ 現代語譯，限時挑戰
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db         = getDB();
$difficulty = $_GET['difficulty'] ?? '';
$validDiffs = ['初級', '進階', '中級', '高級', '專家'];
if (!in_array($difficulty, $validDiffs, true)) {
    $difficulty = '';
}

// 難度對應題對數量
$pairCounts = ['初級' => 8, '進階' => 10, '中級' => 12, '高級' => 16, '專家' => 20];
// 難度對應時間限制（秒）
$timeLimits = ['初級' => 120, '進階' => 100, '中級' => 90, '高級' => 75, '專家' => 60];

$pageTitle  = '文言配對遊戲';
$activePage = 'games';
require __DIR__ . '/../includes/partials/header.php';
?>

<style>
.difficulty-btn {
    padding: 14px 28px;
    border-radius: 10px;
    background: white;
    border: 2px solid rgba(127,179,213,0.25);
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-color);
    transition: var(--transition);
    font-family: inherit;
    text-align: center;
}
.difficulty-btn:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: var(--shadow-medium);
}
.match-board {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.match-col { display: flex; flex-direction: column; gap: 10px; }
.match-card {
    padding: 14px 18px;
    border-radius: 10px;
    background: white;
    border: 2px solid rgba(127,179,213,0.2);
    cursor: pointer;
    font-size: 0.92rem;
    font-weight: 500;
    transition: var(--transition);
    box-shadow: var(--shadow-light);
    text-align: center;
    min-height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1.4;
    user-select: none;
}
.match-card:hover:not(.matched):not(.selected) {
    border-color: var(--primary-color);
    transform: scale(1.02);
}
.match-card.selected {
    border-color: var(--primary-color);
    background: rgba(127,179,213,0.12);
    color: var(--primary-color);
    transform: scale(1.03);
}
.match-card.matched {
    border-color: var(--success-color);
    background: rgba(39,174,96,0.08);
    color: var(--success-color);
    cursor: default;
    opacity: 0.7;
}
.match-card.wrong {
    border-color: var(--error-color);
    background: rgba(231,76,60,0.08);
    animation: shake 0.3s ease;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25%       { transform: translateX(-6px); }
    75%       { transform: translateX(6px); }
}
.timer-bar {
    height: 8px;
    border-radius: 4px;
    background: rgba(127,179,213,0.15);
    overflow: hidden;
    margin-bottom: 20px;
}
.timer-fill {
    height: 100%;
    border-radius: 4px;
    background: var(--gradient-primary);
    transition: width 1s linear, background 0.5s;
}
</style>

<div class="page-container" style="max-width:900px;">
    <h1 class="page-title"><i class="fas fa-puzzle-piece" style="color:var(--highlight-color);margin-right:8px;"></i>文言配對遊戲</h1>
    <p class="page-subtitle">將文言字詞與現代語譯正確配對，越快越好！</p>

    <!-- 難度選擇 -->
    <div id="difficulty-screen">
        <div class="grid-3" style="gap:16px;max-width:600px;margin:0 auto;">
            <?php foreach ($validDiffs as $diff): ?>
            <?php $count = $pairCounts[$diff]; $time = $timeLimits[$diff]; ?>
            <button class="difficulty-btn" onclick="startGame('<?= $diff ?>')">
                <div style="font-size:0.75rem;color:var(--primary-color);margin-bottom:4px;"><?= $diff ?></div>
                <?= $diff ?>
                <div style="font-size:0.75rem;color:var(--dark-color);margin-top:6px;font-weight:400;">
                    <?= $count ?>對 · <?= $time ?>秒
                </div>
            </button>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:40px;text-align:center;">
            <h3 style="font-size:1rem;font-weight:700;color:var(--text-color);margin-bottom:12px;">如何遊玩</h3>
            <div style="max-width:480px;margin:0 auto;font-size:0.88rem;color:var(--dark-color);line-height:1.8;">
                <p>① 點擊左欄的文言字詞</p>
                <p>② 再點擊右欄對應的現代語譯</p>
                <p>③ 配對正確即高亮消除，配錯則短暫變紅</p>
                <p>④ 在時間限制內配完全部，即告勝利！</p>
            </div>
        </div>
    </div>

    <!-- 遊戲畫面 -->
    <div id="game-screen" style="display:none;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
            <div>
                <span id="game-difficulty" class="badge badge-primary" style="font-size:0.85rem;"></span>
                <span id="game-progress"   style="font-size:0.88rem;color:var(--dark-color);margin-left:10px;"></span>
            </div>
            <div style="font-size:1.2rem;font-weight:700;" id="timer-display">--</div>
        </div>
        <div class="timer-bar"><div class="timer-fill" id="timer-fill"></div></div>

        <div class="match-board" id="match-board">
            <div class="match-col" id="left-col"></div>
            <div class="match-col" id="right-col"></div>
        </div>

        <div style="margin-top:16px;text-align:right;">
            <button class="btn btn-outline" onclick="quitGame()" style="padding:7px 16px;font-size:0.82rem;">
                <i class="fas fa-times"></i> 放棄
            </button>
        </div>
    </div>

    <!-- 結果畫面 -->
    <div id="result-screen" style="display:none;">
        <div class="card" style="text-align:center;padding:48px 32px;">
            <div id="result-emoji" style="font-size:3.5rem;margin-bottom:12px;"></div>
            <h2 id="result-title"  style="font-size:1.6rem;font-weight:700;margin-bottom:8px;"></h2>
            <p  id="result-detail" style="color:var(--dark-color);margin-bottom:28px;"></p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-primary" onclick="restartGame()">
                    <i class="fas fa-redo"></i> 再玩一次
                </button>
                <button class="btn btn-outline" onclick="showDifficulty()">
                    <i class="fas fa-list"></i> 更換難度
                </button>
            </div>
        </div>
    </div>

</div>

<script>
const PAIR_COUNTS  = <?= json_encode($pairCounts,  JSON_UNESCAPED_UNICODE) ?>;
const TIME_LIMITS  = <?= json_encode($timeLimits,  JSON_UNESCAPED_UNICODE) ?>;
const ALL_PAIRS    = <?= json_encode(
    $db->query("SELECT classical_term, modern_meaning FROM matching_pairs WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC),
    JSON_UNESCAPED_UNICODE
) ?>;

let currentDiff = '';
let pairs        = [];
let selectedLeft  = null;
let selectedRight = null;
let matchedCount  = 0;
let timerInterval = null;
let timeLeft      = 0;
let totalTime     = 0;

function shuffle(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

function startGame(diff) {
    currentDiff = diff;
    const count = PAIR_COUNTS[diff] || 8;
    const limit = TIME_LIMITS[diff] || 120;

    // 按難度篩選題庫，不足則補充其他難度
    let pool = ALL_PAIRS.filter(function(p) { return true; });
    if (pool.length < count) {
        alert('題庫題目不足，請管理員新增更多配對詞組。');
        return;
    }

    pairs = shuffle(pool).slice(0, count);
    matchedCount   = 0;
    selectedLeft   = null;
    selectedRight  = null;
    totalTime      = limit;
    timeLeft       = limit;

    renderBoard();
    document.getElementById('difficulty-screen').style.display = 'none';
    document.getElementById('result-screen').style.display     = 'none';
    document.getElementById('game-screen').style.display       = 'block';

    document.getElementById('game-difficulty').textContent = diff;
    updateProgress();
    startTimer();
}

function renderBoard() {
    const leftCol  = document.getElementById('left-col');
    const rightCol = document.getElementById('right-col');
    leftCol.innerHTML  = '';
    rightCol.innerHTML = '';

    const leftItems  = shuffle(pairs.map(function(p) { return p.classical_term; }));
    const rightItems = shuffle(pairs.map(function(p) { return p.modern_meaning; }));

    leftItems.forEach(function(term) {
        const card = createCard(term, 'left');
        leftCol.appendChild(card);
    });
    rightItems.forEach(function(meaning) {
        const card = createCard(meaning, 'right');
        rightCol.appendChild(card);
    });
}

function createCard(text, side) {
    const div       = document.createElement('div');
    div.className   = 'match-card';
    div.textContent = text;
    div.dataset.value = text;
    div.dataset.side  = side;
    div.addEventListener('click', function() { onCardClick(this); });
    return div;
}

function onCardClick(card) {
    if (card.classList.contains('matched')) return;

    const side = card.dataset.side;

    if (side === 'left') {
        // 取消已選中的左側卡片
        document.querySelectorAll('#left-col .match-card.selected').forEach(function(c) {
            c.classList.remove('selected');
        });
        card.classList.add('selected');
        selectedLeft = card;
    } else {
        document.querySelectorAll('#right-col .match-card.selected').forEach(function(c) {
            c.classList.remove('selected');
        });
        card.classList.add('selected');
        selectedRight = card;
    }

    // 雙方均已選中 → 驗證
    if (selectedLeft && selectedRight) {
        checkMatch();
    }
}

function checkMatch() {
    const term    = selectedLeft.dataset.value;
    const meaning = selectedRight.dataset.value;

    const isMatch = pairs.some(function(p) {
        return p.classical_term === term && p.modern_meaning === meaning;
    });

    if (isMatch) {
        selectedLeft.classList.remove('selected');
        selectedRight.classList.remove('selected');
        selectedLeft.classList.add('matched');
        selectedRight.classList.add('matched');
        matchedCount++;
        updateProgress();

        if (matchedCount === pairs.length) {
            endGame(true);
        }
    } else {
        selectedLeft.classList.add('wrong');
        selectedRight.classList.add('wrong');
        const l = selectedLeft, r = selectedRight;
        setTimeout(function() {
            l.classList.remove('wrong', 'selected');
            r.classList.remove('wrong', 'selected');
        }, 600);
    }

    selectedLeft  = null;
    selectedRight = null;
}

function updateProgress() {
    document.getElementById('game-progress').textContent =
        '已配對 ' + matchedCount + ' / ' + pairs.length;
}

function startTimer() {
    clearInterval(timerInterval);
    updateTimerDisplay();
    timerInterval = setInterval(function() {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            endGame(false);
        }
    }, 1000);
}

function updateTimerDisplay() {
    const mins = Math.floor(timeLeft / 60);
    const secs = timeLeft % 60;
    document.getElementById('timer-display').textContent =
        (mins > 0 ? mins + ':' : '') + String(secs).padStart(2, '0') + '秒';

    const pct   = (timeLeft / totalTime * 100).toFixed(1);
    const fill  = document.getElementById('timer-fill');
    fill.style.width = pct + '%';
    if (timeLeft < totalTime * 0.25) {
        fill.style.background = 'linear-gradient(135deg, #e74c3c, #f39c12)';
    } else if (timeLeft < totalTime * 0.5) {
        fill.style.background = 'linear-gradient(135deg, #f39c12, #f9ca24)';
    } else {
        fill.style.background = '';
    }
}

function endGame(won) {
    clearInterval(timerInterval);
    document.getElementById('game-screen').style.display   = 'none';
    document.getElementById('result-screen').style.display = 'block';

    const usedTime = totalTime - timeLeft;
    if (won) {
        const stars = usedTime < totalTime * 0.4 ? '⭐⭐⭐' : usedTime < totalTime * 0.7 ? '⭐⭐' : '⭐';
        document.getElementById('result-emoji').textContent  = '🏆';
        document.getElementById('result-title').textContent  = '完美配對！' + stars;
        document.getElementById('result-title').style.color  = 'var(--success-color)';
        document.getElementById('result-detail').textContent =
            '難度：' + currentDiff + '　配對 ' + pairs.length + ' 組　用時 ' + usedTime + ' 秒';
    } else {
        document.getElementById('result-emoji').textContent  = '⏰';
        document.getElementById('result-title').textContent  = '時間到！';
        document.getElementById('result-title').style.color  = 'var(--error-color)';
        document.getElementById('result-detail').textContent =
            '已配對 ' + matchedCount + ' / ' + pairs.length + ' 組，再接再厲！';
    }
}

function quitGame() {
    clearInterval(timerInterval);
    showDifficulty();
}

function showDifficulty() {
    document.getElementById('game-screen').style.display       = 'none';
    document.getElementById('result-screen').style.display     = 'none';
    document.getElementById('difficulty-screen').style.display = 'block';
}

function restartGame() {
    startGame(currentDiff);
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
