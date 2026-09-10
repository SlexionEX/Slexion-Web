<?php
/**
 * 房间存储（JSON文件 + 文件锁）
 */
require_once __DIR__ . '/doudizhu.php';
require_once __DIR__ . '/../../lib/mailer.php';

class RoomStore {
    private static $file = '/tmp/doudizhu_rooms.json';

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

    // 清理超时房间（超过2小时无更新）
    private static function cleanup(&$rooms) {
        $now = time();
        foreach ($rooms as $id => $room) {
            if ($now - ($room['updated_at'] ?? 0) > 7200) {
                unset($rooms[$id]);
            }
        }
    }

    // 获取所有房间
    public static function getAll() {
        $fp = self::lock();
        if (!$fp) return [];
        $rooms = self::readAll($fp);
        self::cleanup($rooms);
        self::writeAll($fp, $rooms);
        self::unlock($fp);
        return $rooms;
    }

    // 获取单个房间
    public static function get($roomId) {
        $rooms = self::getAll();
        return $rooms[$roomId] ?? null;
    }

    // 更新房间（回调函数修改房间数组）
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

    // 创建房间
    public static function create($userId, $username) {
        $fp = self::lock();
        if (!$fp) return null;
        $rooms = self::readAll($fp);
        self::cleanup($rooms);

        $roomId = 'room_' . uniqid();
        $room = [
            'room_id' => $roomId,
            'owner_user_id' => $userId,
            'players' => [
                ['user_id' => $userId, 'username' => $username, 'seat' => 0, 'hand' => [], 'is_landlord' => false, 'is_ai' => false]
            ],
            'status' => 'waiting',
            'current_turn' => 0,
            'last_play' => null,
            'landlord' => -1,
            'bottom_cards' => [],
            'call_scores' => [],
            'pass_count' => 0,
            'winner' => -1,
            'created_at' => time(),
            'updated_at' => time()
        ];
        $rooms[$roomId] = $room;
        self::writeAll($fp, $rooms);
        self::unlock($fp);
        return $room;
    }

    // 加入房间：找人数最多且未满的，没有则创建
    public static function joinOrCreate($userId, $username) {
        $fp = self::lock();
        if (!$fp) return null;
        $rooms = self::readAll($fp);
        self::cleanup($rooms);

        // 检查是否已在某个房间
        foreach ($rooms as $id => $room) {
            foreach ($room['players'] as $p) {
                if ($p['user_id'] == $userId && !$p['is_ai']) {
                    self::unlock($fp);
                    return $room;
                }
            }
        }

        // 找人数最多且未满的 waiting 房间
        $bestRoom = null;
        $bestCount = 0;
        foreach ($rooms as $id => $room) {
            if ($room['status'] == 'waiting' && count($room['players']) < 3) {
                $cnt = count($room['players']);
                if ($cnt > $bestCount) {
                    $bestCount = $cnt;
                    $bestRoom = $id;
                }
            }
        }

        if ($bestRoom) {
            $seat = count($rooms[$bestRoom]['players']);
            $rooms[$bestRoom]['players'][] = [
                'user_id' => $userId, 'username' => $username, 'seat' => $seat,
                'hand' => [], 'is_landlord' => false, 'is_ai' => false
            ];
            $rooms[$bestRoom]['updated_at'] = time();
            // 满3人开始游戏
            if (count($rooms[$bestRoom]['players']) == 3) {
                self::startGame($rooms[$bestRoom]);
            }
            $result = $rooms[$bestRoom];
            self::writeAll($fp, $rooms);
            self::unlock($fp);
            return $result;
        }

        // 创建新房间
        $roomId = 'room_' . uniqid();
        $room = [
            'room_id' => $roomId,
            'owner_user_id' => $userId,
            'players' => [
                ['user_id' => $userId, 'username' => $username, 'seat' => 0, 'hand' => [], 'is_landlord' => false, 'is_ai' => false]
            ],
            'status' => 'waiting',
            'current_turn' => 0,
            'last_play' => null,
            'landlord' => -1,
            'bottom_cards' => [],
            'call_scores' => [],
            'pass_count' => 0,
            'winner' => -1,
            'created_at' => time(),
            'updated_at' => time()
        ];
        $rooms[$roomId] = $room;
        self::writeAll($fp, $rooms);
        self::unlock($fp);
        return $room;
    }

