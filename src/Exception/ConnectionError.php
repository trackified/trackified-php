<?php

declare(strict_types=1);

namespace Trackified\Exception;

/** network failure or timeout; no HTTP status */
class ConnectionError extends TrackifiedError
{
}
