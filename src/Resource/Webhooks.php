<?php

declare(strict_types=1);

namespace Trackified\Resource;

use Trackified\Model\Page;
use Trackified\Model\Tracking;
use Trackified\Model\Webhook;
use Trackified\Model\WebhookEvent;
use Trackified\Model\WebhookEventType;
use Trackified\SignatureVerificationResult;
use Trackified\WebhookSignature;

final class Webhooks extends Resource
{
    /**
     * The returned Webhook's $secret is shown only once: store it.
     *
     * @param list<WebhookEventType|string>|null $eventTypes null subscribes to every event
     */
    public function create(string $url, ?array $eventTypes = null): Webhook
    {
        $body = ['url' => $url];
        if (null !== $eventTypes) {
            $body['event_types'] = self::types($eventTypes);
        }

        return Webhook::fromArray($this->client->request('POST', '/v1/webhooks', body: $body));
    }

    /**
     * @param array{limit?: int, offset?: int} $params
     *
     * @return Page<Webhook>
     */
    public function list(array $params = []): Page
    {
        return Page::fromArray($this->client->request('GET', '/v1/webhooks', $params), Webhook::fromArray(...));
    }

    /**
     * Change the URL, event types or enabled flag (at least one). The secret is unchanged.
     *
     * @param list<WebhookEventType|string>|null $eventTypes
     */
    public function update(string $id, ?string $url = null, ?array $eventTypes = null, ?bool $enabled = null): Webhook
    {
        $body = array_filter(['url' => $url, 'event_types' => null === $eventTypes ? null : self::types($eventTypes), 'enabled' => $enabled], static fn ($v) => null !== $v);
        if ([] === $body) {
            throw new \InvalidArgumentException('update() needs url, eventTypes or enabled');
        }

        return Webhook::fromArray($this->client->request('PATCH', '/v1/webhooks/'.self::id($id), body: $body));
    }

    /**
     * Issue a new secret ($secret on the result, shown once). Until $previousSecretExpiresAt, deliveries are signed
     * with both the new and the old secret.
     *
     * @param ?int $expiresInHours 0-168, default 24 on the server; 0 drops the old secret at once
     */
    public function rotateSecret(string $id, ?int $expiresInHours = null): Webhook
    {
        $body = null === $expiresInHours ? [] : ['expires_in_hours' => $expiresInHours];

        return Webhook::fromArray($this->client->request('POST', '/v1/webhooks/'.self::id($id).'/rotate-secret', body: $body));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/webhooks/'.self::id($id));
    }

    /**
     * Send a signed sample event to the webhook's URL.
     *
     * @return array<mixed> {signature, body}
     */
    public function test(string $id): array
    {
        return $this->client->request('POST', '/v1/webhooks/'.self::id($id).'/test');
    }

    /**
     * @see WebhookSignature::verify()
     *
     * @param string|list<string> $secret
     */
    public function verify(string $payload, string $header, string|array $secret, int $tolerance = WebhookSignature::DEFAULT_TOLERANCE, ?int $now = null): bool
    {
        return WebhookSignature::verify($payload, $header, $secret, $tolerance, $now);
    }

    /**
     * @see WebhookSignature::verifyDetailed()
     *
     * @param string|list<string> $secret
     */
    public function verifyDetailed(string $payload, string $header, string|array $secret, int $tolerance = WebhookSignature::DEFAULT_TOLERANCE, ?int $now = null): SignatureVerificationResult
    {
        return WebhookSignature::verifyDetailed($payload, $header, $secret, $tolerance, $now);
    }

    /**
     * @see WebhookSignature::constructEvent()
     *
     * @param string|list<string> $secret
     */
    public function constructEvent(string $payload, string $header, string|array $secret, int $tolerance = WebhookSignature::DEFAULT_TOLERANCE, ?int $now = null): WebhookEvent
    {
        return WebhookSignature::constructEvent($payload, $header, $secret, $tolerance, $now);
    }

    /** The Tracking of any event type. */
    public function trackingOf(WebhookEvent $event): Tracking
    {
        return $event->tracking();
    }

    /**
     * @param list<WebhookEventType|string> $types
     *
     * @return list<string>
     */
    private static function types(array $types): array
    {
        return array_map(static fn ($t) => $t instanceof WebhookEventType ? $t->value : $t, $types);
    }
}
