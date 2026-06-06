<?php

use App\Entity\User;
use App\Kernel;
use App\Service\ChatService;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');

/** @var ChatService $chat */
$chat = $container->get(ChatService::class);
/** @var \Doctrine\ORM\EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();

$userRepo = $em->getRepository(User::class);
$userA = $userRepo->findOneBy(['email' => 'gestor@unio.dev']);
$userB = $userRepo->findOneBy(['email' => 'membro@unio.dev']);

if (!$userA || !$userB) {
    fwrite(STDERR, "Users not found.\n");
    exit(1);
}

$empresa = $userA->getEmpresa();
if (!$empresa) {
    fwrite(STDERR, "Empresa missing.\n");
    exit(1);
}

$errors = [];

try {
    $convs = $chat->getConversationsPayload($userA, $empresa);
    if ($convs === []) {
        $errors[] = 'getConversationsPayload returned empty';
    }
} catch (\Throwable $e) {
    $errors[] = 'getConversationsPayload: ' . $e->getMessage();
}

try {
    $colleagues = $chat->getColleagues($userA, $empresa);
    if ($colleagues === []) {
        $errors[] = 'getColleagues returned empty';
    }
} catch (\Throwable $e) {
    $errors[] = 'getColleagues: ' . $e->getMessage();
}

try {
    $direct = $chat->createDirect($userA, $empresa, (int) $userB->getId());
    if (($direct['type'] ?? '') !== 'direct') {
        $errors[] = 'createDirect wrong type';
    }
} catch (\Throwable $e) {
    $errors[] = 'createDirect: ' . $e->getMessage();
}

try {
    $group = $chat->createGroup($userA, $empresa, 'Smoke Test ' . date('His'), [(int) $userB->getId()]);
    if (($group['type'] ?? '') !== 'group') {
        $errors[] = 'createGroup wrong type';
    }
    $convId = (int) $group['id'];
    $msgs = $chat->getMessagesPayload($userA, $convId);
    if ($msgs === []) {
        $errors[] = 'group has no welcome message';
    }
    $sent = $chat->sendText($userA, $convId, 'Smoke test message');
    if (($sent['text'] ?? '') !== 'Smoke test message') {
        $errors[] = 'sendText failed';
    }
} catch (\Throwable $e) {
    $errors[] = 'createGroup/sendText: ' . $e->getMessage();
}

try {
    $topics = $chat->getSubscribeTopics($userA, $empresa);
    if ($topics === []) {
        $errors[] = 'getSubscribeTopics empty';
    }
} catch (\Throwable $e) {
    $errors[] = 'getSubscribeTopics: ' . $e->getMessage();
}

if ($errors) {
    fwrite(STDERR, "FAIL:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "OK: ChatService smoke test passed.\n";
