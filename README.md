# Pohoda-Realpad Integration

![logo](pohoda-realpad.svg?raw=true)

This project integrates the Pohoda accounting system with the Realpad CRM system, enabling seamless synchronization of bank movements and payments.

## Features

- **Bank Movements Synchronization**: Extracts bank movements from Pohoda and filters them based on specific criteria.
- **Realpad API Integration**: Sends filtered payments to the Realpad system using their API.
- **Error Handling and Logging**: Provides detailed status messages and logs for every synchronization attempt.

## Requirements

- PHP 8.1 or higher
- Composer
- Curl extension enabled in PHP
- Access to Pohoda and Realpad systems
- Valid Realpad API credentials

## Configuration

Copy `example.env` to `.env` and adjust the values to match your environment:

```shell
cp example.env .env
```

**Configuration options:**

- `APP_DEBUG` — Enable debug mode (`true`/`false`)
- `EASE_LOGGER` — Logging output (e.g., `console`)
- `POHODA_ICO` — Company identifier (IČO)
- `POHODA_URL` — Pohoda mServer endpoint URL
- `POHODA_USERNAME` / `POHODA_PASSWORD` — Pohoda API credentials
- `POHODA_TIMEOUT` — Request timeout in milliseconds
- `POHODA_COMPRESS` — Enable compression (`true`/`false`)
- `POHODA_DEBUG` — Enable Pohoda debug mode (`true`/`false`)
- `REALPAD_USERNAME` / `REALPAD_PASSWORD` — Realpad API credentials
- `REALPAD_PROJECT_ID` — Realpad project identifier

## Report Format

The application generates reports in the [MultiFlexi format](https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.report.schema.json), which includes:

**Required fields:**

- `status` — Operation result: `success`, `error`, or `warning`
- `timestamp` — Completion time in ISO8601 format

**Optional fields:**

- `message` — Human-readable result description
- `artifacts` — Generated files and URLs (endpoints, responses, XML files)
- `metrics` — Operational metrics (payments processed, HTTP codes, etc.)

**Example successful report:**

```json
{
    "status": "success",
    "timestamp": "2025-01-01T12:00:00+00:00",
    "message": "Payment registered successfully. ID: 12345",
    "artifacts": {
        "realpad_endpoint": ["https://cms.realpad.eu/ws/v10/add-payments-pohoda"],
        "pohoda_xml": ["/tmp/Bankovni_doklady.xml"],
        "realpad_response": ["/tmp/realpad_response_abc123.txt"]
    },
    "metrics": {
        "payments_processed": 1,
        "http_response_code": 201,
        "pohoda_records_found": 5,
        "exit_code": 0
    }
}
```

## See also

- <https://github.com/Spoje-NET/pohoda-client-checker>
- <https://github.com/Spoje-NET/realpad2mailkit>
- <https://github.com/Spoje-NET/PHP-Realpad-Takeout>

## MultiFlexi

Pohoda2Realpad is ready for run as [MultiFlexi](https://multiflexi.eu) application.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)

## Debian/Ubuntu installation

Please use the .deb packages. The repository is availble:

 ```shell
    echo "deb http://repo.vitexsoftware.com $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
    sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.com/keyring.gpg
    sudo apt update
    sudo apt install pohoda-realpad
```
