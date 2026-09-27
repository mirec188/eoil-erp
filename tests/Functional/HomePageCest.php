<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use HttpSoft\Message\ServerRequest;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class HomePageCest
{
    public function base(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(uri: '/'),
        );

        assertSame(200, $response->getStatusCode());
        $body = $response->getBody()->getContents();
        assertStringContainsString('eOil ERP', $body);
        assertStringContainsString('Ukážkové údaje', $body);
    }

    public function catalogDetail(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(uri: '/catalog/101'),
        );

        assertSame(200, $response->getStatusCode());
        assertStringContainsString('901.01', $response->getBody()->getContents());
    }
}
