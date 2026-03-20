<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once \dirname(__DIR__).'/vendor/autoload.php';

if (!\function_exists('_')) {
    function _($string)
    {
        return $string;
    }
}

putenv('ZABBIX_HOST=localhost');
putenv('ZABBIX_SERVER=localhost');
