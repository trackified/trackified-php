<?php

declare(strict_types=1);

namespace Trackified\Tests;

use PHPUnit\Framework\TestCase;
use Trackified\Exception\SignatureVerificationError;
use Trackified\Model\TrackingStatus;
use Trackified\Model\WebhookEvent;
use Trackified\SignatureFailureReason;
use Trackified\WebhookSignature;

final class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_trackified_test_vector';
    private const BODY = '{"id":"evt_test","type":"tracking.updated","data":{"id":"trk_123"}}';
    private const HEADER = 't=1792560000,v1=19752dd2fca1872cc8aa6cc36c412fce3bbfa120480580bdb1ad9d7957dfc9f4';
    private const T = 1792560000;
    private const SECRET_B = 'whsec_trackified_test_vector_b';
    private const SIG_A = '19752dd2fca1872cc8aa6cc36c412fce3bbfa120480580bdb1ad9d7957dfc9f4';
    private const SIG_B = 'aaa4b846892609125e20b07a531d46e9e4614d8eec8d6dd3ecccae877b58111f';
    private const DUAL = 't=1792560000,v1='.self::SIG_B.',v1='.self::SIG_A;

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

    public function testDualSignatureVector(): void
    {
        self::assertTrue(WebhookSignature::verify(self::BODY, self::DUAL, self::SECRET, now: self::T));
        self::assertTrue(WebhookSignature::verify(self::BODY, self::DUAL, self::SECRET_B, now: self::T));
        self::assertTrue(WebhookSignature::verify(self::BODY, self::DUAL, [self::SECRET, self::SECRET_B], now: self::T));
        self::assertFalse(WebhookSignature::verify(self::BODY, self::DUAL, 'whsec_other', now: self::T));
    }

    public function testOnlySecondSignatureMatches(): void
    {
        self::assertTrue(WebhookSignature::verify(self::BODY, 't=1792560000,v1='.str_repeat('0', 64).',v1='.self::SIG_A, self::SECRET, now: self::T));
    }

    public function testVerifyDetailedReasons(): void
    {
        $cases = [
            ['', self::SECRET, SignatureFailureReason::Missing],
            [self::HEADER, '', SignatureFailureReason::Missing],
            [self::HEADER, [], SignatureFailureReason::Missing],
            ['garbage', self::SECRET, SignatureFailureReason::Malformed],
            ['t=abc,v1='.self::SIG_A, self::SECRET, SignatureFailureReason::Malformed],
            ['v1='.self::SIG_A, self::SECRET, SignatureFailureReason::Malformed],
            ['t=1792560000', self::SECRET, SignatureFailureReason::Malformed],
            ['t=1792560000,v1=zz', self::SECRET, SignatureFailureReason::Malformed],
            [self::HEADER, 'wrong', SignatureFailureReason::Mismatch],
        ];
        foreach ($cases as [$header, $secret, $reason]) {
            $r = WebhookSignature::verifyDetailed(self::BODY, $header, $secret, now: self::T);
            self::assertFalse($r->valid, $header);
            self::assertSame($reason, $r->reason, $header);
        }
        self::assertSame(SignatureFailureReason::Timestamp, WebhookSignature::verifyDetailed(self::BODY, self::HEADER, self::SECRET, now: self::T + 301)->reason);
        $ok = WebhookSignature::verifyDetailed(self::BODY, self::HEADER, self::SECRET, now: self::T);
        self::assertTrue($ok->valid);
        self::assertNull($ok->reason);
    }

    public function testConstructEventCarriesReason(): void
    {
        try {
            WebhookSignature::constructEvent(self::BODY, self::HEADER, self::SECRET, now: self::T + 999);
            self::fail('expected SignatureVerificationError');
        } catch (SignatureVerificationError $e) {
            self::assertSame(SignatureFailureReason::Timestamp, $e->reason);
            self::assertSame('timestamp', $e->reason->value);
        }
    }

    public function testTypedEventData(): void
    {
        $trk = ['id' => 'trk_1', 'status' => 'delivered'];
        $changed = WebhookEvent::fromArray(['type' => 'tracking.status_changed', 'data' => ['tracking' => $trk, 'previous_status' => 'in_transit', 'current_status' => 'delivered']]);
        $plain = WebhookEvent::fromArray(['type' => 'tracking.delivered', 'data' => $trk]);
        foreach ([$changed, $plain] as $e) {
            self::assertSame('trk_1', $e->tracking()->id);
            self::assertSame(TrackingStatus::Delivered, WebhookSignature::trackingOf($e)->status);
        }
        self::assertSame(TrackingStatus::InTransit, $changed->previousStatus());
        self::assertSame(TrackingStatus::Delivered, $changed->currentStatus());
        self::assertNull($plain->previousStatus());
    }
}
