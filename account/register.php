<?php
/**
 * Slexion Account - 注册接口
 * POST: username, email, password, confirm_password, csrf_token
 * 返回 JSON
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/content_filter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => '仅支持 POST 请求'], 405);
}

$username       = input('username');
$email          = input('email');
$password       = input('password');
$confirmPassword = input('confirm_password');
$csrf           = input('csrf_token');

// CSRF 校验
if (!verify_csrf($csrf)) {
    json_response(['success' => false, 'error' => 'CSRF 校验失败，请刷新页面重试']);
}

// 用户名校验
if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]{3,20}$/u', $username)) {
    json_response(['success' => false, 'error' => '用户名需 3-20 位，支持中英文、数字、下划线']);
}

// 用户名敏感词检测
$filterResult = ContentFilter::check($username, 'register_username');
if (!$filterResult['pass']) {
    json_response(['success' => false, 'error' => '用户名包含违规内容：' . $filterResult['reason']]);
}

// 邮箱校验（可选，填了才校验格式和查重）
if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['success' => false, 'error' => '邮箱格式不正确']);
    }
}

// 密码校验
if (strlen($password) < 6) {
    json_response(['success' => false, 'error' => '密码至少 6 位']);
}
if ($password !== $confirmPassword) {
    json_response(['success' => false, 'error' => '两次输入的密码不一致']);
}

try {
    // 检查用户名是否已存在
    $stmt = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        json_response(['success' => false, 'error' => '用户名已被注册']);
    }

    // 检查邮箱是否已存在（仅当填写了邮箱时）
    if ($email !== '') {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            json_response(['success' => false, 'error' => '邮箱已被注册']);
        }
    }

    // 密码哈希
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // 插入用户
    $stmt = db()->prepare('INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$username, $email, $hash]);

    $userId = db()->lastInsertId();

    // 自动登录
    session_regenerate_id(true);
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['email']    = $email;
    $_SESSION['avatar']   = '';
    $_SESSION['loggedin'] = true;

    json_response([
        'success'  => true,
        'username' => $username,
        'email'    => $email,
        'avatar'   => '',
        'message'  => '注册成功，已自动登录',
    ]);

} catch (PDOException $e) {
    json_response(['success' => false, 'error' => '注册失败，请稍后重试'], 500);
}