    // AI 加入房间
    public static function addAI($roomId, $count = 2) {
        return self::update($roomId, function(&$room) use ($count) {
            if ($room['status'] != 'waiting') return ['error' => '游戏已开始'];
            $aiNames = ['AI-阿尔法', 'AI-贝塔', 'AI-伽马'];
            $added = 0;
            for ($i = 0; $i < $count && count($room['players']) < 3; $i++) {
                $seat = count($room['players']);
                $room['players'][] = [
                    'user_id' => 'ai_' . $seat . '_' . uniqid(),
                    'username' => $aiNames[$seat],
                    'seat' => $seat,
                    'hand' => [],
                    'is_landlord' => false,
                    'is_ai' => true
                ];
                $added++;
            }
            if (count($room['players']) == 3) {
                self::startGame($room);
            }
            return ['added' => $added, 'room' => $room];
        });
    }

    // 开始游戏：发牌，进入叫地主阶段
    private static function startGame(&$room) {
        $deck = DouDiZhu::createDeck();
        list($h0, $h1, $h2, $bottom) = DouDiZhu::deal($deck);
        $room['players'][0]['hand'] = $h0;
        $room['players'][1]['hand'] = $h1;
        $room['players'][2]['hand'] = $h2;
        $room['bottom_cards'] = $bottom;
        $room['status'] = 'calling';
        $room['current_turn'] = 0;
        $room['call_scores'] = [];
        $room['last_play'] = null;
        $room['pass_count'] = 0;
        $room['winner'] = -1;

        // 发送匹配完成邮件（异步，忽略结果）
        self::notifyMatchReady($room);
    }

