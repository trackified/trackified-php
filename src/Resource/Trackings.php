<?php

declare(strict_types=1);

namespace Trackified\Resource;

use Trackified\Model\BulkResultItem;
use Trackified\Model\Data;
use Trackified\Model\Page;
use Trackified\Model\Tracking;
use Trackified\Model\TrackingStatus;
use Trackified\TrackifiedClient;

/**
 * @phpstan-type TrackingInput array{tracking_number: string, carrier?: string, custom_id?: string, customer_email?: string}
 * @phpstan-type ListParams array{limit?: int, offset?: int, status?: TrackingStatus|string, carrier?: string, custom_id?: string, tracking_number?: string}
 */
final class Trackings extends Resource
{
    /**
     * Start tracking a number. Leave out carrier to detect it.
     *
     * @param TrackingInput $params
     * @param ?string       $idempotencyKey generated (UUID v4) when null and reused across retries
     */
    public function create(array $params, ?string $idempotencyKey = null): Tracking
    {
        return Tracking::fromArray($this->client->request('POST', '/v1/trackings', body: $params, idempotencyKey: $idempotencyKey ?? TrackifiedClient::uuid4()));
    }

    /**
     * @param list<TrackingInput> $trackings
     *
     * @return list<BulkResultItem> one per input, in order
     */
    public function bulkCreate(array $trackings, ?string $idempotencyKey = null): array
    {
        $body = $this->client->request('POST', '/v1/trackings/bulk', body: ['trackings' => $trackings], idempotencyKey: $idempotencyKey ?? TrackifiedClient::uuid4());

        return array_map(BulkResultItem::fromArray(...), Data::rows($body, 'data'));
    }

    /**
     * One page, newest first.
     *
     * @param ListParams $params
     *
     * @return Page<Tracking>
     */
    public function list(array $params = []): Page
    {
        return Page::fromArray($this->client->request('GET', '/v1/trackings', $params), Tracking::fromArray(...));
    }

    /**
     * Every matching tracking, fetching pages as you iterate.
     *
     * @param ListParams $params
     *
     * @return \Generator<int, Tracking>
     */
    public function all(array $params = []): \Generator
    {
        $params['offset'] ??= 0;
        do {
            $page = $this->list($params);
            yield from $page->data;
            $params['offset'] = $page->offset + \count($page->data);
        } while ($page->hasMore());
    }

    public function get(string $id): Tracking
    {
        return Tracking::fromArray($this->client->request('GET', '/v1/trackings/'.self::id($id)));
    }

    /** @param array{carrier?: string, custom_id?: string, customer_email?: string} $params */
    public function update(string $id, array $params): Tracking
    {
        return Tracking::fromArray($this->client->request('PUT', '/v1/trackings/'.self::id($id), body: $params));
    }

    /**
     * Stop tracking.
     *
     * @return array<mixed> {deleted: bool, id: string}
     */
    public function delete(string $id): array
    {
        return $this->client->request('DELETE', '/v1/trackings/'.self::id($id));
    }

    /**
     * Queue an immediate re-check with the carrier.
     *
     * @return array{queued: bool, tracking: Tracking}
     */
    public function retrack(string $id): array
    {
        $body = $this->client->request('POST', '/v1/trackings/'.self::id($id).'/retrack');

        return ['queued' => Data::bool($body, 'queued'), 'tracking' => Tracking::fromArray(Data::arr($body, 'tracking'))];
    }
}
