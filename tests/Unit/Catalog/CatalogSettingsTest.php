<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Auth\EoilSignInSettings;
use App\Catalog\CatalogSettings;
use App\Catalog\CatalogSource;
use App\Catalog\InvalidCatalogConfiguration;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringNotContainsString;
use function PHPUnit\Framework\assertTrue;

final class CatalogSettingsTest extends Unit
{
    private const SECRET = 'synthetic-client-secret-5c1e-not-a-real-one';

    public function testFixtureSourceIsDemoWithoutSignIn(): void
    {
        $settings = CatalogSettings::fromEnvironment('dev', 'fixture', null, null);

        assertSame(CatalogSource::Fixture, $settings->source);
        assertTrue($settings->isDemo());
        assertFalse($settings->requiresSignIn());
        assertNull($settings->apiBaseUrl);
        assertSame(CatalogSettings::DEFAULT_TIMEOUT, $settings->timeout);
        assertFalse(EoilSignInSettings::fromEnvironment('dev', $settings, null, null, null, null)->enabled);
    }

    public function testFixtureIgnoresApiVariables(): void
    {
        assertNull(CatalogSettings::fromEnvironment('dev', 'fixture', 'http://example.test', null)->apiBaseUrl);
    }

    public function testSourceMustBeExplicit(): void
    {
        $this->expectException(InvalidCatalogConfiguration::class);
        CatalogSettings::fromEnvironment('dev', null, null, null);
    }

    public function testUnknownSourceIsRejected(): void
    {
        $this->expectException(InvalidCatalogConfiguration::class);
        CatalogSettings::fromEnvironment('dev', 'mysql', null, null);
    }

    public function testHttpsSourceRequiresSignIn(): void
    {
        $settings = CatalogSettings::fromEnvironment('prod', 'http', 'https://eoil.example.test/backend/', '2.5');

        assertSame(CatalogSource::Http, $settings->source);
        assertFalse($settings->isDemo());
        assertTrue($settings->requiresSignIn());
        assertSame('https://eoil.example.test/backend', $settings->apiBaseUrl);
        assertSame(2.5, $settings->timeout);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptedPlainHttp(): iterable
    {
        yield 'test on loopback' => ['test', 'http://127.0.0.1:8092'];
        yield 'dev on localhost' => ['dev', 'http://localhost:8888/eoil/backend/web'];
        yield 'dev to MAMP from container' => ['dev', 'http://host.docker.internal:8888/eoil/backend/web'];
    }

    #[DataProvider('acceptedPlainHttp')]
    public function testPlainHttpOnlyForLocalDevelopment(string $appEnv, string $url): void
    {
        assertSame($url, CatalogSettings::fromEnvironment($appEnv, 'http', $url, null)->apiBaseUrl);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function invalidHttpConfigurations(): iterable
    {
        yield 'missing url' => ['dev', null];
        yield 'plain http in prod' => ['prod', 'http://127.0.0.1:8092'];
        yield 'plain http to remote host in dev' => ['dev', 'http://eoil.example.test'];
        yield 'plain http to docker host in test' => ['test', 'http://host.docker.internal:8888'];
        yield 'relative url' => ['dev', '/erp-api'];
        yield 'credentials in url' => ['dev', 'https://user:pass@eoil.example.test'];
        yield 'query in url' => ['dev', 'https://eoil.example.test/?token=x'];
        yield 'other scheme' => ['dev', 'ftp://eoil.example.test'];
    }

    #[DataProvider('invalidHttpConfigurations')]
    public function testInvalidHttpConfigurationIsRejected(string $appEnv, ?string $url): void
    {
        $this->expectException(InvalidCatalogConfiguration::class);
        CatalogSettings::fromEnvironment($appEnv, 'http', $url, null);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTimeouts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'too long' => ['31'];
        yield 'not a number' => ['fast'];
    }

    #[DataProvider('invalidTimeouts')]
    public function testInvalidTimeoutIsRejected(string $timeout): void
    {
        $this->expectException(InvalidCatalogConfiguration::class);
        CatalogSettings::fromEnvironment('dev', 'fixture', null, $timeout);
    }

    // --- sign-in settings -------------------------------------------------------------------

    private function signIn(array $overrides = []): EoilSignInSettings
    {
        $o = $overrides + [
            'env' => 'dev',
            'authorize' => 'http://localhost:8888/eoil/backend/web/erp-auth/authorize',
            'clientId' => null,
            'secret' => self::SECRET,
            'redirect' => 'http://127.0.0.1:8089/auth/callback',
        ];
        $catalog = CatalogSettings::fromEnvironment($o['env'], 'http', 'http://host.docker.internal:8888/eoil/backend/web', '4');

        return EoilSignInSettings::fromEnvironment($o['env'], $catalog, $o['authorize'], $o['clientId'], $o['secret'], $o['redirect']);
    }

    public function testSignInSettingsForLocalMamp(): void
    {
        $settings = $this->signIn();

        assertTrue($settings->enabled);
        assertSame('eoil-erp', $settings->clientId);
        assertSame('http://host.docker.internal:8888/eoil/backend/web/erp-api/v1/auth/token', $settings->tokenUrl);
        assertSame('http://127.0.0.1:8089/auth/callback', $settings->redirectUri);
        assertSame(4.0, $settings->timeout);
        assertSame(self::SECRET, $settings->clientSecret());
    }

    /**
     * @return iterable<string, array{array<string, ?string>}>
     */
    public static function invalidSignInSettings(): iterable
    {
        yield 'missing authorize url' => [['authorize' => null]];
        yield 'callback on other path' => [['redirect' => 'http://127.0.0.1:8089/callback']];
        yield 'callback with query' => [['redirect' => 'http://127.0.0.1:8089/auth/callback?x=1']];
        yield 'remote plain http callback' => [['redirect' => 'http://erp.example.test/auth/callback']];
        yield 'missing secret' => [['secret' => null]];
        yield 'short secret' => [['secret' => 'too-short']];
        yield 'secret with space' => [['secret' => str_repeat('a', 20) . ' ' . str_repeat('b', 20)]];
        yield 'invalid client id' => [['clientId' => 'eoil erp']];
    }

    #[DataProvider('invalidSignInSettings')]
    public function testInvalidSignInSettingsAreRejectedWithoutLeakingSecret(array $overrides): void
    {
        try {
            $this->signIn($overrides);
            $this->fail('Expected InvalidCatalogConfiguration.');
        } catch (InvalidCatalogConfiguration $e) {
            assertStringNotContainsString(self::SECRET, $e->getMessage());
        }
    }

    public function testDebugOutputHidesSecret(): void
    {
        $settings = $this->signIn();

        assertStringNotContainsString(self::SECRET, print_r($settings, true));
        ob_start();
        var_dump($settings);
        assertStringNotContainsString(self::SECRET, (string) ob_get_clean());
    }
}
