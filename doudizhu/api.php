<?php
/**
 * 斗地主后端 API
 * 用 JSON 文件存储房间状态，轮询获取
 */
require_once __DIR__ . '/../account/config.php';

header('Content-Type: application/json; charset=utf-8');

$ROOMS_DIR = __DIR__ . '/rooms';
if (!is_dir($ROOMS_DIR)) mkdir($ROOMS_DIR, 0777, true);

$user = current_user();
if (!$user) {
    json_response(['success' => false, 'error' => '请先登录']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$uid = $user['id'];
$uname = $user['username'];

function loadRoom($rid) {
    global $ROOMS_DIR;
    $f = $ROOMS_DIR . '/' . $rid . '.json';
    if (!file_exists($f)) return null;
    return json_decode(file_get_contents($f), true);
}

function saveRoom($room) {
    global $ROOMS_DIR;
    $f = $ROOMS_DIR . '/' . $room['id'] . '.json';
    file_put_contents($f, json_encode($room, JSON_UNESCAPED_UNICODE));
}

function findRoom() {
    global $ROOMS_DIR;
    $best = null;
    foreach (glob($ROOMS_DIR . '/*.json') as $f) {
        $r = json_decode(file_get_contents($f), true);
        if (!$r) continue;
        if ($r['state'] === 'waiting' && count($r['players']) < 3) {
            if (!$best || count($r['players']) > count($best['players'])) {
                $best = $r;
            }
        }
    }
    return $best;
}

function createDeck() {
    $deck = [];
    // 0-51: 3-2 四种花色; 52=小王, 53=大王
    for ($i = 0; $i < 52; $i++) $deck[] = $i;
    $deck[] = 52; $deck[] = 53;
    shuffle($deck);
    return $deck;
}

function cardPoint($c) {
    if ($c == 52) return 16; // 小王
    if ($c == 53) return 17; // 大王
    return intdiv($c, 4) + 3; // 3-15 (3=3,...,15=2)
}

function cardSuit($c) {
    if ($c >= 52) return -1;
    return $c % 4; // 0=黑桃,1=红心,2=梅花,3=方块
}

function sortCards(&$cards) {
    usort($cards, function($a, $b) {
        $pa = cardPoint($a); $pb = cardPoint($b);
        if ($pa != $pb) return $pb - $pa;
        return cardSuit($b) - cardSuit($a);
    });
}

// 判断牌型，返回 ['type'=>..., 'main'=>主要点数, 'len'=>长度]
function detectType($cards) {
    if (empty($cards)) return null;
    sortCards($cards);
    $n = count($cards);
    $points = array_map('cardPoint', $cards);
    $cnt = array_count_values($points);
    arsort($cnt);
    $vals = array_values($cnt);
    $keys = array_keys($cnt);

    // 王炸
    if ($n == 2 && $points[0] == 17 && $points[1] == 16)
        return ['type' => 'rocket', 'main' => 17, 'len' => 2];
    // 炸弹
    if ($n == 4 && $vals[0] == 4)
        return ['type' => 'bomb', 'main' => $keys[0], 'len' => 4];
    // 单张
    if ($n == 1) return ['type' => 'single', 'main' => $points[0], 'len' => 1];
    // 对子
    if ($n == 2 && $vals[0] == 2) return ['type' => 'pair', 'main' => $keys[0], 'len' => 2];
    // 三张
    if ($n == 3 && $vals[0] == 3) return ['type' => 'triple', 'main' => $keys[0], 'len' => 3];
    // 三带一
    if ($n == 4 && $vals[0] == 3) return ['type' => 'triple1', 'main' => $keys[0], 'len' => 4];
    // 三带二
    if ($n == 5 && $vals[0] == 3 && $vals[1] == 2) return ['type' => 'triple2', 'main' => $keys[0], 'len' => 5];
    // 四带二(单)
    if ($n == 6 && $vals[0] == 4) return ['type' => 'four2', 'main' => $keys[0], 'len' => 6];
    // 顺子
    if ($n >= 5 && $vals[0] == 1) {
        $ps = array_unique($points); sort($ps);
        if (count($ps) == $n && $ps[0] >= 3 && $ps[count($ps)-1] <= 14 && $ps[count($ps)-1] - $ps[0] == $n - 1)
            return ['type' => 'straight', 'main' => $ps[count($ps)-1], 'len' => $n];
    }
    // 连对
    if ($n >= 6 && $n % 2 == 0 && $vals[0] == 2 && count($vals) == $n / 2) {
        $ps = $keys; sort($ps);
        if ($ps[0] >= 3 && $ps[count($ps)-1] <= 14 && $ps[count($ps)-1] - $ps[0] == count($ps) - 1)
            return ['type' => 'straight_pair', 'main' => $ps[count($ps)-1], 'len' => $n];
    }
    // 飞机(不带翅膀)
    if ($n >= 6 && $n % 3 == 0 && $vals[0] == 3 && count($vals) == $n / 3) {
        $ps = $keys; sort($ps);
        if ($ps[0] >= 3 && $ps[count($ps)-1] <= 14 && $ps[count($ps)-1] - $ps[0] == count($ps) - 1)
            return ['type' => 'plane', 'main' => $ps[count($ps)-1], 'len' => $n];
    }
    return null; // 不合法牌型
}

// 比较牌型，new 是否大于 old
function canBeat($newCards, $oldPlay) {
    $nt = detectType($newCards);
    if (!$nt) return false;
    if (!$oldPlay) return true; // 第一轮随便出
    $ot = $oldPlay['type'];
    // 王炸最大
    if ($nt['type'] == 'rocket') return true;
    if ($ot == 'rocket') return false;
    // 炸弹
    if ($nt['type'] == 'bomb' && $ot != 'bomb') return true;
    if ($ot == 'bomb' && $nt['type'] != 'bomb') return false;
    // 同类型同长度比较
    if ($nt['type'] == $ot && $nt['len'] == $oldPlay['len']) {
        return $nt['main'] > $oldPlay['main'];
    }
    return false;
}

// 简单 AI：选能出的最小牌型
function aiPlay($hand, $lastPlay, $isLandlord, $partnerCardsLeft) {
    sortCards($hand);
    // 如果是第一轮或自己出的牌，出最小单张
    if (!$lastPlay || $lastPlay['player'] == $GLOBALS['ai_self']) {
        return [$hand[count($hand)-1]]; // 最小单张
    }
    // 尝试压牌
    $n = count($hand);
    // 找对子
    for ($i = 0; $i < $n - 1; $i++) {
        if (cardPoint($hand[$i]) == cardPoint($hand[$i+1])) {
            $try = [$hand[$i], $hand[$i+1]];
            if (canBeat($try, $lastPlay)) return $try;
        }
    }
    // 找三张
    for ($i = 0; $i < $n - 2; $i++) {
        if (cardPoint($hand[$i]) == cardPoint($hand[$i+1]) && cardPoint($hand[$i+1]) == cardPoint($hand[$i+2])) {
            $try = [$hand[$i], $hand[$i+1], $hand[$i+2]];
            if (canBeat($try, $lastPlay)) return $try;
        }
    }
    // 单张压
    for ($i = $n - 1; $i >= 0; $i--) {
        $try = [$hand[$i]];
        if (canBeat($try, $lastPlay)) return $try;
    }
    // 炸弹
    for ($i = 0; $i < $n - 3; $i++) {
        if (cardPoint($hand[$i]) == cardPoint($hand[$i+1]) && cardPoint($hand[$i+1]) == cardPoint($hand[$i+2]) && cardPoint($hand[$i+2]) == cardPoint($hand[$i+3])) {
            $try = [$hand[$i], $hand[$i+1], $hand[$i+2], $hand[$i+3]];
            if (canBeat($try, $lastPlay)) return $try;
        }
    }
    return null; // 过
}

switch ($action) {
    case 'join':
        // 查找玩家已在哪个房间
        $existing = null;
        foreach (glob($ROOMS_DIR . '/*.json') as $f) {
            $r = json_decode(file_get_contents($f), true);
            if (!$r) continue;
            foreach ($r['players'] as $p) {
                if ($p['uid'] == $uid) { $existing = $r; break 2; }
            }
        }
        if ($existing) {
            json_response(['success' => true, 'room' => $existing['id'], 'rejoin' => true]);
        }
        $room = findRoom();
        if (!$room) {
            $rid = 'r_' . substr(md5(uniqid()), 0, 8);
            $room = [
                'id' => $rid,
                'state' => 'waiting',
                'players' => [],
                'deck' => [],
                'bottom' => [],
                'landlord' => -1,
                'current_turn' => 0,
                'last_play' => null,
                'last_play_cards' => [],
                'pass_count' => 0,
                'bidder' => 0,
                'bids' => [],
                'max_bid' => 0,
                'winner' => -1,
                'created_at' => time()
            ];
        }
        $pos = count($room['players']);
        $room['players'][] = [
            'uid' => $uid,
            'username' => $uname,
            'position' => $pos,
            'cards' => [],
            'is_landlord' => false,
            'ready' => false
        ];
        // 自动填充 AI 到3人
        $aiNames = ['AI-小蓝', 'AI-小紫'];
        $aiIdx = 0;
        while (count($room['players']) < 3) {
            $aiPos = count($room['players']);
            $room['players'][] = [
                'uid' => -100 - $aiPos,
                'username' => $aiNames[$aiIdx],
                'position' => $aiPos,
                'cards' => [],
                'is_landlord' => false,
                'ready' => true,
                'is_ai' => true
            ];
            $aiIdx++;
        }
        if (count($room['players']) == 3) {
            $room['state'] = 'bidding';
            $deck = createDeck();
            for ($i = 0; $i < 3; $i++) {
                $room['players'][$i]['cards'] = array_slice($deck, $i * 17, 17);
                sortCards($room['players'][$i]['cards']);
            }
            $room['bottom'] = array_slice($deck, 51, 3);
            $room['bidder'] = rand(0, 2);
            $room['current_turn'] = $room['bidder'];
            $room['bids'] = [];
        }
        saveRoom($room);
        json_response(['success' => true, 'room' => $room['id'], 'position' => $pos]);

    case 'state':
        $rid = $_GET['room'] ?? '';
        $room = loadRoom($rid);
        if (!$room) { json_response(['success' => false, 'error' => '房间不存在']); }
        // 只返回当前玩家的牌，其他人只返回数量
        $out = $room;
        foreach ($out['players'] as &$p) {
            if ($p['uid'] != $uid) {
                $p['card_count'] = count($p['cards']);
                unset($p['cards']);
            }
        }
        unset($p);
        $out['my_position'] = -1;
        foreach ($room['players'] as $i => $p) {
            if ($p['uid'] == $uid) { $out['my_position'] = $i; break; }
        }
        json_response(['success' => true, 'room' => $out]);

    case 'bid':
        $rid = $_POST['room'] ?? '';
        $score = intval($_POST['score'] ?? 0);
        $room = loadRoom($rid);
        if (!$room || $room['state'] != 'bidding') json_response(['success' => false, 'error' => '状态错误']);
        $me = -1;
        foreach ($room['players'] as $i => $p) if ($p['uid'] == $uid) $me = $i;
        if ($me != $room['current_turn']) json_response(['success' => false, 'error' => '不是你的回合']);
        $room['bids'][] = ['player' => $me, 'score' => $score];
        if ($score > $room['max_bid']) $room['max_bid'] = $score;
        // 叫地主结束条件：3人都叫过，或有人叫3分
        $allBid = count($room['bids']) >= 3;
        $maxThree = $score == 3;
        if ($allBid || $maxThree) {
            // 确定地主
            $landlord = -1;
            $maxS = 0;
            foreach ($room['bids'] as $b) {
                if ($b['score'] > $maxS) { $maxS = $b['score']; $landlord = $b['player']; }
            }
            if ($landlord == -1) {
                // 都不叫，重新发牌
                $deck = createDeck();
                for ($i = 0; $i < 3; $i++) {
                    $room['players'][$i]['cards'] = array_slice($deck, $i * 17, 17);
                    sortCards($room['players'][$i]['cards']);
                }
                $room['bottom'] = array_slice($deck, 51, 3);
                $room['bidder'] = ($room['bidder'] + 1) % 3;
                $room['current_turn'] = $room['bidder'];
                $room['bids'] = [];
                $room['max_bid'] = 0;
                saveRoom($room);
                json_response(['success' => true, 'rebid' => true]);
            }
            $room['landlord'] = $landlord;
            $room['players'][$landlord]['is_landlord'] = true;
            // 地主拿底牌
            $room['players'][$landlord]['cards'] = array_merge($room['players'][$landlord]['cards'], $room['bottom']);
            sortCards($room['players'][$landlord]['cards']);
            $room['state'] = 'playing';
            $room['current_turn'] = $landlord;
            $room['last_play'] = null;
            $room['last_play_cards'] = [];
            $room['pass_count'] = 0;
        } else {
            $room['current_turn'] = ($room['current_turn'] + 1) % 3;
        }
        saveRoom($room);
        json_response(['success' => true]);

    case 'play':
        $rid = $_POST['room'] ?? '';
        $cards = json_decode($_POST['cards'] ?? '[]', true);
        $pass = ($_POST['pass'] ?? '0') === '1';
        $room = loadRoom($rid);
        if (!$room || $room['state'] != 'playing') json_response(['success' => false, 'error' => '状态错误']);
        $me = -1;
        foreach ($room['players'] as $i => $p) if ($p['uid'] == $uid) $me = $i;
        if ($me != $room['current_turn']) json_response(['success' => false, 'error' => '不是你的回合']);

        if ($pass) {
            if (!$room['last_play']) json_response(['success' => false, 'error' => '第一轮不能过']);
            $room['pass_count']++;
        } else {
            if (!detectType($cards)) json_response(['success' => false, 'error' => '不合法的牌型']);
            if (!canBeat($cards, $room['last_play'])) json_response(['success' => false, 'error' => '压不过上家']);
            // 移除手牌
            $room['players'][$me]['cards'] = array_values(array_diff($room['players'][$me]['cards'], $cards));
            $room['last_play'] = ['player' => $me, 'type' => detectType($cards)['type'], 'main' => detectType($cards)['main'], 'len' => count($cards)];
            $room['last_play_cards'] = $cards;
            $room['pass_count'] = 0;
            // 检查胜利
            if (empty($room['players'][$me]['cards'])) {
                $room['state'] = 'ended';
                $room['winner'] = $me;
                saveRoom($room);
                json_response(['success' => true, 'win' => true]);
            }
        }
        // 两人过，出牌权回到最后出牌的人
        if ($room['pass_count'] >= 2) {
            $room['current_turn'] = $room['last_play']['player'];
            $room['last_play'] = null;
            $room['last_play_cards'] = [];
            $room['pass_count'] = 0;
        } else {
            $room['current_turn'] = ($room['current_turn'] + 1) % 3;
        }
        saveRoom($room);
        json_response(['success' => true]);

    case 'ai_turn':
        // 触发 AI 出牌（前端轮询时如果是 AI 回合则调用）
        $rid = $_POST['room'] ?? '';
        $room = loadRoom($rid);
        if (!$room || $room['state'] != 'playing') json_response(['success' => false, 'error' => '状态错误']);
        $ct = $room['current_turn'];
        // 检查当前玩家是否是真人
        $isHuman = false;
        foreach ($room['players'] as $p) if ($p['uid'] == $uid) $isHuman = true;
        if ($room['players'][$ct]['uid'] == $uid) json_response(['success' => false, 'error' => '是你的回合']);
        // AI 叫地主
        if ($room['state'] == 'bidding') {
            $GLOBALS['ai_self'] = $ct;
            $score = rand(0, 3);
            $_POST['room'] = $rid;
            $_POST['score'] = $score;
            // 直接执行 bid 逻辑
            $room['bids'][] = ['player' => $ct, 'score' => $score];
            if ($score > $room['max_bid']) $room['max_bid'] = $score;
            $allBid = count($room['bids']) >= 3;
            if ($allBid || $score == 3) {
                $landlord = -1; $maxS = 0;
                foreach ($room['bids'] as $b) { if ($b['score'] > $maxS) { $maxS = $b['score']; $landlord = $b['player']; } }
                if ($landlord == -1) {
                    $deck = createDeck();
                    for ($i = 0; $i < 3; $i++) { $room['players'][$i]['cards'] = array_slice($deck, $i*17, 17); sortCards($room['players'][$i]['cards']); }
                    $room['bottom'] = array_slice($deck, 51, 3);
                    $room['bidder'] = ($room['bidder']+1)%3;
                    $room['current_turn'] = $room['bidder'];
                    $room['bids'] = []; $room['max_bid'] = 0;
                    saveRoom($room);
                    json_response(['success' => true, 'ai_bid' => $score]);
                }
                $room['landlord'] = $landlord;
                $room['players'][$landlord]['is_landlord'] = true;
                $room['players'][$landlord]['cards'] = array_merge($room['players'][$landlord]['cards'], $room['bottom']);
                sortCards($room['players'][$landlord]['cards']);
                $room['state'] = 'playing';
                $room['current_turn'] = $landlord;
                $room['last_play'] = null; $room['last_play_cards'] = []; $room['pass_count'] = 0;
            } else {
                $room['current_turn'] = ($ct + 1) % 3;
            }
            saveRoom($room);
            json_response(['success' => true, 'ai_bid' => $score]);
        }
        // AI 出牌
        $GLOBALS['ai_self'] = $ct;
        $aiCards = $room['players'][$ct]['cards'];
        $play = aiPlay($aiCards, $room['last_play'], $room['players'][$ct]['is_landlord'], 0);
        if ($play === null) {
            // 过
            if (!$room['last_play']) $play = [$aiCards[count($aiCards)-1]];
            else {
                $room['pass_count']++;
                if ($room['pass_count'] >= 2) {
                    $room['current_turn'] = $room['last_play']['player'];
                    $room['last_play'] = null; $room['last_play_cards'] = []; $room['pass_count'] = 0;
                } else {
                    $room['current_turn'] = ($ct + 1) % 3;
                }
                saveRoom($room);
                json_response(['success' => true, 'ai_pass' => true]);
            }
        }
        if ($play) {
            $room['players'][$ct]['cards'] = array_values(array_diff($aiCards, $play));
            $room['last_play'] = ['player' => $ct, 'type' => detectType($play)['type'], 'main' => detectType($play)['main'], 'len' => count($play)];
            $room['last_play_cards'] = $play;
            $room['pass_count'] = 0;
            if (empty($room['players'][$ct]['cards'])) {
                $room['state'] = 'ended';
                $room['winner'] = $ct;
                saveRoom($room);
                json_response(['success' => true, 'ai_win' => true]);
            }
            $room['current_turn'] = ($ct + 1) % 3;
            saveRoom($room);
        }
        json_response(['success' => true]);

    case 'leave':
        $rid = $_POST['room'] ?? '';
        $room = loadRoom($rid);
        if ($room) {
            $room['players'] = array_values(array_filter($room['players'], function($p) use ($uid) {
                return $p['uid'] != $uid;
            }));
            if (empty($room['players'])) {
                @unlink($ROOMS_DIR . '/' . $rid . '.json');
            } else {
                saveRoom($room);
            }
        }
        json_response(['success' => true]);

    default:
        json_response(['success' => false, 'error' => '未知操作']);
}
