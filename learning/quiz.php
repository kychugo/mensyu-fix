<?php
/**
 * 文樞 — 關卡測驗頁
 * AI 生成選擇題，提交後計算得分，通關條件：≥ QUIZ_PASS_SCORE 分
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId  = getCurrentUserId();
$db      = getDB();
$tutorId = (int)($_GET['tutor_id'] ?? 0);
$levelId = (int)($_GET['level_id'] ?? 0);

// 驗證關卡
$st = $db->prepare('SELECT l.*, t.name AS tutor_name, t.dynasty, t.gradient_class FROM levels l JOIN tutors t ON l.tutor_id = t.id WHERE l.id = ? AND l.tutor_id = ? AND l.is_active = 1');
$st->execute([$levelId, $tutorId]);
$level = $st->fetch();
if (!$level) {
    header('Location: ' . BASE_URL . '/learning/index.php');
    exit;
}

$gradients = ['gradient-primary'=>'var(--gradient-primary)','gradient-secondary'=>'var(--gradient-secondary)','gradient-accent'=>'var(--gradient-accent)'];
$gradStyle = $gradients[$level['gradient_class'] ?? 'gradient-primary'] ?? 'var(--gradient-primary)';

// ── 處理測驗提交 ────────────────────────────────────────────────────────────
$quizResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_quiz') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF 驗證失敗');
    }
    $questions  = json_decode($_POST['questions'] ?? '[]', true);
    $answers    = $_POST['answers'] ?? [];
    $correct    = 0;
    $total      = count($questions);

    $detail = [];
    foreach ($questions as $i => $q) {
        $userAns    = $answers[$i] ?? '';
        $isCorrect  = ($userAns === $q['answer']);
        if ($isCorrect) $correct++;
        $detail[]   = array_merge($q, ['user_answer' => $userAns, 'is_correct' => $isCorrect]);
    }

    $score   = $total > 0 ? (int)round($correct / $total * 100) : 0;
    $passed  = ($score >= QUIZ_PASS_SCORE);

    // 儲存或更新進度
    $existing = $db->prepare('SELECT id, completed, score FROM user_progress WHERE user_id = ? AND level_id = ?');
    $existing->execute([$userId, $levelId]);
    $row = $existing->fetch();

    if ($row) {
        $newCompleted = $passed ? 1 : (int)$row['completed'];
        $newScore     = max((int)$row['score'], $score);
        $db->prepare('UPDATE user_progress SET completed = ?, score = ?, attempts = attempts + 1, completed_at = IF(? = 1 AND completed = 0, NOW(), completed_at), updated_at = NOW() WHERE id = ?')
           ->execute([$newCompleted, $newScore, $newCompleted, $row['id']]);
    } else {
        $db->prepare('INSERT INTO user_progress (user_id, tutor_id, level_id, completed, score, attempts, completed_at) VALUES (?, ?, ?, ?, ?, 1, ?)')
           ->execute([$userId, $tutorId, $levelId, $passed ? 1 : 0, $score, $passed ? date('Y-m-d H:i:s') : null]);
    }

    // XP 獎勵
    if ($passed) {
        $xpReward = 20 + (int)(($score - QUIZ_PASS_SCORE) / 10) * 5;
        addUserXp($userId, $xpReward);
    }

    // 首次通關此導師 → 觸發古人生成動態
    if ($passed) {
        $stCheck = $db->prepare('SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND tutor_id = ? AND completed = 1');
        $stCheck->execute([$userId, $tutorId]);
        // 若只有剛才這一筆是完成的（即首次通關）
        if ((int)$stCheck->fetchColumn() === 1) {
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
            $sid = session_id();
            // 釋放 session 鎖，避免子請求因 session 鎖等待而死鎖
            session_write_close();
            $ch = curl_init($baseUrl . '/api/generate_tutor_post.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['tutor_id' => $tutorId, 'mode' => 'auto']),
                CURLOPT_TIMEOUT        => 1,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Cookie: PHPSESSID=' . $sid],
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    $quizResult = compact('score', 'passed', 'correct', 'total', 'detail');
}

$csrfToken  = getCsrfToken();
$pageTitle  = '測驗：' . $level['essay_title'];
$activePage = 'learning';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-container" style="max-width:760px;">

    <!-- 標題 -->
    <div class="card" style="background:<?= $gradStyle ?>;color:white;margin-bottom:24px;padding:24px;">
        <div style="font-size:0.8rem;opacity:0.85;">第 <?= $level['level_number'] ?> 關 測驗</div>
        <h1 style="font-family:'Noto Serif TC',serif;font-size:1.5rem;font-weight:700;"><?= e($level['essay_title']) ?></h1>
        <div style="font-size:0.85rem;opacity:0.85;"><?= e($level['tutor_name']) ?> · <?= e($level['dynasty']) ?></div>
    </div>

    <?php if ($quizResult): ?>
    <!-- 測驗結果 -->
    <div class="card" style="text-align:center;margin-bottom:24px;padding:40px 32px;">
        <?php if ($quizResult['passed']): ?>
        <div style="font-size:3rem;margin-bottom:12px;">🏆</div>
        <h2 style="font-size:1.6rem;font-weight:700;color:var(--success-color);margin-bottom:8px;">恭喜通關！</h2>
        <?php else: ?>
        <div style="font-size:3rem;margin-bottom:12px;">📖</div>
        <h2 style="font-size:1.6rem;font-weight:700;color:var(--error-color);margin-bottom:8px;">未能通關</h2>
        <?php endif; ?>
        <p style="color:var(--dark-color);margin-bottom:20px;">
            答對 <?= $quizResult['correct'] ?> / <?= $quizResult['total'] ?> 題，得分 <strong><?= $quizResult['score'] ?>分</strong>
            （通關標準 <?= QUIZ_PASS_SCORE ?> 分）
        </p>

        <!-- 答題詳情 -->
        <?php foreach ($quizResult['detail'] as $i => $q): ?>
        <div style="text-align:left;background:<?= $q['is_correct'] ? 'rgba(39,174,96,0.07)' : 'rgba(231,76,60,0.07)' ?>;border-left:4px solid <?= $q['is_correct'] ? 'var(--success-color)' : 'var(--error-color)' ?>;padding:12px 16px;border-radius:0 8px 8px 0;margin-bottom:12px;">
            <div style="font-weight:600;font-size:0.9rem;margin-bottom:6px;">第 <?= $i + 1 ?> 題：<?= e($q['question']) ?></div>
            <?php foreach ($q['options'] as $opt): ?>
            <div style="font-size:0.85rem;padding:3px 0;
                <?php if ($opt === $q['answer']): ?>color:var(--success-color);font-weight:600;<?php elseif ($opt === $q['user_answer'] && !$q['is_correct']): ?>color:var(--error-color);text-decoration:line-through;<?php else: ?>color:var(--dark-color);<?php endif; ?>">
                <?php if ($opt === $q['answer']): ?><i class="fas fa-check"></i> <?php elseif ($opt === $q['user_answer'] && !$q['is_correct']): ?><i class="fas fa-times"></i> <?php else: ?>○ <?php endif; ?>
                <?= e($opt) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/learning/quiz.php?tutor_id=<?= $tutorId ?>&level_id=<?= $levelId ?>" class="btn btn-outline">
                <i class="fas fa-redo"></i> 重新測驗
            </a>
            <a href="<?= BASE_URL ?>/learning/level.php?tutor_id=<?= $tutorId ?>&level_id=<?= $levelId ?>" class="btn btn-outline">
                <i class="fas fa-book-open"></i> 重讀原文
            </a>
            <?php if ($quizResult['passed']): ?>
            <a href="<?= BASE_URL ?>/learning/level.php?tutor_id=<?= $tutorId ?>" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> 下一關
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- 測驗題目（由 JS 透過 AI 生成後渲染） -->
    <div id="quiz-loading" class="card" style="text-align:center;padding:48px;">
        <span class="spinner" style="width:32px;height:32px;border-width:3px;"></span>
        <p style="margin-top:16px;color:var(--dark-color);">AI 正在為你生成測驗題目，請稍候…</p>
    </div>

    <div id="quiz-form-container" style="display:none;">
        <form method="POST" action="<?= BASE_URL ?>/learning/quiz.php?tutor_id=<?= $tutorId ?>&level_id=<?= $levelId ?>" id="quiz-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action"     value="submit_quiz">
            <input type="hidden" name="questions"  id="questions-json" value="">

            <div id="questions-container"></div>

            <div class="card" style="text-align:center;padding:24px;margin-top:8px;">
                <button type="submit" class="btn btn-primary" style="font-size:1rem;padding:12px 40px;" id="submit-btn" disabled>
                    <i class="fas fa-check"></i> 提交答案
                </button>
                <p style="font-size:0.78rem;color:#aaa;margin-top:8px;">請完成所有題目後再提交</p>
            </div>
        </form>
    </div>

    <div id="quiz-error" class="alert alert-error" style="display:none;"></div>
    <?php endif; ?>

</div>

<?php if (!$quizResult): ?>
<script>
const ESSAY_TITLE   = <?= json_encode($level['essay_title'], JSON_UNESCAPED_UNICODE) ?>;
const ESSAY_CONTENT = <?= json_encode($level['essay_content'], JSON_UNESCAPED_UNICODE) ?>;
const LEVEL_ID      = <?= $levelId ?>;

let questionsData = [];

window.addEventListener('DOMContentLoaded', async function() {
    try {
        const res  = await fetch('<?= BASE_URL ?>/api/quiz_gen.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ level_id: LEVEL_ID, title: ESSAY_TITLE, content: ESSAY_CONTENT })
        });
        const data = await res.json();

        if (!data.success || !data.questions || data.questions.length === 0) {
            throw new Error(data.message || '題目生成失敗');
        }

        questionsData = data.questions;
        document.getElementById('questions-json').value = JSON.stringify(questionsData);

        renderQuestions(questionsData);
        document.getElementById('quiz-loading').style.display      = 'none';
        document.getElementById('quiz-form-container').style.display = 'block';
    } catch (err) {
        document.getElementById('quiz-loading').style.display = 'none';
        const errEl = document.getElementById('quiz-error');
        errEl.style.display  = 'flex';
        errEl.innerHTML      = '<i class="fas fa-exclamation-circle"></i> ' + err.message +
            ' <a href="javascript:location.reload()" style="margin-left:10px;color:var(--error-color);font-weight:700;">重試</a>';
    }
});

function renderQuestions(questions) {
    const container = document.getElementById('questions-container');
    let answered    = 0;

    questions.forEach(function(q, i) {
        const div  = document.createElement('div');
        div.className = 'card';
        div.style.marginBottom = '16px';
        div.style.padding      = '24px';

        let optHtml = '';
        q.options.forEach(function(opt) {
            optHtml += `
            <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-radius:8px;cursor:pointer;margin-bottom:6px;border:1.5px solid rgba(127,179,213,0.2);transition:all 0.2s;" class="opt-label-${i}">
                <input type="radio" name="answers[${i}]" value="${escHtml(opt)}"
                       style="margin-top:2px;accent-color:var(--primary-color);"
                       onchange="onAnswer(${i}, '${escHtml(opt)}')">
                <span style="font-size:0.9rem;">${escHtml(opt)}</span>
            </label>`;
        });

        div.innerHTML = `
            <div style="font-weight:700;font-size:0.95rem;margin-bottom:14px;color:var(--text-color);">
                <span style="background:var(--gradient-primary);color:white;padding:2px 10px;border-radius:999px;font-size:0.82rem;margin-right:8px;">${i + 1}</span>
                ${escHtml(q.question)}
            </div>
            ${optHtml}`;

        container.appendChild(div);
    });

    function onAnswer(questionIdx, val) {
        // 高亮已選
        document.querySelectorAll(`.opt-label-${questionIdx}`).forEach(function(lbl) {
            lbl.style.background   = '';
            lbl.style.borderColor  = 'rgba(127,179,213,0.2)';
        });
        const selected = document.querySelector(`input[name="answers[${questionIdx}]"]:checked`);
        if (selected) {
            const lbl = selected.closest('label');
            lbl.style.background   = 'rgba(127,179,213,0.1)';
            lbl.style.borderColor  = 'var(--primary-color)';
        }

        // 啟用提交按鈕（當全部回答）
        const total = questions.length;
        const done  = document.querySelectorAll('input[type="radio"]:checked').length;
        document.getElementById('submit-btn').disabled = (done < total);
    }
    window.onAnswer = onAnswer;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
