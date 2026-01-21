# CustomerIntel v1.0.0 Package Structure

## ZIP-Ready File Tree for Production

```
customerintel-v1.0.0.zip
│
└── customerintel/
    ├── classes/                        # Core plugin classes
    │   ├── clients/
    │   │   └── llm_client.php         # LLM provider integration
    │   ├── services/
    │   │   ├── assembler.php          # Report generation
    │   │   ├── cost_service.php       # Cost tracking
    │   │   ├── job_queue.php          # Async processing
    │   │   ├── nb_orchestrator.php    # NB processing engine
    │   │   ├── source_service.php     # Data source management
    │   │   ├── telemetry_service.php  # Metrics collection
    │   │   └── versioning_service.php # Snapshot & diff
    │   └── task/
    │       └── process_queue.php      # Scheduled task
    │
    ├── cli/                            # Command-line tools
    │   ├── check_schema_consistency.php
    │   ├── pre_deploy_check.php
    │   └── test_integration.php
    │
    ├── db/                             # Database definitions
    │   ├── access.php                 # Capabilities
    │   ├── install.xml                # Schema definition
    │   └── upgrade.php                # Upgrade scripts
    │
    ├── docs/                           # Documentation
    │   ├── FINAL_VALIDATION_SUMMARY.md
    │   ├── SCHEMA_ALIGNMENT_SUMMARY.md
    │   ├── SOURCE_SERVICE_IMPLEMENTATION.md
    │   └── TESTING_GUIDE.md
    │
    ├── lang/                           # Language strings
    │   └── en/
    │       └── local_customerintel.php
    │
    ├── styles/                         # CSS files
    │   └── customerintel.css
    │
    ├── export.php                      # Export interface
    ├── index.php                       # Main dashboard
    ├── lib.php                         # Library functions
    ├── settings.php                    # Plugin settings
    ├── version.php                     # Version information
    ├── view.php                        # Company view page
    ├── CHANGELOG.md                    # Release notes
    ├── DEPLOYMENT_INSTRUCTIONS.md      # Deployment guide
    └── README.md                       # Plugin documentation
```

## Files Excluded from Production Package

The following files/directories should NOT be included in the production ZIP:

```
EXCLUDED:
├── tests/                              # PHPUnit test files
│   ├── assembler_test.php
│   ├── cost_service_test.php
│   ├── integration_fullstack_test.php
│   ├── job_queue_test.php
│   ├── llm_client_test.php
│   ├── nb_orchestrator_test.php
│   └── versioning_service_test.php
│
├── Development files:
│   ├── .git/                          # Git repository
│   ├── .gitignore
│   ├── .phpunit.xml
│   ├── composer.json (dev dependencies)
│   ├── phpunit.xml
│   ├── *.log                          # Log files
│   ├── *.bak                          # Backup files
│   ├── *.tmp                          # Temporary files
│   └── node_modules/                  # NPM packages
│
└── Development docs:
    ├── COSTSERVICE_JOBQUEUE_IMPLEMENTATION.md
    ├── NB_ORCHESTRATOR_IMPLEMENTATION.md
    ├── REPORT_ASSEMBLY_IMPLEMENTATION.md
    ├── VERSIONING_DIFF_ENGINE_IMPLEMENTATION.md
    ├── db_field_analysis.json
    └── PACKAGE_STRUCTURE.md (this file)
```

## Creating the Production Package

### Automated Packaging Script

```bash
#!/bin/bash
# create_package.sh

VERSION="1.0.0"
PLUGIN_NAME="customerintel"
PACKAGE_NAME="${PLUGIN_NAME}-v${VERSION}"

echo "Creating CustomerIntel v${VERSION} package..."

# Create temp directory
TEMP_DIR=$(mktemp -d)
PACKAGE_DIR="${TEMP_DIR}/${PLUGIN_NAME}"

# Copy files
echo "Copying files..."
mkdir -p "${PACKAGE_DIR}"
cp -r classes "${PACKAGE_DIR}/"
cp -r cli "${PACKAGE_DIR}/"
cp -r db "${PACKAGE_DIR}/"
cp -r docs "${PACKAGE_DIR}/"
cp -r lang "${PACKAGE_DIR}/"
cp -r styles "${PACKAGE_DIR}/"

# Copy individual files
cp export.php "${PACKAGE_DIR}/"
cp index.php "${PACKAGE_DIR}/"
cp lib.php "${PACKAGE_DIR}/"
cp settings.php "${PACKAGE_DIR}/"
cp version.php "${PACKAGE_DIR}/"
cp view.php "${PACKAGE_DIR}/"
cp CHANGELOG.md "${PACKAGE_DIR}/"
cp DEPLOYMENT_INSTRUCTIONS.md "${PACKAGE_DIR}/"
cp README.md "${PACKAGE_DIR}/"

# Remove development files
echo "Removing development files..."
find "${PACKAGE_DIR}" -name "*.bak" -delete
find "${PACKAGE_DIR}" -name "*.log" -delete
find "${PACKAGE_DIR}" -name ".DS_Store" -delete

# Create ZIP
echo "Creating ZIP archive..."
cd "${TEMP_DIR}"
zip -r "${PACKAGE_NAME}.zip" "${PLUGIN_NAME}"

# Move to current directory
mv "${PACKAGE_NAME}.zip" .

# Cleanup
rm -rf "${TEMP_DIR}"

echo "Package created: ${PACKAGE_NAME}.zip"
echo "Size: $(du -h ${PACKAGE_NAME}.zip | cut -f1)"

# Verify package
echo ""
echo "Package contents:"
unzip -l "${PACKAGE_NAME}.zip" | head -20
echo "..."
echo ""
echo "Total files: $(unzip -l ${PACKAGE_NAME}.zip | tail -n 1 | awk '{print $2}')"
```

### Manual Packaging Commands

```bash
# From the local_customerintel directory:

# 1. Create package directory
mkdir -p /tmp/customerintel-package/customerintel

# 2. Copy production files
cp -r classes cli db docs lang styles *.php *.md /tmp/customerintel-package/customerintel/

# 3. Remove test files (if any were copied)
rm -rf /tmp/customerintel-package/customerintel/tests

# 4. Create ZIP
cd /tmp/customerintel-package
zip -r customerintel-v1.0.0.zip customerintel

# 5. Move to desired location
mv customerintel-v1.0.0.zip ~/Desktop/
```

## Package Validation

Before distribution, validate the package:

```bash
# 1. Extract and check structure
unzip -t customerintel-v1.0.0.zip

# 2. Verify no test files included
unzip -l customerintel-v1.0.0.zip | grep -E "(test|Test)" 
# Should return nothing

# 3. Check file count (approximately)
unzip -l customerintel-v1.0.0.zip | tail -1
# Should show ~50-60 files

# 4. Verify critical files present
unzip -l customerintel-v1.0.0.zip | grep -E "(version.php|install.xml|lib.php)"
# Should show all three files
```

## Distribution Checklist

- [ ] Version number updated in version.php
- [ ] CHANGELOG.md updated with release notes
- [ ] All tests passing
- [ ] Pre-deployment check passes
- [ ] Documentation updated
- [ ] Package created without test files
- [ ] Package size < 5MB
- [ ] Package validated
- [ ] Tagged in Git repository
- [ ] Uploaded to distribution server

## Package Information

- **Package Name**: customerintel-v1.0.0.zip
- **Estimated Size**: 1-2 MB
- **File Count**: ~55 files
- **PHP Version**: 7.4+
- **Moodle Version**: 4.0+
- **License**: GPL v3 or later

---

Generated: 2025-01-13
Version: 1.0.0