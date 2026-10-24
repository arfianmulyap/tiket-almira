<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cron endpoint secret
    |--------------------------------------------------------------------------
    |
    | Shared secret required to call the HTTP cron endpoint that runs the
    | overdue-order sweep (PRD FR-08). The endpoint exists because a serverless
    | host has no always-on process to run `schedule:run`.
    |
    | Empty by default, and CronController treats empty as "disabled" and
    | returns 404. That is intentional: the endpoint changes order state, so it
    | must never be reachable just because someone deployed without setting a
    | variable.
    |
    | On Vercel, name the variable CRON_SECRET exactly. Vercel reads that name
    | and sends it automatically as `Authorization: Bearer <secret>` on every
    | cron invocation, so no extra wiring is needed. Generate it with something
    | unguessable, for example `php artisan key:generate --show`.
    |
    */

    'secret' => env('CRON_SECRET', ''),

];
