<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Catalog\CatalogSource;
use App\Shared\Access\DevelopmentAccessPolicy;
use Codeception\Test\Unit;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertTrue;

final class DevelopmentAccessPolicyTest extends Unit
{
    public function testDevWithFixtureIsLocalDemo(): void
    {
        $decision = (new DevelopmentAccessPolicy())->decide('dev', CatalogSource::Fixture);

        assertTrue($decision->allowed);
        assertStringContainsString('ukážkovými', $decision->reason);
    }

    public function testTestWithFixtureIsAllowed(): void
    {
        assertTrue((new DevelopmentAccessPolicy())->decide('test', CatalogSource::Fixture)->allowed);
    }

    public function testDevWithHttpIsAllowed(): void
    {
        assertTrue((new DevelopmentAccessPolicy())->decide('dev', CatalogSource::Http)->allowed);
    }

    public function testProdWithFixtureIsRefused(): void
    {
        $decision = (new DevelopmentAccessPolicy())->decide('prod', CatalogSource::Fixture);

        assertFalse($decision->allowed);
        assertStringContainsString('Ukážkové údaje', $decision->reason);
    }

    public function testProdIsRefusedForEveryCatalogSource(): void
    {
        foreach (CatalogSource::cases() as $source) {
            $decision = (new DevelopmentAccessPolicy())->decide('prod', $source);
            assertFalse($decision->allowed, $source->value);
            assertStringContainsString('prihlásenie', $decision->reason);
        }
    }

    public function testPolicyHasNoIdentityOverride(): void
    {
        // Regression: no speculative string-based production authorization may exist in this slice.
        $parameters = (new \ReflectionMethod(DevelopmentAccessPolicy::class, 'decide'))->getParameters();
        assertTrue(count($parameters) === 2);
    }
}
