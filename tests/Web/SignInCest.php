<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\SignIn;
use App\Tests\Support\WebTester;

/**
 * Sign-in through (mock) eOil for the HTTP-source app. Scenarios are set in the mock eOil.
 */
final class SignInCest
{
    public function guestIsSentToLoginAndReturnsToTheRequestedPage(WebTester $I): void
    {
        SignIn::reset($I);
        $I->stopFollowingRedirects();
        $I->amOnUrl(SignIn::APP . '/catalog?q=olej&page=1');
        $I->seeResponseCodeIs(302);
        $I->seeHttpHeader('Location', '/login?return=%2Fcatalog%3Fq%3Dolej%26page%3D1');
        $I->startFollowingRedirects();

        $I->amOnUrl(SignIn::APP . '/login?return=%2Fcatalog%3Fq%3Dolej%26page%3D1');
        $I->see('Prihlásenie do eOil ERP');
        $I->see('Heslo zadávate iba v eOil');
        $I->dontSeeElement('input[type=password]');
        $I->click('Prihlásiť sa cez eOil');

        $I->seeResponseCodeIs(200);
        $I->seeCurrentUrlEquals('/catalog?q=olej&page=1');
        $I->see('Testovací používateľ (mock)');
        $I->seeElement('form[action="/logout"][method="post"] input[name="_csrf"]');
    }

    public function everyPageButLoginAndHealthRequiresSignIn(WebTester $I): void
    {
        SignIn::reset($I);
        $I->stopFollowingRedirects();
        foreach (['/', '/catalog', '/catalog/501', '/unknown-page'] as $path) {
            $I->amOnUrl(SignIn::APP . $path);
            $I->seeResponseCodeIs(302);
        }
        foreach (['/login', '/health'] as $path) {
            $I->amOnUrl(SignIn::APP . $path);
            $I->seeResponseCodeIs(200);
        }
    }

