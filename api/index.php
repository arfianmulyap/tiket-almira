<?php

/*
|--------------------------------------------------------------------------
| Serverless entrypoint
|--------------------------------------------------------------------------
|
| Vercel invokes files under /api as functions, so this file stands in for
| public/index.php. It only prepares the writable paths the framework needs
| and then hands over to the real front controller, so there is one bootstrap
| path and this file cannot drift away from it.
|
| The filesystem is read-only except /tmp, which is why the redirects below
| exist. /tmp is also per-instance and discarded without warning, so nothing
| that must survive a request may be written there. Uploaded files are the
| obvious case: posters and payment proofs go to object storage instead
| (POSTER_DISK / PAYMENT_PROOF_DISK).
|
*/

// Writable home for the compiled Blade templates.
//
// Views are compiled lazily here on the first request an instance serves,
// rather than cached at build time. `view:cache` would write them into the
// deployed, read-only storage/framework/views, which this path then overrides
// — the pre-compiled files would never be read, so the build step would cost
// time and provide nothing. Compiling on demand into /tmp is slower on a cold
// start but is actually used, and warm invocations reuse it.
//
// Set before the framework boots: config/view.php reads VIEW_COMPILED_PATH
// while resolving configuration, and a value set afterwards is ignored.
$compiled = '/tmp/storage/framework/views';

if (! is_dir($compiled)) {
    mkdir($compiled, 0755, true);
}

putenv("VIEW_COMPILED_PATH={$compiled}");
$_ENV['VIEW_COMPILED_PATH'] = $compiled;
$_SERVER['VIEW_COMPILED_PATH'] = $compiled;

// Remaining framework directories. The app does not depend on these at
// runtime (sessions and cache are in the database, logs go to stderr), but
// they are created so any incidental write lands somewhere writable rather
// than raising a permission error mid-request.
foreach ([
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
    '/tmp/storage/app/private',
    '/tmp/storage/app/public',
] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

// Hand over to the application's own front controller.
require __DIR__.'/../public/index.php';
