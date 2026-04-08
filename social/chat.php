<?php
/**
 * 文樞 — 與古人私訊對話頁
 * 需完成至少一個關卡才能與對應導師對話（社群解鎖）
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId  = getCurrentUserId();
$db      = getDB();

$tutorId = (int)($_GET['tutor_id'] ?? 0);

// 若無指定導師，顯示導師選擇
$tutors      = getActiveTutors();
$unlockedIds = getUnlockedTutorIds($userId);

if ($tutorId > 0) {
    $st = $db->prepare('SELECT * FROM tutors WHERE id = ? AND is_active = 1');
    $st->execute([$tutorId]);
    $tutor = $st->fetch();
    if (!$tutor) {
        header('Location: ' . BASE_URL . '/social/chat.php');
        exit;
    }
    if (!in_array($tutorId, $unlockedIds)) {
        // 未解鎖：導向學習
        $_SESSION['flash_info'] = '請先完成「' . $tutor['name'] . '」的第一個關卡以解鎖私訊功能。';
        header('Location: ' . BASE_URL . '/learning/level.php?tutor_id=' . $tutorId);
        exit;
    }
} else {
    $tutor = null;
}

$gradients = ['gradient-primary'=>'var(--gradient-primary)','gradient-secondary'=>'var(--gradient-secondary)','gradient-accent'=>'var(--gradient-accent)'];
$gradStyle  = $tutor ? ($gradients[$tutor['gradient_class'] ?? 'gradient-primary'] ?? 'var(--gradient-primary)') : '';

$pageTitle  = $tutor ? '與' . $tutor['name'] . '私訊' : '選擇對話導師';
$activePage = 'social';
require __DIR__ . '/../includes/partials/header.php';
?>

<style>
.chat-bubble {
    max-width: 72%;
    padding: 11px 16px;
    border-radius: 16px;
    font-size: 0.92rem;
    line-height: 1.6;
    word-break: break-word;
}
.chat-bubble.user    { background: var(--gradient-primary); color: white; border-bottom-right-radius: 4px; }
.chat-bubble.tutor   { background: white; color: var(--text-color); border-bottom-left-radius: 4px; box-shadow: var(--shadow-light); }
.chat-row            { display: flex; align-items: flex-end; gap: 10px; margin-bottom: 16px; }
.chat-row.user       { flex-direction: row-reverse; }
.chat-avatar         { width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; overflow: hidden; }
.chat-avatar img     { width: 100%; height: 100%; object-fit: cover; }
.typing-dots span {
    display: inline-block;
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--primary-color);
    animation: blink 1.2s infinite;
    margin: 0 2px;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes blink { 0%,80%,100%{opacity:0.2} 40%{opacity:1} }
</style>

<div class="page-container" style="max-width:720px;">

    <?php if (!$tutor): ?>
    <!-- 導師選擇 -->
    <h1 class="page-title"><i class="fas fa-comment-dots" style="color:var(--primary-color);margin-right:8px;"></i>選擇對話導師</h1>
    <p class="page-subtitle">完成至少一個關卡後，即可與該導師私訊對話</p>

    <div class="grid-2">
        <?php foreach ($tutors as $t):
            $grd      = $gradients[$t['gradient_class'] ?? 'gradient-primary'] ?? 'var(--gradient-primary)';
            $unlocked = in_array($t['id'], $unlockedIds);
        ?>
        <div class="card" style="<?= !$unlocked ? 'opacity:0.65;' : '' ?> transition:var(--transition);" <?= $unlocked ? 'onmouseover="this.style.transform=\'translateY(-4px)\'" onmouseout="this.style.transform=\'\'"' : '' ?>>
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="width:52px;height:52px;border-radius:50%;background:<?= $grd ?>;overflow:hidden;flex-shrink:0;">
                    <?php if (!empty($t['avatar_url'])): ?>
                    <img src="<?= e($t['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-weight:700;"><?= e($t['name']) ?></div>
                    <div style="font-size:0.8rem;color:var(--dark-color);"><?= e($t['dynasty']) ?></div>
                </div>
            </div>
            <div style="margin-top:14px;">
                <?php if ($unlocked): ?>
                <a href="<?= BASE_URL ?>/social/chat.php?tutor_id=<?= $t['id'] ?>" class="btn btn-primary" style="width:100%;justify-content:center;">
                    <i class="fas fa-comment"></i> 開始對話
                </a>
                <?php else: ?>
                <a href="<?= BASE_URL ?>/learning/level.php?tutor_id=<?= $t['id'] ?>" class="btn btn-outline" style="width:100%;justify-content:center;">
                    <i class="fas fa-lock"></i> 完成關卡以解鎖
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- 聊天介面 -->
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;">
        <a href="<?= BASE_URL ?>/social/chat.php" style="color:var(--dark-color);font-size:1.1rem;"><i class="fas fa-arrow-left"></i></a>
        <div style="width:44px;height:44px;border-radius:50%;background:<?= $gradStyle ?>;overflow:hidden;flex-shrink:0;">
            <?php if (!empty($tutor['avatar_url'])): ?>
            <img src="<?= e($tutor['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php endif; ?>
        </div>
        <div>
            <div style="font-weight:700;font-size:1rem;"><?= e($tutor['name']) ?></div>
            <div style="font-size:0.78rem;color:var(--dark-color);"><?= e($tutor['dynasty']) ?> · 文學導師</div>
        </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <!-- 聊天記錄 -->
        <div id="chat-messages" style="padding:20px;min-height:400px;max-height:55vh;overflow-y:auto;background:rgba(248,250,250,0.8);">
            <!-- 歡迎訊息 -->
            <div class="chat-row tutor">
                <div class="chat-avatar" style="background:<?= $gradStyle ?>;">
                    <?php if (!empty($tutor['avatar_url'])): ?>
                    <img src="<?= e($tutor['avatar_url']) ?>" onerror="this.style.display='none'">
                    <?php endif; ?>
                </div>
                <div class="chat-bubble tutor">
                    你好！我是<?= e($tutor['name']) ?>，有什麼想和我聊的嗎？無論是詩詞文章，還是對人生的思考，都歡迎一談。
                </div>
            </div>
        </div>

        <!-- 輸入區 -->
        <div style="padding:16px;border-top:1px solid rgba(127,179,213,0.15);display:flex;gap:10px;align-items:flex-end;">
            <textarea id="msg-input" placeholder="輸入訊息…" maxlength="200" rows="1"
                      style="flex:1;padding:10px 14px;border:1.5px solid rgba(127,179,213,0.3);border-radius:20px;font-family:inherit;font-size:0.9rem;resize:none;line-height:1.5;transition:border-color 0.2s;"
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"
                      oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"></textarea>
            <button class="btn btn-primary" onclick="sendMessage()" id="send-btn" style="border-radius:50%;width:42px;height:42px;padding:0;justify-content:center;flex-shrink:0;">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php if ($tutor): ?>
<script>
const TUTOR_ID     = <?= $tutorId ?>;
const TUTOR_NAME   = <?= json_encode($tutor['name'], JSON_UNESCAPED_UNICODE) ?>;
const TUTOR_AVATAR = <?= json_encode($tutor['avatar_url'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
const GRAD_STYLE   = <?= json_encode($gradStyle, JSON_UNESCAPED_UNICODE) ?>;
let chatHistory    = [];

async function sendMessage() {
    const input = document.getElementById('msg-input');
    const msg   = input.value.trim();
    if (!msg) return;

    input.value         = '';
    input.style.height  = 'auto';
    const btn           = document.getElementById('send-btn');
    btn.disabled        = true;

    appendBubble('user', msg);
    chatHistory.push({ role: 'user', content: msg });

    const typingId = appendTyping();

    try {
        const res  = await fetch('<?= BASE_URL ?>/api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tutor_id: TUTOR_ID, message: msg, history: chatHistory.slice(-20) })
        });
        const data = await res.json();
        removeTyping(typingId);

        if (data.success) {
            appendBubble('tutor', data.reply);
            chatHistory.push({ role: 'assistant', content: data.reply });
        } else {
            appendBubble('tutor', '抱歉，我暫時無法回應，請稍後再試。');
        }
    } catch (err) {
        removeTyping(typingId);
        appendBubble('tutor', '網絡出現問題，請稍後再試。');
    }

    btn.disabled = false;
    input.focus();
}

function appendBubble(role, text) {
    const container = document.getElementById('chat-messages');
    const row       = document.createElement('div');
    row.className   = 'chat-row ' + role;

    const avatar = document.createElement('div');
    avatar.className = 'chat-avatar';
    avatar.style.background = role === 'tutor' ? GRAD_STYLE : 'var(--gradient-primary)';
    if (role === 'tutor' && TUTOR_AVATAR) {
        const img = document.createElement('img');
        img.src   = TUTOR_AVATAR;
        img.onerror = function() { this.style.display = 'none'; };
        avatar.appendChild(img);
    }

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble ' + role;
    bubble.textContent = text;

    if (role === 'tutor') {
        row.appendChild(avatar);
        row.appendChild(bubble);
    } else {
        row.appendChild(bubble);
        row.appendChild(avatar);
    }

    container.appendChild(row);
    container.scrollTop = container.scrollHeight;
}

let typingCounter = 0;
function appendTyping() {
    const id        = 'typing-' + (++typingCounter);
    const container = document.getElementById('chat-messages');
    const row       = document.createElement('div');
    row.className   = 'chat-row tutor';
    row.id          = id;

    const avatar = document.createElement('div');
    avatar.className = 'chat-avatar';
    avatar.style.background = GRAD_STYLE;
    if (TUTOR_AVATAR) {
        const img = document.createElement('img');
        img.src   = TUTOR_AVATAR;
        img.onerror = function() { this.style.display = 'none'; };
        avatar.appendChild(img);
    }

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble tutor typing-dots';
    bubble.innerHTML = '<span></span><span></span><span></span>';

    row.appendChild(avatar);
    row.appendChild(bubble);
    container.appendChild(row);
    container.scrollTop = container.scrollHeight;
    return id;
}
function removeTyping(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
