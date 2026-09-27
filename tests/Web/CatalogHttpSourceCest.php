<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTester;

/**
 * The app configured with CATALOG_SOURCE=http against the local mock of the *proposed* eOil API
 * (tests/Support/MockCatalogApi/router.php). This proves the adapter wiring and error states,
 * not a live eOil connection.
 */
final class CatalogHttpSourceCest
{
    private const APP = 'http://127.0.0.1:8082';
    private const TOKEN = 'synthetic-token-7f3a-not-a-secret';

    public function listThroughHttpAdapter(WebTester $I): void
    {
        $I->amOnUrl(self::APP . '/catalog');
        $I->seeResponseCodeIs(200);
        $I->see('Mock API: testovací olej');
        $I->see('990.01');
        $I->dontSee('Ukážkové údaje');
        $I->see('Živé napojenie na eOil nie je overené');
        $I->dontSeeInRawResponse(self::TOKEN);
    }

    public function detailThroughHttpAdapter(WebTester $I): void
    {
        $I->amOnUrl(self::APP . '/catalog/501');
        $I->seeResponseCodeIs(200);
        $I->see('Mock API: testovací olej', 'h5');
        $I->see('990.01');
    }

    public function detailNotFoundThroughHttpAdapter(WebTester $I): void
    {
        $I->amOnUrl(self::APP . '/catalog/404');
        $I->seeResponseCodeIs(404);
        $I->see('Produkt sa nenašiel');
    }

    public function emptyApiResultIsEmptyNotError(WebTester $I): void
    {
        $I->amOnUrl(self::APP . '/catalog?q=mock-empty');
        $I->seeResponseCodeIs(200);
        $I->see('Žiadny produkt nezodpovedá vyhľadávaniu');
    }

    public function upstreamFailuresAreVisibleErrors(WebTester $I): void
    {
        foreach (['mock-500', 'mock-401', 'mock-invalid-json', 'mock-invalid-item', 'mock-timeout'] as $scenario) {
            $I->amOnUrl(self::APP . '/catalog?q=' . $scenario);
            $I->seeResponseCodeIs(503);
            $I->see('Katalóg sa nepodarilo načítať');
            $I->dontSee('Žiadny produkt nezodpovedá vyhľadávaniu');
            $I->dontSeeInRawResponse(self::TOKEN);
            $I->dontSeeInRawResponse('RAW-UPSTREAM-BODY');
            $I->seeLink('Skúsiť znova', '/catalog?q=' . $scenario);
        }
    }

    public function detailFailureIsVisibleError(WebTester $I): void
    {
        $I->amOnUrl(self::APP . '/catalog/500?q=abc&page=2');
        $I->seeResponseCodeIs(503);
        $I->see('Katalóg sa nepodarilo načítať');
        $I->seeLink('Skúsiť znova', '/catalog/500?q=abc&page=2');
        $I->dontSeeInRawResponse(self::TOKEN);
    }
}
