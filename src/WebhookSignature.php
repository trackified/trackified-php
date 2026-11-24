<?php

declare(strict_types=1);

namespace Trackified;

use Trackified\Exception\SignatureVerificationError;
use Trackified\Model\WebhookEvent;

/**
 * Verifies the x-tracking-signature header: "t=<unix seconds>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>".
 */
final class WebhookSignature
{
    public const HEADER = 'x-tracking-signature';
    public const DEFAULT_TOLERANCE = 300;

    /**
     * @param string $payload the raw request body, exactly as received (not re-encoded JSON)
     * @param int    $now     unix seconds, defaults to time(); for tests
     */
    public static function verify(string $payload, string $header, string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): bool
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (2 !== \count($kv)) {
                return false;
            }
            if ('t' === $kv[0]) {
                $timestamp = $kv[1];
            } elseif ('v1' === $kv[0]) {
                $signatures[] = $kv[1];
            }
        }
        if (null === $timestamp || !ctype_digit($timestamp) || [] === $signatures) {
            return false;
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifies the signature and returns the parsed event.
     *
     * @throws SignatureVerificationError
     */
    public static function constructEvent(string $payload, string $header, string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): WebhookEvent
    {
        if (!self::verify($payload, $header, $secret, $tolerance, $now)) {
            throw new SignatureVerificationError('Webhook signature verification failed');
        }
        $data = json_decode($payload, true);
        if (!\is_array($data)) {
            throw new SignatureVerificationError('Webhook payload is not a JSON object');
        }

        return WebhookEvent::fromArray($data);
    }
}
