<?php
/**
 * 房间管理 API
 * GET action=join -> 加入或创建房间
 * GET action=status&room_id=xxx -> 获取房间状态
 * POST action=add_ai&room_id=xxx -> AI加入
 * POST action=leave&room_id=xxx -> 离开房间
 */
require_once __DIR__ . '/../lib/room_store.php';
require_once __DIR__ . '/../../account/config.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'error' => '请先登录']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$roomId = $_GET['room_id'] ?? $_POST['room_id'] ?? '';

function sanitizeRoom($room, $userId) {
    if (!$room) return null;
    $out = $room;
    foreach ($out['players'] as &$p) {
        // 标记当前用户
        $p['is_self'] = ($p['user_id'] == $userId && !$p['is_ai']);
        // 只返回自己的手牌，其他人只返回数量
        if ($p['user_id'] != $userId && !$p['is_ai']) {
            $p['hand_count'] = count($p['hand']);
            $p['hand'] = [];
        } else if ($p['is_ai']) {
            $p['hand_count'] = count($p['hand']);
            $p['hand'] = [];
        }
    }
    return $out;
}

switch ($action) {
    case 'list':
        $rooms = RoomStore::getAll();
        $list = [];
        foreach ($rooms as $r) {
            if ($r['status'] === 'finished') continue;
            $hasHuman = false;
            foreach ($r['players'] as $p) {
                if (!$p['is_ai']) { $hasHuman = true; break; }
            }
            $list[] = [
                'room_id' => $r['room_id'],
                'status' => $r['status'],
                'player_count' => count($r['players']),
                'players' => array_map(function($p) {
                    return ['username' => $p['username'], 'is_ai' => $p['is_ai']];
                }, $r['players']),
                'has_human' => $hasHuman
            ];
        }
        echo json_encode(['success' => true, 'rooms' => $list]);
        break;

    case 'join':
        $room = RoomStore::joinOrCreate($user['id'], $user['username']);
        echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
        break;

    case 'my_room':
        $room = RoomStore::findUserRoom($user['id']);
        if ($room) {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
        } else {
            echo json_encode(['success' => true, 'room' => null]);
        }
        break;

    case 'status':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $room = RoomStore::get($roomId);
        if (!$room) { echo json_encode(['success' => false, 'error' => '房间不存在']); break; }
        echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
        break;

    case 'add_ai':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $result = RoomStore::addAI($roomId, 2);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode(['success' => true, 'added' => $result['added']]);
        }
        break;

    case 'leave':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $result = RoomStore::leave($roomId, $user['id']);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else if (!empty($result['deleted'])) {
            // 房主离开，删除房间
            RoomStore::deleteRoom($roomId);
            echo json_encode(['success' => true, 'deleted' => true]);
        } else {
            echo json_encode(['success' => true]);
        }
        break;

    case 'kick':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $targetUserId = $_POST['target_user_id'] ?? $_GET['target_user_id'] ?? '';
        if (!$targetUserId) { echo json_encode(['success' => false, 'error' => '缺少target_user_id']); break; }
        $result = RoomStore::kickPlayer($roomId, $user['id'], $targetUserId);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($result['room'] ?? null, $user['id'])]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => '未知操作']);
}
