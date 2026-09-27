<?php

declare(strict_types=1);

// Tests always run in the test environment with the synthetic fixture catalog,
// independent of the container's dev defaults. Scenarios that need another
// configuration start their own server with explicit variables (tests/Web.suite.yml).
foreach (['APP_ENV' => 'test', 'CATALOG_SOURCE' => 'fixture'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
}
foreach (['CATALOG_API_TIMEOUT', 'EOIL_API_BASE_URL', 'EOIL_AUTHORIZE_URL', 'EOIL_CLIENT_ID', 'EOIL_CLIENT_SECRET', 'ERP_REDIRECT_URI'] as $key) {
    putenv($key);
    unset($_ENV[$key]);
}

App\Environment::prepare();
