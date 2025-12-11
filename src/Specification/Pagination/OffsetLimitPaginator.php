<?php

declare(strict_types=1);

namespace Cardyo\SpiralOffsetPagination\Specification\Pagination;

use Cardyo\SpiralOffsetPagination\Exception\InvalidPaginationException;
use Spiral\DataGrid\Specification\FilterInterface;
use Spiral\DataGrid\Specification\Pagination\Limit;
use Spiral\DataGrid\Specification\Pagination\Offset;
use Spiral\DataGrid\Specification\SequenceInterface;
use Spiral\DataGrid\Specification\ValueInterface;
use Spiral\DataGrid\SpecificationInterface;

/**
 * Offset/limit paginator for Spiral DataGrid.
 *
 * Parses pagination parameters from the format:
 * - page[offset]=0&page[limit]=10
 *
 * And generates Spiral's native Limit and Offset specifications.
 *
 * Features:
 * - Configurable default limit
 * - Restricted page sizes (security/performance)
 * - Automatic bounds checking (non-negative offset)
 * - Total count support via DataGrid counters
 *
 * Example:
 * ```php
 * use Spiral\DataGrid\Specification\Value\IntValue;
 * use Spiral\DataGrid\Specification\Value\RangeValue;
 * use Spiral\DataGrid\Specification\Value\RangeValue\Boundary;
 *
 * $paginator = new OffsetLimitPaginator(
 *     defaultLimit: 20,
 *     limitValue: new RangeValue(
 *         new IntValue(),
 *         Boundary::including(1),
 *         Boundary::including(100)
 *     )
 * );
 *
 * $schema = new GridSchema();
 * $schema->setPaginator($paginator);
 * ```
 */
final class OffsetLimitPaginator implements FilterInterface, SequenceInterface
{
    private int $offset = 0;

    private int $limit;

    /**
     * @param int $defaultLimit Default number of items per page
     * @param ValueInterface $limitValue Validator for allowed page sizes
     * @throws InvalidPaginationException
     */
    public function __construct(
        int $defaultLimit,
        private readonly ValueInterface $limitValue,
    ) {
        if ($defaultLimit < 1) {
            throw new InvalidPaginationException('Default limit must be a positive integer');
        }

        if (!$this->limitValue->accepts($defaultLimit)) {
            throw new InvalidPaginationException('Default limit must be one of the allowed limits');
        }

        $this->limit = $defaultLimit;
    }

    /**
     * Parse pagination parameters from input.
     *
     * Expected format:
     * - ['offset' => 0, 'limit' => 10]
     * - ['offset' => '20', 'limit' => '50']
     *
     * From query params: ?page[offset]=0&page[limit]=10
     *
     * @throws InvalidPaginationException
     */
    #[\Override]
    public function withValue(mixed $value): \Spiral\DataGrid\SpecificationInterface
    {
        $paginator = clone $this;

        if (!\is_array($value)) {
            return $paginator;
        }

        // Parse limit
        if (isset($value['limit']) && $paginator->limitValue->accepts($value['limit'])) {
            $paginator->limit = $paginator->limitValue->convert($value['limit']);
        }

        // Parse offset
        if (isset($value['offset'])) {
            if (!\is_numeric($value['offset'])) {
                throw new InvalidPaginationException('Offset must be a numeric value');
            }

            $offset = (int) $value['offset'];
            if ($offset < 0) {
                throw new InvalidPaginationException('Offset must be non-negative');
            }

            $paginator->offset = $offset;
        }

        return $paginator;
    }

    /**
     * Get current pagination state.
     *
     * This is what gets stored in GridResponse under 'pagination' key.
     *
     * @return array{offset: int, limit: int}
     */
    #[\Override]
    public function getValue(): array
    {
        return [
            'offset' => $this->offset,
            'limit' => $this->limit,
        ];
    }

    /**
     * Generate Limit and Offset specifications.
     *
     * These are applied by the existing QueryWriter.
     *
     * @return SpecificationInterface[]
     */
    #[\Override]
    public function getSpecifications(): array
    {
        $specifications = [
            new Limit($this->limit),
        ];

        if ($this->offset > 0) {
            $specifications[] = new Offset($this->offset);
        }

        return $specifications;
    }

    /**
     * Get current offset value.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Get current limit value.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }
}
