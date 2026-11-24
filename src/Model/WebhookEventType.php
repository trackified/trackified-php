<?php

declare(strict_types=1);

namespace Trackified\Model;

enum WebhookEventType: string
{
    case Created = 'tracking.created';
    case Updated = 'tracking.updated';
    case StatusChanged = 'tracking.status_changed';
    case Delivered = 'tracking.delivered';
    case Exception = 'tracking.exception';
    case Returned = 'tracking.returned';
    case Expired = 'tracking.expired';
}
