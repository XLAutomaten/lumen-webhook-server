<?php

return [
    'queue' => 'default_test',
    'http_verb' => 'POST',
    'tries' => 5,
    'backoff_strategy' => \Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy::class,
    'timeout_in_seconds' => 10,
    'signer' => \Spatie\WebhookServer\Signer\DefaultSigner::class,
    'signature_header_name' => 'X-Signature',
    'headers' => [],
    'tags' => [],
    'verify_ssl' => true,
];
