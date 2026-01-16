<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Pagination handler.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Pagination;

use InvalidArgumentException;
use JsonSerializable;

class Paginator implements JsonSerializable
{
    public array $items;

    public readonly int $total;

    public readonly int $perPage;

    public readonly int $currentPage;

    public readonly int $lastPage;

    public readonly int $requestedPage;

    public function __construct(array $items, int $total, int $perPage, int $currentPage = 1)
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException('perPage must be greater than 0');
        }

        $this->requestedPage = $currentPage;

        $total = max(0, $total);

        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;

        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 0;
        $this->lastPage = $lastPage;

        $validLastPage = $lastPage > 0 ? $lastPage : 1;
        $boundedPage = max(1, min($this->requestedPage, $validLastPage));

        $this->currentPage = $boundedPage;
    }

    public static function make(array $items, int $total, int $perPage, int $currentPage = 1): self
    {
        return new self($items, $total, $perPage, $currentPage);
    }

    public function items(): array
    {
        return $this->items;
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function getTotalPages(): int
    {
        return $this->lastPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function nextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    public function previousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function isFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    public function isLastPage(): bool
    {
        return $this->currentPage === $this->lastPage && $this->lastPage > 0;
    }

    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function getLimit(): int
    {
        return $this->perPage;
    }

    public function getFrom(): ?int
    {
        if ($this->total === 0) {
            return null;
        }

        return $this->getOffset() + 1;
    }

    public function getTo(): ?int
    {
        if ($this->total === 0) {
            return null;
        }
        $to = $this->currentPage * $this->perPage;

        return $to > $this->total ? $this->total : $to;
    }

    public function exists(): bool
    {
        return $this->total > 0;
    }

    public function hasItems(): bool
    {
        return count($this->items) > 0;
    }

    public function isEmpty(): bool
    {
        return ! $this->hasItems();
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function isValid(): bool
    {
        if ($this->requestedPage < 1) {
            return false;
        }

        if ($this->total === 0) {
            return $this->requestedPage === 1;
        }

        return $this->requestedPage <= $this->lastPage;
    }

    public function getPagesInRange(int $range = 2): array
    {
        if ($this->lastPage <= 1) {
            return $this->lastPage === 1 ? [1] : [];
        }

        $start = max(1, $this->currentPage - $range);
        $end = min($this->lastPage, $this->currentPage + $range);

        $pages = range($start, $end);

        if ($start > 1) {
            array_unshift($pages, 1);
            if ($start > 2) {
                array_splice($pages, 1, 0, '...');
            }
        }

        if ($end < $this->lastPage) {
            if ($end < $this->lastPage - 1) {
                $pages[] = '...';
            }
            $pages[] = $this->lastPage;
        }

        return array_values(array_unique($pages));
    }

    public function toLinkArray(): array
    {
        return [
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'next_page' => $this->nextPage(),
            'prev_page' => $this->previousPage(),
            'first_page' => 1,
            'from' => $this->getFrom(),
            'to' => $this->getTo(),
            'requested_page' => $this->requestedPage,
        ];
    }

    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'from' => $this->getFrom(),
            'to' => $this->getTo(),
            'has_next' => $this->hasNextPage(),
            'has_prev' => $this->hasPreviousPage(),
            'exists' => $this->exists(),
            'has_items' => $this->hasItems(),
            'is_valid' => $this->isValid(),
            'requested_page' => $this->requestedPage,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
