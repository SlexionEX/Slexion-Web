<?php
/**
 * 斗兽棋联机 API（斗地主式房间系统）
 * JSON文件存储 + 文件锁
 */
require_once __DIR__ . '/../account/config.php';

header('Content-Type: application/json; charset=utf-8');

// 登录验证
$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'error' => '请先登录', 'need_login' => true]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$roomId = $_GET['room_id'] ?? $_POST['room_id'] ?? '';

$storeFile = '/tmp/beastplay_rooms.json';

function lockStore() {
    global $storeFile;
    $fp = fopen($storeFile, 'c+');
    if (!$fp) return null;
    flock($fp, LOCK_EX);
    return $fp;
}

function unlockStore($fp) {
    flock($fp, LOCK_UN);
    fclose($fp);
}

function readAll($fp) {
    rewind($fp);
    $data = stream_get_contents($fp);
    $rooms = json_decode($data, true);
    if (!is_array($rooms)) $rooms = [];
    return $rooms;
}

function writeAll($fp, $rooms) {
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($rooms, JSON_UNESCAPED_UNICODE));
}

function cleanup(&$rooms) {
    $now = time();
    foreach ($rooms as $id => $room) {
        // 超过2小时清理
        if ($now - ($room['updated_at'] ?? 0) > 7200) {
            unset($rooms[$id]);
            continue;
        }
        // 等待中超过30分钟清理
        if ($room['status'] === 'waiting' && $now - ($room['created_at'] ?? 0) > 1800) {
            unset($rooms[$id]);
        }
    }
}

function getAllRooms() {
    $fp = lockStore();
    if (!$fp) return [];
    $rooms = readAll($fp);
    cleanup($rooms);
    writeAll($fp, $rooms);
    unlockStore($fp);
    return $rooms;
}

function getRoom($roomId) {
    $rooms = getAllRooms();
    return $rooms[$roomId] ?? null;
}

function saveRoom($roomId, $room) {
    $fp = lockStore();
    if (!$fp) return;
    $rooms = readAll($fp);
    $rooms[$roomId] = $room;
    $rooms[$roomId]['updated_at'] = time();
    writeAll($fp, $rooms);
    unlockStore($fp);
}

function deleteRoom($roomId) {
    $fp = lockStore();
    if (!$fp) return;
    $rooms = readAll($fp);
    unset($rooms[$roomId]);
    writeAll($fp, $rooms);
    unlockStore($fp);
}

function sanitizeRoom($room, $userId) {
    if (!$room) return null;
    $out = $room;
    foreach ($out['players'] as &$p) {
        $p['is_self'] = ($p['user_id'] == $userId && !$p['is_ai']);
    }
    return $out;
}

function getUsernameById($userId) {
    // 从账号系统获取用户名（config.php 可能没有此函数，用数据库）
    return null;
}

