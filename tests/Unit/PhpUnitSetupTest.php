<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PhpUnitSetupTest extends TestCase
{
    public function testBasicAssertionsWork(): void
    {
        self::assertTrue(true);
        self::assertSame(2, 1 + 1);
    }
}
