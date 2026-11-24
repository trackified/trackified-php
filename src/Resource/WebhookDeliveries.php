<?php

declare(strict_types=1);

namespace Trackified\Resource;

use Trackified\Model\Page;
use Trackified\Model\WebhookDelivery;

final class WebhookDeliveries extends Resource
{
    /**
     * Delivery attempts, newest first.
     *
     * @param array{limit?: int, offset?: int} $params
     *
     * @return Page<WebhookDelivery>
     */
    public function list(array $params = []): Page
    {
        return Page::fromArray($this->client->request('GET', '/v1/webhook-deliveries', $params), WebhookDelivery::fromArray(...));
    }
}
