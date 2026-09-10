<?php
/**
 * 腾讯云文本内容安全（TMS）客户端
 * 接口：TextModeration
 * 文档：https://cloud.tencent.com/document/api/1124/51860
 * 签名：TC3-HMAC-SHA256
 */
class TMSClient
{
    private $secretId;
    private $secretKey;
    private $endpoint = 'tms.tencentcloudapi.com';
    private $service  = 'tms';
    private $version  = '2020-12-29';
    private $region   = 'ap-guangzhou';
    private $timeout  = 10;

    public function __construct($secretId, $secretKey, $region = 'ap-guangzhou')
    {
        $this->secretId  = $secretId;
        $this->secretKey = $secretKey;
        $this->region    = $region;
    }

    /**
     * 文本内容审核
     * @param string $text 待审核文本（UTF-8，不超过 10000 字符）
     * @param string $bizType 业务类型，默认 default
     * @return array ['success'=>bool, 'data'=>响应体, 'error'=>错误信息]
     */
    public function TextModeration($text, $bizType = 'default')
    {
        $payload = json_encode([
            'Content' => base64_encode($text),
            'BizType' => $bizType,
        ], JSON_UNESCAPED_UNICODE);

        $timestamp = time();
        $auth = $this->sign($payload, $timestamp, 'TextModeration');

        $headers = [
            'Authorization: ' . $auth,
            'Content-Type: application/json; charset=utf-8',
            'Host: ' . $this->endpoint,
            'X-TC-Action: TextModeration',
            'X-TC-Timestamp: ' . $timestamp,
            'X-TC-Version: ' . $this->version,
            'X-TC-Region: ' . $this->region,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://' . $this->endpoint . '/',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => '网络错误: ' . $error];
        }

        $data = json_decode($response, true);
        if (!$data) {
            return ['success' => false, 'error' => '响应解析失败', 'raw' => $response, 'http_code' => $httpCode];
        }

        if (isset($data['Response']['Error'])) {
            return [
                'success' => false,
                'error'   => $data['Response']['Error']['Message'] ?? '未知错误',
                'code'    => $data['Response']['Error']['Code'] ?? '',
            ];
        }

        return ['success' => true, 'data' => $data['Response']];
    }

    /**
     * TC3-HMAC-SHA256 签名
     */
    private function sign($payload, $timestamp, $action)
    {
        $algorithm = 'TC3-HMAC-SHA256';
        $date      = gmdate('Y-m-d', $timestamp);

        // 1. 规范请求
        $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:" . $this->endpoint . "\n";
        $signedHeaders    = 'content-type;host';
        $hashedPayload    = hash('SHA256', $payload);
        $canonicalRequest = "POST\n/\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $hashedPayload;

        // 2. 待签名字符串
        $credentialScope = $date . '/' . $this->service . '/tc3_request';
        $stringToSign    = $algorithm . "\n" . $timestamp . "\n" . $credentialScope . "\n" . hash('SHA256', $canonicalRequest);

        // 3. 计算签名
        $secretDate    = hash_hmac('SHA256', $date, 'TC3' . $this->secretKey, true);
        $secretService = hash_hmac('SHA256', $this->service, $secretDate, true);
        $secretSigning = hash_hmac('SHA256', 'tc3_request', $secretService, true);
        $signature     = hash_hmac('SHA256', $stringToSign, $secretSigning);

        // 4. Authorization
        return $algorithm . ' '
            . 'Credential=' . $this->secretId . '/' . $credentialScope . ', '
            . 'SignedHeaders=' . $signedHeaders . ', '
            . 'Signature=' . $signature;
    }
}
