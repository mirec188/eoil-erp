<?php

declare(strict_types=1);

use App\Web;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()
        ->routes(
            Route::get('/')
                ->action(Web\HomePage\Action::class)
                ->name('home'),
            Route::get('/health')
                ->action(Web\Health\Action::class)
                ->name('health'),
            Route::get('/catalog')
                ->action(Web\Catalog\ListAction::class)
                ->name('catalog/list'),
            Route::get('/catalog/{id}')
                ->action(Web\Catalog\DetailAction::class)
                ->name('catalog/detail'),
            Route::get('/login')
                ->action(Web\Auth\LoginAction::class)
                ->name('auth/login'),
            Route::get('/login/start')
                ->action(Web\Auth\StartAction::class)
                ->name('auth/start'),
            Route::get('/auth/callback')
                ->action(Web\Auth\CallbackAction::class)
                ->name('auth/callback'),
            Route::post('/logout')
                ->action(Web\Auth\LogoutAction::class)
                ->name('auth/logout'),
        ),
];
