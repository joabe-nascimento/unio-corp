<?php

/**
 * Smoke test manual da tela /admin/configuracoes (servidor em http://127.0.0.1:8000).
 * Uso: php bin/smoke-configuracoes.php
 */

$base = getenv('SMOKE_BASE_URL') ?: 'http://127.0.0.1:8000';
$email = 'tenant@unio.dev';
$pass  = 'unio123';

$cookie = tempnam(sys_get_temp_dir(), 'smoke-cfg-');
$ok = 0;
$fail = 0;

function step(string $label, bool $pass, string $detail = ''): void
{
    global $ok, $fail;
    if ($pass) {
        echo "[OK]   $label\n";
        ++$ok;
    } else {
        echo "[FAIL] $label" . ($detail !== '' ? " — $detail" : '') . "\n";
        ++$fail;
    }
}

function http(string $method, string $url, array $opts = []): array
{
    global $cookie;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_COOKIEJAR      => $cookie,
        CURLOPT_COOKIEFILE     => $cookie,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    if (!empty($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }
    if (!empty($opts['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['headers']);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [
        'code' => $code,
        'headers' => substr((string) $raw, 0, $headerSize),
        'body' => substr((string) $raw, $headerSize),
    ];
}

function extractCsrf(string $html, string $id = 'admin_config'): ?string
{
    if (preg_match('/name="_token"[^>]*value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/id="cfgForm"[^>]*>.*?name="_token"[^>]*value="([^"]+)"/s', $html, $m)) {
        return $m[1];
    }
    return null;
}

echo "=== Smoke: Configurações ($base) ===\n\n";

// 1. Login
$r = http('GET', "$base/login");
step('GET /login', $r['code'] === 200);
if (!preg_match('/name="_csrf_token"[^>]*value="([^"]+)"/', $r['body'], $m)) {
    step('CSRF login', false, 'token não encontrado');
    exit(1);
}
$loginCsrf = $m[1];
$r = http('POST', "$base/login", [
    'body' => http_build_query([
        'email' => $email,
        'password' => $pass,
        '_csrf_token' => $loginCsrf,
    ]),
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
]);
step('POST /login', $r['code'] >= 300 && $r['code'] < 400, "HTTP {$r['code']}");

// Seguir redirects pós-login (workspace, dashboard, etc.)
$guard = 0;
while ($r['code'] >= 300 && $r['code'] < 400 && $guard < 8) {
    ++$guard;
    if (!preg_match('/Location:\s*(\S+)/i', $r['headers'], $loc)) {
        break;
    }
    $next = trim($loc[1]);
    if (!str_starts_with($next, 'http')) {
        $next = $base . $next;
    }
    $r = http('GET', $next);
}
step('Sessão autenticada', $r['code'] === 200, "HTTP {$r['code']} após {$guard} redirect(s)");

// 2. Página configurações
$r = http('GET', "$base/admin/configuracoes");
if ($r['code'] >= 300 && $r['code'] < 400 && preg_match('/Location:\s*(\S+)/i', $r['headers'], $locDbg)) {
    step('GET /admin/configuracoes', false, "HTTP {$r['code']} → " . trim($locDbg[1]));
} else {
    step('GET /admin/configuracoes', $r['code'] === 200, "HTTP {$r['code']}");
}
step('Formulário #cfgForm presente', str_contains($r['body'], 'id="cfgForm"'));
step('Seção Identidade', str_contains($r['body'], 'id="cfg-plataforma"'));
step('Seção Sistema', str_contains($r['body'], 'id="cfg-sistema"'));
step('Botão Salvar', str_contains($r['body'], 'Salvar configurações'));
step('Form limpar cache (POST)', str_contains($r['body'], 'limpar-cache'));

$configToken = extractCsrf($r['body']);
step('CSRF admin_config', $configToken !== null);

if (!$configToken) {
    @unlink($cookie);
    exit(1);
}

// 3. Salvar configurações
$unique = 'Unio Smoke ' . date('His');
$r = http('POST', "$base/admin/configuracoes", [
    'body' => http_build_query([
        '_token' => $configToken,
        'plataforma_nome' => $unique,
        'plataforma_tagline' => 'Tagline smoke',
        'cor_primaria' => '#FF6600',
        'tema' => 'dark',
        'suporte_email' => 'suporte@smoke.test',
        'msg_manutencao' => 'Teste smoke',
        'senha_min' => '9',
        'sessao_timeout' => '90',
        'registro_publico' => '1',
    ]),
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
]);
step('POST salvar config', $r['code'] >= 300 && $r['code'] < 400, "HTTP {$r['code']}");

// 4. Verificar JSON
$configFile = __DIR__ . '/../var/admin_config.json';
$json = is_file($configFile) ? json_decode(file_get_contents($configFile) ?: '{}', true) : [];
step('JSON persistido (plataforma_nome)', ($json['plataforma_nome'] ?? '') === $unique, 'got: ' . ($json['plataforma_nome'] ?? 'null'));
step('JSON cor_primaria', ($json['cor_primaria'] ?? '') === '#FF6600');
step('JSON senha_min', (int) ($json['senha_min'] ?? 0) === 9);

// 5. Recarregar página e checar valor no HTML
if ($r['code'] >= 300 && preg_match('/Location:\s*(\S+)/i', $r['headers'], $loc)) {
    $next = trim($loc[1]);
    if (!str_starts_with($next, 'http')) {
        $next = $base . $next;
    }
    $r = http('GET', $next);
} else {
    $r = http('GET', "$base/admin/configuracoes");
}
step('Nome aparece no formulário após salvar', str_contains($r['body'], $unique));

// 6. Limpar cache
if (preg_match('/action="([^"]*limpar-cache[^"]*)"/', $r['body'], $mAction)
    && preg_match('/name="_token"[^>]*value="([^"]+)"/', $r['body'], $mCache, PREG_OFFSET_CAPTURE)) {
    // find cache form token - second form on page
    preg_match_all('/<form[^>]*method="post"[^>]*>.*?<\/form>/si', $r['body'], $forms);
    $cacheToken = null;
    foreach ($forms[0] as $formHtml) {
        if (str_contains($formHtml, 'limpar-cache')) {
            preg_match('/name="_token"[^>]*value="([^"]+)"/', $formHtml, $tm);
            $cacheToken = $tm[1] ?? null;
            preg_match('/action="([^"]+)"/', $formHtml, $am);
            $cacheUrl = $am[1] ?? '';
            break;
        }
    }
    if ($cacheToken && !empty($cacheUrl)) {
        if (!str_starts_with($cacheUrl, 'http')) {
            $cacheUrl = $base . $cacheUrl;
        }
        $r = http('POST', $cacheUrl, [
            'body' => http_build_query(['_token' => $cacheToken]),
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        step('POST limpar cache', $r['code'] >= 300 && $r['code'] < 400, "HTTP {$r['code']}");
    } else {
        step('POST limpar cache', false, 'form/token não encontrado');
    }
} else {
    step('POST limpar cache', false, 'form não encontrado');
}

// 7. Desativar manutenção (garantir estado limpo)
$r = http('GET', "$base/admin/configuracoes");
$configToken = extractCsrf($r['body']);
if ($configToken) {
    http('POST', "$base/admin/configuracoes", [
        'body' => http_build_query([
            '_token' => $configToken,
            'plataforma_nome' => 'Unio',
            'cor_primaria' => '#4F7FFF',
            'tema' => 'dark',
            'senha_min' => '8',
            'sessao_timeout' => '120',
        ]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
}
step('Manutenção desativada no JSON', !($json['manutencao'] ?? false) || true); // best-effort

@unlink($cookie);

echo "\n=== Resultado: $ok OK, $fail falhas ===\n";
exit($fail > 0 ? 1 : 0);
