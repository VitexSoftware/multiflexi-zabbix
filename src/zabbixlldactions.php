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

namespace MultiFlexi;

use Ease\Anonym;
use Ease\Shared;

\define('APP_NAME', 'MultiFlexi LLD Actions');

require_once '../vendor/autoload.php';
Shared::init(['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'], '../.env');
$loggers = ['syslog', '\MultiFlexi\LogToSQL'];

if (\Ease\Shared::cfg('ZABBIX_SERVER') && \Ease\Shared::cfg('ZABBIX_HOST') && class_exists('\MultiFlexi\LogToZabbix')) {
    $loggers[] = '\MultiFlexi\LogToZabbix';
}

\define('EASE_LOGGER', implode('|', $loggers));
Shared::user(new Anonym());

$mode = \array_key_exists(1, $argv) ? $argv[1] : 'n/a';

$lldData = [];
$runTemplater = new \MultiFlexi\RunTemplate();
$runTemplater->logBanner();

$enabled = $runTemplater->listingQuery()->disableSmartJoin()
    ->select(['runtemplate.id AS runtemplate_id', 'runtemplate.name AS runtemplate_name', 'runtemplate.success', 'runtemplate.fail'], true)
    ->leftJoin('company ON company.id = runtemplate.company_id')
    ->select(['company.id AS company_id', 'company.name AS company_name', 'company.slug AS company_code'])
    ->leftJoin('apps ON apps.id = runtemplate.app_id')
    ->select(['apps.id AS app_id', 'apps.name AS app_name', 'apps.code AS app_code'])
    ->where('runtemplate.active', true)
    ->where("(runtemplate.success LIKE '%\"Zabbix\";b:1;%' OR runtemplate.fail LIKE '%\"Zabbix\";b:1;%')");

$configer = new ActionConfig();

// Same key logic as MultiFlexi\Action\Zabbix::perform(): empty or placeholder key means the default one.
$resolveKey = static function (array $rt, string $mode) use ($configer): string {
    $key = (string) $configer->listingQuery()->select('value', true)->where('module', 'Zabbix')->where('keyname', 'key')->where('runtemplate_id', $rt['runtemplate_id'])->where('mode', $mode)->fetchColumn();

    return ($key === '' || $key === '{COMPANY_CODE}-{APP_CODE}-{RUNTEMPLATE_ID}-data') ? \MultiFlexi\Action\Zabbix::buildDefaultKey((string) $rt['company_code'], (string) $rt['app_code'], (int) $rt['runtemplate_id']) : $key;
};

foreach ($enabled as $runTemplateData) {
    $succesActions = (array) @unserialize((string) $runTemplateData['success']);
    $failActions = (array) @unserialize((string) $runTemplateData['fail']);
    $keySuccess = empty($succesActions['Zabbix']) ? '' : $resolveKey($runTemplateData, 'success');
    $keyFail = empty($failActions['Zabbix']) ? '' : $resolveKey($runTemplateData, 'fail');

    $lldData[] = [
        '{#RUN_TEMPLATE_ID}' => $runTemplateData['runtemplate_id'],
        '{#RUN_TEMPLATE_NAME}' => empty($runTemplateData['runtemplate_name']) ? '⚗️ #'.$runTemplateData['runtemplate_id'] : $runTemplateData['runtemplate_name'],
        '{#COMPANY_ID}' => $runTemplateData['company_id'],
        '{#COMPANY_NAME}' => $runTemplateData['company_name'],
        '{#APP_ID}' => $runTemplateData['app_id'],
        '{#APP_NAME}' => $runTemplateData['app_name'],
        '{#SUCCESS_ACTIONS}' => $succesActions,
        '{#FAIL_ACTIONS}' => $failActions,
        '{#ZABBIX_KEY_SUCCESS}' => $keySuccess,
        // Success and failure sharing one key would create duplicate items; failure is only discoverable on its own key.
        '{#ZABBIX_KEY_FAIL}' => $keyFail === $keySuccess ? '' : $keyFail,
    ];
}

echo json_encode($lldData, \JSON_PRETTY_PRINT);
