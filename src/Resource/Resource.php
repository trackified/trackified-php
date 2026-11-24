<?php

declare(strict_types=1);

namespace Trackified\Resource;

use Trackified\TrackifiedClient;

/** @internal */
abstract class Resource
{
    public function __construct(protected readonly TrackifiedClient $client)
    {
    }

    protected static function id(string $id): string
    {
        return rawurlencode($id);
    }
}
