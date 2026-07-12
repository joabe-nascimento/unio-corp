<?php

namespace App\MessageHandler\PosOperatorio;

use App\Message\PosOperatorio\ContinuidadeTick;
use App\Service\PosOperatorio\ClinicContinuityService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ContinuidadeTickHandler
{
    public function __construct(
        private ClinicContinuityService $continuity,
    ) {}

    public function __invoke(ContinuidadeTick $message): void
    {
        $this->continuity->runAll($message->empresaId);
    }
}
