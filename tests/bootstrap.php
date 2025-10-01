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

// Find and include the Composer autoloader
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require_once __DIR__.'/../vendor/autoload.php';
} elseif (file_exists(__DIR__.'/../../vendor/autoload.php')) {
    require_once __DIR__.'/../../vendor/autoload.php';
} else {
    exit('Could not find vendor/autoload.php. Please run composer install.'.\PHP_EOL);
}

// Set test environment
\define('APP_NAME', 'PohodaBankToRealpadTest');

// You can add common test setup here
