<?php

require __DIR__ . '/../vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$url = rtrim((string) ($_ENV['LEGAL_AI_URL'] ?? ''), '/');
$enabled = filter_var($_ENV['LEGAL_AI_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOL);

echo 'LEGAL_AI_ENABLED=' . ($enabled ? 'true' : 'false') . PHP_EOL;
echo 'LEGAL_AI_URL=' . ($url !== '' ? $url : '(vazio)') . PHP_EOL;

if ($url === '') {
    exit(1);
}

$ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
$health = @file_get_contents($url . '/health', false, $ctx);
$healthStatus = isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m) ? $m[0] : '?';
echo 'HEALTH_HTTP=' . $healthStatus . PHP_EOL;
echo 'HEALTH_BODY=' . substr((string) $health, 0, 200) . PHP_EOL;

$statusCtx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
$statusBody = @file_get_contents($url . '/v1/status', false, $statusCtx);
$statusHttp = isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m) ? $m[0] : '?';
echo 'STATUS_HTTP=' . $statusHttp . PHP_EOL;
echo 'STATUS_BODY=' . substr((string) $statusBody, 0, 800) . PHP_EOL;

$usageCtx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
$usageBody = @file_get_contents($url . '/v1/usage', false, $usageCtx);
$usageHttp = isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m) ? $m[0] : '?';
echo 'USAGE_HTTP=' . $usageHttp . PHP_EOL;
echo 'USAGE_BODY=' . substr((string) $usageBody, 0, 1200) . PHP_EOL;

$payload = json_encode([
    'message' => 'Responda apenas: OK',
    'escritorio_id' => '1',
    'use_rag' => false,
    'history' => [],
]);
$chatCtx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 25,
        'ignore_errors' => true,
    ],
]);
$chatBody = @file_get_contents($url . '/v1/assistant/Sasha/chat', false, $chatCtx);
$chatStatus = isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m) ? $m[0] : '?';
echo 'CHAT_HTTP=' . $chatStatus . PHP_EOL;
echo 'CHAT_BODY=' . substr((string) $chatBody, 0, 500) . PHP_EOL;

$kernel = new App\Kernel('prod', false);
$kernel->boot();
$client = $kernel->getContainer()->get(App\Service\Juridico\JurisFlowAiClient::class);
echo 'isAvailable=' . ($client->isAvailable() ? 'true' : 'false') . PHP_EOL;
$result = $client->chat('Responda apenas: OK', [], ['escritorio_id' => '1']);
echo 'client_chat=' . ($result === null ? 'null' : json_encode($result, JSON_UNESCAPED_UNICODE)) . PHP_EOL;
