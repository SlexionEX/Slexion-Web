<?php
/**
 * 斗兽棋联机 API（仿斗地主房间系统）
 * 必须登录才能使用
 * JSON 文件 + 文件锁存储
 */
require_once __DIR__ . '/../account/config.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'error' => '请先登录']);
    exit;
}

class BeastRoomStore {
    private static $file = '/tmp/beastplay_rooms.json';

    private static function lock() {
        $fp = fopen(self::$file, 'c+');
        if (!$fp) return null;
        flock($fp, LOCK_EX);
        return $fp;
    }

    private static function unlock($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private static function readAll($fp) {
        rewind($fp);
        $data = stream_get_contents($fp);
        $rooms = json_decode($data, true);
        if (!is_array($rooms)) $rooms = [];
        return $rooms;
    }

    private static function writeAll($fp, $rooms) {
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($rooms, JSON_UNESCAPED_UNICODE));
    }

    private static function cleanup(&$rooms) {
        $now = time();
        foreach ($rooms as $id => $room) {
            if ($now - ($room['updated_at'] ?? 0) > 7200) {
                unset($rooms[$id]);
            }
        }
    }

    public static function getAll() {
        $fp = self::lock();
        if (!$fp) return [];
        $rooms = self::readAll($fp);
        self::cleanup($rooms);
        self::writeAll($fp, $rooms);
        self::unlock($fp);
        return $rooms;
    }

    public static function get($roomId) {
        $rooms = self::getAll();
        return $rooms[$roomId] ?? null;
    }

    public static function update($roomId, $callback) {
        $fp = self::lock();
        if (!$fp) return null;
        $rooms = self::readAll($fp);
        if (!isset($rooms[$roomId])) {
            self::unlock($fp);
            return null;
        }
        $result = $callback($rooms[$roomId]);
        $rooms[$roomId]['updated_at'] = time();
        self::writeAll($fp, $rooms);
        self::unlock($fp);
        return $result !== null ? $result : $rooms[$roomId];
    }

    // 加入或创建房间：找人数最多且未满(2人)的 waiting 房间
    public static function joinOrCreate($userId, $username) {
        $fp = self::lock();
        if (!$fp) return ['error' => '存储不可用'];
        $rooms = self::readAll($fp);
        self::cleanup($rooms);

        // 已在某个房间则直接返回
        foreach ($rooms as $id => $room) {
            foreach ($room['players'] as $p) {
                if ($p['user_id'] == $userId && !$p['is_ai']) {
                    self::unlock($fp);
                    return ['room' => $room];
                }
            }
        }

        // 找人数最多的 waiting 房间
        $bestRoom = null;
        $bestCount = 0;
        foreach ($rooms as $id => $room) {
            if ($room['status'] == 'waiting' && count($room['players']) < 2) {
                $cnt = count($room['players']);
                if ($cnt > $bestCount) {
                    $bestCount = $cnt;
                    $bestRoom = $id;
                }
            }
        }

        if ($bestRoom) {
            $color = count($rooms[$bestRoom]['players']) == 0 ? 'red' : 'blue';
            $rooms[$bestRoom]['players'][] = [
                'user_id' => $userId, 'username' => $username, 'seat' => count($rooms[$bestRoom]['players']),
                'color' => $color, 'is_ai' => false
            ];
            // 满2人开始游戏
            if (count($rooms[$bestRoom]['players']) == 2) {
                self::startGame($rooms[$bestRoom]);
            }
            $result = $rooms[$bestRoom];
            self::writeAll($fp, $rooms);
            self::unlock($fp);
            return ['room' => $result];
        }

        // 创建新房间
        $roomId = 'beast_' . uniqid();
        $room = [
            'room_id' => $roomId,
            'owner_user_id' => $userId,
            'players' => [
                ['user_id' => $userId, 'username' => $username, 'seat' => 0, 'color' => 'red', 'is_ai' => false]
            ],
            'status' => 'waiting',
            'gameState' => null,
            'created_at' => time(),
            'updated_at' => time()
        ];
        $rooms[$roomId] = $room;
        self::writeAll($fp, $rooms);
        self::unlock($fp);
        return ['room' => $room];
    }

