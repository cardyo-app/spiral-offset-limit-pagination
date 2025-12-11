# Spiral Framework Offset/Limit Pagination

Minimal offset/limit pagination package for Spiral Framework's DataGrid. This package provides a simple, standards-compliant way to add offset-based pagination to your Spiral applications.

## Features

- ✅ Simple offset/limit pagination (`?paginate[offset]=0&paginate[limit]=10`)
- ✅ Integrates seamlessly with Spiral DataGrid
- ✅ Works with existing filters and sorters
- ✅ Optional total count support
- ✅ Configurable page size limits (security/performance)
- ✅ No custom interceptors or response formatters needed
- ✅ Full test coverage (33 tests)

## Installation

```bash
composer require cardyo/spiral-offset-pagination
```

## Requirements

- PHP >= 8.1
- spiral/data-grid ^3.0

## Quick Start

### 1. Define a GridSchema with Pagination

```php
use Cardyo\SpiralOffsetPagination\Specification\Pagination\OffsetLimitPaginator;
use Spiral\DataGrid\GridSchema;
use Spiral\DataGrid\Specification\Value\IntValue;
use Spiral\DataGrid\Specification\Value\RangeValue;
use Spiral\DataGrid\Specification\Value\RangeValue\Boundary;

$schema = new GridSchema();

// Add paginator with default limit of 20, allowing 1-100 items per page
$schema->setPaginator(
    new OffsetLimitPaginator(
        defaultLimit: 20,
        limitValue: new RangeValue(
            new IntValue(),
            Boundary::including(1),
            Boundary::including(100)
        )
    )
);
```

### 2. Use in Your Controller

```php
use Cycle\ORM\ORMInterface;
use Spiral\DataGrid\Annotation\DataGrid;

class CustomerController
{
    public function __construct(
        private readonly ORMInterface $orm,
    ) {}

    #[DataGrid(grid: 'customers')]
    public function index()
    {
        return $this->orm->getRepository(Customer::class)->select();
    }

    protected function customersGrid(): GridSchema
    {
        $schema = new GridSchema();

        // Add filters
        $schema->addFilter('search', new Like('name', new StringValue()));

        // Add sorters
        $schema->addSorter('name', new Sorter('name'));
        $schema->addSorter('created', new Sorter('created_at'));

        // Add pagination
        $schema->setPaginator(
            new OffsetLimitPaginator(
                defaultLimit: 20,
                limitValue: new RangeValue(
                    new IntValue(),
                    Boundary::including(1),
                    Boundary::including(100)
                )
            )
        );

        return $schema;
    }
}
```

## Query Parameters

### Basic Pagination

```
GET /customers?paginate[offset]=0&paginate[limit]=10
```

Returns the first 10 items.

### Second Page

```
GET /customers?paginate[offset]=10&paginate[limit]=10
```

Returns items 11-20.

### With Filters

```
GET /customers?paginate[offset]=0&paginate[limit]=10&filter[search]=john
```

Returns first 10 customers where name contains 'john'.

### With Sorting

```
GET /customers?paginate[offset]=0&paginate[limit]=10&sort[created]=desc
```

Returns first 10 customers sorted by created_at descending.

### With Total Count

```
GET /customers?paginate[offset]=0&paginate[limit]=10&fetchCount=1
```

Returns first 10 items plus the total count in the response.

## Response Format

The standard Spiral GridResponse automatically formats the pagination data:

```json
{
  "status": 200,
  "data": [
    {"id": 1, "name": "John Doe"},
    {"id": 2, "name": "Jane Smith"}
  ],
  "pagination": {
    "offset": 0,
    "limit": 10
  }
}
```

### With Total Count

When `fetchCount=1` is provided:

```json
{
  "status": 200,
  "data": [...],
  "pagination": {
    "offset": 0,
    "limit": 10,
    "count": 250
  }
}
```

## Advanced Usage

### Custom Page Size Limits

```php
// Allow only specific page sizes
$paginator = new OffsetLimitPaginator(
    defaultLimit: 20,
    limitValue: new EnumValue(
        new IntValue(),
        10, 20, 50, 100  // Only these values allowed
    )
);
```

### Integration with Filters and Sorters

```php
$schema = new GridSchema();

// Add multiple filters
$schema->addFilter('search', new Like('name', new StringValue()));
$schema->addFilter('active', new Equals('is_active', new BoolValue()));

// Add multiple sorters
$schema->addSorter('name', new Sorter('name'));
$schema->addSorter('created', new Sorter('created_at'));
$schema->addSorter('updated', new Sorter('updated_at'));

// Add pagination
$schema->setPaginator(
    new OffsetLimitPaginator(
        defaultLimit: 20,
        limitValue: new RangeValue(
            new IntValue(),
            Boundary::including(1),
            Boundary::including(100)
        )
    )
);
```

Query example:
```
GET /customers?filter[search]=john&filter[active]=1&sort[created]=desc&paginate[offset]=20&paginate[limit]=10
```

## Edge Cases

### Negative Offset

Throws `InvalidPaginationException`:

```php
// This will throw an exception
GET /customers?paginate[offset]=-10&paginate[limit]=10
```

### Invalid Limit

Uses the default limit:

```php
// limit=200 is outside allowed range (1-100), so default (20) is used
GET /customers?paginate[offset]=0&paginate[limit]=200
```

### Missing Parameters

Uses default values:

```php
// No pagination parameters - uses offset=0, limit=20 (default)
GET /customers
```

### Offset Beyond Total

Returns empty array:

```php
// If there are only 50 records, this returns an empty array
GET /customers?paginate[offset]=100&paginate[limit]=10
```

## Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run only unit tests
vendor/bin/phpunit --testsuite=Unit

# Run only integration tests
vendor/bin/phpunit --testsuite=Integration
```

## Why This Package?

### Minimal and Focused

Unlike full-featured pagination packages, this package does exactly one thing: offset/limit pagination. It leverages Spiral's existing infrastructure:

- Uses Spiral's native `Limit` and `Offset` specifications
- Uses standard `GridResponse` for output
- Uses standard `#[DataGrid]` attribute
- No custom interceptors or response formatters

### Comparison

**This Package (Offset Pagination):**
- ~200 lines of code
- 0 custom writers (uses QueryWriter)
- Uses GridResponse
- Simple integer offset/limit
- Deep linking friendly

**Cursor Pagination:**
- ~2000+ lines of code
- 4 custom writers
- Custom response classes
- Opaque cursor encoding
- Strong consistency

Choose offset/limit for:
- Simple APIs
- Public-facing endpoints
- When deep linking is important
- When simplicity is preferred

Choose cursor for:
- Real-time data
- When consistency is critical
- Large datasets with frequent updates

## License

MIT

## Author

Leonid Meleshin (leon0399)
- Email: hello@leon0399.ru

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
