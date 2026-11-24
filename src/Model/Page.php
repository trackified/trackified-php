<?php

declare(strict_types=1);

namespace Trackified\Model;

/**
 * One page of a list endpoint.
 *
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Page implements \IteratorAggregate, \Countable
{
    /** @param list<T> $data */
    public function __construct(
        public readonly array $data,
        public readonly int $limit,
        public readonly int $offset,
        public readonly int $total,
    ) {
    }

    /**
     * @template U
     *
     * @param array<mixed>              $body
     * @param callable(array<mixed>): U $map
     *
     * @return self<U>
     *
     * @internal
     */
    public static function fromArray(array $body, callable $map): self
    {
        $p = Data::arr($body, 'pagination');

        return new self(array_map($map, Data::rows($body, 'data')), Data::int($p, 'limit'), Data::int($p, 'offset'), Data::int($p, 'total'));
    }

    public function hasMore(): bool
    {
        return [] !== $this->data && $this->offset + \count($this->data) < $this->total;
    }

    /** @return \ArrayIterator<int, T> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return \count($this->data);
    }
}
