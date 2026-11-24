<?php

declare(strict_types=1);

namespace Trackified\Model;

enum TrackingStatus: string
{
    case NotYetScanned = 'not_yet_scanned';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case AvailableForPickup = 'available_for_pickup';
    case Delivered = 'delivered';
    case Exception = 'exception';
    case Returned = 'returned';
    case Unknown = 'unknown';
}