switch ($action) {
    case 'list':
        $rooms = getAllRooms();
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
        // 自动加入人数最多的未满房间，没有则创建
        $fp = lockStore();
        if (!$fp) { echo json_encode(['success' => false, 'error' => '存储不可用']); break; }
        $rooms = readAll($fp);
        cleanup($rooms);

        // 检查是否已在某个房间
        foreach ($rooms as $id => $room) {
            foreach ($room['players'] as $p) {
                if ($p['user_id'] == $user['id'] && !$p['is_ai']) {
                    unlockStore($fp);
                    echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id']), 'already_in' => true]);
                    break 2;
                }
            }
        }

        // 找人数最多且未满的等待中房间
        $bestRoom = null;
        foreach ($rooms as $id => $room) {
            if ($room['status'] !== 'waiting') continue;
            if (count($room['players']) >= 2) continue;
            if (!$bestRoom || count($room['players']) > count($bestRoom['players'])) {
                $bestRoom = $room;
            }
        }

        if ($bestRoom) {
            // 加入现有房间（作为 blue）
            $bestRoom['players'][] = ['user_id' => $user['id'], 'username' => $user['username'], 'color' => 'blue', 'is_ai' => false];
            $bestRoom['updated_at'] = time();
            $rooms[$bestRoom['room_id']] = $bestRoom;
            writeAll($fp, $rooms);
            unlockStore($fp);
            echo json_encode(['success' => true, 'room' => sanitizeRoom($bestRoom, $user['id'])]);
        } else {
            // 创建新房间（作为 red 房主）
            $roomId = 'room_' . uniqid();
            $room = [
                'room_id' => $roomId,
                'owner_user_id' => $user['id'],
                'players' => [
                    ['user_id' => $user['id'], 'username' => $user['username'], 'color' => 'red', 'is_ai' => false]
                ],
                'status' => 'waiting',
                'game_state' => null,
                'last_move_by' => null,
                'created_at' => time(),
                'updated_at' => time()
            ];
            $rooms[$roomId] = $room;
            writeAll($fp, $rooms);
            unlockStore($fp);
            echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id']), 'created' => true]);
        }
        break;

    case 'status':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $room = getRoom($roomId);
        if (!$room) { echo json_encode(['success' => false, 'error' => '房间不存在']); break; }
        echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
        break;

    case 'add_ai':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $room = getRoom($roomId);
        if (!$room) { echo json_encode(['success' => false, 'error' => '房间不存在']); break; }
        // 只有房主可以添加 AI
        if ($room['owner_user_id'] != $user['id']) {
            echo json_encode(['success' => false, 'error' => '只有房主可以添加AI']);
            break;
        }
        if (count($room['players']) >= 2) {
            echo json_encode(['success' => false, 'error' => '房间已满']);
            break;
        }
        // 添加 AI 作为 blue
        $room['players'][] = ['user_id' => 'ai_' . uniqid(), 'username' => 'AI', 'color' => 'blue', 'is_ai' => true];
        saveRoom($roomId, $room);
        echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
        break;

    case 'leave':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $fp = lockStore();
        if (!$fp) { echo json_encode(['success' => false, 'error' => '存储不可用']); break; }
        $rooms = readAll($fp);
        if (!isset($rooms[$roomId])) {
            unlockStore($fp);
            echo json_encode(['success' => true, 'deleted' => true]);
            break;
        }
        $room = $rooms[$roomId];
        $isOwner = ($room['owner_user_id'] == $user['id']);

        // 房主离开：删除房间
        if ($isOwner) {
            unset($rooms[$roomId]);
            writeAll($fp, $rooms);
            unlockStore($fp);
            echo json_encode(['success' => true, 'deleted' => true]);
            break;
        }

        // 普通玩家离开：移除自己
        $found = false;
        foreach ($room['players'] as $i => $p) {
            if ($p['user_id'] == $user['id'] && !$p['is_ai']) {
                array_splice($room['players'], $i, 1);
                $found = true;
                break;
            }
        }
        if (!$found) {
            unlockStore($fp);
            echo json_encode(['success' => true]);
            break;
        }
        // 如果只剩一个真人且房间在游戏中，解散
        if ($room['status'] === 'playing' && count($room['players']) < 2) {
            unset($rooms[$roomId]);
            writeAll($fp, $rooms);
            unlockStore($fp);
            echo json_encode(['success' => true, 'deleted' => true]);
            break;
        }
        $rooms[$roomId] = $room;
        writeAll($fp, $rooms);
        unlockStore($fp);
        echo json_encode(['success' => true]);
        break;

    case 'kick':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $targetUserId = $_POST['target_user_id'] ?? $_GET['target_user_id'] ?? '';
        if (!$targetUserId) { echo json_encode(['success' => false, 'error' => '缺少target_user_id']); break; }
        $fp = lockStore();
        if (!$fp) { echo json_encode(['success' => false, 'error' => '存储不可用']); break; }
        $rooms = readAll($fp);
        if (!isset($rooms[$roomId])) {
            unlockStore($fp);
            echo json_encode(['success' => false, 'error' => '房间不存在']);
            break;
        }
        $room = $rooms[$roomId];
        // 只有房主可以踢人
        if ($room['owner_user_id'] != $user['id']) {
            unlockStore($fp);
            echo json_encode(['success' => false, 'error' => '只有房主可以踢人']);
            break;
        }
        foreach ($room['players'] as $i => $p) {
            if ($p['user_id'] == $targetUserId && !$p['is_ai']) {
                array_splice($room['players'], $i, 1);
                break;
            }
        }
        $rooms[$roomId] = $room;
        writeAll($fp, $rooms);
        unlockStore($fp);
        echo json_encode(['success' => true]);
        break;

    case 'get_state':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $room = getRoom($roomId);
        if (!$room) { echo json_encode(['success' => false, 'error' => '房间不存在']); break; }
        echo json_encode([
            'success' => true,
            'game_state' => $room['game_state'],
            'last_update' => $room['updated_at']
        ]);
        break;

    case 'update_state':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $gameState = json_decode($_POST['game_state'] ?? '{}', true);
        if (!$gameState) { echo json_encode(['success' => false, 'error' => '无效状态']); break; }
        $fp = lockStore();
        if (!$fp) { echo json_encode(['success' => false, 'error' => '存储不可用']); break; }
        $rooms = readAll($fp);
        if (!isset($rooms[$roomId])) {
            unlockStore($fp);
            echo json_encode(['success' => false, 'error' => '房间不存在']);
            break;
        }
        // 验证是当前玩家的回合
        $room = $rooms[$roomId];
        if ($room['game_state'] && ($room['game_state']['turn'] ?? '') !== ($gameState['last_move_by'] ?? '')) {
            unlockStore($fp);
            echo json_encode(['success' => false, 'error' => '不是你的回合']);
            break;
        }
        $rooms[$roomId]['game_state'] = $gameState;
        if (($gameState['game_over'] ?? false) && $gameState['last_move_by']) {
            $rooms[$roomId]['status'] = 'finished';
        } else {
            $rooms[$roomId]['status'] = 'playing';
        }
        writeAll($fp, $rooms);
        unlockStore($fp);
        echo json_encode(['success' => true]);
        break;

    case 'reset':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $room = getRoom($roomId);
        if (!$room) { echo json_encode(['success' => false, 'error' => '房间不存在']); break; }
        if ($room['owner_user_id'] != $user['id']) {
            echo json_encode(['success' => false, 'error' => '只有房主可以重开']);
            break;
        }
        $room['game_state'] = null;
        $room['status'] = 'waiting';
        $room['last_move_by'] = null;
        saveRoom($roomId, $room);
        echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => '未知操作']);
}
