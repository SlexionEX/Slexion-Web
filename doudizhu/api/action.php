<?php
/**
 * 游戏动作 API
 * POST action=call&room_id=xxx&score=0|1|2|3 -> 叫地主
 * POST action=play&room_id=xxx&cards=id1,id2,... -> 出牌（空表示不出）
 * POST action=ai_turn&room_id=xxx -> 触发AI行动（前端轮询时调用）
 */
require_once __DIR__ . '/../lib/room_store.php';
require_once __DIR__ . '/../../account/config.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'error' => '请先登录']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$roomId = $_POST['room_id'] ?? $_GET['room_id'] ?? '';

if (!$roomId) {
    echo json_encode(['success' => false, 'error' => '缺少room_id']);
    exit;
}

// 获取当前用户座位
$room = RoomStore::get($roomId);
if (!$room) {
    echo json_encode(['success' => false, 'error' => '房间不存在']);
    exit;
}

$mySeat = -1;
foreach ($room['players'] as $p) {
    if ($p['user_id'] == $user['id']) { $mySeat = $p['seat']; break; }
}

function sanitizeRoom($room, $userId) {
    if (!$room) return null;
    $out = $room;
    foreach ($out['players'] as &$p) {
        // 标记当前用户
        $p['is_self'] = ($p['user_id'] == $userId && !$p['is_ai']);
        if ($p['user_id'] != $userId) {
            $p['hand_count'] = count($p['hand']);
            $p['hand'] = [];
        }
    }
    return $out;
}

switch ($action) {
    case 'call':
        if ($mySeat < 0) { echo json_encode(['success' => false, 'error' => '你不在房间里']); break; }
        $score = intval($_POST['score'] ?? 0);
        if ($score < 0 || $score > 3) { echo json_encode(['success' => false, 'error' => '分数无效']); break; }
        $result = RoomStore::callLandlord($roomId, $mySeat, $score);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($result['room'], $user['id'])]);
        }
        break;

    case 'play':
        if ($mySeat < 0) { echo json_encode(['success' => false, 'error' => '你不在房间里']); break; }
        $cardsStr = $_POST['cards'] ?? '';
        $cardIds = $cardsStr ? explode(',', $cardsStr) : [];
        $result = RoomStore::playCards($roomId, $mySeat, $cardIds);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($result['room'], $user['id']), 'played' => $result['played'] ?? []]);
        }
        break;

    case 'ai_turn':
        // 触发当前AI玩家行动（前端轮询时，如果当前回合是AI，调用此接口）
        $curSeat = $room['current_turn'];
        if ($curSeat >= 0 && $curSeat < 3 && $room['players'][$curSeat]['is_ai']) {
            if ($room['status'] == 'calling' || $room['status'] == 'playing') {
                // AI 行动延迟模拟
                usleep(300000); // 0.3秒
                $result = RoomStore::aiAction($roomId);
                echo json_encode(['success' => true, 'action' => $result, 'room' => sanitizeRoom(RoomStore::get($roomId), $user['id'])]);
            } else {
                echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
            }
        } else {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => '未知操作']);
}
