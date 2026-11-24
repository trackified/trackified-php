<?php

declare(strict_types=1);

namespace Trackified\Resource;

use Trackified\Model\ApiKey;
use Trackified\Model\Page;

final class ApiKeys extends Resource
{
    /**
     * The returned ApiKey's $token is shown only once.
     *
     * @param array{name?: string, mode?: 'test'|'live', scopes?: list<string>} $params
     */
    public function create(array $params = []): ApiKey
    {
        return ApiKey::fromArray($this->client->request('POST', '/v1/account/api-keys', body: $params));
    }

    /**
     * @param array{limit?: int, offset?: int} $params
     *
     * @return Page<ApiKey>
     */
    public function list(array $params = []): Page
    {
        return Page::fromArray($this->client->request('GET', '/v1/account/api-keys', $params), ApiKey::fromArray(...));
    }

    public function revoke(string $id): void
    {
        $this->client->request('DELETE', '/v1/account/api-keys/'.self::id($id));
    }
}
