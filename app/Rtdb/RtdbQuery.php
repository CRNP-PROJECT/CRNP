<?php

declare(strict_types=1);

namespace App\Rtdb;

use InvalidArgumentException;

/**
 * A bounded RTDB query. Every queried field must be declared with `.indexOn`
 * in `database.rules.json`, or Firebase answers HTTP 400 "Index not defined".
 *
 * Values are quoted for the RTDB REST grammar (`orderBy="status"`); URL
 * encoding is left to the HTTP transport.
 */
final readonly class RtdbQuery
{
    /**
     * The `\uf8ff` sentinel sorts after every printable character, so a
     * prefix match is a range bounded by value and value + sentinel. It is
     * sent as the literal escape sequence the RTDB REST grammar expects.
     */
    private const PREFIX_SENTINEL = '\uf8ff';

    private function __construct(
        public string $orderBy,
        public ?string $equalTo = null,
        public ?string $startAt = null,
        public ?string $endAt = null,
        public ?int $limitToFirst = null,
        public ?int $limitToLast = null,
    ) {}

    public static function orderBy(string $field): self
    {
        if ($field === '') {
            throw new InvalidArgumentException('A query must order by a field.');
        }

        return new self($field);
    }

    public function equalTo(string|int $value): self
    {
        return new self(
            $this->orderBy,
            (string) $value,
            $this->startAt,
            $this->endAt,
            $this->limitToFirst,
            $this->limitToLast,
        );
    }

    public function startsWith(string $prefix): self
    {
        return new self(
            $this->orderBy,
            null,
            $prefix,
            $prefix . self::PREFIX_SENTINEL,
            $this->limitToFirst,
            $this->limitToLast,
        );
    }

    public function range(string|int $start, string|int $end): self
    {
        return new self(
            $this->orderBy,
            null,
            (string) $start,
            (string) $end,
            $this->limitToFirst,
            $this->limitToLast,
        );
    }

    public function startAt(string|int $start): self
    {
        return new self(
            $this->orderBy,
            null,
            (string) $start,
            $this->endAt,
            $this->limitToFirst,
            $this->limitToLast,
        );
    }

    public function limitToFirst(int $count): self
    {
        return new self(
            $this->orderBy,
            $this->equalTo,
            $this->startAt,
            $this->endAt,
            $count,
            $this->limitToLast,
        );
    }

    public function limitToLast(int $count): self
    {
        return new self(
            $this->orderBy,
            $this->equalTo,
            $this->startAt,
            $this->endAt,
            $this->limitToFirst,
            $count,
        );
    }

    /**
     * The RTDB REST query parameters, quoted for the REST grammar.
     *
     * @return array<string, string>
     */
    public function toQueryParams(): array
    {
        $params = ['orderBy' => '"' . $this->orderBy . '"'];

        if ($this->equalTo !== null) {
            $params['equalTo'] = '"' . $this->equalTo . '"';
        }

        if ($this->startAt !== null) {
            $params['startAt'] = '"' . $this->startAt . '"';
        }

        if ($this->endAt !== null) {
            $params['endAt'] = '"' . $this->endAt . '"';
        }

        if ($this->limitToFirst !== null) {
            $params['limitToFirst'] = (string) $this->limitToFirst;
        }

        if ($this->limitToLast !== null) {
            $params['limitToLast'] = (string) $this->limitToLast;
        }

        return $params;
    }
}
