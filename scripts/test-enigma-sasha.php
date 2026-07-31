<?php

require __DIR__ . '/../vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$url = rtrim((string) ($_ENV['LEGAL_AI_URL'] ?? 'http://127.0.0.1:8097'), '/');
$mensagem = 'Você era o dono de uma vila, e nessa vila haviam somente apenas seis tias, cada prima tinha um irmão, e cada irmão tinha um avô, quantas pessoas tinham na casa e quem era o dono ?';

$payload = json_encode([
    'message' => $mensagem,
    'escritorio_id' => '1',
    'use_rag' => false,
    'history' => [],
], JSON_UNESCAPED_UNICODE);

$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 60,
        'ignore_errors' => true,
    ],
]);

$started = microtime(true);
$body = @file_get_contents($url . '/v1/assistant/Sasha/chat', false, $ctx);
$elapsed = round(microtime(true) - $started, 2);
$status = isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m) ? $m[0] : '?';

echo "HEALTH_CHECK=";
$h = @file_get_contents($url . '/health', false, stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]));
echo trim((string) $h) . PHP_EOL;
echo "CHAT_HTTP=$status" . PHP_EOL;
echo "ELAPSED_SEC=$elapsed" . PHP_EOL;
echo "RAW=" . substr((string) $body, 0, 2500) . PHP_EOL;

$data = json_decode((string) $body, true);
if (is_array($data)) {
    echo PHP_EOL . "=== RESPOSTA SASHA ===" . PHP_EOL;
    echo (string) ($data['answer'] ?? $data['reply'] ?? '(sem answer)') . PHP_EOL;
    if (isset($data['usage'])) {
        echo PHP_EOL . "USAGE=" . json_encode($data['usage'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
