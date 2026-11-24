<?php

declare(strict_types=1);

namespace Trackified\Tests;

use PHPUnit\Framework\TestCase;
use Trackified\Exception\SignatureVerificationError;
use Trackified\WebhookSignature;

final class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_trackified_test_vector';
    private const BODY = '{"id":"evt_test","type":"tracking.updated","data":{"id":"trk_123"}}';
    private const HEADER = 't=1792560000,v1=19752dd2fca1872cc8aa6cc36c412fce3bbfa120480580bdb1ad9d7957dfc9f4';
    private const T = 1792560000;

    public function testAcceptsSharedTestVector(): void
    {
        self::assertTrue(WebhookSignature::verify(self::BODY, self::HEADER, self::SECRET, now: self::T));
    }

    public function testRejectsFlippedHexDigit(): void
    {
        $header = substr(self::HEADER, 0, -1).'5';
        self::assertFalse(WebhookSignature::verify(self::BODY, $header, self::SECRET, now: self::T));
    }

    public function testRejectsDifferentBody(): void
    {
        self::assertFalse(WebhookSignature::verify(str_replace('trk_123', 'trk_124', self::BODY), self::HEADER, self::SECRET, now: self::T));
    }

    public function testRejectsTimestampOutsideTolerance(): void
    {
        self::assertFalse(WebhookSignature::verify(self::BODY, self::HEADER, self::SECRET, now: self::T + 301));
        self::assertFalse(WebhookSignature::verify(self::BODY, self::HEADER, self::SECRET, now: self::T - 301));
        self::assertTrue(WebhookSignature::verify(self::BODY, self::HEADER, self::SECRET, tolerance: 600, now: self::T + 301));
    }

    public function testRejectsMalformedHeaders(): void
    {
        foreach (['', 'garbage', 't=1792560000', 'v1=abc', 't=abc,v1=19752dd2', 't=1792560000;v1=x'] as $header) {
            self::assertFalse(WebhookSignature::verify(self::BODY, $header, self::SECRET, now: self::T), $header);
        }
    }

    public function testConstructEvent(): void
    {
        $event = WebhookSignature::constructEvent(self::BODY, self::HEADER, self::SECRET, now: self::T);
        self::assertSame('evt_test', $event->id);
        self::assertSame('tracking.updated', $event->type);
        self::assertSame(['id' => 'trk_123'], $event->data);

        $this->expectException(SignatureVerificationError::class);
        WebhookSignature::constructEvent(self::BODY, self::HEADER, 'wrong', now: self::T);
    }
}