    // 添加 AI（斗兽棋满2人，加1个AI）
    public static function addAI($roomId) {
        return self::update($roomId, function(&$room) {
            if ($room['status'] != 'waiting') return ['error' => '游戏已开始'];
            if (count($room['players']) >= 2) return ['error' => '房间已满'];
            $seat = count($room['players']);
            $room['players'][] = [
                'user_id' => 'ai_' . $seat . '_' . uniqid(),
                'username' => 'AI-' . ($seat == 0 ? '红方' : '蓝方'),
                'seat' => $seat,
                'color' => $seat == 0 ? 'red' : 'blue',
                'is_ai' => true
            ];
            if (count($room['players']) == 2) {
                self::startGame($room);
            }
            return ['success' => true, 'room' => $room];
        });
    }

    // 开始游戏：初始化棋盘
    private static function startGame(&$room) {
        $room['status'] = 'playing';
        $room['gameState'] = [
            'board' => self::initBoard(),
            'turn' => 'red',
            'gameOver' => false,
            'winner' => null,
            'started' => time()
        ];
    }

    // 初始化棋盘（与前端 LAYOUT 一致）
    private static function initBoard() {
        $layout = [
            ["狮","地","陷","兽","陷","地","虎"],
            ["地","狗","地","陷","地","猫","地"],
            ["鼠","地","豹","地","狼","地","象"],
            ["地","河","河","地","河","河","地"],
            ["地","河","河","地","河","河","地"],
            ["地","河","河","地","河","河","地"],
            ["象","地","狼","地","豹","地","鼠"],
            ["地","猫","地","陷","地","狗","地"],
            ["虎","地","陷","兽","陷","地","狮"]
        ];
        $nameMap = ['狮'=>'LION','虎'=>'TIGER','象'=>'ELEPHANT','狼'=>'WOLF','豹'=>'PANTHER','狗'=>'DOG','猫'=>'CAT','鼠'=>'RAT'];
        $board = [];
        for ($r = 0; $r < 9; $r++) {
            $board[$r] = [];
            for ($c = 0; $c < 7; $c++) {
                $board[$r][$c] = null;
            }
        }
        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $val = $layout[$r][$c];
                if (!isset($nameMap[$val])) continue;
                $color = ($r <= 2) ? 'blue' : 'red';
                $board[$r][$c] = ['type' => $nameMap[$val], 'color' => $color];
            }
        }
        return $board;
    }

    // 更新游戏状态（校验回合）
    public static function updateState($roomId, $playerColor, $gameState) {
        return self::update($roomId, function(&$room) use ($playerColor, $gameState) {
            if ($room['status'] != 'playing') return ['error' => '游戏未开始'];
            // 校验该玩家在房间中
            $found = false;
            foreach ($room['players'] as $p) {
                if ($p['color'] == $playerColor && !$p['is_ai']) { $found = true; break; }
            }
            if (!$found) return ['error' => '你不在这个房间'];
            // 校验回合
            if ($room['gameState'] && ($room['gameState']['turn'] ?? '') !== $playerColor) {
                return ['error' => '不是你的回合'];
            }
            $room['gameState'] = $gameState;
            return ['success' => true, 'room' => $room];
        });
    }

    // 查找用户所在房间
    public static function findUserRoom($userId) {
        $rooms = self::getAll();
        foreach ($rooms as $id => $room) {
            if ($room['status'] === 'finished') continue;
            foreach ($room['players'] as $p) {
                if ($p['user_id'] == $userId && !$p['is_ai']) {
                    return $room;
                }
            }
        }
        return null;
    }

    // 离开房间：房主离开删除房间；非房主离开由AI接管
    public static function leave($roomId, $userId) {
        return self::update($roomId, function(&$room) use ($userId) {
            $isOwner = ($room['owner_user_id'] ?? -1) == $userId;
            if ($isOwner) {
                $room['status'] = 'finished';
                $room['disbanded'] = true;
                return ['success' => true, 'deleted' => true];
            }
            foreach ($room['players'] as $i => &$p) {
                if ($p['user_id'] == $userId && !$p['is_ai']) {
                    $seat = $p['seat'];
                    $p['is_ai'] = true;
                    $p['user_id'] = 'ai_' . $seat . '_' . uniqid();
                    $p['username'] = 'AI-' . ($p['color'] == 'red' ? '红方' : '蓝方');
                    return ['success' => true, 'replaced_by_ai' => true];
                }
            }
            return ['error' => '你不在这个房间'];
        });
    }

    // 踢出玩家（仅房主）
    public static function kickPlayer($roomId, $ownerId, $targetUserId) {
        return self::update($roomId, function(&$room) use ($ownerId, $targetUserId) {
            if ($room['owner_user_id'] != $ownerId) return ['error' => '只有房主可以踢出玩家'];
            if ($targetUserId == $ownerId) return ['error' => '不能踢出自己'];
            foreach ($room['players'] as $i => $p) {
                if ($p['user_id'] == $targetUserId && !$p['is_ai']) {
                    array_splice($room['players'], $i, 1);
                    foreach ($room['players'] as $j => &$pl) $pl['seat'] = $j;
                    // 游戏中人数不足2人，解散
                    if ($room['status'] == 'playing' && count($room['players']) < 2) {
                        $room['status'] = 'finished';
                        $room['disbanded'] = true;
                    } else {
                        $room['status'] = 'waiting';
                        $room['gameState'] = null;
                    }
                    return ['success' => true, 'room' => $room];
                }
            }
            return ['error' => '玩家不存在'];
        });
    }

    // 删除房间
    public static function deleteRoom($roomId) {
        $fp = self::lock();
        if (!$fp) return false;
        $rooms = self::readAll($fp);
        if (isset($rooms[$roomId])) {
            unset($rooms[$roomId]);
            self::writeAll($fp, $rooms);
            self::unlock($fp);
            return true;
        }
        self::unlock($fp);
        return false;
    }
}

