<?php
// Simple Ollama-powered chatbot endpoint for Motoshapi.
// POST JSON: {"message":"..."}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$message = isset($data['message']) ? trim((string)$data['message']) : '';
if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing message']);
    exit;
}

function ms_is_site_related_message(string $text): bool {
    $t = mb_strtolower(trim($text), 'UTF-8');
    if ($t === '') {
        return false;
    }

    // Allow basic greetings / conversational openers.
    $greetings = [
        'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening',
        'help', 'support', 'thanks', 'thank you'
    ];
    foreach ($greetings as $g) {
        if ($t === $g || str_starts_with($t, $g . ' ') || str_starts_with($t, $g . '!')) {
            return true;
        }
    }

    // Allow Motoshapi/ecommerce-related topics.
    $keywords = [
        'motoshapi',
        'product', 'products', 'price', 'cost', 'php', 'peso', '₱',
        'cart', 'checkout', 'buy', 'buy now',
        'order', 'orders', 'track', 'status',
        'payment', 'pay', 'cod', 'cash on delivery', 'paypal',
        'delivery', 'shipping', 'address',
        'return', 'refund', 'exchange',
        'login', 'register', 'profile', 'account',
        'stock', 'available', 'variation', 'size', 'color',
        'featured', 'category', 'categories'
    ];

    foreach ($keywords as $kw) {
        if ($kw !== '' && str_contains($t, $kw)) {
            return true;
        }
    }

    return false;
}

if (!ms_is_site_related_message($message)) {
    echo json_encode([
        'reply' => "I can only help with Motoshapi store questions (products, prices, cart, checkout, COD payment, orders, and accounts). Please ask something related to our website.",
    ]);
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/currency.php';

function ms_get_active_payment_mode_names(PDO $conn): array {
    try {
        $stmt = $conn->query("SELECT mode_name, mode_code FROM payment_modes WHERE is_active = 1 ORDER BY id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $names = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['mode_name'] ?? ''));
            $code = trim((string)($row['mode_code'] ?? ''));
            if ($name === '' && $code !== '') {
                $name = strtoupper($code);
            }
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    } catch (Throwable $e) {
        return [];
    }
}

if (!isset($_SESSION['chat_history']) || !is_array($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

$productLines = [];
try {
    $stmt = $conn->query("SELECT name, price FROM products ORDER BY featured DESC, created_at DESC LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name = $row['name'] ?? '';
        $price = $row['price'] ?? null;
        if ($name === '' || $price === null) {
            continue;
        }
        $productLines[] = '- ' . $name . ' — ' . format_price($price);
    }
} catch (Throwable $e) {
}

$paymentModes = ms_get_active_payment_mode_names($conn);
$paymentModesText = '';
if (!empty($paymentModes)) {
    $paymentModesText = 'Payment methods currently available in our system: ' . implode(', ', $paymentModes) . '.';
} else {
    $paymentModesText = 'Payment methods are not available right now (not configured).';
}

$system = "You are the official ecommerce support assistant for the Motoshapi motorcycle parts website. " .
    "Only answer questions related to the Motoshapi site: products, prices, stock/variations, cart, checkout, COD payment, orders, and accounts. " .
    "If the user asks anything unrelated (general knowledge, coding help, politics, math, etc.), refuse briefly and ask them to ask a Motoshapi-related question. " .
    $paymentModesText . " " .
    "If you are unsure, ask a brief clarifying question or suggest checking the Products page. " .
    "Do not invent policies (shipping, returns) that you don't know; say you don't have that information.";

if (!empty($productLines)) {
    $system .= "\n\nAvailable products (sample):\n" . implode("\n", $productLines);
}

$messages = [];
$messages[] = ['role' => 'system', 'content' => $system];

$history = $_SESSION['chat_history'];
$history = array_slice($history, -12);
foreach ($history as $m) {
    if (!is_array($m) || !isset($m['role'], $m['content'])) {
        continue;
    }
    $role = (string)$m['role'];
    if ($role !== 'user' && $role !== 'assistant') {
        continue;
    }
    $messages[] = ['role' => $role, 'content' => (string)$m['content']];
}

$messages[] = ['role' => 'user', 'content' => $message];

$payload = [
    'model' => 'llama3.2:3b',
    'messages' => $messages,
    'stream' => false,
    'options' => [
        'temperature' => 0.3,
    ],
];

$ch = curl_init('http://127.0.0.1:11434/api/chat');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 20,
]);

$responseBody = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseBody === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to contact Ollama', 'details' => $curlErr]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    echo json_encode(['error' => 'Ollama error', 'status' => $httpCode, 'body' => $responseBody]);
    exit;
}

$decoded = json_decode($responseBody, true);
$reply = $decoded['message']['content'] ?? null;
if (!is_string($reply) || trim($reply) === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Unexpected Ollama response', 'body' => $decoded]);
    exit;
}

$_SESSION['chat_history'][] = ['role' => 'user', 'content' => $message];
$_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $reply];
$_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -24);

echo json_encode(['reply' => $reply]);
