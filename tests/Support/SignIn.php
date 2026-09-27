<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Drives the sign-in of the HTTP-source app (127.0.0.1:8082) through the mock eOil (127.0.0.1:8092).
 */
final class SignIn
{
    public const APP = 'http://127.0.0.1:8082';
    public const MOCK = 'http://127.0.0.1:8092';

    public static function reset(WebTester $I, string $scenario = 'allowed'): void
    {
        $I->amOnUrl(self::MOCK . '/__mock/reset?scenario=' . $scenario);
        $I->seeResponseCodeIs(200);
    }

    public static function as(WebTester $I, string $scenario = 'allowed', string $startPath = '/login'): void
    {
        self::reset($I, $scenario);
        $I->amOnUrl(self::APP . $startPath);
        $I->click('Prihlásiť sa cez eOil');
    }
}
