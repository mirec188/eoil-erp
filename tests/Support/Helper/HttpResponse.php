<?php

declare(strict_types=1);

namespace App\Tests\Support\Helper;

use Codeception\Module;
use Codeception\Module\PhpBrowser;

use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringNotContainsString;

/**
 * Header and raw-body assertions for PhpBrowser (which only offers HTML-level assertions).
 */
final class HttpResponse extends Module
{
    public function seeHttpHeader(string $name, ?string $value = null): void
    {
        $actual = $this->grabHttpHeader($name);
        assertNotNull($actual, "Response header $name is missing.");
        if ($value !== null) {
            assertSame($value, $actual, "Unexpected value of response header $name.");
        }
    }

    public function grabHttpHeader(string $name): ?string
    {
        return $this->browser()->client->getInternalResponse()->getHeader($name);
    }

    public function seeResponseEquals(string $expected): void
    {
        assertSame($expected, $this->browser()->_getResponseContent());
    }

    public function dontSeeInRawResponse(string $needle): void
    {
        assertStringNotContainsString($needle, $this->browser()->_getResponseContent());
    }

    private function browser(): PhpBrowser
    {
        /** @var PhpBrowser */
        return $this->getModule('PhpBrowser');
    }
}
