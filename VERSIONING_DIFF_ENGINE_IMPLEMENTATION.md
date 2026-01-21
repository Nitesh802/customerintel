# Versioning & Diff Engine Implementation Summary

## Implementation Overview
Successfully implemented comprehensive versioning and diff engine for Customer Intelligence Dashboard (local_customerintel) according to PRD Section 8.5 and Appendix 24.2 specifications.

## Components Delivered

### 1. Enhanced VersioningService (`classes/services/versioning_service.php`)
**Purpose**: Core service for snapshot creation, diff computation, and version history management

**Key Features Implemented**:
- **Snapshot Creation** (`create_snapshot()`):
  - Builds complete immutable JSON snapshots of runs
  - Includes NB results, citations, sources, and metadata
  - Records telemetry for duration and size
  - Automatically computes diffs with previous snapshots
  
- **Diff Computation** (`compute_diff()`):
  - Deep nested comparison of JSON structures
  - Tracks additions, changes, and removals
  - Special handling for citations
  - Stores diffs in database for reuse
  
- **Version History** (`get_history()`):
  - Returns chronological list of snapshots
  - Includes run metadata and user information
  - Formatted timestamps and durations
  
- **Telemetry Integration**:
  - `snapshot_creation_duration_ms`: Tracks snapshot creation time
  - `snapshot_size_kb`: Records snapshot storage size
  - `diff_field_changes`: Counts total changes in diff

**Methods Added**:
```php
public function create_snapshot(int $runid): int
public function compute_diff(int $fromid, int $toid): array
public function get_history(int $companyid): array
public function get_diff(int $snapshotid, int $previousid = null): ?array
public function get_reusable_snapshot(int $companyid, int $maxage = 2592000): ?int
public function format_diff_display(array $diff): string
protected function deep_compare_arrays(array $old, array $new, array &$changes, string $path = ''): void
protected function record_snapshot_telemetry(int $runid, int $snapshotid, float $duration, float $size): void
protected function record_diff_telemetry(int $runid, int $fromid, int $toid, int $changecount): void
```

### 2. CLI Utilities

#### `cli/create_snapshot.php`
**Purpose**: Manual snapshot creation for completed runs

**Features**:
- Create snapshot for specific run ID (`--runid=ID`)
- Create snapshot for latest company run (`--companyid=ID`)
- Force recreation of existing snapshots (`--force`)
- Verbose output mode (`--verbose`)
- Automatic diff computation with previous snapshots
- Telemetry display in verbose mode

**Usage Examples**:
```bash
# Create snapshot for run 123
php cli/create_snapshot.php --runid=123

# Create snapshot for latest run of company 456
php cli/create_snapshot.php --companyid=456

# Force recreation with verbose output
php cli/create_snapshot.php --runid=123 --force --verbose
```

#### `cli/show_diff.php`
**Purpose**: Display formatted diffs between snapshots

**Features**:
- Compare specific snapshots (`--from=ID --to=ID`)
- Show latest diff for company (`--companyid=ID --latest`)
- Multiple output formats (`--format=text|json|html`)
- Export to file (`--output=FILE`)
- Statistics-only mode (`--stats`)
- Caches and reuses existing diffs

**Usage Examples**:
```bash
# Show diff between snapshots 10 and 15
php cli/show_diff.php --from=10 --to=15

# Show latest diff for company 5
php cli/show_diff.php --companyid=5 --latest

# Export diff as JSON
php cli/show_diff.php --from=10 --to=15 --format=json --output=diff.json

# Show statistics only
php cli/show_diff.php --from=10 --to=15 --stats
```

### 3. PHPUnit Test Suite (`tests/versioning_service_test.php`)
**Coverage**: Comprehensive testing of all versioning functionality

**Test Cases Implemented**:
- `test_create_snapshot()`: Verifies snapshot creation and structure
- `test_compute_diff()`: Tests diff computation between snapshots
- `test_diff_additions()`: Validates detection of added fields
- `test_diff_removals()`: Validates detection of removed fields
- `test_diff_changes()`: Validates detection of changed values
- `test_nested_object_diff()`: Tests deep nested object comparison
- `test_citation_diff()`: Verifies citation tracking in diffs
- `test_get_history()`: Tests version history retrieval
- `test_get_reusable_snapshot()`: Tests snapshot freshness logic
- `test_format_diff_display()`: Tests diff formatting
- `test_get_or_create_diff()`: Tests diff caching mechanism

**Test Data Setup**:
- Creates test companies, runs, and NB results
- Generates realistic snapshot scenarios
- Includes helper methods for data modification

### 4. Integration Points

