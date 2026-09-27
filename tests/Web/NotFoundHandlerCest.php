<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTester;

final class NotFoundHandlerCest
{
    public function nonExistentPage(WebTester $I): void
    {
        $I->amOnPage('/non-existent-page');
        $I->seeResponseCodeIs(404);
        $I->see('404 — stránka neexistuje');
        $I->see('/non-existent-page');
    }

    public function pathIsEscaped(WebTester $I): void
    {
        $I->amOnPage('/%3Cscript%3Ealert(1)%3C%2Fscript%3E');
        $I->seeResponseCodeIs(404);
        $I->dontSeeElement('script:not([src])');
    }

    public function returnHome(WebTester $I): void
    {
        $I->amOnPage('/non-existent-page');
        $I->click('Späť na prehľad');
        $I->seeResponseCodeIs(200);
        $I->see('Prehľad', 'h4');
    }
}
