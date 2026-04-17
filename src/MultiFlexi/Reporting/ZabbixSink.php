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

namespace MultiFlexi\Reporting;

use MultiFlexi\Zabbix\Request\Metric;
use MultiFlexi\Zabbix\Request\Packet;
use MultiFlexi\Zabbix\ZabbixSender;

/**
 * Zabbix metric sink — translates MultiFlexi job events into Zabbix items.
 *
 * On job start, sends LLD discovery data to the trapper key `multiflexi.job.lld`
 * so Zabbix can auto-create item prototypes for each job via Low-Level Discovery.
 *
 * LLD macros sent per job:
 *   {#JOB_ID}, {#APP_ID}, {#APP_NAME},
 *   {#COMPANY_ID}, {#COMPANY_NAME}, {#RUNTEMPLATE_ID}, {#RUNTEMPLATE_NAME}
 *
 * On job end, sends per-job trapper items:
 *   multiflexi.job.exitcode[{job_id}]  = exit code
 *   multiflexi.job.duration[{job_id}]  = seconds (float)
 *   multiflexi.job.success[{job_id}]   = 1|0
 *
 * Configuration via environment / Ease\Shared::cfg():
 *   ZABBIX_SERVER  — hostname or IP of Zabbix server (required)
 *   ZABBIX_PORT    — TCP port, default 10051
 *   ZABBIX_HOST    — monitored host name sent to Zabbix, default gethostname()
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class ZabbixSink extends \Ease\Sand implements MetricSinkInterface
{
    /** Zabbix trapper key for LLD discovery of jobs. */
    public const LLD_KEY = 'multiflexi.job.lld';

    private bool $enabled = false;
    private ?ZabbixSender $sender = null;

    /** Host name reported to Zabbix (monitored host). */
    private string $zabbixHost;

    public function __construct()
    {
        $server = \Ease\Shared::cfg('ZABBIX_SERVER', '');

        if (empty($server)) {
            $this->addStatusMessage(_('ZabbixSink: ZABBIX_SERVER not configured, sink disabled'), 'debug');

            return;
        }

        $port = (int) \Ease\Shared::cfg('ZABBIX_PORT', 10051);
        $this->zabbixHost = (string) \Ease\Shared::cfg('ZABBIX_HOST', gethostname());

        try {
            $this->sender = new ZabbixSender($server, $port);
            $this->enabled = true;
            $this->addStatusMessage(
                sprintf(_('ZabbixSink initialized, server=%s:%d, host=%s'), $server, $port, $this->zabbixHost),
                'debug',
            );
        } catch (\Throwable $e) {
            $this->addStatusMessage(
                sprintf(_('ZabbixSink: failed to create sender: %s'), $e->getMessage()),
                'error',
            );
        }
    }

    /**
     * Send LLD discovery record to `multiflexi.job.lld`.
     *
     * Zabbix expects the trapper value to be a JSON array of macro objects:
     *   [{ "{#JOB_ID}": "123", "{#APP_NAME}": "MyApp", … }]
     *
     * Zabbix then auto-creates item prototypes for this job via the discovery rule.
     */
    #[\Override]
    public function recordJobStart(
        int $jobId,
        int $appId,
        string $appName,
        int $companyId,
        string $companyName,
        int $runtemplateId,
        string $runtemplateName,
    ): void {
        if (!$this->enabled) {
            return;
        }

        $lldPayload = json_encode([
            [
                '{#JOB_ID}' => (string) $jobId,
                '{#APP_ID}' => (string) $appId,
                '{#APP_NAME}' => $appName,
                '{#COMPANY_ID}' => (string) $companyId,
                '{#COMPANY_NAME}' => $companyName,
                '{#RUNTEMPLATE_ID}' => (string) $runtemplateId,
                '{#RUNTEMPLATE_NAME}' => $runtemplateName,
            ],
        ]);

        $packet = new Packet();
        $packet->addMetric(
            (new Metric(self::LLD_KEY, $lldPayload))->withHostname($this->zabbixHost),
        );

        $this->dispatch($packet, sprintf(_('ZabbixSink: LLD discovery sent for job #%d'), $jobId));
    }

    /**
     * Send job-end state to the per-RunTemplate trapper item.
     *
     * Key format: job-[{company_code}-{app_code}-{runtemplate_id}]
     * Value: full job state as JSON (mirrors Job::reportToZabbix() behaviour).
     *
     * Only this phase writes to the existing RunTemplate item — subsequent calls
     * overwrite the value intentionally (latest state wins, no history scatter).
     *
     * Required attributes: company_code, app_code, runtemplate_id, job_id,
     *   app_name, company_name (others are forwarded as-is).
     */
    #[\Override]
    public function recordJobEnd(int $exitCode, float $duration, array $attributes): void
    {
        if (!$this->enabled) {
            return;
        }

        $companyCode = (string) ($attributes['company_code'] ?? '');
        $appCode = (string) ($attributes['app_code'] ?? '');
        $runtemplateId = (int) ($attributes['runtemplate_id'] ?? 0);
        $jobId = (int) ($attributes['job_id'] ?? 0);

        $itemKey = sprintf('job-[%s-%s-%d]', $companyCode, $appCode, $runtemplateId);

        $payload = json_encode(array_merge($attributes, [
            'phase' => 'jobDone',
            'exitcode' => $exitCode,
            'duration' => round($duration, 3),
        ]));

        $packet = new Packet();
        $packet->addMetric(
            (new Metric($itemKey, $payload))->withHostname($this->zabbixHost),
        );

        $this->dispatch($packet, sprintf(_('ZabbixSink: job end #%d sent to %s'), $jobId, $itemKey));
    }

    /** Zabbix protocol is synchronous — flush is a no-op. */
    #[\Override]
    public function flush(): void
    {
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send packet and log result or error.
     */
    private function dispatch(Packet $packet, string $context): void
    {
        try {
            $this->sender->send($packet);
            $this->addStatusMessage($context, 'debug');
        } catch (\Throwable $e) {
            $this->addStatusMessage(
                sprintf('%s — send failed: %s', $context, $e->getMessage()),
                'error',
            );
        }
    }
}
