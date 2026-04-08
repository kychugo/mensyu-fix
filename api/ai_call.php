<?php
/**
 * 統一 AI API 呼叫端點（伺服器端代理）
 *
 * 功能：
 *  - 自動輪換 API Keys 和 Models（key 失敗 → 切換 model；model 均失敗 → 切換 key）
 *  - API Keys 永不暴露於前端
 *  - 接受 POST JSON：{ "messages": [...], "max_tokens": N, "temperature": F }
 *  - 返回 JSON：{ "success": true, "content": "...", "model_used": "..." }
 *            或 { "success": false, "message": "..." }
 *
 * 呼叫此端點前必須已登入（requireLogin）
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

// 只允許已登入用戶呼叫 AI（防止未授權使用）
requireLogin();

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '方法不允許'], 405);
}

// 解析輸入
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || empty($body['messages'])) {
    jsonResponse(['success' => false, 'message' => '缺少 messages 參數'], 400);
}

$messages    = $body['messages'];
$maxTokens   = isset($body['max_tokens'])  ? (int)$body['max_tokens']  : 4096;
$temperature = isset($body['temperature']) ? (float)$body['temperature'] : 0.7;

// 執行帶有自動重試的 API 呼叫
$result = callAiWithFallback($messages, $maxTokens, $temperature);

if ($result === null) {
    jsonResponse(['success' => false, 'message' => '所有 AI 模型均暫時無回應，請稍後再試'], 503);
}

jsonResponse([
    'success'    => true,
    'content'    => $result['content'],
    'model_used' => $result['model'],
]);

// ════════════════════════════════════════════════════════════════════════════

/**
 * 帶有 key/model 自動切換的 AI 呼叫函數
 *
 * 切換邏輯：
 *   對每個 key：依次嘗試所有 models
 *   若某 model 失敗 → 嘗試下一個 model（同一 key）
 *   若同一 key 所有 models 均失敗 → 切換至下一個 key
 *   若所有 keys 均失敗 → 返回 null
 *
 * @param array $messages   OpenAI 格式的 messages 陣列
 * @param int   $maxTokens
 * @param float $temperature
 * @return array|null ['content' => string, 'model' => string] 或 null
 */
function callAiWithFallback(array $messages, int $maxTokens, float $temperature): ?array
{
    $keys   = AI_API_KEYS;
    $models = AI_MODELS;

    foreach ($keys as $key) {
        foreach ($models as $model) {
            $result = callSingleAi($key, $model, $messages, $maxTokens, $temperature);
            if ($result !== null) {
                return $result;
            }
            // 記錄失敗但繼續嘗試
            error_log("[文樞 AI] 失敗 key=***{$key[-4]} model={$model}");
        }
    }

    return null;
}

/**
 * 對單一 key + model 發出一次 API 請求
 *
 * @return array|null ['content' => string, 'model' => string] 或 null（失敗）
 */
function callSingleAi(string $apiKey, string $model, array $messages, int $maxTokens, float $temperature): ?array
{
    $payload = json_encode([
        'model'       => $model,
        'messages'    => $messages,
        'max_tokens'  => $maxTokens,
        'temperature' => $temperature,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(AI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        return null;
    }

    $content = $data['choices'][0]['message']['content'];
    // 過濾 <think>...</think> 思考過程（DeepSeek 等模型會輸出）
    $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
    $content = trim($content);

    return ['content' => $content, 'model' => $model];
}

/**
 * 輸出 JSON 回應並終止腳本（此檔案自定，不依賴 functions.php 以保持輕量）
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
