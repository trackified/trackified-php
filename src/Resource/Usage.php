<?php

declare(strict_types=1);

namespace Trackified\Resource;

use Trackified\Model\Usage as UsageModel;

final class Usage extends Resource
{
    /** This billing period's usage and limits (GET /v1/account/usage). */
    public function get(): UsageModel
    {
        return UsageModel::fromArray($this->client->request('GET', '/v1/account/usage'));
    }
}
