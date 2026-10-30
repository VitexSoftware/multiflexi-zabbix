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

namespace MultiFlexi\Action;

/**
 * Description of TriggerJenkins.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class Zabbix extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     *
     * @return string
     */
    public static function name()
    {
        return _('Zabbix');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Send Job Output to Zabbix');
    }

    /**
     * Is this Action Suitable for Application.
     *
     * @param Application $app
     */
    #[\Override]
    public static function usableForApp($app): bool
    {
        return \is_object($app);
    }

    /**
     * Perform Action.
     */
    #[\Override]
    public function perform(\MultiFlexi\Job $job): void
    {
        $overrideHost = $job->getRunTemplate()->getCompany()->getDataValue('zabbix_host');
        $me = empty($overrideHost) ? \Ease\Shared::cfg('ZABBIX_HOST', gethostname()) : $overrideHost;

        $server = \Ease\Shared::cfg('ZABBIX_SERVER');

        if ($server) {
            $configuredKey = (string) $this->getDataValue('key');
            $zabbixKey = 'zabbix_action-['.(($configuredKey === '' || $configuredKey === '{COMPANY_CODE}-{APP_CODE}-{RUNTEMPLATE_ID}-data') ? $this->defaultKey() : $configuredKey).']';
            $dataForZabbix = null;
            $metricsfile = $this->getDataValue('metricsfile');

            if (empty($metricsfile)) {
                $dataForZabbix = stripslashes((string) $job->getDataValue('stdout'));
                $exitCode = $job->getDataValue('exitcode');

                // A failed job often has empty stdout; report the cause so the failure reaches Zabbix with its reason
                if (empty($dataForZabbix) && $exitCode !== null && (int) $exitCode !== 0) {
                    $dataForZabbix = trim(sprintf(_('Job failed with exit code %d').': %s', (int) $exitCode, mb_substr(trim($job->getErrorOutput()), -1000)), ': ');
                }
            } else {
                if (file_exists($metricsfile)) {
                    $dataForZabbix = file_get_contents($metricsfile); // TODO: Use Executor
                } else {
                    $this->addStatusMessage(_('Required metrics file not found '), 'warning');
                }
            }

            if ($dataForZabbix) {
                // Note: Artifacts are now automatically created by Job class
                // This action only sends data to Zabbix server

                if (\Ease\Shared::cfg('USE_ZABBIX_SENDER', false) && file_exists('/usr/bin/zabbix_sender')) {
                    // Argument array: no shell is involved, so job output cannot inject commands
                    $cmd = ['/usr/bin/zabbix_sender', '-v', '-z', (string) $server, '-s', (string) $me, '-k', $zabbixKey, '-o', $dataForZabbix];
                    $this->addStatusMessage('zabbix_sender -z '.$server.' -s '.$me.' -k '.$zabbixKey, 'debug');
                    $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    $output = '';
                    $return_var = -1;

                    if (\is_resource($process)) {
                        $output = (string) stream_get_contents($pipes[1]).(string) stream_get_contents($pipes[2]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        $return_var = proc_close($process);
                    }

                    $this->addStatusMessage((string) $return_var.':'.$output, 'debug');
                } else {
                    $packet = new \MultiFlexi\Zabbix\Request\Packet();
                    $packet->addMetric((new \MultiFlexi\Zabbix\Request\Metric($zabbixKey, $dataForZabbix))->withHostname($me));
                    $zabbixSender = new \MultiFlexi\ZabbixSender($server);

                    try {
                        $zabbixSender->send($packet);
                        $this->addStatusMessage(sprintf(_('Job metric %s sent to %s as %s: %s'), $zabbixKey, $server, $me, json_encode($packet)), 'debug');
                    } catch (\Exception $exc) {
                    }
                }
            } else {
                $this->addStatusMessage(_('No Data for Zabbix provided'), 'warning');
            }
        } else {
            $this->addStatusMessage(_('No Zabbix server defined'), 'warning');
        }
    }

    public function defaultKey(): string
    {
        $company = $this->runtemplate->getCompany();
        // company.code was renamed to company.slug; keep the fallback for databases not migrated yet
        $companyCode = (string) ($company->getDataValue('slug') ?: $company->getDataValue('code'));

        return self::buildDefaultKey($companyCode, (string) $this->runtemplate->getApplication()->getDataValue('code'), (int) $this->runtemplate->getMyKey());
    }

    /**
     * Default trapper key part; shared with the Action LLD so discovered items match what is sent.
     */
    public static function buildDefaultKey(string $companyCode, string $appCode, int $runtemplateId): string
    {
        return $companyCode.'-'.$appCode.'-'.$runtemplateId.'-data';
    }
}
