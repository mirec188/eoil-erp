<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$root = __DIR__;
return (new Configuration())
    ->disableComposerAutoloadPathScan()
    ->setFileExtensions(['php'])
    ->addPathToScan($root . '/src', isDev: false)
    ->addPathToExclude($root . '/src/bootstrap.php')
    ->addPathToScan($root . '/src/bootstrap.php', isDev: true)
    ->addPathToScan($root . '/config', isDev: false)
    ->addPathToScan($root . '/public/index.php', isDev: false)
    ->addPathToScan($root . '/yii', isDev: false)
    ->addPathToScan($root . '/tests', isDev: true)
    ->addPathToExclude($root . '/tests/Support/_generated')
    ->ignoreErrorsOnPackage('yiisoft/config', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/di', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/view', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/router-fastroute', [ErrorType::UNUSED_DEPENDENCY])
    // Console runner for ./yii; no application commands exist yet.
    ->ignoreErrorsOnPackage('symfony/console', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/yii-console', [ErrorType::UNUSED_DEPENDENCY]);
