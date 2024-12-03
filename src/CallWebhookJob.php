<?php

namespace Jmsfwk\WebhookServer;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

class CallWebhookJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /** @var string */
    public $webhookUrl;

    /** @var string */
    public $httpVerb;

    /** @var int */
    public $tries;

    /** @var int */
    public $requestTimeout;

    /** @var string */
    public $backoffStrategyClass;

    /** @var string */
    public $signerClass;

    /** @var array */
    public $headers = [];

    /** @var bool */
    public $verifySsl;

    /** @var string */
    public $queue;

    /** @var array */
    public $payload = [];

    /** @var array */
    public $meta = [];

    /** @var array */
    public $tags = [];

    /** @var string */
    public $errorType = null;

    /** @var string */
    public $errorMessage = null;

    /** @var \GuzzleHttp\Psr7\Response|null */
    private $response;

    public function handle()
    {
        /** @var \GuzzleHttp\Client $client */
        $client = app(Client::class);

        try {
            $this->response = $client->request($this->httpVerb, $this->webhookUrl, [
                'timeout' => $this->requestTimeout,
                'body' => json_encode($this->payload),
                'verify' => $this->verifySsl,
                'headers' => $this->headers,
            ]);

            if (!Str::startsWith($this->response->getStatusCode(), 2)) {
                throw new Exception('Webhook call failed');
            }

            $this->dispatchEvent(WebhookCallSucceededEvent::class);

            return;
        } catch (Exception $exception) {
            /** @var \Spatie\WebhookServer\BackoffStrategy\BackoffStrategy $backoffStrategry */
            $backoffStrategy = app($this->backoffStrategyClass);

            $waitInSeconds = $backoffStrategy->waitInSecondsAfterAttempt($this->attempts());

            $this->errorMessage = $exception->getMessage();
            $this->errorType = get_class($exception);

            $this->dispatchEvent(WebhookCallFailedEvent::class);

            $this->release($waitInSeconds);
        }

        if ($this->attempts() >= $this->tries) {
            $this->dispatchEvent(FinalWebhookCallFailedEvent::class);

            $this->delete();
        }
    }

    private function dispatchEvent(string $eventClass)
    {
        event(new $eventClass(
            $this->httpVerb,
            $this->webhookUrl,
            $this->payload,
            $this->headers,
            $this->meta,
            $this->tags,
            $this->attempts(),
            $this->response,
            $this->errorType,
            $this->errorMessage,
            $this->meta['uuid'] ?? (string) Str::uuid(), // Generate a UUID if not provided
            null // Pass null for transferStats if not available
        ));
    }

    public function tags()
    {
        return $this->tags;
    }
}

