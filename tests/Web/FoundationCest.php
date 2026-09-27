<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTester;

final class FoundationCest
{
    public function foundation(WebTester $I): void
    {
        $I->amOnPage('/');
        $I->seeResponseCodeIs(200);
        $I->see('eOil ERP');
        $I->seeLink('Katalóg', '/catalog');
        $I->amOnPage('/health');
        $I->seeResponseCodeIs(200);
        $I->see('ok');
    }

    public function overviewDoesNotPretendLiveData(WebTester $I): void
    {
        $I->amOnPage('/');
        $I->see('Prehľad', 'h4');
        $I->see('Vitajte v eOil ERP');
        $I->see('Ukážkové údaje');
        $I->see('Nie sú to skutočné produkty z eOil');
        // Business-facing copy: implementation details belong in docs, not in the UI.
        foreach (['ProductHasPack', 'server-rendered', 'mock', 'idempot', 'M2', 'adaptér', 'Yii3'] as $jargon) {
            $I->dontSee($jargon, 'main');
        }
        // No invented sales/stock figures: no metric headings and no currency amounts.
        $I->dontSee('Tržby', 'h5');
        $I->dontSee('Sklad', 'h5');
        $I->dontSee('€');
        $I->seeLink('Otvoriť katalóg', '/catalog');
    }

    public function healthExposesNoConfiguration(WebTester $I): void
    {
        $I->amOnPage('/health');
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Content-Type', 'application/json');
        $I->seeHttpHeader('Cache-Control', 'no-store');
        $I->seeResponseEquals('{"status":"ok"}');
    }

    public function layoutUsesLocalThemeAssets(WebTester $I): void
    {
        $I->amOnPage('/');
        $I->seeElement('.navbar.navbar-dark.navbar-static');
        $I->seeElement('link[href*="css/all.min.css"]');
        $I->seeElement('link[href*="icons/phosphor/styles.min.css"]');
        $I->seeElement('script[src*="bootstrap.bundle.min.js"]');
        $I->dontSeeElement('link[href^="http"]');
        $I->dontSeeElement('script[src^="http"]');
        $I->dontSeeElement('script:not([src])');
        $I->dontSeeInSource('yii.js');
    }

    public function securityHeadersArePresent(WebTester $I): void
    {
        $I->amOnPage('/');
        $I->seeHttpHeader('X-Content-Type-Options', 'nosniff');
        $I->seeHttpHeader('X-Frame-Options', 'DENY');
        $I->seeHttpHeader('Content-Security-Policy');
    }

    public function productionConfigurationRefusesFixtureCatalog(WebTester $I): void
    {
        foreach (['/', '/catalog', '/catalog/101', '/health'] as $path) {
            $I->amOnUrl('http://127.0.0.1:8083' . $path);
            $I->seeResponseCodeIs(503);
            $I->see('Prístup odmietnutý');
            $I->dontSee('Ukážkový motorový olej');
        }
    }
}
