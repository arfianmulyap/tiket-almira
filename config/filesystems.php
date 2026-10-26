<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Poster disk
    |--------------------------------------------------------------------------
    |
    | Disk that stores and serves event posters (PRD FR-03). Named here rather
    | than hardcoded in EventPosterService because a serverless host has no
    | persistent local filesystem: on Vercel every write outside /tmp fails and
    | /tmp itself is discarded when the function instance goes away, so posters
    | must live on object storage instead.
    |
    | Defaults to `public` so local development and the test suite keep using
    | the local disk they fake. Set POSTER_DISK=s3 in a serverless environment.
    |
    */

    'poster_disk' => env('POSTER_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        /*
         * Private disk. Holds payment proofs (PRD FR-06), which are personal
         * data and must never be reachable over HTTP.
         *
         * `serve` is false, changed from the Laravel default of true. With it
         * enabled the framework registers a GET /storage/{path} route over
         * this disk's root, which would expose every payment proof to anyone
         * able to produce a signed URL — bypassing OrderPolicy entirely.
         * Proofs are streamed only by Admin\PaymentProofController, which
         * authorises each request. Do not re-enable this.
         */
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Public object storage. Holds posters when the application runs
         * somewhere without a writable local disk. `visibility` is public
         * because posters are shown on the public ordering page and are
         * served straight from the bucket, not through Laravel.
         */
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Private object storage. The cloud counterpart of the `local` disk:
         * holds payment proofs (PRD FR-06), which are personal data.
         *
         * `visibility` is private, so objects are not readable by URL even if
         * the bucket name is known. Proofs are still streamed only by
         * Admin\PaymentProofController, which authorises every request, so the
         * access rule is unchanged by moving the bytes to a bucket.
         *
         * A separate bucket from `s3` is strongly preferred: it makes the
         * public/private split a property of the infrastructure rather than of
         * one config line. Defaults to AWS_BUCKET only so a single-bucket
         * setup still functions for internal testing.
         */
        's3_private' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_PRIVATE_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
