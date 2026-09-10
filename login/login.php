<?php
/**
 * Slexion Account - 独立登录页
 * 连接 MySQL 验证，支持跳转注册
 */
require_once __DIR__ . '/../account/config.php';
require_once __DIR__ . '/../account/csrf.php';

// 已登录则跳转到主页
if (is_logged_in()) {
    header('Location: /home/home.html');
    exit;
}

$msg = '';
$msgType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!csrf_verify($csrfToken)) {
        $msg = '安全校验失败，请刷新页面重试';
    } elseif ($username === '' || $password === '') {
        $msg = '用户名和密码不能为空';
    } else {
        $ip = get_client_ip();

        // 检查锁定
        $lockRemaining = get_lock_remaining($ip, $username);
        if ($lockRemaining > 0) {
            $minutes = ceil($lockRemaining / 60);
            $msg = "登录失败次数过多，请 {$minutes} 分钟后再试";
            $msgType = 'warn';
        } else {
            // 查询用户
            $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $attempts = record_failed_attempt($ip, $username);
                $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
                if ($remaining <= 0) {
                    $msg = '登录失败次数过多，账号已锁定 ' . LOCK_DURATION_MINUTES . ' 分钟';
                    $msgType = 'warn';
                } else {
                    $msg = "用户名或密码错误，还可尝试 {$remaining} 次";
                }
            } else {
                // 登录成功
                session_regenerate_id(true);
                clear_login_attempts($ip, $username);
                $stmt = db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $stmt->execute([$user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['email_verified'] = !empty($user['email_verified']);
                $_SESSION['login_time'] = time();

                header('Location: /home/home.html');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title data-en="LOGIN :: SLEXION">登录 :: SLEXION</title>
<style>
:root{--cyan:#00eeff;--magenta:#ff00c8;--dark:#0a0a12;}
*{box-sizing:border-box;margin:0;padding:0;}
body{
    min-height:100vh;display:flex;align-items:center;justify-content:center;
    background:#000;color:#fff;font-family:'JetBrains Mono',monospace;
    background-image:
        linear-gradient(0deg,rgba(0,238,255,0.08) 1px,transparent 1px),
        linear-gradient(90deg,rgba(0,238,255,0.08) 1px,transparent 1px);
    background-size:20px 20px;position:relative;
}
.blobs{position:fixed;inset:-10%;z-index:0;pointer-events:none;
    background:radial-gradient(circle at 20% 25%,rgba(0,238,255,.30),transparent 45%),
        radial-gradient(circle at 80% 30%,rgba(255,0,200,.26),transparent 45%),
        radial-gradient(circle at 50% 85%,rgba(60,110,255,.30),transparent 50%);
    filter:blur(24px);animation:blobFloat 16s ease-in-out infinite alternate;}
@keyframes blobFloat{0%{transform:translate(0,0) scale(1);}100%{transform:translate(3%,2%) scale(1.06);}}
.cyber-box{
    width:360px;max-width:92vw;position:relative;z-index:1;
    background:rgba(255,255,255,.05);
    -webkit-backdrop-filter:blur(18px) saturate(180%) brightness(1.12);
    backdrop-filter:blur(18px) saturate(180%) brightness(1.12);
    padding:32px 28px;border:1px solid rgba(255,255,255,.16);border-radius:28px;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.28),0 0 15px rgba(0,238,255,.3),0 0 30px rgba(255,0,200,.15),0 12px 32px rgba(0,0,0,.4);
}
.cyber-box::before{content:"";position:absolute;top:0;left:0;right:0;height:45%;
    background:linear-gradient(180deg,rgba(255,255,255,.09),transparent);pointer-events:none;border-radius:28px 28px 0 0;}
h2{text-align:center;color:var(--cyan);text-shadow:0 0 5px var(--cyan),0 0 10px var(--magenta);margin-bottom:24px;font-size:20px;}
.msg{text-align:center;margin:12px 0;padding:10px;border-radius:10px;font-size:13px;}
.msg.error{background:rgba(255,80,80,.12);color:#ff6b6b;border:1px solid rgba(255,80,80,.25);}
.msg.warn{background:rgba(255,180,0,.1);color:#ffb400;border:1px solid rgba(255,180,0,.25);}
input{width:100%;padding:12px 16px;margin:8px 0;background:#000;border:1px solid var(--cyan);
    color:#fff;font-family:monospace;font-size:14px;border-radius:10px;
    box-shadow:0 0 8px rgba(0,238,255,.4);transition:border-color .2s,box-shadow .2s;}
input:focus{outline:none;border-color:var(--magenta);box-shadow:0 0 12px rgba(255,0,200,.6);}
input::placeholder{color:#555;}
button{width:100%;padding:13px;margin-top:12px;
    background:linear-gradient(90deg,var(--cyan),var(--magenta));
    border:none;color:#000;font-weight:bold;font-family:monospace;font-size:15px;
    cursor:pointer;border-radius:10px;transition:transform .15s,box-shadow .2s;
    box-shadow:0 4px 16px rgba(0,238,255,.3);}
button:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(255,0,200,.4);}
.register-link{text-align:center;margin-top:16px;font-size:13px;color:#888;}
.register-link a{color:var(--cyan);text-decoration:none;}
.register-link a:hover{text-decoration:underline;}
.back-home{text-align:center;margin-top:10px;font-size:12px;}
.back-home a{color:#666;text-decoration:none;}
.back-home a:hover{color:var(--cyan);}
</style>
</head>
<body>
<div class="blobs"></div>
<div class="cyber-box">
    <h2 data-en="USER AUTHENTICATION">用户认证</h2>
    <?php if ($msg): ?><div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="text" name="username" placeholder="USER ID" data-en-ph="USER ID" required autofocus>
        <input type="password" name="password" placeholder="PASSWORD" data-en-ph="PASSWORD" required>
        <button type="submit" data-en="CONNECT">登 录</button>
    </form>
    <div class="register-link">
        <span data-en="No account?">还没有账号？</span>
        <a href="/home?auth=register" data-en="Register now">立即注册</a>
    </div>
    <div class="back-home">
        <a href="/home" data-en="← Back to home">← 返回主页</a>
    </div>
</div>
<script src="/navbar.js"></script>
<script src="/account/auth.js"></script>
<script src="/i18n.js"></script>
<script src="/account.js"></script>
<script>
// 如果 URL 带 auth=register 参数，自动打开注册弹窗
if (window.location.search.indexOf('auth=register') > -1) {
    setTimeout(function(){ if(window.SlexionAuth) window.SlexionAuth.open('register'); }, 300);
}
</script>
</body>
</html>
