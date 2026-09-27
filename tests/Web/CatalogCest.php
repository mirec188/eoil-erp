<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTester;

final class CatalogCest
{
    public function preservesMrpIdentity(WebTester $I): void
    {
        $I->amOnPage('/catalog?q=901.01&page=1');
        $I->see('Ukážkové údaje');
        $I->see('901.01');
        $I->click('Detail');
        $I->see('101');
        $I->see('901.01');
    }

    public function listShowsColumnsAndDemoNotice(WebTester $I): void
    {
        $I->amOnPage('/catalog');
        $I->seeResponseCodeIs(200);
        $I->see('Katalóg', 'h4');
        $I->see('Ukážkové údaje');
        $I->see('Nie sú to skutočné produkty z eOil');
        $I->see('ID balenia v eOil', 'th');
        $I->seeElement('td[data-label="ID eOil"]');
        foreach (['ProductHasPack', 'adaptér', 'mock', 'kontrakt'] as $jargon) {
            $I->dontSee($jargon);
        }
        $I->see('MRP', 'th');
        $I->see('Ukážkový motorový olej 5W-30');
        $I->see('0904.01');
        $I->see('907.10');
        $I->see('Neaktívny');
        $I->see('Strana 1 z 2');
        $I->seeElement('form[method="get"][action="/catalog"] input[name="q"]');
    }

    public function secondPageKeepsQueryInPagination(WebTester $I): void
    {
        $I->amOnPage('/catalog?q=ukážkový');
        $I->see('Strana 1 z 2');
        $I->seeElement('a[href="/catalog?q=uk%C3%A1%C5%BEkov%C3%BD&page=2"]');
        $I->click('Ďalšia');
        $I->seeResponseCodeIs(200);
        $I->seeInCurrentUrl('page=2');
        $I->see('Strana 2 z 2');
        $I->seeInField('q', 'ukážkový');
        $I->seeElement('a[href="/catalog?q=uk%C3%A1%C5%BEkov%C3%BD"]');
    }

    public function unfilteredSecondPage(WebTester $I): void
    {
        $I->amOnPage('/catalog?page=2');
        $I->see('Strana 2 z 2');
        $I->see('126', 'td');
        $I->see('132', 'td');
        $I->dontSee('101', 'td');
    }

    public function searchWithDiacritics(WebTester $I): void
    {
        $I->amOnPage('/catalog?q=' . rawurlencode('ťažkých nečistôt'));
        $I->see('Ukážkový čistič ťažkých nečistôt – ľahký');
        $I->see('Nájdené: 1');

        $I->amOnPage('/catalog?q=PREVODOVY');
        $I->see('Ukážkový prevodový olej 75W-90');
        $I->see('Nájdené: 2');
    }

    public function emptyResultIsDistinctFromError(WebTester $I): void
    {
        $I->amOnPage('/catalog?q=' . rawurlencode('neexistujúci výraz xyz'));
        $I->seeResponseCodeIs(200);
        $I->see('Žiadny produkt nezodpovedá vyhľadávaniu');
        $I->dontSee('Katalóg sa nepodarilo načítať');
        $I->seeLink('Zrušiť vyhľadávanie', '/catalog');
    }

    public function pageBeyondRangeOffersFirstPage(WebTester $I): void
    {
        $I->amOnPage('/catalog?page=9');
        $I->seeResponseCodeIs(200);
        $I->see('Na tejto strane nie sú žiadne položky');
        $I->seeLink('Prejsť na prvú stranu', '/catalog');
    }

    public function htmlInProductNameIsText(WebTester $I): void
    {
        $I->amOnPage('/catalog?q=906.01');
        $I->see('Ukážka <img src=x onerror=alert(1)> & "HTML" v názve');
        $I->dontSeeElement('img');
        $I->dontSeeElement('[onerror]');
        $I->seeInSource('&lt;img src=x onerror=alert(1)&gt;');
        // The name is also used in an attribute; its double quotes must not terminate it.
        $I->seeElement('a', ['aria-label' => 'Detail: Ukážka <img src=x onerror=alert(1)> & "HTML" v názve']);
        $I->dontSeeElement('a[html]');

        $I->click('Detail');
        $I->see('Ukážka <img src=x onerror=alert(1)> & "HTML" v názve', 'h5');
        $I->dontSeeElement('img');
        $I->dontSeeElement('[onerror]');
    }

