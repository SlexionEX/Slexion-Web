<?php
/**
 * Slexion Account - 当前登录状态查询
 * GET 返回 JSON: {loggedin, username, email, csrf_token}
 */
require_once __DIR__ . '/config.php';

$user = current_user();

json_response([
    'loggedin'   => $user !== null,
    'username'   => $user['username'] ?? '',
    'email'      => $user['email'] ?? '',
    'avatar'     => $user['avatar'] ?? '',
    'csrf_token' => csrf_token(),
]);
