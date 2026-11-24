<?php

declare(strict_types=1);

namespace Trackified\Resource;

use Trackified\Model\Carrier;
use Trackified\Model\CarrierCandidate;
use Trackified\Model\CarrierResolution;
use Trackified\Model\Data;
use Trackified\Model\Page;

final class Carriers extends Resource
{
    /**
     * @param array{limit?: int, offset?: int} $params
     *
     * @return Page<Carrier>
     */
    public function list(array $params = []): Page
    {
        return Page::fromArray($this->client->request('GET', '/v1/carriers', $params), Carrier::fromArray(...));
    }

    public function get(string $id): Carrier
    {
        return Carrier::fromArray($this->client->request('GET', '/v1/carriers/'.self::id($id)));
    }

    /**
     * Map free text ("USPS Priority Mail", "dhl_express", "Correos de España") to a carrier we serve.
     *
     * @return ?CarrierResolution null when nothing we serve matches
     */
    public function resolve(string $name): ?CarrierResolution
    {
        $body = $this->client->request('GET', '/v1/carriers/resolve', ['name' => $name]);

        return null === Data::nstr($body, 'carrier') ? null : CarrierResolution::fromArray($body);
    }

    /**
     * Guess the carrier for a tracking number.
     *
     * @return list<CarrierCandidate> most likely first
     */
    public function detect(string $trackingNumber): array
    {
        $body = $this->client->request('GET', '/v1/carriers/detect', ['number' => $trackingNumber]);

        return array_map(CarrierCandidate::fromArray(...), Data::rows($body, 'candidates'));
    }

    /**
     * Search every known carrier, including ones not tracked yet.
     *
     * @param array{q?: string, support?: 'scraper'|'api'|'unsupported', limit?: int} $params
     *
     * @return array<mixed> {counts, total, data: list<array>}
     */
    public function catalog(array $params = []): array
    {
        return $this->client->request('GET', '/v1/carriers/catalog', $params);
    }

    /**
     * Validation status of each carrier integration.
     *
     * @return array<mixed> {counts, values}
     */
    public function status(): array
    {
        return $this->client->request('GET', '/v1/carriers/status');
    }
}
