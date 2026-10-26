<?php

use App\Services\WhatsApp\LogWhatsAppGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp notification delivery
    |--------------------------------------------------------------------------
    |
    | PRD section 5 lists the WhatsApp API as not yet confirmed, and the
    | gateway provider has not been chosen by the business. Phase 14 therefore
    | ships the delivery architecture and a local `log` driver, but keeps the
    | feature switched off until a provider is decided and an adapter for it
    | exists.
    |
    | The `log` driver does not contact any provider. It writes the message to
    | the application log so the reminder flow can be exercised locally and in
    | staging. Because the message contains the order's secure status link,
    | that log output is as sensitive as the link itself.
    |
    */

    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Gateway driver
    |--------------------------------------------------------------------------
    |
    | The driver name is resolved through the `drivers` map below, so adding a
    | real gateway is a matter of writing one class that implements
    | App\Services\WhatsApp\WhatsAppGateway and registering it here. No
    | provider-specific URL, token, or payload shape is assumed by the
    | application itself.
    |
    */

    'driver' => env('WHATSAPP_DRIVER', 'log'),

    'drivers' => [
        'log' => [
            'class' => LogWhatsAppGateway::class,
        ],
    ],

    'reminders' => [

        /*
        | Attempts per reminder stage. Each retry only happens while the order
        | is still waiting for a proof and the deadline has not passed, so a
        | transient gateway failure has a bounded chance to recover without the
        | scheduler ever sending a reminder that is no longer relevant.
        |
        */

        'max_attempts' => (int) env('WHATSAPP_MAX_ATTEMPTS', 3),
    ],

];
