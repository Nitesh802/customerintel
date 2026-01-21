# Target-Aware Synthesis Engine Setup

## Overview
Implementation of database table and view updates to support the Target-Aware Synthesis Engine functionality.

## Changes Made

### 1. Database Schema (install.xml)
- Added `local_ci_synthesis` table with fields:
  - `id` (PK, auto-increment)
  - `runid` (FK to local_ci_run, unique)
  - `htmlcontent` (LONGTEXT) - Final rendered Playbook HTML
  - `jsoncontent` (LONGTEXT) - Machine-readable JSON structure
  - `voice_report` (LONGTEXT) - Voice & style validation results  
  - `selfcheck_report` (LONGTEXT) - Self-check validation results
  - `createdat` (INT) - Creation timestamp
  - `updatedat` (INT) - Last modified timestamp

### 2. Database Upgrade (upgrade.php)
- Added version 2025102008 upgrade step
- Creates synthesis table if it doesn't exist
- Includes proper foreign key relationship to `local_ci_run`
- Adds unique index on `runid` field

### 3. View Report Updates (view_report.php)
- **Priority Logic**: Checks for synthesis data before using assembler
- **Synthesis Rendering**: Displays Intelligence Playbook when available
- **UI Toggle**: "Show Raw NB Results" button to switch between views
- **JavaScript**: Toggle functionality between synthesis and raw NB results

### 4. Version Update
- Updated to v1.0.18 (version 2025102008)
- Release name: "Target-Aware Synthesis Engine foundation"

## Testing
- Created `test_synthesis_setup.php` to verify table structure
- Tests table creation, column presence, and basic CRUD operations

## Usage
1. **Without Synthesis**: Existing behavior - shows assembler report or raw NB results
2. **With Synthesis**: Shows Intelligence Playbook by default with toggle option

## Next Steps
The foundation is now ready for implementing the actual synthesis engine components:
- NB normalization service
- Pattern detection algorithms  
- Target-relevance mapping
- Voice & style enforcement
- Self-check validation
- Citation enrichment

## Files Modified
- `local_customerintel/db/install.xml`
- `local_customerintel/db/upgrade.php` 
- `local_customerintel/view_report.php`
- `local_customerintel/version.php`
- `test_synthesis_setup.php` (new)
- `SYNTHESIS_SETUP_NOTES.md` (new)