<?php

declare(strict_types=1);

// Tests always run in the test environment with the synthetic fixture catalog,
// independent of the container's dev defaults. Scenarios that need another
// configuration start their own server with explicit variables (tests/Web.suite.yml).
foreach (['APP_ENV' => 'test', 'CATALOG_SOURCE' => 'fixture'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
}
foreach (['CATALOG_API_BASE_URL', 'CATALOG_API_TOKEN', 'CATALOG_API_TIMEOUT'] as $key) {
    putenv($key);
    unset($_ENV[$key]);
}

App\Environment::prepare();
