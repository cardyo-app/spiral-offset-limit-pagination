<?php

declare(strict_types=1);

namespace Cardyo\Tests\SpiralOffsetPagination\Unit\Specification\Pagination;

use Cardyo\SpiralOffsetPagination\Exception\InvalidPaginationException;
use Cardyo\SpiralOffsetPagination\Specification\Pagination\OffsetLimitPaginator;
use PHPUnit\Framework\TestCase;
use Spiral\DataGrid\Specification\Pagination\Limit;
use Spiral\DataGrid\Specification\Pagination\Offset;
use Spiral\DataGrid\Specification\Value\IntValue;
use Spiral\DataGrid\Specification\Value\RangeValue;
use Spiral\DataGrid\Specification\Value\RangeValue\Boundary;

class OffsetLimitPaginatorTest extends TestCase
{
    private function createPaginator(int $defaultLimit = 20): OffsetLimitPaginator
    {
        return new OffsetLimitPaginator(
            defaultLimit: $defaultLimit,
            limitValue: new RangeValue(
                new IntValue(),
                Boundary::including(1),
                Boundary::including(100),
            ),
        );
    }

    // Constructor Tests

    public function testConstructorWithValidDefaults(): void
    {
        $paginator = $this->createPaginator(20);

        $this->assertSame(0, $paginator->getOffset());
        $this->assertSame(20, $paginator->getLimit());
    }

    public function testConstructorThrowsExceptionForNegativeDefaultLimit(): void
    {
        $this->expectException(InvalidPaginationException::class);
        $this->expectExceptionMessage('Default limit must be a positive integer');

        new OffsetLimitPaginator(
            defaultLimit: 0,
            limitValue: new RangeValue(
                new IntValue(),
                Boundary::including(1),
                Boundary::including(100),
            ),
        );
    }

    public function testConstructorThrowsExceptionForDefaultLimitNotInAllowedValues(): void
    {
        $this->expectException(InvalidPaginationException::class);
        $this->expectExceptionMessage('Default limit must be one of the allowed limits');

        new OffsetLimitPaginator(
            defaultLimit: 200, // Outside of 1-100 range
            limitValue: new RangeValue(
                new IntValue(),
                Boundary::including(1),
                Boundary::including(100),
            ),
        );
    }

    // withValue() Parsing Tests

    public function testWithValueParsesOffsetAndLimit(): void
    {
        $paginator = $this->createPaginator();
        $result = $paginator->withValue(['offset' => 40, 'limit' => 10]);

        $this->assertSame(40, $result->getOffset());
        $this->assertSame(10, $result->getLimit());
    }

    public function testWithValueParsesOffsetOnly(): void
    {
        $paginator = $this->createPaginator(20);
        $result = $paginator->withValue(['offset' => 60]);

        $this->assertSame(60, $result->getOffset());
        $this->assertSame(20, $result->getLimit()); // Uses default
    }

    public function testWithValueParsesLimitOnly(): void
    {
        $paginator = $this->createPaginator();
        $result = $paginator->withValue(['limit' => 50]);

        $this->assertSame(0, $result->getOffset()); // Stays at 0
        $this->assertSame(50, $result->getLimit());
    }

    public function testWithValueParsesStringNumbers(): void
    {
        $paginator = $this->createPaginator();
        $result = $paginator->withValue(['offset' => '20', 'limit' => '10']);

        $this->assertSame(20, $result->getOffset());
        $this->assertSame(10, $result->getLimit());
    }

    public function testWithValueHandlesNullInput(): void
    {
        $paginator = $this->createPaginator(20);
        $result = $paginator->withValue(null);

        $this->assertSame(0, $result->getOffset());
        $this->assertSame(20, $result->getLimit());
    }

    public function testWithValueHandlesNonArrayInput(): void
    {
        $paginator = $this->createPaginator(20);
        $result = $paginator->withValue('invalid');

        $this->assertSame(0, $result->getOffset());
        $this->assertSame(20, $result->getLimit());
    }

    public function testWithValueHandlesEmptyArray(): void
    {
        $paginator = $this->createPaginator(20);
        $result = $paginator->withValue([]);

        $this->assertSame(0, $result->getOffset());
        $this->assertSame(20, $result->getLimit());
    }

    // Validation Tests

    public function testWithValueThrowsExceptionForNegativeOffset(): void
    {
        $this->expectException(InvalidPaginationException::class);
        $this->expectExceptionMessage('Offset must be non-negative');

        $paginator = $this->createPaginator();
        $paginator->withValue(['offset' => -10]);
    }

    public function testWithValueThrowsExceptionForNonNumericOffset(): void
    {
        $this->expectException(InvalidPaginationException::class);
        $this->expectExceptionMessage('Offset must be a numeric value');

        $paginator = $this->createPaginator();
        $paginator->withValue(['offset' => 'invalid']);
    }

    public function testWithValueIgnoresInvalidLimit(): void
    {
        $paginator = $this->createPaginator(20);
        $result = $paginator->withValue(['limit' => 200]); // Outside allowed range

        $this->assertSame(20, $result->getLimit()); // Uses default
    }

    public function testWithValueIgnoresZeroLimit(): void
    {
        $paginator = $this->createPaginator(20);
        $result = $paginator->withValue(['limit' => 0]);

        $this->assertSame(20, $result->getLimit()); // Uses default
    }

    // Specification Generation Tests

    public function testGetSpecificationsWithOnlyLimit(): void
    {
        $paginator = $this->createPaginator(10);
        $specifications = $paginator->getSpecifications();

        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(Limit::class, $specifications[0]);
        $this->assertSame(10, $specifications[0]->getValue());
    }

    public function testGetSpecificationsWithOffsetAndLimit(): void
    {
        $paginator = $this->createPaginator();
        $result = $paginator->withValue(['offset' => 20, 'limit' => 10]);
        $specifications = $result->getSpecifications();

        $this->assertCount(2, $specifications);
        $this->assertInstanceOf(Limit::class, $specifications[0]);
        $this->assertInstanceOf(Offset::class, $specifications[1]);
        $this->assertSame(10, $specifications[0]->getValue());
        $this->assertSame(20, $specifications[1]->getValue());
    }

    public function testGetSpecificationsWithZeroOffset(): void
    {
        $paginator = $this->createPaginator();
        $result = $paginator->withValue(['offset' => 0, 'limit' => 10]);
        $specifications = $result->getSpecifications();

        // Offset of 0 should not generate an Offset specification
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(Limit::class, $specifications[0]);
    }

    // getValue() Tests

    public function testGetValueReturnsCorrectState(): void
    {
        $paginator = $this->createPaginator(20);
        $value = $paginator->getValue();

        $this->assertIsArray($value);
        $this->assertArrayHasKey('offset', $value);
        $this->assertArrayHasKey('limit', $value);
        $this->assertSame(0, $value['offset']);
        $this->assertSame(20, $value['limit']);
    }

    public function testGetValueReturnsUpdatedState(): void
    {
        $paginator = $this->createPaginator();
        $result = $paginator->withValue(['offset' => 40, 'limit' => 50]);
        $value = $result->getValue();

        $this->assertSame(40, $value['offset']);
        $this->assertSame(50, $value['limit']);
    }

    // Immutability Tests

    public function testWithValueReturnsNewInstance(): void
    {
        $original = $this->createPaginator();
        $modified = $original->withValue(['offset' => 10]);

        $this->assertNotSame($original, $modified);
        $this->assertSame(0, $original->getOffset());
        $this->assertSame(10, $modified->getOffset());
    }
}
