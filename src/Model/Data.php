<?php

declare(strict_types=1);

namespace Trackified\Model;

/**
 * Typed reads from decoded JSON, tolerant of missing or unexpected fields.
 *
 * @internal
 */
final class Data
{
    /** @param array<mixed> $a */
    public static function str(array $a, string $key): string
    {
        return self::nstr($a, $key) ?? '';
    }

    /** @param array<mixed> $a */
    public static function nstr(array $a, string $key): ?string
    {
        $v = $a[$key] ?? null;

        return \is_scalar($v) ? (string) $v : null;
    }

    /** @param array<mixed> $a */
    public static function int(array $a, string $key): int
    {
        return self::nint($a, $key) ?? 0;
    }

    /** @param array<mixed> $a */
    public static function nint(array $a, string $key): ?int
    {
        $v = $a[$key] ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    /** @param array<mixed> $a */
    public static function float(array $a, string $key): float
    {
        $v = $a[$key] ?? null;

        return is_numeric($v) ? (float) $v : 0.0;
    }

    /** @param array<mixed> $a */
    public static function bool(array $a, string $key): bool
    {
        return true === ($a[$key] ?? null);
    }

    /**
     * @param array<mixed> $a
     *
     * @return array<mixed>
     */
    public static function arr(array $a, string $key): array
    {
        $v = $a[$key] ?? null;

        return \is_array($v) ? $v : [];
    }

    /**
     * @param array<mixed> $a
     *
     * @return list<array<mixed>>
     */
    public static function rows(array $a, string $key): array
    {
        $out = [];
        foreach (self::arr($a, $key) as $row) {
            if (\is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param array<mixed> $a
     *
     * @return list<string>
     */
    public static function strings(array $a, string $key): array
    {
        $out = [];
        foreach (self::arr($a, $key) as $v) {
            if (\is_string($v)) {
                $out[] = $v;
            }
        }

        return $out;
    }

    /**
     * Unknown event types (added server-side later) are dropped rather than failing.
     *
     * @param array<mixed> $a
     *
     * @return list<WebhookEventType>
     */
    public static function eventTypes(array $a, string $key): array
    {
        $out = [];
        foreach (self::strings($a, $key) as $v) {
            $type = WebhookEventType::tryFrom($v);
            if (null !== $type) {
                $out[] = $type;
            }
        }

        return $out;
    }
}
