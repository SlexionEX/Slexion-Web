<?php
/**
 * 斗地主核心逻辑库
 * 牌表示: {point: 3-17, suit: 0-3, id: string}
 * point: 3=3, 4=4, ..., 13=K, 14=A, 15=2, 16=小王, 17=大王
 * suit: 0=黑桃, 1=红桃, 2=梅花, 3=方块 (大小王 suit=-1)
 */

class DouDiZhu {

    // 生成一副牌（54张）
    public static function createDeck() {
        $deck = [];
        $suits = [0, 1, 2, 3];
        for ($point = 3; $point <= 15; $point++) {
            foreach ($suits as $suit) {
                $deck[] = ['point' => $point, 'suit' => $suit, 'id' => "{$point}_{$suit}"];
            }
        }
        $deck[] = ['point' => 16, 'suit' => -1, 'id' => 'joker_small'];
        $deck[] = ['point' => 17, 'suit' => -1, 'id' => 'joker_big'];
        return $deck;
    }

    // 洗牌（多次 Fisher-Yates + 随机切牌，确保彻底随机）
    public static function shuffle(&$deck) {
        $n = count($deck);
        // 洗3次
        for ($round = 0; $round < 3; $round++) {
            // Fisher-Yates 洗牌
            for ($i = $n - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                $temp = $deck[$i];
                $deck[$i] = $deck[$j];
                $deck[$j] = $temp;
            }
            // 随机切牌
            if ($round < 2) {
                $cut = random_int(10, $n - 10);
                $part1 = array_slice($deck, 0, $cut);
                $part2 = array_slice($deck, $cut);
                $deck = array_merge($part2, $part1);
            }
        }
    }

    // 发牌：返回 [玩家1牌, 玩家2牌, 玩家3牌, 底牌]
    public static function deal($deck) {
        self::shuffle($deck);
        $p1 = array_slice($deck, 0, 17);
        $p2 = array_slice($deck, 17, 17);
        $p3 = array_slice($deck, 34, 17);
        $bottom = array_slice($deck, 51, 3);
        foreach ([$p1, $p2, $p3] as &$p) self::sortHand($p);
        self::sortHand($bottom);
        return [$p1, $p2, $p3, $bottom];
    }

    // 手牌排序（从大到小）
    public static function sortHand(&$hand) {
        usort($hand, function($a, $b) {
            if ($a['point'] !== $b['point']) return $b['point'] - $a['point'];
            return $a['suit'] - $b['suit'];
        });
    }

    // 统计每种点数的数量
    private static function countPoints($cards) {
        $counts = [];
        foreach ($cards as $c) {
            $p = $c['point'];
            if (!isset($counts[$p])) $counts[$p] = 0;
            $counts[$p]++;
        }
        return $counts;
    }

