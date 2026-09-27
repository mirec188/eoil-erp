<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\SignIn;
use App\Tests\Support\WebTester;

/**
 * The app configured with CATALOG_SOURCE=http against the local mock eOil
 * (tests/Support/MockCatalogApi/router.php), after signing in through the mock.
 * This proves the ERP side of the wiring and error states, not a live eOil connection.
 */
final class CatalogHttpSourceCest
{
    private const SECRET = 'synthetic-client-secret-7f3a-not-a-real-secret';

    public function _before(WebTester $I): void
    {
        SignIn::as($I);
        $I->seeResponseCodeIs(200);
    }

    public function listThroughHttpAdapter(WebTester $I): void
    {
        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->seeResponseCodeIs(200);
        $I->see('Mock API: testovací olej');
        $I->see('990.01');
        $I->dontSee('Ukážkové údaje');
        $I->see('Údaje z eOil');
        $I->dontSeeInRawResponse('mock-access-');
        $I->dontSeeInRawResponse(self::SECRET);
    }

    public function detailThroughHttpAdapter(WebTester $I): void
    {
        $I->amOnUrl(SignIn::APP . '/catalog/501');
        $I->seeResponseCodeIs(200);
        $I->see('Mock API: testovací olej', 'h5');
        $I->see('990.01');
    }

    public function detailNotFoundThroughHttpAdapter(WebTester $I): void
    {
        $I->amOnUrl(SignIn::APP . '/catalog/404');
        $I->seeResponseCodeIs(404);
        $I->see('Produkt sa nenašiel');
    }

    public function emptyApiResultIsEmptyNotError(WebTester $I): void
    {
        $I->amOnUrl(SignIn::APP . '/catalog?q=mock-empty');
        $I->seeResponseCodeIs(200);
        $I->see('Žiadny produkt nezodpovedá vyhľadávaniu');
    }

    public function upstreamFailuresAreVisibleErrors(WebTester $I): void
    {
        foreach (['mock-500', 'mock-invalid-json', 'mock-invalid-item', 'mock-timeout'] as $scenario) {
            $I->amOnUrl(SignIn::APP . '/catalog?q=' . $scenario);
            $I->seeResponseCodeIs(503);
            $I->see('Katalóg sa nepodarilo načítať');
            $I->dontSee('Žiadny produkt nezodpovedá vyhľadávaniu');
            $I->dontSeeInRawResponse('mock-access-');
            $I->dontSeeInRawResponse('RAW-UPSTREAM-BODY');
            $I->seeLink('Skúsiť znova', '/catalog?q=' . $scenario);
        }
    }

    public function detailFailureIsVisibleError(WebTester $I): void
    {
        $I->amOnUrl(SignIn::APP . '/catalog/500?q=abc&page=2');
        $I->seeResponseCodeIs(503);
        $I->see('Katalóg sa nepodarilo načítať');
        $I->seeLink('Skúsiť znova', '/catalog/500?q=abc&page=2');
    }
}
