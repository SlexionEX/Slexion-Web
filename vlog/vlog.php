<?php
// 接入 Slexion Account 系统（session + current_user）
require_once __DIR__ . '/../account/config.php';

$isLogin = isset($_SESSION['user']);
$username = $isLogin ? $_SESSION['user']['username'] : '';
$host = "localhost";
$dbname = "slex_vlog";
$user = "slex_vlog";
$pass = "114514";
$ADMIN_PASSWORD = "Slexion1145DEL";

// 当前登录的 Slexion Account 用户
$acctUser = current_user();
$isAcctLoggedIn = $acctUser !== null;

// 同步登录（vlog 原有管理员逻辑）
$isAdmin = false;
if (isset($_COOKIE['slexion_user'])) {
    $u = $_COOKIE['slexion_user'];
    if (preg_match('/^.+#Slexion\$ZH$/', $u)) {
        $isAdmin = true;
        $_SESSION['admin'] = true;
    }
}
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) $isAdmin = true;

// 数据库连接（表情支持）
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("数据库连接失败");
}

// 强制建表 + 强制改表 支持表情😀
$pdo->exec("CREATE TABLE IF NOT EXISTS vlogs (id INT AUTO_INCREMENT PRIMARY KEY, content TEXT NOT NULL, ip VARCHAR(45) DEFAULT '', user_id INT DEFAULT 0, username VARCHAR(50) DEFAULT '', create_time DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("ALTER TABLE vlogs MODIFY content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("ALTER TABLE vlogs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
// 确保 ip 列存在
$ipCol = $pdo->query("SHOW COLUMNS FROM vlogs LIKE 'ip'")->fetchAll();
if (empty($ipCol)) {
    $pdo->exec("ALTER TABLE vlogs ADD COLUMN ip VARCHAR(45) DEFAULT '' AFTER content");
}
// 确保 user_id 列存在
$userIdCol = $pdo->query("SHOW COLUMNS FROM vlogs LIKE 'user_id'")->fetchAll();
if (empty($userIdCol)) {
    $pdo->exec("ALTER TABLE vlogs ADD COLUMN user_id INT DEFAULT 0 AFTER ip");
}
// 确保 username 列存在
$usernameCol = $pdo->query("SHOW COLUMNS FROM vlogs LIKE 'username'")->fetchAll();
if (empty($usernameCol)) {
    $pdo->exec("ALTER TABLE vlogs ADD COLUMN username VARCHAR(50) DEFAULT '' AFTER user_id");
}

// 防刷屏配置：同一IP 10分钟内最多发布10条
define('VLOG_RATE_LIMIT', 10);
define('VLOG_RATE_WINDOW', 10);

if ($_POST) {
    if ($_POST['action'] === 'logout') {
        session_destroy();
        setcookie("slexion_user", "", time() - 3600);
        header("Location: /vlog");
        exit;
    }

    if ($_POST['action'] === 'post') {
        // 必须登录才能发布
        if (!$isAcctLoggedIn) {
            $_SESSION['vlog_error'] = 'not_logged_in';
            header("Location: /vlog");
            exit;
        }

        $content = trim($_POST['content'] ?? '');
        if ($content && mb_strlen($content) <= 500) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // 防刷屏：检查同一IP最近10分钟发布数量
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vlogs WHERE ip = ? AND create_time > DATE_SUB(NOW(), INTERVAL " . VLOG_RATE_WINDOW . " MINUTE)");
            $stmt->execute([$ip]);
            $recentCount = (int)$stmt->fetchColumn();

            if ($recentCount >= VLOG_RATE_LIMIT) {
                $_SESSION['vlog_error'] = 'rate_limited';
                header("Location: /vlog");
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO vlogs (content, ip, user_id, username) VALUES (?, ?, ?, ?)");
            $stmt->execute([$content, $ip, $acctUser['id'], $acctUser['username']]);
        }
        header("Location: /vlog");
        exit;
    }

    if ($_POST['action'] === 'del' && $isAdmin) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM vlogs WHERE id=?");
        $stmt->execute([$id]);
        header("Location: /vlog");
        exit;
    }
}

// 错误消息中英文映射
$VLOG_ERRORS = [
    'not_logged_in' => ['zh' => '请先登录 Slexion Account 后再发布 VLOG', 'en' => 'Please log in to Slexion Account before posting VLOG'],
    'rate_limited'  => ['zh' => '发布过于频繁，同一IP 10分钟内最多发布10条，请稍后再试', 'en' => 'Rate limit exceeded: max 10 posts per 10 minutes from the same IP, please try again later'],
];

// 读取错误提示
$vlogError = '';
$vlogErrorEn = '';
if (!empty($_SESSION['vlog_error'])) {
    $errKey = $_SESSION['vlog_error'];
    if (isset($VLOG_ERRORS[$errKey])) {
        $vlogError = $VLOG_ERRORS[$errKey]['zh'];
        $vlogErrorEn = $VLOG_ERRORS[$errKey]['en'];
    } else {
        $vlogError = $errKey;
    }
    unset($_SESSION['vlog_error']);
}

$list = $pdo->query("SELECT * FROM vlogs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>VLOG | Slexion</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#050118 url("/pic/cyberbg.png") no-repeat center center fixed;background-size:cover;color:#e0e0e0;font-family:sans-serif;position:relative}
        /* ===== Liquid Glass 背景彩色光晕 ===== */
        .blobs{position:fixed;inset:-10%;z-index:0;pointer-events:none;background:
            radial-gradient(circle at 15% 20%, rgba(0,255,255,0.30), transparent 42%),
            radial-gradient(circle at 85% 25%, rgba(255,0,255,0.26), transparent 42%),
            radial-gradient(circle at 50% 85%, rgba(60,110,255,0.32), transparent 45%),
            radial-gradient(circle at 90% 80%, rgba(140,60,255,0.24), transparent 40%);
            filter:blur(22px);animation:blobFloat 16s ease-in-out infinite alternate}
        @keyframes blobFloat{0%{transform:translate(0,0) scale(1);}100%{transform:translate(3%,2%) scale(1.06);}}
        .container{max-width:900px;margin:20px auto 40px;padding:0 20px}
        .cyber-title{font-size:32px;color:#0ff;text-shadow:0 0 10px #0ff}
        .glitch-line{height:3px;background:#f0f;margin:15px 0}
        .share-box{background:rgba(255,255,255,0.05);-webkit-backdrop-filter:blur(18px) saturate(180%) brightness(1.12);backdrop-filter:blur(18px) saturate(180%) brightness(1.12);border:1px solid rgba(255,255,255,0.14);border-left:4px solid #0ff;box-shadow:inset 0 1px 0 rgba(255,255,255,0.28),0 12px 32px rgba(0,0,0,0.40);padding:25px;margin-bottom:30px;border-radius:16px;position:relative;overflow:hidden}
        .share-box::before,.vlog-item::before{content:"";position:absolute;top:0;left:0;right:0;height:45%;background:linear-gradient(180deg,rgba(255,255,255,0.09),transparent);pointer-events:none}
        .vlog-textarea{width:100%;height:140px;background:rgba(255,255,255,0.06);border:2px solid rgba(0,255,255,0.5);color:#fff;padding:12px;resize:none;border-radius:8px}
        .vlog-btn{background:#0ff;color:#000;padding:12px 25px;border:none;cursor:pointer;margin-top:10px;border-radius:8px}
        .vlog-item{background:rgba(255,255,255,0.05);-webkit-backdrop-filter:blur(18px) saturate(180%) brightness(1.12);backdrop-filter:blur(18px) saturate(180%) brightness(1.12);border:1px solid rgba(255,255,255,0.14);border-left:4px solid #f0f;box-shadow:inset 0 1px 0 rgba(255,255,255,0.28),0 12px 32px rgba(0,0,0,0.40);padding:20px;margin:15px 0;position:relative;overflow:hidden;border-radius:16px}
        .vlog-item-time{color:#aaa;font-size:14px;margin-bottom:10px}
        .vlog-item-author{color:#0ff;font-size:13px;margin-bottom:8px;font-weight:bold}
        .vlog-item-author::before{content:"@ "}
        .admin-del{position:absolute;right:20px;top:20px;background:#ff3366;color:#fff;border:none;padding:6px 10px;font-size:12px;cursor:pointer}
        .vlog-error{background:rgba(255,51,102,0.15);border:1px solid #ff3366;color:#ff6688;padding:12px 16px;border-radius:8px;margin-bottom:15px;font-size:14px}
        .login-prompt{text-align:center;padding:30px 20px}
        .login-prompt p{color:#aaa;margin-bottom:15px;font-size:15px}
        .login-prompt-btn{background:linear-gradient(90deg,#0ff,#f0f);color:#000;padding:12px 30px;border:none;cursor:pointer;font-weight:bold;font-size:15px;border-radius:8px;box-shadow:0 0 15px rgba(0,255,255,0.4)}
        .login-prompt-btn:hover{box-shadow:0 0 25px rgba(0,255,255,0.6),0 0 35px rgba(255,0,255,0.4)}
    </style>
</head>
<body>
<div class="blobs"></div>
<div class="container">
    <h1 class="cyber-title" data-en="VLOG Center">VLOG 中心</h1><div class="glitch-line"></div>
    <?php if($isAdmin): ?>
    <div style="margin-bottom:15px">
        <span style="color:#0ff" data-en="✅ Admin logged in">✅ 已登录管理员</span>
        <form method="POST" style="display:inline;margin-left:10px">
            <button style="background:#ff3366;color:#fff;border:none;padding:6px 10px;cursor:pointer" type="submit" data-en="Logout">登出</button>
            <input type="hidden" name="action" value="logout">
        </form>
    </div>
    <?php endif; ?>

    <?php if($vlogError): ?>
    <div class="vlog-error" data-en="<?=htmlspecialchars($vlogErrorEn)?>"><?=htmlspecialchars($vlogError)?></div>
    <?php endif; ?>

    <?php if($isAcctLoggedIn): ?>
    <!-- 已登录：显示发布框 -->
    <div class="share-box">
        <div style="margin-bottom:10px;color:#0ff;font-size:14px">
            <span data-en="Logged in as">当前登录：</span><strong><?=htmlspecialchars($acctUser['username'])?></strong>
        </div>
        <form method="POST">
            <textarea name="content" class="vlog-textarea" maxlength="500" placeholder="分享你的VLOG..." data-en-ph="Share your VLOG..."></textarea>
            <input type="hidden" name="action" value="post">
            <button class="vlog-btn" type="submit" data-en="Post VLOG">发布 VLOG</button>
        </form>
    </div>
    <?php else: ?>
    <!-- 未登录：显示登录提示 -->
    <div class="share-box">
        <div class="login-prompt">
            <p data-en="Please log in to post VLOG">请先登录 Slexion Account 后才能发布 VLOG</p>
            <button class="login-prompt-btn" onclick="document.getElementById('acct-btn').click()" data-en="Login / Register">登录 / 注册</button>
        </div>
    </div>
    <?php endif; ?>

    <h2 class="cyber-title" style="font-size:24px" data-en="Latest Updates">最新动态</h2><div class="glitch-line"></div>
    <?php if(empty($list)): ?>
        <div style="text-align:center;padding:30px" data-en="No VLOG yet">暂无 VLOG</div>
    <?php else: ?>
        <?php foreach($list as $item): ?>
        <div class="vlog-item">
            <div class="vlog-item-time"><?=$item['create_time']?></div>
            <?php if(!empty($item['username'])): ?>
            <div class="vlog-item-author"><?=htmlspecialchars($item['username'])?></div>
            <?php endif; ?>
            <div class="vlog-item-content"><?=htmlspecialchars($item['content'])?></div>
            <?php if($isAdmin): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm(window.I18N ? I18N.t('确定删除？','Confirm delete?') : '确定删除？')">
                <button class="admin-del" type="submit">Administrator DELETE</button>
                <input type="hidden" name="action" value="del">
                <input type="hidden" name="id" value="<?=$item['id']?>">
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
const appleSvg = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin:0 2px;">
<path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
</svg>`;

function replaceAppleLogo(el) {
    const nodes = Array.from(el.childNodes);
    for (const node of nodes) {
        if (node.nodeType === Node.TEXT_NODE) {
            if (node.textContent.includes('\uF8FF')) {
                const parts = node.textContent.split('\uF8FF');
                const frag = document.createDocumentFragment();
                for (let i = 0; i < parts.length; i++) {
                    frag.append(document.createTextNode(parts[i]));
                    if (i !== parts.length - 1) {
                        const span = document.createElement('span');
                        span.innerHTML = appleSvg;
                        frag.append(span);
                    }
                }
                node.replaceWith(frag);
            }
        } else {
            const tag = node.tagName?.toLowerCase();
            if (!['textarea','input'].includes(tag)) {
                replaceAppleLogo(node);
            }
        }
    }
}

window.addEventListener('DOMContentLoaded', () => {
    replaceAppleLogo(document.body);
});
</script>
<script src="/navbar.js"></script>
<script src="/account/auth.js"></script>
<script src="/i18n.js"></script>
<script src="/account.js"></script>
</body>
</html>