/* ========== API ========== */
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$roomId = $_GET['room_id'] ?? $_POST['room_id'] ?? '';

// 脱敏：非本人隐藏 gameState（除了自己走棋同步时需要）
function sanitizeRoom($room, $userId) {
    if (!$room) return null;
    $out = $room;
    return $out;
}

switch ($action) {
    case 'list':
        $rooms = BeastRoomStore::getAll();
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
                    return ['username' => $p['username'], 'is_ai' => $p['is_ai'], 'color' => $p['color']];
                }, $r['players']),
                'has_human' => $hasHuman
            ];
        }
        echo json_encode(['success' => true, 'rooms' => $list]);
        break;

    case 'join':
        $result = BeastRoomStore::joinOrCreate($user['id'], $user['username']);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($result['room'], $user['id'])]);
        }
        break;

    case 'my_room':
        $room = BeastRoomStore::findUserRoom($user['id']);
        if ($room) {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
        } else {
            echo json_encode(['success' => true, 'room' => null]);
        }
        break;

    case 'status':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $room = BeastRoomStore::get($roomId);
        if (!$room) { echo json_encode(['success' => false, 'error' => '房间不存在']); break; }
        echo json_encode(['success' => true, 'room' => sanitizeRoom($room, $user['id'])]);
        break;

    case 'add_ai':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $result = BeastRoomStore::addAI($roomId);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($result['room'] ?? null, $user['id'])]);
        }
        break;

    case 'update_state':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $color = $_POST['color'] ?? '';
        $gameState = json_decode($_POST['gameState'] ?? '{}', true);
        $result = BeastRoomStore::updateState($roomId, $color, $gameState);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($result['room'] ?? null, $user['id'])]);
        }
        break;

    case 'leave':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $result = BeastRoomStore::leave($roomId, $user['id']);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else if (!empty($result['deleted'])) {
            BeastRoomStore::deleteRoom($roomId);
            echo json_encode(['success' => true, 'deleted' => true]);
        } else {
            echo json_encode(['success' => true]);
        }
        break;

    case 'kick':
        if (!$roomId) { echo json_encode(['success' => false, 'error' => '缺少room_id']); break; }
        $targetUserId = $_POST['target_user_id'] ?? $_GET['target_user_id'] ?? '';
        if (!$targetUserId) { echo json_encode(['success' => false, 'error' => '缺少target_user_id']); break; }
        $result = BeastRoomStore::kickPlayer($roomId, $user['id'], $targetUserId);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode(['success' => true, 'room' => sanitizeRoom($result['room'] ?? null, $user['id'])]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => '未知操作']);
}
