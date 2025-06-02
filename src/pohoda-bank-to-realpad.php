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
// 2) Keep Only Matched banks movements
// 3) Push matched banks into realpad

use Ease\Shared;

\define('APP_NAME', 'PohodaBankToRealpad');

require_once '../vendor/autoload.php';

$options = getopt('o::e::', ['output::environment::']);

Shared::init(
    ['POHODA_URL', 'POHODA_USERNAME', 'POHODA_PASSWORD'],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);
$destination = \array_key_exists('o', $options) ? $options['o'] : (\array_key_exists('output', $options) ? $options['output'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout'));
$exitcode = 0;
$report = [];
$banker = new \mServer\Bank();

if (Shared::cfg('APP_DEBUG')) {
    $banker->logBanner();
}

if ($banker->isOnline()) {
    $report['statement'] = $banker->getBankList("BV.ParSym IS NOT NULL AND BV.ParSym <> ''");

    $outxml = sys_get_temp_dir().'/Bankovni_doklady.xml';

    if (file_put_contents($outxml, $banker->lastCurlResponse)) {
        // Initialize cURL
        $ch = curl_init();

        curl_setopt($ch, \CURLOPT_URL, \Ease\Shared::cfg('REALPAD_POHODA_WS', 'https://cms.realpad.eu/ws/v10/add-payments-pohoda'));
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, [
            'login' => Shared::cfg('REALPAD_USERNAME'),
            'password' => Shared::cfg('REALPAD_PASSWORD'),
            'projectid' => Shared::cfg('REALPAD_PROJECT_ID'),
            'file' => new \CURLFile($outxml, 'application/xml'),
        ]);
        curl_setopt($ch, \CURLOPT_HTTPHEADER, [
            'User-Agent: PohodaRealpad/'.Shared::appVersion().' https://github.com/Spoje-NET/pohoda-realpad',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);

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

            if ($httpCode !== 201) {
                $exitcode = $httpCode;
            }
        }

        curl_close($ch);

        // Clean up the temporary file
        unlink($outxml);
    }
} else {
    $report['result'] = _('no statement obtained');
    $banker->addStatusMessage($report['result'], 'error');
    $exitcode = 2;
}

$banker->addStatusMessage('processing done', 'debug');

$report['exitcode'] = $exitcode;

if (!isset($report['keeping']) || !\is_array($report['keeping'])) {
    $report['keeping'] = [];
}

$report['send'] = \count($report['keeping']);
$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$banker->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
