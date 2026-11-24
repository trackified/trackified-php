<?php

declare(strict_types=1);

namespace Trackified\Exception;

/** 402: the plan's tracking quota is used up (errorCode "quota_exceeded"). */
class QuotaExceededError extends TrackifiedError
{
}
