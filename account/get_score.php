<?php
/**
 * Slexion Account - 获取游戏最高分
 * GET: game, key
 * 返回 JSON: {success, score}
 */
require_once __DIR__ . '/config.php';

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => '未登录']);
}

$game = trim($_GET['game'] ?? '');
$key = trim($_GET['key'] ?? '');

if ($game === '' || $key === '') {
    json_response(['success' => false, 'error' => '参数不完整']);
}

try {
    $stmt = db()->prepare('SELECT score FROM game_scores WHERE user_id = ? AND game = ? AND score_key = ?');
    $stmt->execute([$user['id'], $game, $key]);
    $row = $stmt->fetch();

    if ($row) {
        json_response(['success' => true, 'score' => (int)$row['score']]);
    } else {
        json_response(['success' => true, 'score' => 0]);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'error' => '查询失败']);
}
