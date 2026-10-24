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

// Relocate the whole storage directory to /tmp.
//
// Every writable path the framework derives from storage_path() has to move,
// not just one of them: logs (storage/logs), the file cache
// (storage/framework/cache), file sessions (storage/framework/sessions) and
// compiled Blade templates (storage/framework/views) all live there. The
// deployed copy is read-only, so a single missed path is enough to break a
// request.
//
// Getting this wrong fails in a particularly opaque way. When logging cannot
// write its file, the exception handler throws *while reporting* the original
// exception, so nothing is rendered and nothing is logged: the response is a
// blank HTTP 500 with no Laravel error page and an empty function log.
//
// Application::storagePath() honours LARAVEL_STORAGE_PATH from $_ENV/$_SERVER,
// which moves all of the above in one go. Read while resolving configuration,
// so it must be set before the framework boots — a value assigned afterwards
// is ignored.
$storage = '/tmp/storage';

// Views are compiled lazily on the first request an instance serves rather
// than cached at build time. `view:cache` would write into the deployed,
// read-only storage/framework/views, which this path then overrides, so the
// pre-compiled files would never be read. Compiling on demand costs a slower
// cold start but is actually used, and warm invocations reuse it.
foreach ([
    $storage.'/framework/views',
    $storage.'/framework/cache/data',
    $storage.'/framework/cache/locks',
    $storage.'/framework/sessions',
    $storage.'/logs',
    $storage.'/app/private',
    $storage.'/app/public',
] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

putenv("LARAVEL_STORAGE_PATH={$storage}");
$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;

// Hand over to the application's own front controller.
require __DIR__.'/../public/index.php';
