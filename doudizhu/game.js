/* ============================================================
 * 斗地主前端游戏逻辑 v2
 * 功能：房间选择、触屏支持、AI自动行动、SVG牌
 * ============================================================ */
(function () {

    var state = {
        roomId: null,
        mySeat: -1,
        myUserId: null,
        isOwner: false,
        room: null,
        selectedCards: [],
        pollTimer: null,
        aiProcessing: false,
        screen: 'lobby', // lobby | waiting | game
        prevStatus: null // 上一个房间状态，用于检测游戏开始触发发牌动画
    };

    var API = {
        room: '/doudizhu/api/room.php',
        action: '/doudizhu/api/action.php'
    };

    /* ===== SVG 扑克牌渲染 ===== */
    function cardSVG(card, small) {
        var w = small ? 40 : 64;
        var h = small ? 58 : 94;
        var point = card.point;
        var suit = card.suit;
        var isJoker = point >= 16;
        var color = (suit == 1 || suit == 3) ? '#e63946' : '#1a1a2e';
        if (point == 16) color = '#333';
        if (point == 17) color = '#e63946';

        var pointText = '';
        if (point == 11) pointText = 'J';
        else if (point == 12) pointText = 'Q';
        else if (point == 13) pointText = 'K';
        else if (point == 14) pointText = 'A';
        else if (point == 15) pointText = '2';
        else if (point == 16) pointText = '小王';
        else if (point == 17) pointText = '大王';
        else pointText = String(point);

        var suitSymbol = '';
        if (suit == 0) suitSymbol = '♠';
        else if (suit == 1) suitSymbol = '♥';
        else if (suit == 2) suitSymbol = '♣';
        else if (suit == 3) suitSymbol = '♦';

        var fontSize = small ? 11 : 14;
        var bigFontSize = small ? 20 : 32;

        if (isJoker) {
            return '<svg viewBox="0 0 ' + w + ' ' + h + '" xmlns="http://www.w3.org/2000/svg">' +
                '<rect x="1" y="1" width="' + (w-2) + '" height="' + (h-2) + '" rx="5" fill="white" stroke="#ccc" stroke-width="1"/>' +
                '<text x="' + (w/2) + '" y="' + (h*0.38) + '" text-anchor="middle" font-size="' + bigFontSize + '">' + (point==17?'👑':'🃏') + '</text>' +
                '<text x="' + (w/2) + '" y="' + (h*0.72) + '" text-anchor="middle" font-size="' + (small?9:11) + '" font-weight="bold" fill="' + color + '">' + pointText + '</text>' +
                '</svg>';
        }

        return '<svg viewBox="0 0 ' + w + ' ' + h + '" xmlns="http://www.w3.org/2000/svg">' +
            '<rect x="1" y="1" width="' + (w-2) + '" height="' + (h-2) + '" rx="5" fill="white" stroke="#ccc" stroke-width="1"/>' +
            '<text x="6" y="' + (fontSize+4) + '" font-size="' + fontSize + '" font-weight="bold" fill="' + color + '">' + pointText + '</text>' +
            '<text x="6" y="' + (fontSize*2+6) + '" font-size="' + fontSize + '" fill="' + color + '">' + suitSymbol + '</text>' +
            '<text x="' + (w/2) + '" y="' + (h*0.62) + '" text-anchor="middle" font-size="' + bigFontSize + '" fill="' + color + '">' + suitSymbol + '</text>' +
            '</svg>';
    }

    function cardBackHTML() {
        return '<div class="card-back">🂠</div>';
    }

    /* ===== 工具 ===== */
    function post(url, data, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            try { cb(JSON.parse(xhr.responseText)); }
            catch (e) { cb({ success: false, error: '响应解析失败' }); }
        };
        xhr.onerror = function () { cb({ success: false, error: '网络错误' }); };
        var params = [];
        for (var k in data) params.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
        xhr.send(params.join('&'));
    }

    function get(url, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.onload = function () {
            try { cb(JSON.parse(xhr.responseText)); }
            catch (e) { cb({ success: false, error: '响应解析失败' }); }
        };
        xhr.onerror = function () { cb({ success: false, error: '网络错误，请重试' }); };
        xhr.send();
    }

    function showToast(msg) {
        var t = document.getElementById('toast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 2000);
    }

    /* ===== 大厅（房间选择） ===== */
    function showLobby() {
        state.screen = 'lobby';
        document.getElementById('lobby-screen').style.display = 'flex';
        document.getElementById('waiting-screen').style.display = 'none';
        document.getElementById('game-screen').style.display = 'none';
        stopPolling();
        loadRoomList();
    }

    function loadRoomList() {
        get(API.room + '?action=list', function (res) {
            if (!res.success) return;
            var container = document.getElementById('room-list');
            container.innerHTML = '';
            if (!res.rooms || res.rooms.length === 0) {
                container.innerHTML = '<div class="room-empty">暂无房间，点击下方按钮创建</div>';
                return;
            }
            res.rooms.forEach(function (r) {
                var div = document.createElement('div');
                div.className = 'room-item';
                var statusText = r.status === 'waiting' ? '等待中' : '游戏中';
                var canJoin = r.status === 'waiting' && r.player_count < 3;
                var playersText = r.players.map(function(p) { return p.username + (p.is_ai ? '(AI)' : ''); }).join(', ');
                div.innerHTML =
                    '<div class="room-info">' +
                        '<div class="room-id">房间 ' + r.room_id.substring(5, 12) + '</div>' +
                        '<div class="room-meta">' + statusText + ' · ' + r.player_count + '/3人</div>' +
                        '<div class="room-players">' + playersText + '</div>' +
                    '</div>' +
                    '<button class="room-join-btn" ' + (canJoin ? '' : 'disabled') + ' data-room="' + r.room_id + '">' +
                        (canJoin ? '加入' : '已满') +
                    '</button>';
                container.appendChild(div);
            });
            // 绑定加入按钮
            container.querySelectorAll('.room-join-btn:not([disabled])').forEach(function (btn) {
                var handler = function () {
                    joinSpecificRoom(btn.dataset.room);
                };
                btn.addEventListener('click', handler);
                btn.addEventListener('touchend', function (e) { e.preventDefault(); handler(); });
            });
        });
    }

    function joinSpecificRoom(roomId) {
        // 直接加入指定房间（通过 join 接口，如果房间不存在会创建新的）
        // 实际上 joinOrCreate 会自动匹配，这里我们需要一个新的 join 接口
        // 简化：直接调用 join，它会加入人数最多的房间
        // 要加入指定房间，需要后端支持。这里简化为快速加入。
        joinRoom();
    }

    /* ===== 加入房间 ===== */
    function joinRoom() {
        get(API.room + '?action=join', function (res) {
            if (!res.success) { showToast(res.error); return; }
            if (!res.room) { showLobby(); return; }
            enterRoom(res.room);
        });
    }

    /* ===== 等待界面渲染 ===== */
    function renderWaiting() {
        var container = document.getElementById('waiting-players');
        if (!container) return;
        container.innerHTML = '';
        for (var i = 0; i < 3; i++) {
            var p = state.room.players[i];
            var div = document.createElement('div');
            div.className = 'waiting-player';
            if (p) {
                var isSelf = (p.user_id == state.myUserId);
                var kickBtnHtml = '';
                if (state.isOwner && !p.is_ai && !isSelf) {
                    kickBtnHtml = '<button class="kick-btn" data-user-id="' + p.user_id + '">踢出</button>';
                }
                var ownerTag = (p.user_id == state.room.owner_user_id) ? ' <span class="owner-tag">房主</span>' : '';
                div.innerHTML = '<div class="waiting-avatar">' + p.username.charAt(0).toUpperCase() + '</div>' +
                    '<div class="waiting-name">' + p.username + (p.is_ai ? ' (AI)' : '') + ownerTag + '</div>' +
                    kickBtnHtml;
            } else {
                div.innerHTML = '<div class="waiting-avatar empty">?</div><div class="waiting-name">等待中</div>';
            }
            container.appendChild(div);
        }
        // 绑定踢出按钮
        var kickBtns = container.querySelectorAll('.kick-btn');
        kickBtns.forEach(function (btn) {
            var handler = function () { kickPlayer(btn.dataset.userId); };
            btn.addEventListener('click', handler);
            btn.addEventListener('touchend', function (e) { e.preventDefault(); handler(); });
        });
        var aiBtn = document.getElementById('add-ai-btn');
        if (aiBtn) {
            if (state.isOwner && state.room.players.length === 1 && state.room.status === 'waiting') {
                aiBtn.style.display = 'inline-block';
            } else {
                aiBtn.style.display = 'none';
            }
        }
        // 退出按钮：房主显示"解散房间"，非房主在等待中显示"退出房间"，游戏中都不显示
        var backBtn = document.getElementById('back-to-lobby-btn');
        if (backBtn) {
            if (state.room.status === 'waiting') {
                backBtn.style.display = 'inline-block';
                backBtn.textContent = state.isOwner ? '← 解散房间' : '← 退出房间';
            } else {
                backBtn.style.display = 'none';
            }
        }
    }

    // 踢出玩家
    function kickPlayer(targetUserId) {
        if (!confirm('确定要踢出该玩家吗？')) return;
        post(API.room, { action: 'kick', room_id: state.roomId, target_user_id: targetUserId }, function (res) {
            if (!res.success) { showToast(res.error); return; }
            showToast('已踢出玩家');
            if (res.room) {
                state.room = res.room;
                renderWaiting();
            }
        });
    }

    /* ===== 游戏界面渲染 ===== */
    function renderGame() {
        if (!state.room) return;
        var room = state.room;

        document.getElementById('waiting-screen').style.display = 'none';
        document.getElementById('game-screen').style.display = 'flex';
        state.screen = 'game';

        // 座位映射：我永远在底部
        var displaySeats = {};
        displaySeats[state.mySeat] = 'bottom';
        var others = [];
        for (var i = 0; i < 3; i++) {
            if (i !== state.mySeat) others.push(i);
        }
        others.sort(function(a,b){return a-b;});
        displaySeats[others[0]] = 'left';
        displaySeats[others[1]] = 'right';

        var posMap = { left: '0', right: '2', bottom: '1' };

        for (var s = 0; s < 3; s++) {
            var p = room.players[s];
            if (!p) continue;
            var pos = displaySeats[s];
            var idx = posMap[pos];
            var nameEl = document.getElementById('name-' + idx);
            var roleEl = document.getElementById('role-' + idx);
            var cardsEl = document.getElementById('cards-' + idx);
            if (nameEl) nameEl.textContent = p.username + (p.is_ai ? ' (AI)' : '');
            if (roleEl) {
                if (p.is_landlord) { roleEl.textContent = '地主'; roleEl.classList.add('show'); }
                else { roleEl.classList.remove('show'); }
            }
            // 修复0张问题：优先用hand_count
            var handCount = 0;
            if (p.hand_count) handCount = p.hand_count;
            else if (p.hand && p.hand.length > 0) handCount = p.hand.length;
            if (cardsEl) cardsEl.textContent = handCount + '张';

            // 高亮当前回合
            var area = document.getElementById('player-' + idx);
            if (area) {
                if (room.current_turn === s && (room.status === 'playing' || room.status === 'calling')) {
                    area.classList.add('active');
                } else {
                    area.classList.remove('active');
                }
            }

            // 对手手牌背面
            if (pos !== 'bottom') {
                var handEl = document.getElementById('hand-' + idx);
                if (handEl) {
                    handEl.innerHTML = '';
                    for (var c = 0; c < Math.min(handCount, 17); c++) {
                        handEl.innerHTML += cardBackHTML();
                    }
                }
            }
        }

        // 底牌
        var bottomEl = document.getElementById('bottom-cards');
        if (bottomEl) {
            bottomEl.innerHTML = '';
            var showBottom = room.status === 'playing' || room.status === 'finished';
            for (var b = 0; b < 3; b++) {
                var bc = room.bottom_cards[b];
                var div = document.createElement('div');
                div.className = 'bottom-card';
                if (showBottom && bc) {
                    div.innerHTML = cardSVG(bc, true);
                    div.style.cssText = 'padding:0;background:transparent;border:none;';
                } else {
                    div.textContent = '?';
                }
                bottomEl.appendChild(div);
            }
        }

        // 出牌区
        var playCardsEl = document.getElementById('play-cards');
        var playInfoEl = document.getElementById('play-info');
        if (playCardsEl) playCardsEl.innerHTML = '';
        if (room.last_play && room.last_play.cards && room.last_play.cards.length > 0) {
            var lp = room.last_play;
            if (playCardsEl) {
                lp.cards.forEach(function (c) {
                    var span = document.createElement('span');
                    span.innerHTML = cardSVG(c, true);
                    span.style.cssText = 'width:44px;height:62px;display:inline-block;margin-left:-8px;';
                    playCardsEl.appendChild(span);
                });
            }
            if (playInfoEl) {
                var pname = room.players[lp.seat] ? room.players[lp.seat].username : '?';
                playInfoEl.textContent = pname + ' 出牌';
            }
        } else {
            if (playInfoEl) playInfoEl.textContent = room.status === 'playing' ? '自由出牌' : '';
        }

        // 我的手牌
        renderMyHand();
        updateActionButtons();

        // 叫地主界面
        if (room.status === 'calling') {
            document.getElementById('call-overlay').style.display = 'flex';
            var callBtns = document.querySelectorAll('.call-btn');
            var isMyTurn = room.current_turn === state.mySeat;
            var callStatus = document.getElementById('call-status');
            var called = [];
            room.call_scores.forEach(function (cs) {
                var nm = room.players[cs.seat] ? room.players[cs.seat].username : '?';
                called.push(nm + ': ' + (cs.score === 0 ? '不叫' : cs.score + '分'));
            });
            if (isMyTurn) {
                if (callStatus) callStatus.textContent = '轮到你叫分！' + (called.length ? '（已叫: ' + called.join(', ') + '）' : '');
                callBtns.forEach(function (btn) { btn.disabled = false; });
            } else {
                var curName = room.players[room.current_turn] ? room.players[room.current_turn].username : '?';
                if (callStatus) callStatus.textContent = '等待 ' + curName + ' 叫分...' + (called.length ? '（已叫: ' + called.join(', ') + '）' : '');
                callBtns.forEach(function (btn) { btn.disabled = true; });
            }
        } else {
            document.getElementById('call-overlay').style.display = 'none';
        }

        // 游戏结束
        if (room.status === 'finished') {
            document.getElementById('result-overlay').style.display = 'flex';
            var winner = room.winner >= 0 ? room.players[room.winner] : null;
            var myPlayer = room.players[state.mySeat];
            var myRole = myPlayer ? (myPlayer.is_landlord ? '地主' : '农民') : '';
            var winnerRole = winner ? (winner.is_landlord ? '地主' : '农民') : '';
            var myTeamWin = winner && myPlayer && (winner.is_landlord === myPlayer.is_landlord);
            document.getElementById('result-title').textContent = myTeamWin ? '🎉 你赢了！' : '😢 你输了';
            document.getElementById('result-detail').textContent =
                '你的身份: ' + myRole + ' | 获胜方: ' + winnerRole +
                (winner ? ' (' + winner.username + ')' : '') +
                ' | 地主: ' + (room.landlord >= 0 ? (room.players[room.landlord] ? room.players[room.landlord].username : '?') : '无');
            stopPolling();
        }
    }

    function renderMyHand() {
        var handEl = document.getElementById('my-hand');
        if (!handEl) return;
        handEl.innerHTML = '';
        var myPlayer = state.room.players[state.mySeat];
        if (!myPlayer || !myPlayer.hand) return;
        // 手牌排序：从大到小（point降序，suit升序）
        var sortedHand = myPlayer.hand.slice().sort(function (a, b) {
            if (a.point !== b.point) return b.point - a.point;
            return a.suit - b.suit;
        });
        sortedHand.forEach(function (card) {
            var div = document.createElement('div');
            div.className = 'my-card';
            div.dataset.id = card.id;
            if (state.selectedCards.indexOf(card.id) >= 0) div.classList.add('selected');
            div.innerHTML = cardSVG(card, false);
            // 触屏+鼠标支持
            var toggle = function (e) {
                if (e.type === 'touchend') e.preventDefault();
                toggleCard(card.id);
            };
            div.addEventListener('click', toggle);
            div.addEventListener('touchend', toggle);
            handEl.appendChild(div);
        });
    }

    function toggleCard(id) {
        var idx = state.selectedCards.indexOf(id);
        if (idx >= 0) state.selectedCards.splice(idx, 1);
        else state.selectedCards.push(id);
        renderMyHand();
    }

    function updateActionButtons() {
        var room = state.room;
        var playBtn = document.getElementById('play-btn');
        var passBtn = document.getElementById('pass-btn');
        var hintBtn = document.getElementById('hint-btn');
        if (!playBtn) return;
        var isMyTurn = room.current_turn === state.mySeat && room.status === 'playing';
        var hasLastPlay = room.last_play && room.last_play.cards && room.last_play.cards.length > 0;
        playBtn.disabled = !isMyTurn || state.selectedCards.length === 0;
        passBtn.disabled = !isMyTurn || !hasLastPlay;
        passBtn.textContent = hasLastPlay ? '要不起' : '不出';
        hintBtn.disabled = !isMyTurn;
    }

    /* ===== 轮询 ===== */
    function startPolling() {
        if (state.pollTimer) return;
        state.pollTimer = setInterval(pollStatus, 800);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function pollStatus() {
        if (!state.roomId) return;
        get(API.room + '?action=status&room_id=' + state.roomId, function (res) {
            if (!res.success || !res.room) {
                // 房间不存在或已被删除
                stopPolling();
                showRoomDisbanded();
                return;
            }
            // 房间已解散
            if (res.room.disbanded || res.room.status === 'finished') {
                stopPolling();
                if (res.room.disbanded) {
                    showRoomDisbanded();
                } else {
                    state.room = res.room;
                    renderGame();
                }
                return;
            }
            state.room = res.room;
            // 检测游戏开始：从 waiting 变为 calling/playing 时播放发牌动画
            if (state.prevStatus === 'waiting' && (res.room.status === 'calling' || res.room.status === 'playing')) {
                playDealAnimation();
            }
            state.prevStatus = res.room.status;
            // 更新当前用户身份（通过 is_self 标记）
            state.mySeat = -1;
            state.myUserId = null;
            for (var si = 0; si < res.room.players.length; si++) {
                if (res.room.players[si].is_self) {
                    state.mySeat = res.room.players[si].seat;
                    state.myUserId = res.room.players[si].user_id;
                    break;
                }
            }
            // 更新房主状态
            state.isOwner = (res.room.owner_user_id != null && state.myUserId != null && res.room.owner_user_id == state.myUserId);

            if (res.room.status === 'waiting') {
                if (state.screen !== 'waiting') {
                    document.getElementById('lobby-screen').style.display = 'none';
                    document.getElementById('waiting-screen').style.display = 'flex';
                    document.getElementById('game-screen').style.display = 'none';
                    state.screen = 'waiting';
                }
                renderWaiting();
            } else {
                renderGame();
            }

            // 触发AI行动
            if (!state.aiProcessing && res.room.status !== 'finished' && res.room.status !== 'waiting') {
                var cur = res.room.current_turn;
                if (cur >= 0 && cur < 3 && res.room.players[cur] && res.room.players[cur].is_ai) {
                    state.aiProcessing = true;
                    post(API.action, { action: 'ai_turn', room_id: state.roomId }, function () {
                        setTimeout(function () { state.aiProcessing = false; }, 600);
                    });
                }
            }
        });
    }

    // 显示房间已解散
    function showRoomDisbanded() {
        document.getElementById('lobby-screen').style.display = 'none';
        document.getElementById('waiting-screen').style.display = 'none';
        document.getElementById('game-screen').style.display = 'none';
        var overlay = document.getElementById('disbanded-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
        } else {
            showToast('房间已解散');
            state.roomId = null;
            state.room = null;
            showLobby();
        }
    }

    // 播放发牌动画
    function playDealAnimation() {
        var anim = document.getElementById('deal-animation');
        if (!anim) return;
        anim.style.display = 'flex';
        // 动画持续2.5秒后隐藏
        setTimeout(function () {
            anim.style.display = 'none';
        }, 2500);
    }

    /* ===== 操作 ===== */
    function addAI() {
        post(API.room, { action: 'add_ai', room_id: state.roomId }, function (res) {
            if (!res.success) { showToast(res.error); return; }
            showToast('AI已加入，游戏开始！');
        });
    }

    function callLandlord(score) {
        post(API.action, { action: 'call', room_id: state.roomId, score: score }, function (res) {
            if (!res.success) { showToast(res.error); return; }
            state.room = res.room;
            renderGame();
        });
    }

    function playCards() {
        if (state.selectedCards.length === 0) { showToast('请选择要出的牌'); return; }
        post(API.action, {
            action: 'play',
            room_id: state.roomId,
            cards: state.selectedCards.join(',')
        }, function (res) {
            if (!res.success) { showToast(res.error); return; }
            state.selectedCards = [];
            state.room = res.room;
            renderGame();
        });
    }

    function pass() {
        post(API.action, { action: 'play', room_id: state.roomId, cards: '' }, function (res) {
            if (!res.success) { showToast(res.error); return; }
            state.selectedCards = [];
            state.room = res.room;
            renderGame();
        });
    }

    function hint() {
        if (!state.room.last_play || !state.room.last_play.cards) {
            showToast('自由出牌，请自行选择');
            return;
        }
        var myPlayer = state.room.players[state.mySeat];
        if (!myPlayer || !myPlayer.hand) return;
        var lp = state.room.last_play;
        if (lp.cards.length === 1) {
            var lastPoint = lp.cards[0].point;
            var candidates = myPlayer.hand.filter(function (c) {
                return c.point > lastPoint && c.point < 16;
            }).sort(function (a, b) { return a.point - b.point; });
            if (candidates.length > 0) {
                state.selectedCards = [candidates[0].id];
                renderMyHand();
                return;
            }
        }
        showToast('没有能压的牌，选择不出吧');
    }

    function backToLobby() {
        if (state.isOwner && state.room && state.room.status !== 'finished') {
            if (!confirm('你是房主，退出将解散房间，确定吗？')) return;
        }
        if (state.roomId) {
            post(API.room, { action: 'leave', room_id: state.roomId }, function (res) {
                if (!res.success) { showToast(res.error); return; }
                // 房客退出：由AI接管，显示提示后返回大厅
                if (res.replaced_by_ai) {
                    showToast('你已退出，由AI接管游戏');
                    setTimeout(function () {
                        stopPolling();
                        state.roomId = null;
                        state.room = null;
                        state.selectedCards = [];
                        state.isOwner = false;
                        state.myUserId = null;
                        state.prevStatus = null;
                        showLobby();
                    }, 1500);
                    return;
                }
                // 房主退出或其他情况：直接返回大厅
                stopPolling();
                state.roomId = null;
                state.room = null;
                state.selectedCards = [];
                state.isOwner = false;
                state.myUserId = null;
                state.prevStatus = null;
                showLobby();
            });
        } else {
            showLobby();
        }
    }

    // 游戏中退出
    function exitGame() {
        var T = window.I18N ? I18N.t.bind(I18N) : function(z) { return z; };
        var msg = state.isOwner
            ? T('你是房主，退出将解散房间并结束游戏，确定退出吗？', 'You are the host. Leaving will disband the room and end the game. Are you sure?')
            : T('确定退出当前游戏吗？退出后将由AI接管继续游戏。', 'Are you sure you want to exit? An AI will take over and continue the game.');
        if (!confirm(msg)) return;
        backToLobby();
    }

    /* ===== 初始化 ===== */
    function init() {
        // 大厅按钮
        var createBtn = document.getElementById('create-room-btn');
        if (createBtn) {
            createBtn.addEventListener('click', joinRoom);
            createBtn.addEventListener('touchend', function (e) { e.preventDefault(); joinRoom(); });
        }
        var refreshBtn = document.getElementById('refresh-rooms-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadRoomList);
            refreshBtn.addEventListener('touchend', function (e) { e.preventDefault(); loadRoomList(); });
        }
        var backBtn = document.getElementById('back-to-lobby-btn');
        if (backBtn) {
            backBtn.addEventListener('click', backToLobby);
            backBtn.addEventListener('touchend', function (e) { e.preventDefault(); backToLobby(); });
        }

        // 游戏中退出按钮
        var exitBtn = document.getElementById('game-exit-btn');
        if (exitBtn) {
            exitBtn.addEventListener('click', exitGame);
            exitBtn.addEventListener('touchend', function (e) { e.preventDefault(); exitGame(); });
        }

        // AI按钮
        var aiBtn = document.getElementById('add-ai-btn');
        if (aiBtn) {
            aiBtn.addEventListener('click', addAI);
            aiBtn.addEventListener('touchend', function (e) { e.preventDefault(); addAI(); });
        }

        // 操作按钮
        var playBtn = document.getElementById('play-btn');
        var passBtn = document.getElementById('pass-btn');
        var hintBtn = document.getElementById('hint-btn');
        if (playBtn) {
            playBtn.addEventListener('click', playCards);
            playBtn.addEventListener('touchend', function (e) { e.preventDefault(); playCards(); });
        }
        if (passBtn) {
            passBtn.addEventListener('click', pass);
            passBtn.addEventListener('touchend', function (e) { e.preventDefault(); pass(); });
        }
        if (hintBtn) {
            hintBtn.addEventListener('click', hint);
            hintBtn.addEventListener('touchend', function (e) { e.preventDefault(); hint(); });
        }

        // 叫地主按钮
        document.querySelectorAll('.call-btn').forEach(function (btn) {
            var handler = function () { callLandlord(parseInt(btn.dataset.score)); };
            btn.addEventListener('click', handler);
            btn.addEventListener('touchend', function (e) { e.preventDefault(); handler(); });
        });

        // 再来一局
        var resultBtn = document.querySelector('.result-btn');
        if (resultBtn) {
            resultBtn.addEventListener('click', function () { location.reload(); });
        }

        // 房间已解散 - 返回大厅
        var disbandedBtn = document.getElementById('disbanded-back-btn');
        if (disbandedBtn) {
            disbandedBtn.addEventListener('click', function () {
                document.getElementById('disbanded-overlay').style.display = 'none';
                state.roomId = null;
                state.room = null;
                state.isOwner = false;
                showLobby();
            });
        }

        // 检查是否已在房间中（刷新重新加入）
        checkMyRoom();
    }

    // 检查用户当前所在房间，有则自动进入
    function checkMyRoom() {
        get(API.room + '?action=my_room', function (res) {
            if (res.success && res.room) {
                // 已在房间中，自动进入
                enterRoom(res.room);
            } else {
                showLobby();
            }
        });
    }

    // 进入房间（根据房间状态显示对应界面）
    function enterRoom(room) {
        state.room = room;
        state.roomId = room.room_id;
        state.mySeat = -1;
        state.myUserId = null;
        // 通过 is_self 标记识别当前用户，而不是找第一个非AI玩家
        for (var i = 0; i < room.players.length; i++) {
            if (room.players[i].is_self) {
                state.mySeat = room.players[i].seat;
                state.myUserId = room.players[i].user_id;
                break;
            }
        }
        // 兜底：如果没有 is_self 标记，找第一个非AI玩家（兼容旧数据）
        if (state.mySeat < 0) {
            for (var j = 0; j < room.players.length; j++) {
                if (!room.players[j].is_ai) {
                    state.mySeat = room.players[j].seat;
                    state.myUserId = room.players[j].user_id;
                    break;
                }
            }
        }
        state.isOwner = (room.owner_user_id != null && state.myUserId != null && room.owner_user_id == state.myUserId);
        state.prevStatus = room.status; // 初始化上一个状态

        if (room.status === 'waiting') {
            state.screen = 'waiting';
            document.getElementById('lobby-screen').style.display = 'none';
            document.getElementById('waiting-screen').style.display = 'flex';
            document.getElementById('game-screen').style.display = 'none';
            renderWaiting();
        } else if (room.status === 'calling' || room.status === 'playing') {
            state.screen = 'game';
            document.getElementById('lobby-screen').style.display = 'none';
            document.getElementById('waiting-screen').style.display = 'none';
            document.getElementById('game-screen').style.display = 'flex';
            renderGame();
        } else if (room.status === 'finished') {
            showLobby();
            return;
        }
        startPolling();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
