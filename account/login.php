<?php
/**
 * Slexion Account - 登录接口
 * POST: username(或email), password, csrf_token
 * 返回 JSON
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => '仅支持 POST 请求'], 405);
}

$identifier = input('username'); // 支持用户名或邮箱登录
$password   = input('password');
$csrf       = input('csrf_token');

// CSRF 校验
if (!verify_csrf($csrf)) {
    json_response(['success' => false, 'error' => 'CSRF 校验失败，请刷新页面重试']);
}

// 基本校验
if ($identifier === '' || $password === '') {
    json_response(['success' => false, 'error' => '请输入账号和密码']);
}

try {
    // 按用户名或邮箱查找用户
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(['success' => false, 'error' => '账号或密码错误']);
    }

    // 检查是否被锁定
    if (!empty($user['lock_until']) && strtotime($user['lock_until']) > time()) {
        $remaining = strtotime($user['lock_until']) - time();
        $minutes = ceil($remaining / 60);
        json_response(['success' => false, 'error' => "账号已被锁定，请 {$minutes} 分钟后再试"]);
    }

    // 验证密码
    if (!password_verify($password, $user['password'])) {
        // 失败次数 +1
        $failed = (int)$user['failed_attempts'] + 1;
        $lockUntil = null;
        $msg = '账号或密码错误';

        if ($failed >= MAX_FAILED_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOCK_DURATION_SECONDS);
            $msg = '连续失败 ' . MAX_FAILED_ATTEMPTS . ' 次，账号已锁定 3 分钟';
            $failed = 0; // 锁定后重置计数，解锁后重新计算
        }

        $upd = db()->prepare('UPDATE users SET failed_attempts = ?, lock_until = ? WHERE id = ?');
        $upd->execute([$failed, $lockUntil, $user['id']]);

        json_response(['success' => false, 'error' => $msg]);
    }

    // 登录成功：重置失败计数、更新最后登录时间、写 Session
    $upd = db()->prepare('UPDATE users SET failed_attempts = 0, lock_until = NULL, last_login = NOW() WHERE id = ?');
    $upd->execute([$user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['avatar']   = $user['avatar'] ?? '';
    $_SESSION['loggedin'] = true;

    json_response([
        'success'  => true,
        'username' => $user['username'],
        'email'    => $user['email'],
        'avatar'   => $user['avatar'] ?? '',
        'message'  => '登录成功',
    ]);

} catch (PDOException $e) {
    json_response(['success' => false, 'error' => '服务器错误，请稍后重试'], 500);
}
