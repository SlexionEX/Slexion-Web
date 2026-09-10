<?php
/**
 * Slexion Account - 保存游戏最高分
 * POST: game, key, score
 * 返回 JSON: {success, message}
 */
require_once __DIR__ . '/config.php';

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => '未登录']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => '仅支持 POST 请求']);
}

$game = trim($_POST['game'] ?? '');
$key = trim($_POST['key'] ?? '');
$score = (int)($_POST['score'] ?? 0);

if ($game === '' || $key === '') {
    json_response(['success' => false, 'error' => '参数不完整']);
}

if ($score < 0) {
    json_response(['success' => false, 'error' => '分数不能为负']);
}

try {
    // 使用 INSERT ... ON DUPLICATE KEY UPDATE
    $stmt = db()->prepare('
        INSERT INTO game_scores (user_id, game, score_key, score)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE score = GREATEST(score, VALUES(score)), updated_at = CURRENT_TIMESTAMP
    ');
    $stmt->execute([$user['id'], $game, $key, $score]);

    json_response(['success' => true, 'message' => '分数已保存']);
} catch (Exception $e) {
    json_response(['success' => false, 'error' => '保存失败: ' . $e->getMessage()]);
}