    public function htmlInQueryIsText(WebTester $I): void
    {
        $payload = '"><script>alert(1)</script><b onmouseover=alert(1)>';
        $I->amOnPage('/catalog?q=' . rawurlencode($payload));
        $I->seeResponseCodeIs(200);
        $I->seeInField('q', $payload);
        $I->dontSeeElement('script:not([src])');
        $I->dontSeeElement('[onmouseover]');
        $I->dontSeeElement('b');
    }

    public function attributeBreakoutInQueryIsText(WebTester $I): void
    {
        $payload = '" autofocus onfocus="alert(1)';
        $I->amOnPage('/catalog?q=' . rawurlencode($payload));
        $I->seeResponseCodeIs(200);
        $I->seeInField('q', $payload);
        $I->dontSeeElement('[onfocus]');
        $I->dontSeeElement('input[autofocus]');
        $I->dontSeeElement('script:not([src])');
    }

    public function attributeBreakoutInDetailReturnParametersIsText(WebTester $I): void
    {
        $payload = '" onmouseover="alert(1)';
        $I->amOnPage('/catalog/101?q=' . rawurlencode($payload));
        $I->seeResponseCodeIs(200);
        $I->dontSeeElement('[onmouseover]');
        $I->click('Späť na zoznam');
        $I->seeInField('q', $payload);
    }

    public function invalidQueryIs400(WebTester $I): void
    {
        foreach (['/catalog?page=0', '/catalog?page=-1', '/catalog?page=abc', '/catalog?page=1.5', '/catalog?q[]=x', '/catalog?page=99999999999999999999', '/catalog?q=' . str_repeat('a', 201)] as $url) {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(400);
            $I->see('Neplatné vyhľadávanie');
            $I->seeLink('Späť na katalóg', '/catalog');
        }
    }

    public function maximumQueryLengthIsAccepted(WebTester $I): void
    {
        $I->amOnPage('/catalog?q=' . rawurlencode(str_repeat('ž', 200)));
        $I->seeResponseCodeIs(200);
    }

    public function detailShowsReadOnlyData(WebTester $I): void
    {
        $I->amOnPage('/catalog/104');
        $I->seeResponseCodeIs(200);
        $I->see('Ukážkový prevodový olej 75W-90', 'h5');
        $I->see('ID balenia v eOil', 'dt');
        $I->see('104');
        $I->dontSee('ProductHasPack');
        $I->see('20 l');
        $I->see('902.02');
        $I->see('902.20');
        $I->see('Aktívny');
        $I->see('Ukážkové údaje');
        $I->dontSeeElement('form[method="post"]');
        $I->dontSee('Upraviť');
        $I->dontSee('Uložiť');
    }

    public function detailWithoutUnitAndMrp(WebTester $I): void
    {
        $I->amOnPage('/catalog/108');
        $I->see('neuvedená');
        $I->see('Bez MRP referencie');
    }

    public function backLinkPreservesQueryAndPage(WebTester $I): void
    {
        $I->amOnPage('/catalog?q=ukážkový&page=2');
        $I->click('Detail');
        $I->seeResponseCodeIs(200);
        $I->click('Späť na zoznam');
        $I->seeResponseCodeIs(200);
        $I->seeInCurrentUrl('page=2');
        $I->seeInField('q', 'ukážkový');
        $I->see('Strana 2 z 2');
    }

    public function backLinkIgnoresForeignOrInvalidParameters(WebTester $I): void
    {
        $I->amOnPage('/catalog/101?q=x&page=0&return=https://evil.example');
        $I->seeResponseCodeIs(200);
        $I->seeLink('Späť na zoznam', '/catalog');
        $I->dontSeeInSource('evil.example');
    }

    public function missingDetailIs404(WebTester $I): void
    {
        $I->amOnPage('/catalog/999999');
        $I->seeResponseCodeIs(404);
        $I->see('Produkt sa nenašiel');
        $I->seeLink('Späť na zoznam', '/catalog');
    }

    public function invalidDetailIdIs400(WebTester $I): void
    {
        foreach (['/catalog/0', '/catalog/-3', '/catalog/abc', '/catalog/1e3', '/catalog/0101', '/catalog/99999999999999999999'] as $url) {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(400);
            $I->see('Neplatné ID');
        }
    }

    public function navigationMarksCatalogActive(WebTester $I): void
    {
        $I->amOnPage('/catalog/101');
        $I->seeElement('a.navbar-nav-link.active[href="/catalog"][aria-current="page"]');
    }
}
