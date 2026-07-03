<?php

namespace App\Tests\Security;

use App\Entity\TiChamado;
use App\Entity\User;
use App\Security\TiGrantService;
use PHPUnit\Framework\TestCase;

class TiGrantServiceTest extends TestCase
{
    private TiGrantService $service;

    protected function setUp(): void
    {
        $this->service = (new \ReflectionClass(TiGrantService::class))->newInstanceWithoutConstructor();
    }

    public function testRequesterCanReplyEvenWhenOperator(): void
    {
        $user = $this->userWithId(7);
        $ticket = ['requester_id' => 7, 'status' => TiChamado::STATUS_EM_ANALISE];

        self::assertTrue($this->service->canReplyAsSolicitante($user, $ticket));
    }

    public function testOperatorCannotReplyOnSomeoneElseTicket(): void
    {
        $user = $this->userWithId(7);
        $ticket = ['requester_id' => 9, 'status' => TiChamado::STATUS_EM_ANALISE];

        self::assertFalse($this->service->canReplyAsSolicitante($user, $ticket));
    }

    public function testRequesterCannotReplyWhenResolved(): void
    {
        $user = $this->userWithId(7);
        $ticket = ['requester_id' => 7, 'status' => TiChamado::STATUS_RESOLVIDO];

        self::assertFalse($this->service->canReplyAsSolicitante($user, $ticket));
    }

    public function testRequesterCanReopenWhenResolved(): void
    {
        $user = $this->userWithId(7);
        $ticket = ['requester_id' => 7, 'status' => TiChamado::STATUS_RESOLVIDO];

        self::assertTrue($this->service->canReopenChamado($user, $ticket));
    }

    public function testReopenNotAvailableWhenOpen(): void
    {
        $user = $this->userWithId(7);
        $ticket = ['requester_id' => 7, 'status' => TiChamado::STATUS_EM_ANALISE];

        self::assertFalse($this->service->canReopenChamado($user, $ticket));
    }

    public function testRequesterCanRateCsatEvenWhenOperator(): void
    {
        $user = $this->userWithId(4);
        $ticket = [
            'requester_id' => 4,
            'status' => TiChamado::STATUS_RESOLVIDO,
            'csat_score' => null,
            'csat_em' => null,
        ];

        self::assertTrue($this->service->canRateCsat($user, $ticket));
    }

    public function testTenantBypassesAssert(): void
    {
        $tenant = $this->createMock(User::class);
        $tenant->method('hasPlatformAccess')->willReturn(true);

        $this->service->assert($tenant, false);

        self::assertTrue(true);
    }

    public function testPlatformOwnerBypassesAssert(): void
    {
        $owner = $this->createMock(User::class);
        $owner->method('hasPlatformAccess')->willReturn(true);

        $this->service->assert($owner, false);

        self::assertTrue(true);
    }

    private function userWithId(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('hasPlatformAccess')->willReturn(false);

        return $user;
    }
}