    public function authorizeRequestCarriesStateAndPkce(WebTester $I): void
    {
        SignIn::reset($I);
        $I->stopFollowingRedirects();
        $I->amOnUrl(SignIn::APP . '/login/start');
        $I->seeResponseCodeIs(302);
        $location = (string) $I->grabHttpHeader('Location');
        $I->assertStringStartsWith(SignIn::MOCK . '/erp-auth/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $I->assertSame('code', $query['response_type']);
        $I->assertSame('eoil-erp', $query['client_id']);
        $I->assertSame(SignIn::APP . '/auth/callback', $query['redirect_uri']);
        $I->assertSame('S256', $query['code_challenge_method']);
        $I->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $query['code_challenge']);
        $I->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $query['state']);
        $I->assertArrayNotHasKey('client_secret', $query);
        $I->assertStringNotContainsString('synthetic-client-secret', $location);
    }

    public function deniedUserSeesAccessDenied(WebTester $I): void
    {
        SignIn::as($I, 'denied');
        $I->seeResponseCodeIs(403);
        $I->see('Prístup zamietnutý');
        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->see('Prihlásenie do eOil ERP');
    }

    public function userBlockedBeforeTokenExchangeSeesAccessDenied(WebTester $I): void
    {
        SignIn::as($I, 'token-denied');
        $I->seeResponseCodeIs(403);
        $I->see('Prístup zamietnutý');
    }

    public function unavailableTokenEndpointIsVisibleAndSanitized(WebTester $I): void
    {
        SignIn::as($I, 'token-unavailable');
        $I->seeResponseCodeIs(503);
        $I->see('Prihlásenie je dočasne nedostupné');
        $I->dontSeeInRawResponse('RAW-UPSTREAM-BODY');
    }

    public function guestInEoilSeesEoilLoginPrompt(WebTester $I): void
    {
        SignIn::as($I, 'guest');
        $I->seeResponseCodeIs(200);
        $I->see('Najprv sa prihláste do eOil');
    }

    public function callbackWithoutMatchingStateIsRejected(WebTester $I): void
    {
        SignIn::reset($I);
        $I->amOnUrl(SignIn::APP . '/auth/callback?code=abc&state=forged-state-value-0123456789');
        $I->seeResponseCodeIs(400);
        $I->see('Prihlásenie sa nepodarilo');

        // A real pending sign-in cannot be completed with another state either.
        $I->stopFollowingRedirects();
        $I->amOnUrl(SignIn::APP . '/login/start');
        $I->amOnUrl(SignIn::APP . '/auth/callback?code=abc&state=forged-state-value-0123456789');
        $I->seeResponseCodeIs(400);
    }

    public function replayedCallbackIsRejected(WebTester $I): void
    {
        SignIn::reset($I);
        $I->stopFollowingRedirects();
        $I->amOnUrl(SignIn::APP . '/login/start');
        $I->amOnUrl((string) $I->grabHttpHeader('Location'));
        $callback = (string) $I->grabHttpHeader('Location');
        $I->amOnUrl($callback);
        $I->seeResponseCodeIs(302);
        $I->amOnUrl($callback);
        $I->seeResponseCodeIs(400);
    }

    public function revokedAccessEndsTheSessionWithAccessDenied(WebTester $I): void
    {
        SignIn::as($I);
        $I->see('Testovací používateľ (mock)');
        $I->amOnUrl(SignIn::MOCK . '/__mock/revoke');

        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->seeResponseCodeIs(403);
        $I->see('Prístup zamietnutý');
        $I->dontSee('Testovací používateľ (mock)');

        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->see('Prihlásenie do eOil ERP');
    }

    public function expiredTokenIsRenewedAutomaticallyAndReturnsToTheSamePage(WebTester $I): void
    {
        SignIn::as($I);
        $I->amOnUrl(SignIn::MOCK . '/__mock/expire');

        $I->amOnUrl(SignIn::APP . '/catalog/501?q=abc');
        $I->seeResponseCodeIs(200);
        $I->seeCurrentUrlEquals('/catalog/501?q=abc');
        $I->see('Mock API: testovací olej', 'h5');
        $I->see('Testovací používateľ (mock)');
        $I->dontSee('Prihlásenie vypršalo');
    }

    public function renewalThatFailsImmediatelyDoesNotLoop(WebTester $I): void
    {
        SignIn::as($I);
        $I->amOnUrl(SignIn::MOCK . '/__mock/expire');
        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->see('Testovací používateľ (mock)');

        // The renewed token is rejected at once as well: no second automatic round within the interval.
        $I->amOnUrl(SignIn::MOCK . '/__mock/expire');
        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->seeResponseCodeIs(200);
        $I->seeCurrentUrlEquals('/login?return=%2Fcatalog');
        $I->see('Prihlásenie vypršalo');
    }

    public function renewalWithoutEoilSessionGoesToTheEoilLogin(WebTester $I): void
    {
        SignIn::as($I);
        // eOil session gone (mock shows its login prompt) and the ERP token no longer valid.
        $I->amOnUrl(SignIn::MOCK . '/__mock/reset?scenario=guest');

        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->see('Najprv sa prihláste do eOil');
    }

    public function renewalOfABlockedUserEndsInAccessDenied(WebTester $I): void
    {
        SignIn::as($I);
        $I->amOnUrl(SignIn::MOCK . '/__mock/reset?scenario=denied');

        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->seeResponseCodeIs(403);
        $I->see('Prístup zamietnutý');
        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->see('Prihlásenie do eOil ERP');
    }

    public function logoutRequiresPostWithCsrfAndEndsTheErpSession(WebTester $I): void
    {
        SignIn::as($I);
        $I->amOnUrl(SignIn::APP . '/logout');
        $I->seeResponseCodeIs(405);

        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->submitForm('form[action="/logout"]', ['_csrf' => 'forged']);
        $I->seeResponseCodeIs(422);

        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->click('Odhlásiť', 'form[action="/logout"]');
        $I->seeCurrentUrlEquals('/login');
        $I->see('Boli ste odhlásený z ERP');
        // No automatic sign-in right after an explicit sign-out, although the (mock) eOil session is valid.
        $I->amOnUrl(SignIn::APP . '/catalog');
        $I->seeCurrentUrlEquals('/login?return=%2Fcatalog');
        $I->see('Prihlásenie do eOil ERP');
    }

    public function sessionIdChangesOnSignIn(WebTester $I): void
    {
        SignIn::reset($I);
        $I->amOnUrl(SignIn::APP . '/login');
        $before = $I->grabCookie('ERPSESSID');
        $I->click('Prihlásiť sa cez eOil');
        $I->see('Testovací používateľ (mock)');
        $after = $I->grabCookie('ERPSESSID');
        $I->assertNotEmpty($after);
        $I->assertNotSame($before, $after);
    }

    public function sessionCookieIsHttpOnlyAndLax(WebTester $I): void
    {
        SignIn::reset($I);
        $I->amOnUrl(SignIn::APP . '/login');
        $cookie = (string) $I->grabHttpHeader('Set-Cookie');
        $I->assertStringContainsString('ERPSESSID=', $cookie);
        $I->assertStringContainsStringIgnoringCase('httponly', $cookie);
        $I->assertStringContainsStringIgnoringCase('samesite=lax', $cookie);
    }

    public function foreignReturnPathIsIgnored(WebTester $I): void
    {
        SignIn::reset($I);
        foreach (['//evil.example/x', 'https://evil.example/', '/\\evil.example'] as $return) {
            $I->amOnUrl(SignIn::APP . '/login?return=' . rawurlencode($return));
            $I->click('Prihlásiť sa cez eOil');
            $I->seeCurrentUrlEquals('/');
            $I->amOnUrl(SignIn::APP . '/catalog');
            $I->click('Odhlásiť', 'form[action="/logout"]');
        }
    }
}
