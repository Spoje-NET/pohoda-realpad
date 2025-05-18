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

header('Content-Type: text/plain');

// Získání POST dat
$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';
$project = $_POST['projectid'] ?? '';
$file = $_FILES['file'] ?? null;

// Cesta do systémové složky dočasných souborů
$logDir = sys_get_temp_dir().'/realpad-mock-log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0o777, true);
}

// Uložení přijatých dat do souboru
$logFile = $logDir.'/request_'.date('Ymd_His').'_'.uniqid().'.log';
$logData = [
    'datetime' => date('c'),
    'login' => $login,
    'password' => $password,
    'projectid' => $project,
    'file_name' => $file['name'] ?? null,
    'file_type' => $file['type'] ?? null,
    'file_size' => $file['size'] ?? null,
    'post' => $_POST,
    'files' => $_FILES,
];
file_put_contents($logFile, print_r($logData, true));

if ($file && $file['error'] === \UPLOAD_ERR_OK) {
    file_put_contents($logFile.'.xml', file_get_contents($file['tmp_name']));
}

// Ověření přihlašovacích údajů
if ($login !== 'daramis-pohoda' || $password !== '33MS5Abm4C') {
    http_response_code(401);
    echo 'Unauthorized';

    exit;
}

// Ověření souboru
if (!$file || $file['error'] !== \UPLOAD_ERR_OK) {
    http_response_code(400);
    echo 'Missing or invalid file';

    exit;
}

// Ověření, že soubor je XML
$xmlContent = file_get_contents($file['tmp_name']);

if (!str_contains(strtolower($file['type']), strtolower('xml')) && !str_contains(strtolower($xmlContent), strtolower('<?xml'))) {
    http_response_code(400);
    echo 'File is not XML';

    exit;
}

// Simulace úspěšné registrace platby
if (mt_rand(0, 1)) {
    http_response_code(201);
    echo 'mock-payment-id-'.mt_rand(1000, 9999);
} else {
    http_response_code(200);
    echo 'mock-existing-payment-id-'.mt_rand(1000, 9999);
}
