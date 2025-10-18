<?php

declare(strict_types=1);

/**
 * This file is part of the RealpadTakeout package
 *
 * https://github.com/Spoje-NET/PHP-Realpad-Takeout
 *
 * (c) Spoje.Net IT s.r.o. <http://spojenenet.cz/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Integration test to validate the report format in real-world scenarios.
 */
class ReportIntegrationTest extends TestCase
{
    /**
     * Test that the application generates correct report format for different scenarios.
     */
    public function testApplicationReportFormat(): void
    {
        // Test 1: Connection error scenario
        $errorReport = [
            'status' => 'error',
            'timestamp' => '2025-01-01T12:00:00+00:00',
            'message' => 'Connection error: Could not resolve host: invalid.example.com',
            'artifacts' => [
                'realpad_endpoint' => ['http://localhost/pohoda-realpad/tests/realpad-mock.php'],
                'pohoda_endpoint' => ['https://invalid.example.com/status'],
            ],
            'metrics' => [
                'payments_processed' => 0,
                'http_response_code' => 0,
                'pohoda_records_found' => 0,
                'exit_code' => 1,
            ],
        ];

        $this->validateMultiFlexiReport($errorReport);
        $this->assertEquals('error', $errorReport['status']);
        $this->assertStringContainsString('Could not resolve host', $errorReport['message']);

        // Test 2: Mixed scenario (Pohoda success, Realpad error)
        $mixedReport = [
            'status' => 'error',
            'timestamp' => '2025-01-01T12:00:00+00:00',
            'message' => 'Unexpected HTTP code: 404. Response: Not Found',
            'artifacts' => [
                'realpad_endpoint' => ['http://localhost/pohoda-realpad/tests/realpad-mock.php'],
                'pohoda_xml' => ['/tmp/Bankovni_doklady.xml'],
                'realpad_response' => ['/tmp/realpad_response_abc123.txt'],
            ],
            'metrics' => [
                'payments_processed' => 0,
                'http_response_code' => 404,
                'pohoda_records_found' => 5,
                'exit_code' => 404,
            ],
        ];

        $this->validateMultiFlexiReport($mixedReport);
        $this->assertEquals('error', $mixedReport['status']);
        $this->assertEquals(404, $mixedReport['metrics']['http_response_code']);
        $this->assertEquals(5, $mixedReport['metrics']['pohoda_records_found']);

        // Test 3: Success scenario
        $successReport = [
            'status' => 'success',
            'timestamp' => '2025-01-01T12:00:00+00:00',
            'message' => 'Payment registered successfully. ID: mock-payment-id-1234',
            'artifacts' => [
                'realpad_endpoint' => ['http://localhost/pohoda-realpad/tests/realpad-mock.php'],
                'pohoda_xml' => ['/tmp/Bankovni_doklady.xml'],
                'realpad_response' => ['/tmp/realpad_response_def456.txt'],
            ],
            'metrics' => [
                'payments_processed' => 1,
                'http_response_code' => 201,
                'pohoda_records_found' => 3,
                'exit_code' => 0,
            ],
        ];

        $this->validateMultiFlexiReport($successReport);
        $this->assertEquals('success', $successReport['status']);
        $this->assertEquals(1, $successReport['metrics']['payments_processed']);
        $this->assertEquals(201, $successReport['metrics']['http_response_code']);

        // Test 4: Warning scenario (payment already exists)
        $warningReport = [
            'status' => 'warning',
            'timestamp' => '2025-01-01T12:00:00+00:00',
            'message' => 'Payment already exists. ID: mock-existing-payment-1234',
            'artifacts' => [
                'realpad_endpoint' => ['http://localhost/pohoda-realpad/tests/realpad-mock.php'],
                'pohoda_xml' => ['/tmp/Bankovni_doklady.xml'],
                'realpad_response' => ['/tmp/realpad_response_ghi789.txt'],
            ],
            'metrics' => [
                'payments_processed' => 0,
                'http_response_code' => 200,
                'pohoda_records_found' => 2,
                'exit_code' => 0,
            ],
        ];

        $this->validateMultiFlexiReport($warningReport);
        $this->assertEquals('warning', $warningReport['status']);
        $this->assertEquals(0, $warningReport['metrics']['payments_processed']);
        $this->assertEquals(200, $warningReport['metrics']['http_response_code']);
    }

    /**
     * Validate that a report conforms to MultiFlexi schema.
     *
     * @param array $report The report to validate
     */
    private function validateMultiFlexiReport(array $report): void
    {
        // Required fields
        $this->assertArrayHasKey('status', $report, 'Report must have status field');
        $this->assertArrayHasKey('timestamp', $report, 'Report must have timestamp field');

        // Status must be valid
        $this->assertContains(
            $report['status'],
            ['success', 'error', 'warning'],
            'Status must be one of: success, error, warning',
        );

        // Timestamp must be ISO8601
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $report['timestamp'],
            'Timestamp must be in ISO8601 format',
        );

        // Optional fields validation
        if (isset($report['message'])) {
            $this->assertIsString($report['message'], 'Message must be a string');
        }

        if (isset($report['artifacts'])) {
            $this->assertIsArray($report['artifacts'], 'Artifacts must be an array');

            foreach ($report['artifacts'] as $artifactType => $artifactList) {
                $this->assertIsString($artifactType, 'Artifact type must be a string');
                $this->assertIsArray($artifactList, 'Artifact list must be an array');

                foreach ($artifactList as $artifact) {
                    $this->assertIsString($artifact, 'Each artifact must be a string');
                }
            }
        }

        if (isset($report['metrics'])) {
            $this->assertIsArray($report['metrics'], 'Metrics must be an array');

            foreach ($report['metrics'] as $metricName => $metricValue) {
                $this->assertIsString($metricName, 'Metric name must be a string');
                $this->assertTrue(
                    is_numeric($metricValue) || \is_string($metricValue),
                    'Metric value must be numeric or string',
                );
            }
        }

        // Validate JSON encoding
        $json = json_encode($report);
        $this->assertNotFalse($json, 'Report must be JSON encodable');

        $decoded = json_decode($json, true);
        $this->assertEquals($report, $decoded, 'Report must survive JSON encode/decode cycle');
    }
}
