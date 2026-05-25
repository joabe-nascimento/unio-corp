<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// PHPUnit (APP_ENV=test) não carrega .env.local — reutiliza DATABASE_URL do dev local se não houver .env.test.local
$root = dirname(__DIR__);
if (($_SERVER['APP_ENV'] ?? '') === 'test' && !is_file($root.'/.env.test.local') && is_file($root.'/.env.local')) {
    $parsed = (new Dotenv())->parse(file_get_contents($root.'/.env.local'), $root.'/.env.local');
    if (!empty($parsed['DATABASE_URL'])) {
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $parsed['DATABASE_URL'];
    }
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
