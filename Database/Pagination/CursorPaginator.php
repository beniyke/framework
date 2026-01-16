<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Cursor-based pagination handler.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Pagination;

use InvalidArgumentException;
use JsonSerializable;

class CursorPaginator implements JsonSerializable
{
    public array $items;

    public readonly int $perPage;

    public readonly ?string $nextCursor;

    public readonly ?string $previousCursor;

    public readonly string $cursorColumn;

    private readonly bool $hasPreviousPage;

    private const CURSOR_SEPARATOR = ':';

    public function __construct(array $items, int $perPage, string $cursorColumn, ?string $startingAfter = null, ?string $startingBefore = null)
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException('perPage must be greater than 0');
        }

        if (empty($cursorColumn)) {
            throw new InvalidArgumentException('cursorColumn is required');
        }

        $this->items = $items;
        $this->perPage = $perPage;
        $this->cursorColumn = $cursorColumn;
        $this->hasPreviousPage = $startingAfter !== null || $startingBefore !== null;
        $this->nextCursor = $this->calculateNextCursor($items, $cursorColumn);
        $previousBoundary = $this->calculatePreviousCursor($items, $cursorColumn);
        $this->previousCursor = $this->hasPreviousPage ? $previousBoundary : null;
    }

    public static function after(
        array $items,
        int $perPage,
        string $cursorColumn,
        ?string $after = null
    ): self {
        return new self($items, $perPage, $cursorColumn, $after);
    }

    public static function before(
        array $items,
        int $perPage,
        string $cursorColumn,
        ?string $before = null
    ): self {
        return new self($items, $perPage, $cursorColumn, null, $before);
    }

    private function encodeCursor(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return base64_encode($this->cursorColumn . self::CURSOR_SEPARATOR . $value);
    }

    private function decodeCursor(string $cursor): ?string
    {
        $decoded = base64_decode($cursor, true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode(self::CURSOR_SEPARATOR, $decoded, 2);

        if (count($parts) !== 2) {
            return null;
        }

        if ($parts[0] !== $this->cursorColumn) {
            return null;
        }

        return $parts[1];
    }

    private function calculateNextCursor(array $items, string $cursorColumn): ?string
    {
        if (empty($items)) {
            return null;
        }

        $lastItem = end($items);
        $value = $lastItem->{$cursorColumn} ?? ($lastItem->attributes[$cursorColumn] ?? null) ?? ($lastItem[$cursorColumn] ?? null);

        return $value !== null ? $this->encodeCursor($value) : null;
    }

    private function calculatePreviousCursor(array $items, string $cursorColumn): ?string
    {
        if (empty($items)) {
            return null;
        }

        $firstItem = reset($items);
        $value = $firstItem->{$cursorColumn} ?? ($firstItem->attributes[$cursorColumn] ?? null) ?? ($firstItem[$cursorColumn] ?? null);

        return $value !== null ? $this->encodeCursor($value) : null;
    }

    public function hasMore(): bool
    {
        return count($this->items) === $this->perPage;
    }

    public function hasPrevious(): bool
    {
        return $this->hasPreviousPage;
    }

    public function exists(): bool
    {
        return ! empty($this->items);
    }

    public function hasItems(): bool
    {
        return $this->exists();
    }

    public function isEmpty(): bool
    {
        return ! $this->hasItems();
    }

    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function getPreviousCursor(): ?string
    {
        return $this->previousCursor;
    }

    public function toLinkArray(): array
    {
        return [
            'per_page' => $this->perPage,
            'next_cursor' => $this->getNextCursor(),
            'previous_cursor' => $this->getPreviousCursor(),
            'has_more' => $this->hasMore(),
            'has_previous' => $this->hasPrevious(),
        ];
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'pagination' => $this->toLinkArray(),
            'exists' => $this->exists(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
