#!/usr/bin/env php
<?php
/**
 * Espelha empresa + user + user_product_grant da producao em homolog.
 *
 * Uso no servidor:
 *   php scripts/sync-homolog-identity-from-prod.php export /tmp/unio-identity.json
 *   php scripts/sync-homolog-identity-from-prod.php import staging /tmp/unio-identity.json
 *   php scripts/sync-homolog-identity-from-prod.php import rh /tmp/unio-identity.json
 */

declare(strict_types=1);

use App\Kernel;
use Doctrine\DBAL\Connection;
use Symfony\Component\Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$action = $argv[1] ?? '';
$base = '/home2/joabef36';
$prodRoot = $base . '/unio';

$targets = [
    'staging' => ['root' => $base . '/unio-staging', 'rh_extra' => false],
    'rh' => ['root' => $base . '/unio-rh', 'rh_extra' => true],
];

function bootConnection(string $root): Connection
{
    if (!is_file($root . '/vendor/autoload.php')) {
        throw new RuntimeException("App nao encontrado em {$root}");
    }
    require_once $root . '/vendor/autoload.php';
    $_SERVER['APP_ENV'] = 'prod';
    $_ENV['APP_ENV'] = 'prod';
    putenv('APP_ENV=prod');
    (new Dotenv())->bootEnv($root . '/.env');
    $kernel = new Kernel('prod', false);
    $kernel->boot();

    return $kernel->getContainer()->get('doctrine.dbal.default_connection');
}

function exportIdentity(Connection $prod, string $file): void
{
    $data = [
        'empresa' => $prod->fetchAllAssociative('SELECT * FROM empresa ORDER BY id'),
        'user' => $prod->fetchAllAssociative('SELECT * FROM `user` ORDER BY id'),
        'user_product_grant' => $prod->fetchAllAssociative('SELECT * FROM user_product_grant ORDER BY id'),
        'gestor_password_hash' => $prod->fetchOne("SELECT password FROM `user` WHERE email IN ('renata.oliveira@unio.dev', 'gestor@unio.dev') ORDER BY CASE WHEN email = 'renata.oliveira@unio.dev' THEN 0 ELSE 1 END LIMIT 1"),
    ];
    file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    echo 'Exportado: ' . count($data['empresa']) . ' empresas, ' . count($data['user']) . " usuarios -> {$file}\n";
}

function importIdentity(Connection $target, string $file, bool $rhExtra): void
{
    /** @var array{empresa:list<array<string,mixed>>,user:list<array<string,mixed>>,user_product_grant:list<array<string,mixed>>,gestor_password_hash:?string} $data */
    $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

    $target->executeStatement('SET FOREIGN_KEY_CHECKS=0');
    $target->executeStatement('DELETE FROM user_product_grant');
    $target->executeStatement('DELETE FROM `user`');
    $target->executeStatement('DELETE FROM empresa');
    $target->executeStatement('SET FOREIGN_KEY_CHECKS=1');

    foreach ($data['empresa'] as $row) {
        $target->insert('empresa', $row);
    }
    echo '   ' . count($data['empresa']) . " empresas\n";

    foreach ($data['user'] as $row) {
        $target->insert('user', $row);
    }
    echo '   ' . count($data['user']) . " usuarios (mesmas senhas da prod)\n";

    foreach ($data['user_product_grant'] as $row) {
        $target->insert('user_product_grant', $row);
    }
    echo '   ' . count($data['user_product_grant']) . " grants\n";

    if ($rhExtra && !empty($data['gestor_password_hash'])) {
        createRhExtras($target, (string) $data['gestor_password_hash']);
    }

    $users = $target->fetchAllAssociative('SELECT email, perfil FROM `user` ORDER BY id');
    echo "\n== Usuarios ==\n";
    foreach ($users as $u) {
        echo "   {$u['email']} ({$u['perfil']})\n";
    }
}

function createRhExtras(Connection $target, string $hash): void
{
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $tenants = [
        ['Alpina Logística S.A.', '47.882.103/0001-56', 'alpina-logistica', 'Logística', 'gestor.alpina@homolog.uniowork.com.br', 'Gestor Alpina'],
        ['Horizonte Saúde Ltda', '58.391.774/0001-22', 'horizonte-saude', 'Saúde', 'gestor.horizonte@homolog.uniowork.com.br', 'Gestor Horizonte'],
        ['Meridian Tech ME', '63.204.918/0001-87', 'meridian-tech', 'Tecnologia', 'gestor.meridian@homolog.uniowork.com.br', 'Gestor Meridian'],
    ];

    echo "[RH] Empresas ficticias extras\n";
    foreach ($tenants as [$nome, $cnpj, $slug, $setor, $email, $gestorNome]) {
        $target->insert('empresa', [
            'nome' => $nome, 'cnpj' => $cnpj, 'setor' => $setor, 'logo' => null,
            'ativo' => 1, 'slug' => $slug, 'carreiras_ativo' => 0, 'criado_em' => $now,
        ]);
        $empresaId = (int) $target->lastInsertId();
        $target->insert('user', [
            'email' => $email, 'roles' => '["ROLE_GESTOR"]', 'password' => $hash,
            'nome' => $gestorNome, 'perfil' => 'GESTOR', 'avatar' => null, 'ativo' => 1,
            'criado_em' => $now, 'onboarding_completed_steps' => '[]',
            'termos_aceitos_em' => null, 'termos_versao' => null, 'empresa_id' => $empresaId,
        ]);
        $userId = (int) $target->lastInsertId();
        foreach ([
            ['hub_operacoes', 'rh', 'GESTOR'],
            ['product_rh', 'funcionarios', 'GESTOR'],
            ['product_rh', 'admissoes', 'GESTOR'],
            ['product_rh', 'portal', 'GESTOR'],
        ] as [$scope, $productId, $perfil]) {
            $target->insert('user_product_grant', [
                'user_id' => $userId, 'scope' => $scope, 'product_id' => $productId,
                'perfil_grant' => $perfil, 'atualizado_em' => $now,
            ]);
        }
        echo "   + {$nome} -> {$email}\n";
    }
}

try {
    if ($action === 'export') {
        $file = $argv[2] ?? '/tmp/unio-identity.json';
        echo "== Export prod -> {$file}\n";
        exportIdentity(bootConnection($prodRoot), $file);
        exit(0);
    }

    if ($action === 'import') {
        $mode = $argv[2] ?? '';
        $file = $argv[3] ?? '/tmp/unio-identity.json';
        if (!isset($targets[$mode])) {
            throw new RuntimeException('Modo invalido — use staging ou rh');
        }
        echo "== Import {$file} -> {$mode}\n";
        importIdentity(bootConnection($targets[$mode]['root']), $file, $targets[$mode]['rh_extra']);
        echo "\nConcluido. Login com as mesmas senhas da producao.\n";
        exit(0);
    }

    fwrite(STDERR, "Uso:\n  php {$argv[0]} export [/tmp/unio-identity.json]\n  php {$argv[0]} import staging|rh [/tmp/unio-identity.json]\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
    exit(1);
}
