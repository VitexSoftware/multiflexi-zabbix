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

\define('APP_NAME', 'MultiFlexi LLD Credentials');

require_once '../vendor/autoload.php';
Shared::init(['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'], '../.env');
$loggers = ['syslog', '\MultiFlexi\LogToSQL'];

if (Shared::cfg('ZABBIX_SERVER') && Shared::cfg('ZABBIX_HOST') && class_exists('\MultiFlexi\LogToZabbix')) {
    $loggers[] = '\MultiFlexi\LogToZabbix';
}

\define('EASE_LOGGER', implode('|', $loggers));
Shared::user(new Anonym());

$mode = \array_key_exists(1, $argv) ? $argv[1] : null;

$credentialer = new Credential();

if (null !== $mode) {
    // Single credential check mode: multiflexi.credential.check[{#CREDENTIAL_ID}]
    $credentialer->loadFromSQL((int) $mode);

    $prototype = $credentialer->getCredentialType()?->getPrototype();

    if ($prototype instanceof checkableCredentialInterface) {
        $result = $prototype->checkAvailability();
    } else {
        $result = CredentialAvailability::completenessFallback($credentialer->getFields());
    }

    echo json_encode([
        'state' => $result->state->value,
        'state_code' => array_search($result->state, CredentialState::cases(), true),
        'message' => $result->message,
        'checked_at' => $result->checkedAt,
        'ttl' => $result->ttl,
        'details' => $result->details,
    ], \JSON_PRETTY_PRINT);

    exit;
}

// Discovery mode: multiflexi.credential.lld
$lldData = [];

$credentials = $credentialer->listingQuery()->disableSmartJoin()
    ->select(['credentials.id AS credential_id', 'credentials.name AS credential_name'])
    ->leftJoin('credential_type ON credential_type.id = credentials.credential_type_id')
    ->select(['credential_type.name AS credential_type_name'])
    ->leftJoin('company ON company.id = credentials.company_id')
    ->select(['company.id AS company_id', 'company.name AS company_name']);

foreach ($credentials as $credentialData) {
    $lldData[] = [
        '{#CREDENTIAL_ID}' => $credentialData['credential_id'],
        '{#CREDENTIAL_NAME}' => $credentialData['credential_name'],
        '{#CREDENTIAL_TYPE}' => $credentialData['credential_type_name'],
        '{#COMPANY_ID}' => $credentialData['company_id'],
        '{#COMPANY_NAME}' => $credentialData['company_name'],
    ];
}

echo json_encode($lldData, \JSON_PRETTY_PRINT);
