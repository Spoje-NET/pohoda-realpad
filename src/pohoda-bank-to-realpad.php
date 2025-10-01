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

// 1) Obtain bank movements from Pohoda
// 2) Add movements to report for further audit
// 3) Push matched banks into realpad

use Ease\Shared;

\define('APP_NAME', 'PohodaBankToRealpad');

require_once '../vendor/autoload.php';

$options = getopt('o:e:', ['output:','environment:']);

Shared::init(
    ['POHODA_URL', 'POHODA_USERNAME', 'POHODA_PASSWORD'],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);
$destination = \array_key_exists('o', $options) ? $options['o'] : (\array_key_exists('output', $options) ? $options['output'] : Shared::cfg('RESULT_FILE', 'php://stdout'));
$exitcode = 0;

// Initialize report according to MultiFlexi schema: https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.report.schema.json
$report = [
    'status' => 'success',              // Required: 'success', 'error', or 'warning'
    'timestamp' => '',                  // Required: ISO8601 datetime string
    'message' => '',                    // Optional: Human-readable result description
    'artifacts' => [],                  // Optional: Generated files and URLs
    'metrics' => [                      // Optional: Operational metrics
        'payments_processed' => 0,
        'http_response_code' => 0,
        'pohoda_records_found' => 0
    ]
];
$banker = new \mServer\Bank();

if (Shared::cfg('APP_DEBUG')) {
    $banker->logBanner();
}

$realpadUri = empty(Shared::cfg('REALPAD_POHODA_WS')) ? 'https://cms.realpad.eu/ws/v10/add-payments-pohoda' : Shared::cfg('REALPAD_POHODA_WS');
$report['artifacts']['realpad_endpoint'] = [$realpadUri];

try {
    $isOnline = $banker->isOnline();
} catch (\Exception $e) {
    $isOnline = false;
    $report['status'] = 'error';
    $report['message'] = sprintf(_('Connection error: %s'), $e->getMessage());
    $report['metrics']['http_response_code'] = 0;
    $banker->addStatusMessage($report['message'], 'error');
    $exitcode = 1;
}

if ($isOnline) {
    $bankListResult = $banker->getBankList("BV.ParSym IS NOT NULL AND BV.ParSym <> ''");
    $report['metrics']['pohoda_records_found'] = is_array($bankListResult) ? count($bankListResult) : 0;

    $outxml = sys_get_temp_dir().'/Bankovni_doklady.xml';

    $saved = file_put_contents($outxml, $banker->lastCurlResponse);
    if ($saved) {
        $report['artifacts']['pohoda_xml'] = [$outxml];
    }

    $banker->addStatusMessage(sprintf(_('Saving Pohoda Bank movements to %s'), $outxml), $saved ? 'debug' : 'error');

    if ($saved) {
        // Initialize cURL
        $ch = curl_init();

        curl_setopt($ch, \CURLOPT_URL, $realpadUri);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, [
            'login' => Shared::cfg('REALPAD_USERNAME'),
            'password' => Shared::cfg('REALPAD_PASSWORD'),
            'projectid' => Shared::cfg('REALPAD_PROJECT_ID'),
            'file' => new \CURLFile($outxml, 'application/xml'),
        ]);
        curl_setopt($ch, \CURLOPT_HTTPHEADER, [
            'User-Agent: PohodaToRealpad/'.Shared::appVersion().' https://github.com/Spoje-NET/pohoda-realpad',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);

        $responseFile = sys_get_temp_dir().'/realpad_response_'.uniqid().'.txt';
        file_put_contents($responseFile, $response);
        $report['artifacts']['realpad_response'] = [$responseFile];
        $report['metrics']['http_response_code'] = $httpCode;

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            $banker->addStatusMessage(sprintf(_('Curl error: %s'), $curlError), 'error');
            $report['status'] = 'error';
            $report['message'] = sprintf(_('Curl error: %s'), $curlError);
        } else {
            switch ($httpCode) {
                case 201:
                    $banker->addStatusMessage(sprintf(_('Payment registered successfully. ID: %s'), $response), 'success');
                    $report['status'] = 'success';
                    $report['message'] = sprintf(_('Payment registered successfully. ID: %s'), $response);
                    $report['metrics']['payments_processed'] = 1;

                    break;
                case 200:
                    $banker->addStatusMessage(sprintf(_('Payment already exists. ID: %s'), $response), 'info');
                    $report['status'] = 'warning';
                    $report['message'] = sprintf(_('Payment already exists. ID: %s'), $response);
                    $report['metrics']['payments_processed'] = 0;

                    break;
                case 401:
                    $banker->addStatusMessage(_('Unauthorized: Invalid credentials or banned account/IP.'), 'error');
                    $report['status'] = 'error';
                    $report['message'] = _('Unauthorized: Invalid credentials or banned account/IP.');

                    break;
                case 400:
                    $banker->addStatusMessage(sprintf(_('Bad Request: %s'), $response), 'error');
                    $report['status'] = 'error';
                    $report['message'] = sprintf(_('Bad Request: %s'), $response);

                    break;
                case 418:
                    $banker->addStatusMessage(_('API version deprecated. Please contact Realpad support.'), 'error');
                    $report['status'] = 'error';
                    $report['message'] = _('API version deprecated. Please contact Realpad support.');

                    break;

                default:
                    $banker->addStatusMessage(sprintf(_('Unexpected HTTP code: %d. Response: %s'), $httpCode, $response), 'error');
                    $report['status'] = 'error';
                    $report['message'] = sprintf(_('Unexpected HTTP code: %d. Response: %s'), $httpCode, $response);

                    break;
            }

            if ($httpCode !== 201 && $httpCode !== 200) {
                $exitcode = $httpCode;
            }
        }

        curl_close($ch);

        // Clean up the temporary file
        unlink($outxml);
    }
} else {
    $report['status'] = 'error';
    $report['message'] = $banker->lastResponseMessage ?: _('Pohoda connection failed');
    $report['artifacts']['pohoda_endpoint'] = [$banker->curlInfo['url'] ?? 'unknown'];
    $report['metrics']['http_response_code'] = $banker->lastResponseCode ?: 0;
    $banker->addStatusMessage($report['message'], 'error');
    $exitcode = $banker->lastResponseCode ?: 1;
}

$banker->addStatusMessage('processing done', 'debug');

// Set final timestamp
$report['timestamp'] = (new \DateTime())->format(\DateTime::ATOM);

// Set final status if not already set by specific error conditions
if ($report['status'] === 'success' && $exitcode !== 0) {
    $report['status'] = 'error';
}

// Set default message if empty
if (empty($report['message'])) {
    $report['message'] = $report['status'] === 'success' 
        ? _('Processing completed successfully') 
        : _('Processing completed with errors');
}

// Add exit code to metrics
$report['metrics']['exit_code'] = $exitcode;

$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$banker->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
