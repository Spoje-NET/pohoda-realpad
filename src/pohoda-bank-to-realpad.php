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
$report = [];
$banker = new \mServer\Bank();

if (Shared::cfg('APP_DEBUG')) {
    $banker->logBanner();
}

$realpadUri = empty(Shared::cfg('REALPAD_POHODA_WS')) ? 'https://cms.realpad.eu/ws/v10/add-payments-pohoda' : Shared::cfg('REALPAD_POHODA_WS');
$report['realpad'] = $realpadUri;

if ($banker->isOnline()) {
    $report['statement'] = $banker->getBankList("BV.ParSym IS NOT NULL AND BV.ParSym <> ''");

    $outxml = sys_get_temp_dir().'/Bankovni_doklady.xml';

    $saved = file_put_contents($outxml, $banker->lastCurlResponse);

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
        $report['realpad_response_file'] = $responseFile;
        $report['realpad_response'] = $response;

        $report['realpad_response_code'] = $httpCode;

        if (curl_errno($ch)) {
            $banker->addStatusMessage(sprintf(_('Curl error: %s'), curl_error($ch)), 'error');
        } else {
            switch ($httpCode) {
                case 201:
                    $banker->addStatusMessage(sprintf(_('Payment registered successfully. ID: %s'), $response), 'success');

                    break;
                case 200:
                    $banker->addStatusMessage(sprintf(_('Payment already exists. ID: %s'), $response), 'info');

                    break;
                case 401:
                    $banker->addStatusMessage(_('Unauthorized: Invalid credentials or banned account/IP.'), 'error');

                    break;
                case 400:
                    $banker->addStatusMessage(sprintf(_('Bad Request: %s'), $response), 'error');

                    break;
                case 418:
                    $banker->addStatusMessage(_('API version deprecated. Please contact Realpad support.'), 'error');

                    break;

                default:
                    $banker->addStatusMessage(sprintf(_('Unexpected HTTP code: %d. Response: %s'), $httpCode, $response), 'error');

                    break;
            }

            $report['realpad_status'] = match ($httpCode) {
                201 => 'Payment registered successfully',
                200 => 'Payment already exists',
                401 => 'Unauthorized: Invalid credentials or banned account/IP.',
                400 => 'Bad Request',
                418 => 'API version deprecated. Please contact Realpad support.',
                500 => 'Internal Server Error',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                502 => 'Bad Gateway',
                404 => 'Not Found',
                403 => 'Forbidden',
                default => 'Unexpected HTTP code',
            };

            if ($httpCode !== 201) {
                $exitcode = $httpCode;
            }
        }

        curl_close($ch);

        // Clean up the temporary file
        unlink($outxml);
    }
} else {
    $report['success'] = $banker->processResponse($banker->lastResponseCode);
    $report['result'] = $banker->lastResponseMessage;
    $report['pohoda'] = $banker->curlInfo['url'];
    $banker->addStatusMessage($report['result'], 'error');
    $exitcode = $banker->lastResponseCode;
}

$banker->addStatusMessage('processing done', 'debug');

$report['exitcode'] = $exitcode;
$report['send'] = 0; // Number of items sent (keeping array is not used in current implementation)
$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$banker->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
