<?php
/**
 * 斗地主游戏入口
 * 必须登录才能玩
 */
require_once __DIR__ . '/../account/config.php';
$user = current_user();
$needLogin = !$user;
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title data-en="Dou Dizhu | Slexion">斗地主 | Slexion</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="stylesheet" href="/doudizhu/style.css?v=6">
    <style>
        /* 未登录提示弹窗样式 */
        .login-required-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.85);
            display: flex; align-items: center; justify-content: center;
            z-index: 99999;
            background-image: radial-gradient(circle at 20% 30%, rgba(0,229,255,0.15), transparent 50%),
                              radial-gradient(circle at 80% 70%, rgba(255,0,255,0.12), transparent 50%);
        }
        .login-required-box {
            background: rgba(15, 15, 30, 0.9);
            -webkit-backdrop-filter: blur(24px) saturate(180%) brightness(1.15);
            backdrop-filter: blur(24px) saturate(180%) brightness(1.15);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 24px;
            padding: 50px 60px;
            text-align: center;
            max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .login-required-icon { font-size: 56px; margin-bottom: 16px; }
        .login-required-box h1 {
            font-size: 28px; color: #fff;
            margin-bottom: 12px;
            background: linear-gradient(90deg, #00e5ff, #ff00ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .login-required-box p { color: #aaa; margin-bottom: 28px; font-size: 15px; line-height: 1.6; }
        .login-required-buttons { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .login-required-btn {
            padding: 12px 28px; border-radius: 12px; font-size: 15px;
            font-weight: bold; cursor: pointer; text-decoration: none;
            transition: transform .15s, box-shadow .15s; border: none;
            font-family: inherit;
        }
        .login-required-btn:active { transform: scale(0.96); }
        .home-btn {
            background: rgba(255,255,255,0.08);
            color: #fff; border: 1px solid rgba(255,255,255,0.2);
        }
        .home-btn:hover { background: rgba(255,255,255,0.12); }
        .login-btn {
            background: linear-gradient(90deg, #00e5ff, #ff00ff);
            color: #000;
            box-shadow: 0 4px 20px rgba(0,229,255,0.3);
        }
        .login-btn:hover { box-shadow: 0 6px 28px rgba(0,229,255,0.5); }
    </style>
</head>
<body>
<?php if ($needLogin): ?>
    <!-- 未登录提示弹窗 -->
    <div class="login-required-overlay">
        <div class="login-required-box">
            <div class="login-required-icon">🔒</div>
            <h1 data-en="Login Required">需要登录</h1>
            <p data-en="You need to login with Slexion Account to play Dou Dizhu">斗地主需要登录 Slexion Account 才能游玩</p>
            <div class="login-required-buttons">
                <a href="/home" class="login-required-btn home-btn" data-en="Back to Home">返回主页</a>
                <button class="login-required-btn login-btn" onclick="window.location.href='/home?login=1'" data-en="Login">去登录</button>
            </div>
        </div>
    </div>
</body>
</html>
<?php exit; endif; ?>
    <div class="game-bg"></div>

    <!-- 大厅（房间选择） -->
    <div id="lobby-screen" class="screen">
        <div class="lobby-card">
            <h1 class="game-title" data-en="🃏 Dou Dizhu">🃏 斗地主</h1>
            <p class="lobby-sub" data-en="Select a room or create a new one">选择房间或创建新房间</p>
            <div class="lobby-actions">
                <button id="create-room-btn" class="create-btn" data-en="🚀 Quick Join / Create Room">🚀 快速加入 / 创建房间</button>
                <button id="refresh-rooms-btn" class="refresh-btn" data-en="🔄 Refresh List">🔄 刷新列表</button>
            </div>
            <div id="room-list" class="room-list">
                <div class="room-empty" data-en="Loading...">加载中...</div>
            </div>
        </div>
    </div>

    <!-- 等待界面 -->
    <div id="waiting-screen" class="screen" style="display:none;">
        <div class="waiting-card">
            <button id="back-to-lobby-btn" class="back-btn" style="display:none;" data-en="← Disband Room">← 解散房间</button>
            <h1 class="game-title" data-en="🃏 Dou Dizhu">🃏 斗地主</h1>
            <p class="waiting-sub" data-en="Matching players...">正在匹配玩家...</p>
            <div id="waiting-players" class="waiting-players"></div>
            <button id="add-ai-btn" class="ai-btn" style="display:none;" data-en="🤖 Add 2 AI to Start">🤖 添加2个AI开始游戏</button>
            <p class="waiting-tip" data-en="Game starts when 3 players join, or click above to add AI">房间满3人自动开始，或点击上方按钮让AI加入</p>
        </div>
    </div>

    <!-- 游戏界面 -->
    <div id="game-screen" class="screen" style="display:none;">
        <!-- 退出按钮 -->
        <button id="game-exit-btn" class="game-exit-btn" data-en="✕ Exit">✕ 退出</button>
        <!-- 左边玩家 -->
        <div class="player-area player-left" id="player-0">
            <div class="player-info">
                <span class="player-name" id="name-0" data-en="Player 1">玩家1</span>
                <span class="player-role" id="role-0"></span>
                <span class="player-cards" id="cards-0">17张</span>
            </div>
            <div class="player-hand-back" id="hand-0"></div>
        </div>

        <!-- 右边玩家 -->
        <div class="player-area player-right" id="player-2">
            <div class="player-info">
                <span class="player-name" id="name-2" data-en="Player 3">玩家3</span>
                <span class="player-role" id="role-2"></span>
                <span class="player-cards" id="cards-2">17张</span>
            </div>
            <div class="player-hand-back" id="hand-2"></div>
        </div>

        <!-- 中间桌子 -->
        <div class="table-area">
            <div class="bottom-cards" id="bottom-cards"></div>
            <div class="play-area" id="play-area">
                <div class="play-cards" id="play-cards"></div>
                <div class="play-info" id="play-info"></div>
            </div>
            <div class="table-ellipse"></div>
        </div>

        <!-- 底部（我） -->
        <div class="player-area player-bottom" id="player-1">
            <div class="my-hand" id="my-hand"></div>
            <div class="player-info-bottom" id="player-info-bottom">
                <span class="player-name" id="name-1" data-en="Me">我</span>
                <span class="player-role" id="role-1"></span>
                <span class="player-cards" id="cards-1">17张</span>
            </div>
            <div class="action-bar" id="action-bar">
                <button id="pass-btn" class="action-btn pass-btn" data-en="Pass">不出</button>
                <button id="play-btn" class="action-btn play-btn" data-en="Play">出牌</button>
                <button id="hint-btn" class="action-btn hint-btn" data-en="Hint">提示</button>
            </div>
        </div>

        <!-- 叫地主 -->
        <div id="call-overlay" class="call-overlay" style="display:none;">
            <div class="call-box">
                <h2 data-en="Call Landlord">叫地主</h2>
                <p id="call-status" data-en="Waiting for other players to bid...">等待其他玩家叫分...</p>
                <div class="call-buttons" id="call-buttons">
                    <button class="call-btn" data-score="0" data-en="Pass">不叫</button>
                    <button class="call-btn" data-score="1" data-en="1 Point">1分</button>
                    <button class="call-btn" data-score="2" data-en="2 Points">2分</button>
                    <button class="call-btn" data-score="3" data-en="3 Points">3分</button>
                </div>
            </div>
        </div>

        <!-- 游戏结束 -->
        <div id="result-overlay" class="result-overlay" style="display:none;">
            <div class="result-box">
                <h1 id="result-title" data-en="Game Over">游戏结束</h1>
                <p id="result-detail"></p>
                <button class="result-btn" onclick="location.reload()" data-en="Play Again">再来一局</button>
            </div>
        </div>

        <div id="toast" class="toast"></div>
    </div>

    <!-- 房间已解散（移到game-screen外面，避免被隐藏） -->
    <div id="disbanded-overlay" class="disbanded-overlay" style="display:none;">
        <div class="disbanded-box">
            <h1 data-en="Room Disbanded">房间已解散</h1>
            <p data-en="Host left or not enough players">房主已退出房间或人数不足</p>
            <button id="disbanded-back-btn" class="disbanded-btn" data-en="Back to Lobby">返回大厅</button>
        </div>
    </div>

    <!-- 发牌动画 -->
    <div id="deal-animation" class="deal-animation" style="display:none;">
        <div class="deal-card deal-card-0"></div>
        <div class="deal-card deal-card-1"></div>
        <div class="deal-card deal-card-2"></div>
        <div class="deal-text" data-en="Dealing...">发牌中...</div>
    </div>

    <script src="/navbar.js"></script>
    <script src="/i18n.js"></script>
    <script src="/account.js"></script>
    <script src="/doudizhu/game.js?v=6"></script>
</body>
</html>
