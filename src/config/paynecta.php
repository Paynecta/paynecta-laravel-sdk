<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Your API keys
    |--------------------------------------------------------------------------
    |
    | The pair from Developers, API keys in your Paynecta dashboard. The
    | secret half is exchanged once for a short-lived access token and is
    | never sent on an ordinary request; one endpoint sees it, and checking
    | it is that endpoint's whole job.
    |
    | The publishable key is not a secret and is safe in a page. The secret
    | key is, and belongs in .env like any other.
    |
    */

    'public_key' => env('PAYNECTA_PUBLIC_KEY'),
    'secret_key' => env('PAYNECTA_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Where to reach Paynecta
    |--------------------------------------------------------------------------
    |
    | Rarely changed. It is here for a staging environment rather than for
    | routine configuration, and it has a working default so that an install
    | with nothing set still talks to the right place.
    |
    */

    'base_url' => env('PAYNECTA_BASE_URL', 'https://api.paynecta.co.ke'),

    'timeout' => env('PAYNECTA_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | The page money lands in
    |--------------------------------------------------------------------------
    |
    | Which of your TapFlows a checkout collects into, by id or address. Set
    | it here and you need not name one on every call; pass one explicitly
    | and it wins.
    |
    */

    'tapflow' => env('PAYNECTA_TAPFLOW'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | The signing secret is what proves a delivery came from us. Paynecta
    | returns it when you register an address, and this package refuses every
    | delivery when it is not set.
    |
    | That refusal is deliberate. Until 2.0 this package verified nothing at
    | all: it checked that five fields were present and then acted on the
    | body. Anybody who knew the URL could post a payment.completed and have
    | an order marked paid. An unverified webhook endpoint is a way for a
    | stranger to write payments into your books, so an unconfigured secret
    | now fails closed rather than open.
    |
    | Tolerance is how old a delivery may be and still be accepted, in
    | seconds. Paynecta publishes five minutes. Without a window, a delivery
    | captured once can be replayed for ever, because a signature does not
    | expire on its own.
    |
    */

    'webhook_secret' => env('PAYNECTA_WEBHOOK_SECRET'),

    'webhook_tolerance' => env('PAYNECTA_WEBHOOK_TOLERANCE', 300),

    'webhook_path' => env('PAYNECTA_WEBHOOK_PATH', 'paynecta/webhook'),

    /*
    | No 'web' group and no CSRF. A webhook is a server posting to you, and
    | it holds no session cookie and no token. Verification is the signature.
    */

    'webhook_middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Not hearing about the same payment twice
    |--------------------------------------------------------------------------
    |
    | Deliveries can arrive more than once, by design: we would rather tell
    | you twice than not at all. Each carries a delivery id, and this
    | remembers the ones already handled so your listeners run once.
    |
    | Long enough to cover our retries and no longer. The cache is a
    | convenience, not a ledger: if it is cleared, a repeat gets through, so
    | your own handler should still be safe to run twice.
    |
    */

    'prevent_duplicates' => env('PAYNECTA_PREVENT_DUPLICATES', true),

    'duplicate_window' => env('PAYNECTA_DUPLICATE_WINDOW', 86400),

    /*
    |--------------------------------------------------------------------------
    | Telling us this application exists
    |--------------------------------------------------------------------------
    |
    | Registers the app under Developers, Installations in your dashboard, so
    | you can see it is connected and when it last spoke. Payments it takes
    | are marked with it, so one account running a shop and a market stall
    | can tell the takings apart.
    |
    | Off by default. It reports your application's URL and name, and that is
    | yours to opt into rather than ours to assume.
    |
    */

    'register_installation' => env('PAYNECTA_REGISTER_INSTALLATION', false),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Requests and responses, for working out what a call actually sent. Keys
    | and tokens are never written, whatever this is set to.
    |
    */

    'logging' => env('PAYNECTA_LOGGING', false),

    'log_channel' => env('PAYNECTA_LOG_CHANNEL', config('logging.default')),

];
