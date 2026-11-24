<?php

declare(strict_types=1);

namespace Trackified\Resource;

use Trackified\Model\Page;
use Trackified\Model\Webhook;
use Trackified\Model\WebhookEvent;
use Trackified\Model\WebhookEventType;
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
            $body['event_types'] = array_map(static fn ($t) => $t instanceof WebhookEventType ? $t->value : $t, $eventTypes);
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

    /** @see WebhookSignature::verify() */
    public function verify(string $payload, string $header, string $secret, int $tolerance = WebhookSignature::DEFAULT_TOLERANCE, ?int $now = null): bool
    {
        return WebhookSignature::verify($payload, $header, $secret, $tolerance, $now);
    }

    /** @see WebhookSignature::constructEvent() */
    public function constructEvent(string $payload, string $header, string $secret, int $tolerance = WebhookSignature::DEFAULT_TOLERANCE, ?int $now = null): WebhookEvent
    {
        return WebhookSignature::constructEvent($payload, $header, $secret, $tolerance, $now);
    }
}
