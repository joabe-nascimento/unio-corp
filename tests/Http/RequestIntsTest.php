<?php

namespace App\Tests\Http;

use App\Http\RequestInts;
use PHPUnit\Framework\TestCase;

final class RequestIntsTest extends TestCase
{
    public function testEmptyStringIsNullNotException(): void
    {
        self::assertNull(RequestInts::optional(''));
        self::assertNull(RequestInts::optional(null));
        self::assertNull(RequestInts::positiveOrNull(''));
        self::assertNull(RequestInts::positiveOrNull('0'));
    }

    public function testParsesPositiveInts(): void
    {
        self::assertSame(12, RequestInts::optional('12'));
        self::assertSame(12, RequestInts::positiveOrNull('12'));
        self::assertSame(1, RequestInts::withDefault('', 1));
        self::assertSame(3, RequestInts::withDefault('3', 1));
    }
}
