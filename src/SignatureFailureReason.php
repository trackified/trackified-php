<?php

declare(strict_types=1);

namespace Trackified;

/** Why a webhook signature was rejected. */
enum SignatureFailureReason: string
{
    /** empty or absent header or secret */
    case Missing = 'missing';
    /** no t or no v1, non-numeric t, or bad hex */
    case Malformed = 'malformed';
    /** t is outside the tolerance */
    case Timestamp = 'timestamp';
    /** no v1 matches any secret */
    case Mismatch = 'mismatch';
}
