<?php

declare(strict_types=1);

namespace App\Web\Auth;

use App\Auth\AuthSession;
use App\Auth\EoilSignInSettings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Header;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

use function is_string;

/** GET /login — explains the sign-in through eOil and offers the button. */
final readonly class LoginAction
{
    public function __construct(
        private EoilSignInSettings $settings,
        private AuthSession $authSession,
        private SignInResponses $responses,
        private WebViewRenderer $viewRenderer,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $return = $request->getQueryParams()['return'] ?? '/';
        $return = AuthSession::safeReturnPath(is_string($return) ? $return : '/');

        if (!$this->settings->enabled) {
            return $this->responses->redirect('/');
        }
        if ($this->authSession->currentUser() !== null) {
            return $this->responses->redirect($return);
        }

        return $this->viewRenderer
            ->render(__DIR__ . '/login', [
                'notice' => $this->authSession->pullNotice(),
                'startUrl' => '/login/start' . ($return === '/' ? '' : '?' . http_build_query(['return' => $return])),
            ])
            ->withHeader(Header::CACHE_CONTROL, 'no-store');
    }
}