    /**
     * 判断牌型，返回 ['type' => 牌型, 'value' => 比较值, 'length' => 长度] 或 false
     * 牌型优先级（数字越大越大）:
     * 1=单张, 2=对子, 3=三张, 4=三带一, 5=三带二, 6=顺子, 7=连对, 8=飞机, 9=飞机带单, 10=飞机带对, 11=四带二, 12=四带两对, 13=炸弹, 14=王炸
     */
    public static function getPattern($cards) {
        if (empty($cards)) return false;
        $n = count($cards);
        $counts = self::countPoints($cards);
        $points = array_keys($counts);
        sort($points);

        // 王炸
        if ($n == 2 && isset($counts[16]) && isset($counts[17])) {
            return ['type' => 14, 'value' => 17, 'length' => 2];
        }

        // 单张
        if ($n == 1) {
            return ['type' => 1, 'value' => $cards[0]['point'], 'length' => 1];
        }

        // 对子
        if ($n == 2 && $counts[$points[0]] == 2) {
            return ['type' => 2, 'value' => $points[0], 'length' => 2];
        }

        // 三张
        if ($n == 3 && $counts[$points[0]] == 3) {
            return ['type' => 3, 'value' => $points[0], 'length' => 3];
        }

        // 炸弹
        if ($n == 4 && $counts[$points[0]] == 4) {
            return ['type' => 13, 'value' => $points[0], 'length' => 4];
        }

        // 三带一
        if ($n == 4) {
            foreach ($points as $p) {
                if ($counts[$p] == 3) {
                    return ['type' => 4, 'value' => $p, 'length' => 4];
                }
            }
        }

        // 三带二
        if ($n == 5) {
            $three = -1; $two = -1;
            foreach ($points as $p) {
                if ($counts[$p] == 3) $three = $p;
                if ($counts[$p] == 2) $two = $p;
            }
            if ($three >= 0 && $two >= 0) {
                return ['type' => 5, 'value' => $three, 'length' => 5];
            }
        }

        // 顺子 (5张及以上连续单张，不含2和王)
        if ($n >= 5 && count($points) == $n) {
            $valid = true;
            foreach ($points as $p) {
                if ($p >= 15) { $valid = false; break; }
            }
            if ($valid && $points[count($points)-1] - $points[0] == $n - 1) {
                return ['type' => 6, 'value' => $points[0], 'length' => $n];
            }
        }

        // 连对 (3对及以上连续对子，不含2和王)
        if ($n >= 6 && $n % 2 == 0) {
            $allTwo = true;
            foreach ($points as $p) {
                if ($counts[$p] != 2 || $p >= 15) { $allTwo = false; break; }
            }
            if ($allTwo && count($points) == $n/2 && $points[count($points)-1] - $points[0] == $n/2 - 1) {
                return ['type' => 7, 'value' => $points[0], 'length' => $n];
            }
        }

        // 四带二 (四张+两张单张，不是炸弹)
        if ($n == 6) {
            foreach ($points as $p) {
                if ($counts[$p] == 4) {
                    return ['type' => 11, 'value' => $p, 'length' => 6];
                }
            }
        }

        // 四带两对 (四张+两对)
        if ($n == 8) {
            $four = -1; $twos = 0;
            foreach ($points as $p) {
                if ($counts[$p] == 4) $four = $p;
                if ($counts[$p] == 2) $twos++;
            }
            if ($four >= 0 && $twos == 2) {
                return ['type' => 12, 'value' => $four, 'length' => 8];
            }
        }

        // 飞机 (两个及以上连续三张，不含2和王)
        $threes = [];
        foreach ($points as $p) {
            if ($counts[$p] == 3 && $p < 15) $threes[] = $p;
        }
        sort($threes);
        // 找连续的三张
        $planeLen = 0;
        $planeStart = -1;
        for ($i = 0; $i < count($threes); $i++) {
            $len = 1;
            $start = $threes[$i];
            for ($j = $i + 1; $j < count($threes); $j++) {
                if ($threes[$j] == $threes[$j-1] + 1) $len++;
                else break;
            }
            if ($len >= 2 && $len * 3 <= $n) {
                // 检查剩余牌是否匹配
                $remain = $n - $len * 3;
                if ($remain == 0) {
                    // 纯飞机
                    return ['type' => 8, 'value' => $start, 'length' => $n];
                }
                if ($remain == $len) {
                    // 飞机带单
                    return ['type' => 9, 'value' => $start, 'length' => $n];
                }
                if ($remain == $len * 2) {
                    // 飞机带对：检查剩余是否都是对子
                    $remainCards = [];
                    foreach ($cards as $c) {
                        if (!in_array($c['point'], range($start, $start + $len - 1))) {
                            $remainCards[] = $c;
                        }
                    }
                    $rc = self::countPoints($remainCards);
                    $allPairs = true;
                    foreach ($rc as $p => $cnt) {
                        if ($cnt != 2) { $allPairs = false; break; }
                    }
                    if ($allPairs && count($rc) == $len) {
                        return ['type' => 10, 'value' => $start, 'length' => $n];
                    }
                }
            }
        }

        return false;
    }

