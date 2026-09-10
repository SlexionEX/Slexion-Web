<?php
/**
 * Slexion Account - 头像上传接口
 * POST: avatar (multipart 文件), csrf_token
 * 限制: 图片且大小 < 1MB；统一转 PNG 保存到 /usertx/{uuid}.png
 * 返回 JSON: {success, avatar, avatar_url, message}
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => '仅支持 POST 请求'], 405);
}

$user = current_user();
if (!$user) {
    json_response(['success' => false, 'error' => '请先登录']);
}

$csrf = input('csrf_token');
if (!verify_csrf($csrf)) {
    json_response(['success' => false, 'error' => 'CSRF 校验失败，请刷新页面重试']);
}

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    json_response(['success' => false, 'error' => '请选择要上传的头像']);
}

$file = $_FILES['avatar'];

// 大小限制 1MB
if ($file['size'] > 1048576) {
    json_response(['success' => false, 'error' => '头像大小不能超过 1MB']);
}
if ($file['size'] <= 0) {
    json_response(['success' => false, 'error' => '文件内容为空']);
}

// 校验真实图片类型
$info = @getimagesize($file['tmp_name']);
if ($info === false) {
    json_response(['success' => false, 'error' => '文件不是有效的图片']);
}
$allowed = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
if (!in_array($info[2], $allowed, true)) {
    json_response(['success' => false, 'error' => '仅支持 PNG / JPG / GIF / WEBP 图片']);
}

// GD 解码并转为 PNG
$src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
if ($src === false) {
    json_response(['success' => false, 'error' => '图片解码失败']);
}

// 最长边限制 512px，等比缩放（避免超大图片占用空间）
$maxSide = 512;
$w = imagesx($src);
$h = imagesy($src);
if ($w > $maxSide || $h > $maxSide) {
    $ratio = min($maxSide / $w, $maxSide / $h);
    $nw = max(1, (int)round($w * $ratio));
    $nh = max(1, (int)round($h * $ratio));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);
    $src = $dst;
}

// 生成 UUID 文件名并保存
$uuid = bin2hex(random_bytes(16));
$dir  = dirname(__DIR__) . '/usertx';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
$path = $dir . '/' . $uuid . '.png';
if (!imagepng($src, $path)) {
    imagedestroy($src);
    json_response(['success' => false, 'error' => '保存头像失败']);
}
imagedestroy($src);

// 删除旧头像文件（仅允许 usertx 目录内的 UUID 文件名，防路径穿越）
$old = $user['avatar'] ?? '';
if ($old !== '' && preg_match('/^[a-f0-9]{32}\.png$/', $old)) {
    $oldPath = $dir . '/' . $old;
    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

// 更新数据库与 Session
$stmt = db()->prepare('UPDATE users SET avatar = ? WHERE id = ?');
$stmt->execute([$uuid . '.png', $user['id']]);
$_SESSION['avatar'] = $uuid . '.png';

json_response([
    'success'   => true,
    'avatar'    => $uuid . '.png',
    'avatar_url'=> '/usertx/' . $uuid . '.png',
    'message'   => '头像已更新',
]);
