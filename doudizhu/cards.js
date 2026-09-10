/* ============================================================
 *  斗地主 SVG 扑克牌
 *  card id: 0-51 普通牌, 52 小王, 53 大王
 * ============================================================ */
(function () {
    'use strict';

    var SUITS = ['♠', '♥', '♣', '♦']; // 黑桃,红心,梅花,方块
    var SUIT_COLOR = ['#1a1a2e', '#e63946', '#1a1a2e', '#e63946'];
    var RANKS = ['', '', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A', '2'];

    function cardPoint(c) {
        if (c == 52) return 16;
        if (c == 53) return 17;
        return Math.floor(c / 4) + 3;
    }

    function cardSuit(c) {
        if (c >= 52) return -1;
        return c % 4;
    }

    function rankText(p) {
        if (p == 16) return 'JOKER';
        if (p == 17) return 'JOKER';
        return RANKS[p] || '';
    }

    /**
     * 生成一张牌的 SVG 字符串
     * @param {number} c - 牌 id
     * @param {object} opts - {width, height, selected, hidden}
     */
    function cardSVG(c, opts) {
        opts = opts || {};
        var w = opts.width || 70;
        var h = opts.height || 100;
        var selected = opts.selected || false;
        var hidden = opts.hidden || false;

        if (hidden) {
            // 牌背
            return '<svg width="' + w + '" height="' + h + '" viewBox="0 0 70 100" xmlns="http://www.w3.org/2000/svg">' +
                '<rect x="1" y="1" width="68" height="98" rx="8" fill="#1a1a3e" stroke="#00e5ff" stroke-width="1.5"/>' +
                '<rect x="5" y="5" width="60" height="90" rx="5" fill="none" stroke="#00e5ff" stroke-width="0.5" opacity="0.5"/>' +
                '<text x="35" y="55" text-anchor="middle" font-size="28" fill="#00e5ff" opacity="0.8">⚡</text>' +
                '</svg>';
        }

        var p = cardPoint(c);
        var s = cardSuit(c);
        var isJoker = (p >= 16);
        var color = isJoker ? (p == 17 ? '#ff00ff' : '#1a1a2e') : SUIT_COLOR[s];
        var rank = rankText(p);
        var suitSym = isJoker ? (p == 17 ? '★' : '☆') : SUITS[s];
        var fontSize = (rank == '10') ? 11 : 13;

        var selY = selected ? -12 : 0;
        var selStroke = selected ? '#00e5ff' : 'rgba(0,0,0,0.2)';
        var selShadow = selected ? 'filter="drop-shadow(0 0 6px #00e5ff)"' : '';

        // 中间大花色
        var centerContent = '';
        if (isJoker) {
            var jokerColor = p == 17 ? '#ff00ff' : '#333';
            var jokerText = p == 17 ? '大王' : '小王';
            centerContent =
                '<text x="35" y="48" text-anchor="middle" font-size="14" font-weight="bold" fill="' + jokerColor + '">' + jokerText + '</text>' +
                '<text x="35" y="72" text-anchor="middle" font-size="22" fill="' + jokerColor + '">' + suitSym + '</text>';
        } else {
            centerContent = '<text x="35" y="62" text-anchor="middle" font-size="32" fill="' + color + '">' + suitSym + '</text>';
        }

        return '<svg width="' + w + '" height="' + h + '" viewBox="0 0 70 100" xmlns="http://www.w3.org/2000/svg" style="transform:translateY(' + selY + 'px);transition:transform 0.15s;cursor:pointer;" ' + selShadow + '>' +
            '<rect x="1" y="1" width="68" height="98" rx="8" fill="#ffffff" stroke="' + selStroke + '" stroke-width="' + (selected ? 2 : 1) + '"/>' +
            // 左上角
            '<text x="8" y="16" text-anchor="middle" font-size="' + fontSize + '" font-weight="bold" fill="' + color + '">' + rank + '</text>' +
            '<text x="8" y="28" text-anchor="middle" font-size="12" fill="' + color + '">' + suitSym + '</text>' +
            // 右下角（旋转）
            '<g transform="translate(62,84) rotate(180)">' +
            '<text x="0" y="0" text-anchor="middle" font-size="' + fontSize + '" font-weight="bold" fill="' + color + '">' + rank + '</text>' +
            '<text x="0" y="12" text-anchor="middle" font-size="12" fill="' + color + '">' + suitSym + '</text>' +
            '</g>' +
            // 中间
            centerContent +
            '</svg>';
    }

    // 导出
    window.DoudizhuCards = {
        cardSVG: cardSVG,
        cardPoint: cardPoint,
        cardSuit: cardSuit,
        SUITS: SUITS,
        RANKS: RANKS
    };
})();
