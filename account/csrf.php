<?php
/**
 * CSRF Token 生成与验证
 */
require_once __DIR__ . '/config.php';

/**
 * 生成 CSRF Token（存入 Session）
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证 CSRF Token
 */
function csrf_verify($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 输出隐藏的 CSRF input 字段（供表单使用）
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
