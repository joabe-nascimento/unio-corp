<?php
$port = 8098;
$envPortFile = __DIR__ . '/lib/jurisflow-hostgator.env';
if (is_readable($envPortFile)) {
    foreach (file($envPortFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), 'JURISFLOW_HOSTGATOR_PORT=')) {
            $port = (int) trim(substr(trim($line), strlen('JURISFLOW_HOSTGATOR_PORT=')));
            break;
        }
    }
}
$url = 'http://127.0.0.1:' . $port;
$health = @file_get_contents($url . '/health');
echo "HEALTH=" . trim((string) $health) . PHP_EOL;

$payload = json_encode([
    'message' => 'Responda apenas: OK',
    'escritorio_id' => '1',
    'use_rag' => false,
    'history' => [],
]);
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 30,
        'ignore_errors' => true,
    ],
]);
$body = @file_get_contents($url . '/v1/assistant/Sasha/chat', false, $ctx);
$status = isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m) ? $m[0] : '?';
echo "CHAT_HTTP=$status" . PHP_EOL;
echo "CHAT_BODY=" . substr((string) $body, 0, 400) . PHP_EOL;

$cron = shell_exec('crontab -l 2>/dev/null | grep watchdog');
echo "CRON=" . trim((string) $cron) . PHP_EOL;
$sup = @file_get_contents('/home2/joabef36/jurisflow-ai/supervisor.log');
$lines = array_slice(explode("\n", (string) $sup), -5);
echo "SUPERVISOR_TAIL=\n" . implode("\n", $lines) . PHP_EOL;
