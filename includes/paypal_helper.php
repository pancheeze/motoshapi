<?php

function paypal_base_url(): string {
    return (defined('PAYPAL_ENV') && PAYPAL_ENV === 'live')
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

function paypal_is_configured(): bool {
    return defined('PAYPAL_CLIENT_ID')
        && defined('PAYPAL_CLIENT_SECRET')
        && PAYPAL_CLIENT_ID !== ''
        && PAYPAL_CLIENT_SECRET !== ''
        && PAYPAL_CLIENT_ID !== 'YOUR_SANDBOX_CLIENT_ID_HERE'
        && PAYPAL_CLIENT_SECRET !== 'YOUR_SANDBOX_CLIENT_SECRET_HERE'
        && PAYPAL_CLIENT_ID !== 'client_id_here'
        && PAYPAL_CLIENT_SECRET !== 'client_secret_here';
}

function paypal_http_request(string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if (defined('PAYPAL_VERIFY_SSL') && constant('PAYPAL_VERIFY_SSL') === false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $headerLines = [];
    foreach ($headers as $key => $value) {
        $headerLines[] = $key . ': ' . $value;
    }
    if (!empty($headerLines)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('PayPal request failed: ' . $err);
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawBody = substr($raw, $headerSize);

    return [
        'status' => $status,
        'body_raw' => $rawBody,
        'body_json' => json_decode($rawBody, true)
    ];
}

function paypal_new_request_id(): string {
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return uniqid('pp_', true);
    }
}

function paypal_get_access_token(): string {
    if (!paypal_is_configured()) {
        throw new Exception('PayPal is not configured.');
    }

    $url = paypal_base_url() . '/v1/oauth2/token';
    $basic = base64_encode(PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET);

    $resp = paypal_http_request('POST', $url, [
        'Authorization' => 'Basic ' . $basic,
        'Content-Type' => 'application/x-www-form-urlencoded'
    ], 'grant_type=client_credentials');

    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        $msg = $resp['body_json']['error_description'] ?? $resp['body_raw'];
        throw new Exception('Failed to get PayPal access token: ' . $msg);
    }

    $token = $resp['body_json']['access_token'] ?? null;
    if (!$token) {
        throw new Exception('PayPal access token missing in response.');
    }

    return $token;
}

function paypal_create_order(string $currencyCode, string $amountValue, string $description): array {
    $token = paypal_get_access_token();

    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'description' => $description,
            'amount' => [
                'currency_code' => $currencyCode,
                'value' => $amountValue
            ]
        ]]
    ];

    $resp = paypal_http_request('POST', paypal_base_url() . '/v2/checkout/orders', [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ], json_encode($payload));

    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        $msg = $resp['body_json']['message'] ?? $resp['body_raw'];
        throw new Exception('Failed to create PayPal order: ' . $msg);
    }

    return $resp['body_json'] ?? [];
}

function paypal_capture_order(string $paypalOrderId, ?string $requestId = null): array {
    $token = paypal_get_access_token();

    $headers = [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ];
    if ($requestId !== null && trim($requestId) !== '') {
        $headers['PayPal-Request-Id'] = trim($requestId);
    }

    $resp = paypal_http_request(
        'POST',
        paypal_base_url() . '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture',
        $headers,
        '{}'
    );

    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        $msg = $resp['body_json']['message'] ?? $resp['body_raw'];
        throw new Exception('Failed to capture PayPal order: ' . $msg);
    }

    return $resp['body_json'] ?? [];
}

function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_input(bool $strict = false): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if ($strict) {
        throw new Exception('Invalid JSON payload.');
    }
    return [];
}