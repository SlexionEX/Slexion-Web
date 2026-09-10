<?php
/**
 * Slexion 统一内容过滤层
 * 基于腾讯云文本内容安全（TMS）API
 *
 * 使用前请配置下方 TMS_SECRET_ID / TMS_SECRET_KEY，并将 TMS_ENABLED 设为 true
 * 密钥获取：腾讯云控制台 → 访问管理 → API 密钥管理
 * 开通服务：腾讯云 → 内容安全 → 文本内容安全（新用户 3000 条免费/15天）
 */

// ==================== 配置区 ====================
define('TMS_ENABLED', false);              // 改为 true 启用检测
define('TMS_SECRET_ID', 'YOUR_SECRET_ID'); // 你的 SecretId
define('TMS_SECRET_KEY', 'YOUR_SECRET_KEY'); // 你的 SecretKey
define('TMS_REGION', 'ap-guangzhou');       // 地域，默认广州
// =================================================

require_once __DIR__ . '/tms_client.php';

class ContentFilter
{
    private static $tms = null;

    /** 标签中文映射 */
    private static $labelMap = [
        'Normal'      => '正常',
        'Porn'        => '色情',
        'Ads'         => '广告',
        'Illegal'     => '违法',
        'Abuse'       => '辱骂',
        'Terrorism'   => '暴恐',
        'Contraband'  => '违禁',
        'Politics'    => '涉政',
        'Spam'        => '垃圾信息',
        'Risk'        => '风险',
    ];

    private static function getTMS()
    {
        if (self::$tms === null) {
            self::$tms = new TMSClient(TMS_SECRET_ID, TMS_SECRET_KEY, TMS_REGION);
        }
        return self::$tms;
    }

    /**
     * 检测文本是否安全
     *
     * @param string $text  待检测文本
     * @param string $scene 场景标识（用于日志，如 register_username / feedback）
     * @return array {
     *   pass: bool,           // 是否通过
     *   reason: string,       // 不通过的原因（中文）
     *   label: string,        // 风险标签
     *   score: int,           // 风险分数 0-100
     *   keywords: array,      // 命中的关键词
     *   suggestion: string,   // 腾讯云建议 Pass/Review/Block
     *   skipped: bool,        // 是否跳过了检测（未启用或API失败）
     * }
     */
    public static function check($text, $scene = 'default')
    {
        $text = trim($text);

        // 空文本直接通过
        if ($text === '') {
            return self::passResult('空文本');
        }

        // 未启用 TMS，直接通过（记录日志提醒）
        if (!TMS_ENABLED) {
            error_log('[ContentFilter] TMS 未启用，跳过检测 | 场景: ' . $scene . ' | 文本: ' . mb_substr($text, 0, 50));
            $r = self::passResult('TMS未启用');
            $r['skipped'] = true;
            return $r;
        }

        // 密钥未配置
        if (TMS_SECRET_ID === 'YOUR_SECRET_ID' || TMS_SECRET_KEY === 'YOUR_SECRET_KEY') {
            error_log('[ContentFilter] TMS 密钥未配置，跳过检测 | 场景: ' . $scene);
            $r = self::passResult('密钥未配置');
            $r['skipped'] = true;
            return $r;
        }

        try {
            $result = self::getTMS()->TextModeration($text);
        } catch (Exception $e) {
            error_log('[ContentFilter] TMS 调用异常: ' . $e->getMessage() . ' | 场景: ' . $scene);
            $r = self::passResult('API异常');
            $r['skipped'] = true;
            return $r;
        }

        // API 调用失败，默认放行（避免影响业务），但记录日志
        if (!$result['success']) {
            error_log('[ContentFilter] TMS 调用失败: ' . ($result['error'] ?? '未知') . ' | 场景: ' . $scene);
            $r = self::passResult('API调用失败');
            $r['skipped'] = true;
            return $r;
        }

        $data       = $result['data'];
        $suggestion = $data['Suggestion'] ?? 'Pass';
        $label      = $data['Label'] ?? 'Normal';
        $score      = isset($data['Score']) ? (int)$data['Score'] : 0;

        // 提取命中关键词
        $keywords = [];
        if (!empty($data['DetailResults'])) {
            foreach ($data['DetailResults'] as $detail) {
                if (!empty($detail['Keywords'])) {
                    $keywords = array_merge($keywords, $detail['Keywords']);
                }
            }
        }
        $keywords = array_unique($keywords);

        // Pass = 通过；Review/Block = 拦截
        $pass = ($suggestion === 'Pass');

        $reason = '';
        if (!$pass) {
            $labelText = self::$labelMap[$label] ?? $label;
            $reason = '检测到' . $labelText . '内容';
            if (!empty($keywords)) {
                $reason .= '（' . implode('、', array_slice($keywords, 0, 3)) . '）';
            }
        }

        return [
            'pass'       => $pass,
            'reason'     => $reason,
            'label'      => $label,
            'score'      => $score,
            'keywords'   => $keywords,
            'suggestion' => $suggestion,
            'skipped'    => false,
        ];
    }

    private static function passResult($reason = '')
    {
        return [
            'pass'       => true,
            'reason'     => $reason,
            'label'      => 'Normal',
            'score'      => 0,
            'keywords'   => [],
            'suggestion' => 'Pass',
            'skipped'    => false,
        ];
    }
}
