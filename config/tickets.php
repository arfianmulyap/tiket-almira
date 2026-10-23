<?php

return [

    /*
    |--------------------------------------------------------------------------
    | E-ticket email delivery
    |--------------------------------------------------------------------------
    |
    | PRD FR-09 sends the e-ticket by email only "jika layanan email sudah
    | dikonfigurasi". The provider is still an open question (PRD section 15
    | question 7), so delivery is opt-in: it stays off until the business
    | confirms a mail service and the MAIL_* credentials are set for the
    | target environment.
    |
    | Turning this on never affects ticket validity. Issuance and delivery are
    | separate steps and a delivery failure is logged, not fatal.
    |
    */

    'email_enabled' => env('TICKET_EMAIL_ENABLED', false),

];
