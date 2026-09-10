<?php
/**
 * Slexion 反馈提交后端
 * POST: name(可选), content
 * 返回 JSON
 * 敏感词检测通过后存入 feedback_log.json 并发送邮件通知管理员
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../lib/content_filter.php';
require_once __DIR__ . '/../lib/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '仅支持 POST 请求']);
    exit;
}

$name    = trim($_POST['name'] ?? '');
$content = trim($_POST['content'] ?? '');
$ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// IP频率限制：每IP 10分钟最多10条反馈
$rateLimitFile = __DIR__ . '/feedback_ratelimit.json';
$rateLimits = [];
if (file_exists($rateLimitFile)) {
    $raw = file_get_contents($rateLimitFile);
    $rateLimits = json_decode($raw, true);
    if (!is_array($rateLimits)) {
        $rateLimits = [];
    }
}

// 清理超过10分钟的记录
$tenMinutesAgo = time() - 600;
if (isset($rateLimits[$ip])) {
    $rateLimits[$ip] = array_filter($rateLimits[$ip], function ($t) use ($tenMinutesAgo) {
        return $t > $tenMinutesAgo;
    });
}

// 检查是否超过限制
if (isset($rateLimits[$ip]) && count($rateLimits[$ip]) >= 10) {
    echo json_encode([
        'success' => false,
        'error' => '提交过于频繁，请10分钟后再试（每IP 10分钟最多10条）',
    ]);
    exit;
}

// 记录本次提交时间
if (!isset($rateLimits[$ip])) {
    $rateLimits[$ip] = [];
}
$rateLimits[$ip][] = time();

// 清理超过10分钟的记录并保存
foreach ($rateLimits as $key => $times) {
    $rateLimits[$key] = array_filter($times, function ($t) use ($tenMinutesAgo) {
        return $t > $tenMinutesAgo;
    });
    if (empty($rateLimits[$key])) {
        unset($rateLimits[$key]);
    }
}
file_put_contents(
    $rateLimitFile,
    json_encode($rateLimits, JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

// 内容非空校验
if ($content === '') {
    echo json_encode(['success' => false, 'error' => '请输入反馈内容']);
    exit;
}

// 长度限制
if (mb_strlen($content) > 2000) {
    echo json_encode(['success' => false, 'error' => '反馈内容不能超过 2000 字']);
    exit;
}

// 敏感词检测（反馈内容）
$filterResult = ContentFilter::check($content, 'feedback');
if (!$filterResult['pass']) {
    echo json_encode([
        'success' => false,
        'error'   => '反馈内容包含违规内容：' . $filterResult['reason'],
        'label'   => $filterResult['label'],
    ]);
    exit;
}

// 敏感词检测（昵称，非空时检测）
if ($name !== '') {
    if (mb_strlen($name) > 50) {
        echo json_encode(['success' => false, 'error' => '昵称不能超过 50 字']);
        exit;
    }
    $nameFilter = ContentFilter::check($name, 'feedback_name');
    if (!$nameFilter['pass']) {
        echo json_encode(['success' => false, 'error' => '昵称包含违规内容']);
        exit;
    }
}

// 存入日志文件
$logFile = __DIR__ . '/feedback_log.json';
$feedbacks = [];
if (file_exists($logFile)) {
    $raw = file_get_contents($logFile);
    $feedbacks = json_decode($raw, true);
    if (!is_array($feedbacks)) {
        $feedbacks = [];
    }
}

$feedbacks[] = [
    'id'      => uniqid('fb_', true),
    'name'    => $name !== '' ? $name : '匿名',
    'content' => $content,
    'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
    'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'time'    => date('Y-m-d H:i:s'),
];

// 只保留最近 500 条，避免文件过大
if (count($feedbacks) > 500) {
    $feedbacks = array_slice($feedbacks, -500);
}

$written = file_put_contents(
    $logFile,
    json_encode($feedbacks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

if ($written === false) {
    echo json_encode(['success' => false, 'error' => '保存失败，请稍后重试']);
    exit;
}

// 发送邮件通知管理员（异步，忽略结果）
try {
    @Mailer::sendFeedbackNotification(
        $name !== '' ? $name : '匿名',
        $content,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    );
} catch (Exception $e) {
    // 邮件发送失败不影响反馈提交
}

echo json_encode([
    'success' => true,
    'message' => '反馈已提交，感谢你的反馈！',
]);