    // 给绑定邮箱的玩家发送匹配完成通知
    private static function notifyMatchReady($room) {
        try {
            if (!function_exists('db')) return;
            $pdo = db();
            foreach ($room['players'] as $p) {
                if ($p['is_ai']) continue;
                if (empty($p['user_id'])) continue;
                $stmt = $pdo->prepare('SELECT email, email_verified FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$p['user_id']]);
                $user = $stmt->fetch();
                if ($user && !empty($user['email']) && !empty($user['email_verified'])) {
                    @Mailer::sendMatchNotification($user['email'], $p['username'], $room['room_id']);
                }
            }
        } catch (Exception $e) {
            // 邮件发送失败不影响游戏
        }
    }

    // 叫地主
    public static function callLandlord($roomId, $seat, $score) {
        return self::update($roomId, function(&$room) use ($seat, $score) {
            if ($room['status'] != 'calling') return ['error' => '不在叫地主阶段'];
            if ($room['current_turn'] != $seat) return ['error' => '不是你的回合'];

            $room['call_scores'][] = ['seat' => $seat, 'score' => $score];

            // 有人叫3分，直接当地主
            if ($score == 3) {
                self::setLandlord($room, $seat, 3);
                return ['room' => $room];
            }

            // 三人都叫过，选最高分
            if (count($room['call_scores']) >= 3) {
                $maxScore = 0; $maxSeat = -1;
                foreach ($room['call_scores'] as $cs) {
                    if ($cs['score'] > $maxScore) {
                        $maxScore = $cs['score'];
                        $maxSeat = $cs['seat'];
                    }
                }
                if ($maxSeat >= 0) {
                    self::setLandlord($room, $maxSeat, $maxScore);
                } else {
                    // 都不叫，重新发牌
                    self::startGame($room);
                }
                return ['room' => $room];
            }

            // 下一个人叫
            $room['current_turn'] = ($seat + 1) % 3;
            return ['room' => $room];
        });
    }

    private static function setLandlord(&$room, $seat, $score) {
        $room['landlord'] = $seat;
        $room['players'][$seat]['is_landlord'] = true;
        // 地主拿底牌
        $room['players'][$seat]['hand'] = array_merge($room['players'][$seat]['hand'], $room['bottom_cards']);
        DouDiZhu::sortHand($room['players'][$seat]['hand']);
        $room['status'] = 'playing';
        $room['current_turn'] = $seat;
        $room['last_play'] = null;
        $room['pass_count'] = 0;
    }

    // 出牌
    public static function playCards($roomId, $seat, $cardIds) {
        return self::update($roomId, function(&$room) use ($seat, $cardIds) {
            if ($room['status'] != 'playing') return ['error' => '游戏未开始'];
            if ($room['current_turn'] != $seat) return ['error' => '不是你的回合'];

            $player = &$room['players'][$seat];

            // 不出（空牌）
            if (empty($cardIds)) {
                if (!$room['last_play']) return ['error' => '你是先手，必须出牌'];
                $room['pass_count']++;
                $room['current_turn'] = ($seat + 1) % 3;
                // 连续两人不出，第三人自由出牌
                if ($room['pass_count'] >= 2) {
                    $room['last_play'] = null;
                    $room['pass_count'] = 0;
                }
                return ['room' => $room, 'played' => []];
            }

            // 找出牌
            $idSet = array_flip($cardIds);
            $cards = array_values(array_filter($player['hand'], function($c) use ($idSet) {
                return isset($idSet[$c['id']]);
            }));

            if (count($cards) != count($cardIds)) return ['error' => '牌不匹配'];

            // 验证牌型
            $pattern = DouDiZhu::getPattern($cards);
            if (!$pattern) return ['error' => '非法牌型'];

            // 验证能压过上家
            if ($room['last_play']) {
                if (!DouDiZhu::canBeat($cards, $room['last_play']['cards'])) {
                    return ['error' => '压不过上家的牌'];
                }
            }

            // 移除手牌
            DouDiZhu::removeCards($player['hand'], $cardIds);
            $room['last_play'] = ['seat' => $seat, 'cards' => $cards, 'pattern' => $pattern];
            $room['pass_count'] = 0;

            // 检查胜利
            if (empty($player['hand'])) {
                $room['status'] = 'finished';
                $room['winner'] = $seat;
                return ['room' => $room, 'played' => $cards, 'win' => true];
            }

            $room['current_turn'] = ($seat + 1) % 3;
            return ['room' => $room, 'played' => $cards];
        });
    }

    // AI 自动行动（叫地主或出牌）
    public static function aiAction($roomId) {
        return self::update($roomId, function(&$room) {
            $seat = $room['current_turn'];
            if ($seat < 0 || $seat >= 3) return null;
            $player = &$room['players'][$seat];
            if (!$player['is_ai']) return null;

            if ($room['status'] == 'calling') {
                $score = DouDiZhu::aiCallLandlord($player['hand']);
                $room['call_scores'][] = ['seat' => $seat, 'score' => $score];
                if ($score == 3) {
                    self::setLandlord($room, $seat, 3);
                    return ['action' => 'call', 'score' => $score];
                }
                if (count($room['call_scores']) >= 3) {
                    $maxScore = 0; $maxSeat = -1;
                    foreach ($room['call_scores'] as $cs) {
                        if ($cs['score'] > $maxScore) { $maxScore = $cs['score']; $maxSeat = $cs['seat']; }
                    }
                    if ($maxSeat >= 0) self::setLandlord($room, $maxSeat, $maxScore);
                    else self::startGame($room);
                    return ['action' => 'call', 'score' => $score];
                }
                $room['current_turn'] = ($seat + 1) % 3;
                return ['action' => 'call', 'score' => $score];
            }

            if ($room['status'] == 'playing') {
                $lastCards = $room['last_play'] ? $room['last_play']['cards'] : [];
                // 如果上家是自己（自由出牌），last_play 为 null
                if ($room['last_play'] && $room['last_play']['seat'] == $seat) {
                    $lastCards = [];
                }
                $play = DouDiZhu::aiPlay($player['hand'], $lastCards, $player['is_landlord']);
                if (empty($play)) {
                    // 不出
                    if (!$room['last_play']) {
                        // 必须出，随便出
                        $play = [$player['hand'][count($player['hand'])-1]];
                    } else {
                        $room['pass_count']++;
                        $room['current_turn'] = ($seat + 1) % 3;
                        if ($room['pass_count'] >= 2) {
                            $room['last_play'] = null;
                            $room['pass_count'] = 0;
                        }
                        return ['action' => 'pass'];
                    }
                }
                $ids = array_column($play, 'id');
                DouDiZhu::removeCards($player['hand'], $ids);
                $pattern = DouDiZhu::getPattern($play);
                $room['last_play'] = ['seat' => $seat, 'cards' => $play, 'pattern' => $pattern];
                $room['pass_count'] = 0;
                if (empty($player['hand'])) {
                    $room['status'] = 'finished';
                    $room['winner'] = $seat;
                    return ['action' => 'play', 'cards' => $play, 'win' => true];
                }
                $room['current_turn'] = ($seat + 1) % 3;
                return ['action' => 'play', 'cards' => $play];
            }
            return null;
        });
    }

    // 查找用户当前所在的房间
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

    // 踢出玩家（仅房主可操作）
    public static function kickPlayer($roomId, $ownerId, $targetUserId) {
        return self::update($roomId, function(&$room) use ($ownerId, $targetUserId) {
            if ($room['owner_user_id'] != $ownerId) {
                return ['error' => '只有房主可以踢出玩家'];
            }
            if ($targetUserId == $ownerId) {
                return ['error' => '不能踢出自己'];
            }
            foreach ($room['players'] as $i => $p) {
                if ($p['user_id'] == $targetUserId && !$p['is_ai']) {
                    array_splice($room['players'], $i, 1);
                    foreach ($room['players'] as $j => &$pl) $pl['seat'] = $j;
                    // 游戏中人数不足3人，解散房间
                    if ($room['status'] == 'playing' || $room['status'] == 'calling') {
                        if (count($room['players']) < 3) {
                            $room['status'] = 'finished';
                            $room['disbanded'] = true;
                        }
                    } else {
                        $room['status'] = 'waiting';
                    }
                    return ['success' => true, 'room' => $room];
                }
            }
            return ['error' => '玩家不存在'];
        });
    }

    // 离开房间
    public static function leave($roomId, $userId) {
        return self::update($roomId, function(&$room) use ($userId) {
            $isOwner = ($room['owner_user_id'] ?? -1) == $userId;
            // 房主离开：删除房间
            if ($isOwner) {
                $room['status'] = 'finished';
                $room['disbanded'] = true;
                return ['success' => true, 'deleted' => true];
            }
            // 非房主离开：转换为AI接管，游戏继续
            $aiNames = ['AI-阿尔法', 'AI-贝塔', 'AI-伽马'];
            foreach ($room['players'] as $i => &$p) {
                if ($p['user_id'] == $userId && !$p['is_ai']) {
                    // 转换为AI，继承手牌和座位
                    $seat = $p['seat'];
                    $p['is_ai'] = true;
                    $p['user_id'] = 'ai_' . $seat . '_' . uniqid();
                    $p['username'] = $aiNames[$seat] ?? ('AI-' . ($seat + 1));
                    // 如果是房主离开（理论上不会到这里，因为上面已处理），转移房主
                    if ($room['owner_user_id'] == $userId) {
                        foreach ($room['players'] as $op) {
                            if (!$op['is_ai']) {
                                $room['owner_user_id'] = $op['user_id'];
                                break;
                            }
                        }
                    }
                    return ['success' => true, 'replaced_by_ai' => true];
                }
            }
            return ['error' => '你不在这个房间'];
        });
    }
}
