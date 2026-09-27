<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\CatalogSettings;
use App\Catalog\CatalogSource;
use App\Catalog\InvalidCatalogConfiguration;
use Codeception\Test\Unit;
use Codeception\Attribute\DataProvider;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringNotContainsString;
use function PHPUnit\Framework\assertTrue;

final class CatalogSettingsTest extends Unit
{
    private const TOKEN = 'synthetic-token-5c1e-not-a-secret';

    public function testFixtureSourceIsDemo(): void
    {
        $settings = CatalogSettings::fromEnvironment('dev', 'fixture', null, null, null);

        assertSame(CatalogSource::Fixture, $settings->source);
        assertTrue($settings->isDemo());
        assertNull($settings->apiBaseUrl);
        assertSame(CatalogSettings::DEFAULT_TIMEOUT, $settings->timeout);
    }

    public function testFixtureIgnoresApiVariables(): void
    {
        $settings = CatalogSettings::fromEnvironment('dev', 'fixture', 'http://example.test', self::TOKEN, null);

        assertNull($settings->apiBaseUrl);
    }

    public function testSourceMustBeExplicit(): void
    {
        $this->expectException(InvalidCatalogConfiguration::class);
        CatalogSettings::fromEnvironment('dev', null, null, null, null);
    }

    public function testUnknownSourceIsRejected(): void
    {
        $this->expectException(InvalidCatalogConfiguration::class);
        CatalogSettings::fromEnvironment('dev', 'mysql', null, null, null);
    }

    public function testHttpsSourceIsAccepted(): void
    {
        $settings = CatalogSettings::fromEnvironment('prod', 'http', 'https://api.example.test/', self::TOKEN, '2.5');

        assertSame(CatalogSource::Http, $settings->source);
        assertFalse($settings->isDemo());
        assertSame('https://api.example.test', $settings->apiBaseUrl);
        assertSame(self::TOKEN, $settings->apiToken());
        assertSame(2.5, $settings->timeout);
    }

    public function testPlainHttpToLoopbackIsAcceptedInTestEnvironment(): void
    {
        $settings = CatalogSettings::fromEnvironment('test', 'http', 'http://127.0.0.1:8092', self::TOKEN, null);

        assertSame('http://127.0.0.1:8092', $settings->apiBaseUrl);
    }

    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function invalidHttpConfigurations(): iterable
    {
        yield 'missing url' => ['dev', null, self::TOKEN];
        yield 'missing token' => ['dev', 'https://api.example.test', null];
        yield 'token with space' => ['dev', 'https://api.example.test', 'abc def'];
        yield 'token with newline' => ['dev', 'https://api.example.test', "abc\r\nX-Injected: 1"];
        yield 'plain http in dev' => ['dev', 'http://127.0.0.1:8092', self::TOKEN];
        yield 'plain http in prod' => ['prod', 'http://127.0.0.1:8092', self::TOKEN];
        yield 'plain http to remote host in test' => ['test', 'http://api.example.test', self::TOKEN];
        yield 'relative url' => ['dev', '/erp-api', self::TOKEN];
        yield 'credentials in url' => ['dev', 'https://user:pass@api.example.test', self::TOKEN];
        yield 'query in url' => ['dev', 'https://api.example.test/?token=x', self::TOKEN];
        yield 'other scheme' => ['dev', 'ftp://api.example.test', self::TOKEN];
    }

    #[DataProvider('invalidHttpConfigurations')]
    public function testInvalidHttpConfigurationIsRejectedWithoutLeakingToken(
        string $appEnv,
        ?string $url,
        ?string $token,
    ): void {
        try {
            CatalogSettings::fromEnvironment($appEnv, 'http', $url, $token, null);
            $this->fail('Expected InvalidCatalogConfiguration.');
        } catch (InvalidCatalogConfiguration $e) {
            if ($token !== null) {
                assertStringNotContainsString($token, $e->getMessage());
                // Application frames redact the token (#[SensitiveParameter]); this test's own
                // frame necessarily receives it from the data provider and is skipped.
                foreach ($e->getTrace() as $frame) {
                    if (str_starts_with($frame['class'] ?? '', 'App\\Catalog\\')) {
                        assertStringNotContainsString($token, print_r($frame['args'] ?? [], true));
                    }
                }
            }
        }
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
        CatalogSettings::fromEnvironment('dev', 'fixture', null, null, $timeout);
    }

    public function testDebugOutputHidesToken(): void
    {
        $settings = CatalogSettings::fromEnvironment('prod', 'http', 'https://api.example.test', self::TOKEN, null);

        assertStringNotContainsString(self::TOKEN, print_r($settings, true));
        ob_start();
        var_dump($settings);
        assertStringNotContainsString(self::TOKEN, (string) ob_get_clean());
    }
}
