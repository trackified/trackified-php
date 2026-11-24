<?php

declare(strict_types=1);

namespace Trackified;

use Trackified\Exception\SignatureVerificationError;
use Trackified\Model\Tracking;
use Trackified\Model\WebhookEvent;

/**
 * Verifies the x-tracking-signature header: "t=<unix seconds>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>".
 * During a secret rotation the header carries one v1 per secret ("t=..,v1=<new>,v1=<old>"); any match is valid.
 */
final class WebhookSignature
{
    public const HEADER = 'x-tracking-signature';
    public const DEFAULT_TOLERANCE = 300;

    /**
     * @param string              $payload the raw request body, exactly as received (not re-encoded JSON)
     * @param string|list<string> $secret  the webhook secret, or several during a rotation
     * @param int                 $now     unix seconds, defaults to time(); for tests
     */
    public static function verify(string $payload, string $header, string|array $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): bool
    {
        return self::verifyDetailed($payload, $header, $secret, $tolerance, $now)->valid;
    }

    /**
     * Like verify(), but says why a signature was rejected.
     *
     * @param string|list<string> $secret
     */
    public static function verifyDetailed(string $payload, string $header, string|array $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): SignatureVerificationResult
    {
        $secrets = array_values(array_filter((array) $secret, static fn ($s) => '' !== $s));
        if ('' === trim($header) || [] === $secrets) {
            return new SignatureVerificationResult(false, SignatureFailureReason::Missing);
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (2 !== \count($kv)) {
                return new SignatureVerificationResult(false, SignatureFailureReason::Malformed);
            }
            if ('t' === $kv[0]) {
                $timestamp = $kv[1];
            } elseif ('v1' === $kv[0]) {
                if (1 !== preg_match('/^[0-9a-fA-F]{64}$/', $kv[1])) {
                    return new SignatureVerificationResult(false, SignatureFailureReason::Malformed);
                }
                $signatures[] = strtolower($kv[1]);
            }
        }
        if (null === $timestamp || !ctype_digit($timestamp) || [] === $signatures) {
            return new SignatureVerificationResult(false, SignatureFailureReason::Malformed);
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            return new SignatureVerificationResult(false, SignatureFailureReason::Timestamp);
        }

        $valid = false;
        foreach ($secrets as $s) {
            $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $s);
            foreach ($signatures as $signature) {
                // no early exit, so timing does not reveal which pair matched
                $valid = hash_equals($expected, $signature) || $valid;
            }
        }

        return $valid ? new SignatureVerificationResult(true) : new SignatureVerificationResult(false, SignatureFailureReason::Mismatch);
    }

    /**
     * Verifies the signature and returns the parsed event.
     *
     * @param string|list<string> $secret
     *
     * @throws SignatureVerificationError with $reason
     */
    public static function constructEvent(string $payload, string $header, string|array $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): WebhookEvent
    {
        $result = self::verifyDetailed($payload, $header, $secret, $tolerance, $now);
        if (!$result->valid) {
            throw new SignatureVerificationError('Webhook signature verification failed ('.$result->reason?->value.')', reason: $result->reason);
        }
        $data = json_decode($payload, true);
        if (!\is_array($data)) {
            throw new SignatureVerificationError('Webhook payload is not a JSON object', reason: SignatureFailureReason::Malformed);
        }

        return WebhookEvent::fromArray($data);
    }

    /** The Tracking of any event type. */
    public static function trackingOf(WebhookEvent $event): Tracking
    {
        return $event->tracking();
    }
}
