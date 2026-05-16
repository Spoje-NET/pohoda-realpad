# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

Pohoda-Realpad is a PHP 8.1+ application that integrates the Pohoda accounting system with the Realpad CRM system. It synchronizes bank movements from Pohoda to Realpad by extracting bank data, filtering it based on specific criteria, and sending matched payments via Realpad's API.

## Common Development Commands

### Setup and Installation
```bash
# Install dependencies
composer install

# Copy environment configuration
cp example.env .env
# Then edit .env with your Pohoda and Realpad credentials
```

### Development Workflow
```bash
# Run static code analysis
make static-code-analysis
vendor/bin/phpstan analyse --configuration=phpstan-default.neon.dist --memory-limit=-1

# Generate PHPStan baseline (when needed)
make static-code-analysis-baseline

# Run tests
make tests
vendor/bin/phpunit tests

# Apply coding standards
make cs
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --verbose

# Reformat code to PSR-12
make codingstandards
phpcbf --colors --standard=PSR12 --extensions=php --ignore=vendor/ src/
```

### Running the Application
```bash
# IMPORTANT: Run from src/ directory for development (makes ../vendor and ../.env paths work)
cd src/
php pohoda-bank-to-realpad.php

# Run with custom environment file
php pohoda-bank-to-realpad.php -e /path/to/.env

# Run with custom output file
php pohoda-bank-to-realpad.php -o /path/to/output.json

# Using the binary (after installation from .deb package)
/usr/bin/pohoda-bank-to-realpad
```

### Testing and Development
```bash
# Test against mock Realpad API
# Set REALPAD_POHODA_WS=http://localhost/pohoda-realpad/tests/realpad-mock.php in .env
php tests/realpad-mock.php

# Run application in development mode (from src/ directory)
cd src/
php pohoda-bank-to-realpad.php

# View mock logs
ls /tmp/realpad-mock-log/
```

## Architecture and Code Structure

### Core Components

**Main Script (`src/pohoda-bank-to-realpad.php`)**
- Single-file application that orchestrates the entire synchronization process
- Uses `mServer\Bank` class for Pohoda integration
- Implements direct cURL calls for Realpad API communication
- Follows a simple 3-step workflow:
  1. Extract bank movements from Pohoda using filters (`BV.ParSym IS NOT NULL AND BV.ParSym <> ''`)
  2. Save movements to temporary XML file
  3. Upload XML to Realpad API with authentication

**API Integration Pattern**
- Pohoda: Uses mServer library through `\mServer\Bank()` class
- Realpad: Direct cURL implementation with multipart form data
- Error handling includes comprehensive HTTP status code mapping (200, 201, 400, 401, 418, 500, etc.)

**Configuration Management**
- Uses `Ease\Shared` for environment configuration
- Required variables: `POHODA_URL`, `POHODA_USERNAME`, `POHODA_PASSWORD`
- Optional debugging and timeout configurations
- Supports custom Realpad API endpoints for testing

**Path Handling Strategy**
- Development: Uses relative paths (`../vendor`, `../.env`) - requires running from `src/` directory
- Production: Paths are rewritten during Debian packaging (`debian/rules`) to absolute system paths
- The `sed` commands in `debian/rules` transform paths for installed package deployment

### File Structure
```
src/pohoda-bank-to-realpad.php    # Main application logic
bin/pohoda-bank-to-realpad        # Bash wrapper script
tests/realpad-mock.php            # Mock Realpad API for testing
multiflexi/                       # MultiFlexi platform integration
debian/                           # Debian packaging files
```

### Key Dependencies
- `vitexsoftware/pohoda-connector`: Pohoda mServer API client
- `vitexsoftware/pohodaser`: Additional Pohoda utilities
- Development tools: PHPUnit, PHPStan, PHP-CS-Fixer

### Error Handling and Logging
- Uses `Ease\Shared` logging framework with configurable output (console, file)
- Comprehensive status messages for each operation step
- MultiFlexi-compliant JSON report format with status, timestamp, artifacts, and metrics
- Temporary file cleanup after processing
- Proper exception handling for connection failures

### MultiFlexi Integration
This application is designed as a MultiFlexi app with:
- JSON configuration schema for environment variables
- Docker support (`docker.io/spojenet/pohoda-realpad`)
- Debian package distribution
- Web interface integration for configuration management

## Development Standards

### Code Quality Requirements
- PHP 8.4+ with strict typing (`declare(strict_types=1)`)
- PSR-12 coding standard compliance
- English comments and error messages
- Comprehensive docblocks for functions and classes
- Type hints for all parameters and return types
- Unit tests for new classes using PHPUnit
- PHPStan static analysis with baseline support

### Security Practices
- Environment-based configuration (no hardcoded credentials)
- Proper exception handling with meaningful error messages
- Secure file handling with temporary file cleanup
- Input validation for API responses

### Internationalization
- Uses `_()` functions for translatable strings
- Currently supports Czech comments in legacy code but new code should use English

### Testing Strategy
- Mock API endpoint available for Realpad integration testing
- PHPUnit configuration with coverage reporting
- Integration testing through MultiFlexi platform
- Debian package testing in CI/CD pipeline

## Configuration

### Required Environment Variables
- `POHODA_URL`: mServer endpoint URL
- `POHODA_USERNAME`/`POHODA_PASSWORD`: API credentials
- `REALPAD_USERNAME`/`REALPAD_PASSWORD`: Realpad API credentials
- `REALPAD_PROJECT_ID`: Target project identifier

### Optional Configuration
- `POHODA_TIMEOUT`: Request timeout (default: 4000ms)
- `REALPAD_POHODA_WS`: Custom API endpoint for testing
- `RESULT_FILE`: Output file path (default: stdout)
- `APP_DEBUG`: Enable verbose logging