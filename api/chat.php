<?php
/**
 * 文樞 — 與古人私訊對話 API
 *
 * POST JSON: { "tutor_id": 1, "message": "你好", "history": [...] }
 * 回應: { "success": true, "reply": "..." }
 *
 * history 格式：[{ "role": "user"|"assistant", "content": "..." }, ...]
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '方法不允許'], 405);
}

$body    = json_decode(file_get_contents('php://input'), true) ?: [];
$tutorId = (int)($body['tutor_id'] ?? 0);
$message = trim($body['message'] ?? '');
$history = is_array($body['history'] ?? null) ? $body['history'] : [];

if ($tutorId <= 0 || empty($message)) {
    jsonResponse(['success' => false, 'message' => '缺少必要參數'], 400);
}

$db = getDB();
$st = $db->prepare('SELECT * FROM tutors WHERE id = ? AND is_active = 1');
$st->execute([$tutorId]);
$tutor = $st->fetch();

if (!$tutor) {
    jsonResponse(['success' => false, 'message' => '找不到此導師'], 404);
}

// 構建對話 messages
$systemPrompt = buildChatSystemPrompt($tutor);
$messages     = [['role' => 'system', 'content' => $systemPrompt]];

// 附加歷史（最多最近 10 輪）
$historySlice = array_slice($history, -20);
foreach ($historySlice as $turn) {
    $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $messages[] = ['role' => $role, 'content' => (string)($turn['content'] ?? '')];
}

// 加入當前訊息
$messages[] = ['role' => 'user', 'content' => $message];

$reply = callAiForChat($messages);

if ($reply === null) {
    jsonResponse(['success' => false, 'message' => 'AI 服務暫時不可用，請稍後再試'], 503);
}

$reply = trim(preg_replace('/<think>.*?<\/think>/s', '', $reply));
$reply = preg_replace('/[*#【】]/u', '', $reply);

jsonResponse(['success' => true, 'reply' => $reply]);

// ════════════════════════════════════════════════════════════════════════════

function buildChatSystemPrompt(array $tutor): string
{
    $name       = $tutor['name'];
    $dynasty    = $tutor['dynasty'];
    $background = $tutor['background']     ?? '';
    $personality = $tutor['personality']    ?? '';
    $style      = $tutor['language_style'] ?? '';

    return "你現在扮演{$dynasty}文學家{$name}，與現代學生進行私訊對話。

背景資料：{$background}

性格特點：{$personality}

語言風格：{$style}

對話規則：
1. 只有引用詩文典故時才用文言文，平常對話一概用生活化的香港粵語
2. 保持{$name}的性格和時代觀點，但能理解現代學生的處境
3. 回應長度適中，100字以內
4. 若學生請教文學問題，耐心解答並適當引用相關詩文
5. 不要在回應後加任何括號解釋
6. 稱呼學生為「同學」或「朋友」";
}

function callAiForChat(array $messages): ?string
{
    foreach (AI_API_KEYS as $key) {
        foreach (AI_MODELS as $model) {
            $payload = json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => 512,
                'temperature' => 0.75,
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init(AI_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['choices'][0]['message']['content'])) {
                    return $data['choices'][0]['message']['content'];
                }
            }
        }
    }
    return null;
}
