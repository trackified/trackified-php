<?php

declare(strict_types=1);

namespace Trackified\Exception;

/** 409, e.g. errorCode "idempotency_in_progress": a request with the same idempotency key is still running. Not retried. */
class ConflictError extends TrackifiedError
{
}