    /**
     * 判断 $newCards 是否能压过 $lastCards
     */
    public static function canBeat($newCards, $lastCards) {
        $np = self::getPattern($newCards);
        $lp = self::getPattern($lastCards);
        if (!$np) return false;
        if (!$lp) return true; // 没有上家牌，任意合法牌型都能出

        // 王炸最大
        if ($np['type'] == 14) return true;
        if ($lp['type'] == 14) return false;

        // 炸弹可以压非炸弹
        if ($np['type'] == 13 && $lp['type'] != 13) return true;
        if ($lp['type'] == 13 && $np['type'] != 13) return false;

        // 同牌型比较
        if ($np['type'] == $lp['type'] && $np['length'] == $lp['length']) {
            return $np['value'] > $lp['value'];
        }

        return false;
    }

    /**
     * 从手牌中移除指定的牌（按id）
     */
    public static function removeCards(&$hand, $cardIds) {
        $idSet = array_flip($cardIds);
        $hand = array_values(array_filter($hand, function($c) use ($idSet) {
            return !isset($idSet[$c['id']]);
        }));
    }

    /**
     * 简单 AI：根据上家牌选择出牌
     * 返回要出的牌数组，空数组表示不出
     */
    public static function aiPlay($hand, $lastCards, $isLandlord, $partnerCards = null) {
        // 如果没有上家牌，主动出牌
        if (empty($lastCards)) {
            return self::aiLead($hand);
        }

        $lp = self::getPattern($lastCards);
        if (!$lp) return [];

        $counts = self::countPoints($hand);
        $points = array_keys($counts);
        sort($points);

        // 根据牌型找能压的最小牌
        switch ($lp['type']) {
            case 1: // 单张
                foreach ($points as $p) {
                    if ($p > $lp['value'] && $counts[$p] >= 1) {
                        // 不要拆炸弹
                        if ($counts[$p] == 4) continue;
                        foreach ($hand as $c) if ($c['point'] == $p) return [$c];
                    }
                }
                // 尝试炸弹
                return self::findBomb($hand, $lp['value']);

            case 2: // 对子
                foreach ($points as $p) {
                    if ($p > $lp['value'] && $counts[$p] >= 2 && $counts[$p] != 4) {
                        $cards = [];
                        foreach ($hand as $c) if ($c['point'] == $p) { $cards[] = $c; if (count($cards)==2) break; }
                        return $cards;
                    }
                }
                return self::findBomb($hand, $lp['value']);

            case 3: // 三张
            case 4: // 三带一
            case 5: // 三带二
                foreach ($points as $p) {
                    if ($p > $lp['value'] && $counts[$p] >= 3 && $counts[$p] != 4) {
                        $three = [];
                        foreach ($hand as $c) if ($c['point'] == $p) { $three[] = $c; if (count($three)==3) break; }
                        if ($lp['type'] == 3) return $three;
                        if ($lp['type'] == 4) {
                            // 带一张最小的单牌
                            foreach ($hand as $c) {
                                if ($c['point'] != $p && $counts[$c['point']] == 1) {
                                    return array_merge($three, [$c]);
                                }
                            }
                            foreach ($hand as $c) if ($c['point'] != $p) return array_merge($three, [$c]);
                        }
                        if ($lp['type'] == 5) {
                            // 带一对
                            foreach ($points as $p2) {
                                if ($p2 != $p && $counts[$p2] >= 2 && $counts[$p2] != 4) {
                                    $pair = [];
                                    foreach ($hand as $c) if ($c['point'] == $p2) { $pair[] = $c; if (count($pair)==2) break; }
                                    return array_merge($three, $pair);
                                }
                            }
                        }
                    }
                }
                return self::findBomb($hand, $lp['value']);

            case 6: // 顺子
                $len = $lp['length'];
                for ($start = $lp['value'] + 1; $start <= 14 - $len + 1; $start++) {
                    $valid = true;
                    for ($i = 0; $i < $len; $i++) {
                        if (!isset($counts[$start + $i]) || $counts[$start + $i] < 1) { $valid = false; break; }
                    }
                    if ($valid) {
                        $cards = [];
                        for ($i = 0; $i < $len; $i++) {
                            foreach ($hand as $c) if ($c['point'] == $start + $i) { $cards[] = $c; break; }
                        }
                        return $cards;
                    }
                }
                return self::findBomb($hand, $lp['value']);

            case 13: // 炸弹
                foreach ($points as $p) {
                    if ($p > $lp['value'] && $counts[$p] == 4) {
                        $cards = [];
                        foreach ($hand as $c) if ($c['point'] == $p) $cards[] = $c;
                        return $cards;
                    }
                }
                return [];

            default:
                // 复杂牌型尝试炸弹
                return self::findBomb($hand, 15);
        }
    }

