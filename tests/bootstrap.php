<?php

declare(strict_types=1);

require_once \dirname(__DIR__).'/vendor/autoload.php';

if (!\function_exists('_')) {
    function _($string) {
        return $string;
    }
}

putenv('ZABBIX_HOST=localhost');
putenv('ZABBIX_SERVER=localhost');