#### Report Assembly Integration
The VersioningService is already integrated with the report assembly system via `assembler.php`:

```php
// In assembler.php - dynamic diff loading
if ($comparisonid) {
    $versioningservice = new versioning_service();
    $diff = $versioningservice->get_diff($currentsnapshotid, $comparisonsnapshotid);
    // Apply diff highlighting to report elements
}
```

#### Database Schema Support
Utilizes existing tables from Step 1:
- `local_ci_snapshot`: Stores immutable JSON snapshots
- `local_ci_diff`: Caches computed diffs
- `local_ci_telemetry`: Records performance metrics

## Diff Format Specification (PRD Section 24.2)

The implementation follows the exact JSON structure specified:

```json
{
  "from_snapshot_id": 123,
  "to_snapshot_id": 124,
  "timestamp": 1699564800,
  "nb_diffs": [
    {
      "nb_code": "NB1",
      "added": {
        "new_field": "new_value"
      },
      "changed": {
        "summary": {
          "from": "old_summary",
          "to": "new_summary"
        }
      },
      "removed": {
        "deprecated_field": "old_value"
      },
      "citations": {
        "added": [101, 102],
        "removed": [50]
      }
    }
  ]
}
```

## Key Algorithms

### Deep Comparison Algorithm
The `deep_compare_arrays()` method implements recursive comparison:
1. Identifies all unique keys between old and new arrays
2. Categorizes each key as added, removed, or potentially changed
3. For nested structures, recursively compares
4. Handles both associative arrays (objects) and indexed arrays
5. Maintains path tracking for nested field references

### Telemetry Tracking
Three key metrics are tracked:
1. **Snapshot Creation Duration**: Time to build and store snapshot
2. **Snapshot Size**: Storage requirements in KB
3. **Field Change Count**: Number of differences detected

## Performance Considerations

1. **Diff Caching**: Diffs are computed once and stored for reuse
2. **Selective Loading**: Snapshots load only required data
3. **Batch Processing**: Telemetry records are batched where possible
4. **JSON Efficiency**: Uses compact JSON encoding

## Testing Instructions

### Running PHPUnit Tests
```bash
# Run all versioning tests
vendor/bin/phpunit local/customerintel/tests/versioning_service_test.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/ local/customerintel/tests/versioning_service_test.php
```

### Manual Testing with CLI

1. **Create Test Snapshot**:
```bash
php local/customerintel/cli/create_snapshot.php --runid=1 --verbose
```

2. **View Diff**:
```bash
php local/customerintel/cli/show_diff.php --from=1 --to=2 --format=text
```

3. **Export Diff**:
```bash
php local/customerintel/cli/show_diff.php --from=1 --to=2 --format=json --output=/tmp/diff.json
```

## Error Handling

The implementation includes comprehensive error handling:
- Missing snapshots trigger appropriate errors
- Invalid run IDs are validated before processing
- JSON parsing errors are caught and logged
- Database transaction safety for diff storage
- CLI scripts provide helpful error messages

## Future Enhancements (Optional)

1. **Diff Visualization**: Enhanced HTML formatting with side-by-side comparison
2. **Bulk Operations**: Batch snapshot creation for multiple runs
3. **Compression**: Optional snapshot compression for storage efficiency
4. **Incremental Diffs**: Chain multiple diffs for version ranges
5. **Audit Trail**: Track who viewed/accessed snapshots

## Compliance with PRD

✅ **PRD Section 8.5 - Versioning & Diffs**: Full implementation of snapshot creation and diff computation
✅ **PRD Section 24.2 - Diff JSON Example**: Exact format match for diff structure
✅ **PRD Section 15 - Reuse & Freshness**: Snapshot reusability with configurable freshness window
✅ **PRD Section 23.3 - Telemetry Keys**: All required telemetry metrics implemented

## Files Modified/Created

### Created:
- `/local_customerintel/cli/create_snapshot.php` - CLI for snapshot creation
- `/local_customerintel/cli/show_diff.php` - CLI for diff display
- `/local_customerintel/tests/versioning_service_test.php` - Comprehensive test suite

### Enhanced:
- `/local_customerintel/classes/services/versioning_service.php` - Added telemetry, improved diff computation

## Summary

The Versioning & Diff Engine implementation is complete and fully operational. All components have been implemented according to PRD specifications, with comprehensive testing and CLI utilities for operations. The system provides immutable snapshots, accurate diff computation with deep nested object support, and full telemetry tracking for performance monitoring.

Integration with the existing report assembly system ensures that diffs can be dynamically loaded and displayed in the Customer Intelligence Dashboard UI. The implementation is production-ready and includes all required error handling, logging, and performance optimizations.