    // AI 主动出牌
    private static function aiLead($hand) {
        $counts = self::countPoints($hand);
        $points = array_keys($counts);
        sort($points);

        // 优先出顺子
        for ($len = 12; $len >= 5; $len--) {
            for ($start = 3; $start <= 14 - $len + 1; $start++) {
                $valid = true;
                for ($i = 0; $i < $len; $i++) {
                    if (!isset($counts[$start + $i]) || $counts[$start + $i] < 1) { $valid = false; break; }
                }
                if ($valid) {
                    $cards = [];
                    for ($i = 0; $i < $len; $i++) {
                        foreach ($hand as $c) if ($c['point'] == $start + $i) { $cards[] = $c; break; }
                    }
                    return $cards;
                }
            }
        }

        // 出最小的单张（不是2和王）
        foreach ($points as $p) {
            if ($p < 15 && $counts[$p] == 1) {
                foreach ($hand as $c) if ($c['point'] == $p) return [$c];
            }
        }
        // 出最小的对子
        foreach ($points as $p) {
            if ($p < 15 && $counts[$p] == 2) {
                $cards = [];
                foreach ($hand as $c) if ($c['point'] == $p) { $cards[] = $c; if (count($cards)==2) break; }
                return $cards;
            }
        }
        // 出最小的三张
        foreach ($points as $p) {
            if ($p < 15 && $counts[$p] == 3) {
                $cards = [];
                foreach ($hand as $c) if ($c['point'] == $p) { $cards[] = $c; if (count($cards)==3) break; }
                return $cards;
            }
        }
        // 出最小的单张（包括2）
        foreach ($points as $p) {
            if ($counts[$p] >= 1 && $p < 16) {
                foreach ($hand as $c) if ($c['point'] == $p) return [$c];
            }
        }
        // 随便出一张
        return [$hand[count($hand)-1]];
    }

    // 找炸弹
    private static function findBomb($hand, $minValue) {
        $counts = self::countPoints($hand);
        foreach ($counts as $p => $cnt) {
            if ($cnt == 4 && $p > $minValue) {
                $cards = [];
                foreach ($hand as $c) if ($c['point'] == $p) $cards[] = $c;
                return $cards;
            }
        }
        // 王炸
        $hasSmall = false; $hasBig = false;
        foreach ($hand as $c) {
            if ($c['point'] == 16) $hasSmall = true;
            if ($c['point'] == 17) $hasBig = true;
        }
        if ($hasSmall && $hasBig) {
            $cards = [];
            foreach ($hand as $c) if ($c['point'] >= 16) $cards[] = $c;
            return $cards;
        }
        return [];
    }

    // AI 叫地主：返回分数 0(不叫), 1, 2, 3
    public static function aiCallLandlord($hand) {
        $score = 0;
        $counts = self::countPoints($hand);
        // 大王+3，小王+2
        if (isset($counts[17])) $score += 3;
        if (isset($counts[16])) $score += 2;
        // 2每张+1
        if (isset($counts[15])) $score += $counts[15];
        // 炸弹每个+4
        foreach ($counts as $p => $cnt) {
            if ($cnt == 4) $score += 4;
        }
        if ($score >= 8) return 3;
        if ($score >= 5) return 2;
        if ($score >= 3) return 1;
        return 0;
    }

    // 牌面文字
    public static function pointText($point) {
        $map = [3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10',11=>'J',12=>'Q',13=>'K',14=>'A',15=>'2',16=>'小王',17=>'大王'];
        return $map[$point] ?? '?';
    }

    // 花色符号
    public static function suitSymbol($suit) {
        $map = [0=>'♠',1=>'♥',2=>'♣',3=>'♦'];
        return $map[$suit] ?? '';
    }

    // 花色颜色
    public static function suitColor($suit) {
        return ($suit == 1 || $suit == 3) ? '#ff3366' : '#1a1a2e';
    }
}
