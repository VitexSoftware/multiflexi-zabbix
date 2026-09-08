# multiflexi-zabbix

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
![Packaging: deb](https://img.shields.io/badge/packaging-.deb-red?logo=debian&logoColor=white)

multiflexi to zabbix integration package

This package provides two independent ways to get MultiFlexi data into Zabbix:

1. **Active Zabbix agent checks** (this is what most deployments use) - `zabbix/multiflexi.conf`
   installs UserParameters that let the Zabbix agent itself pull status, job statistics, and
   per-credential availability on its own schedule. Paired with `zabbix/multiflexi-template.xml`,
   which turns those checks into discovered items, dependent items, triggers, and value maps.
2. **Trapper-based push from RunTemplate actions** - the `MultiFlexi\Zabbix\ZabbixSender` PHP library
   (below), used when a RunTemplate is configured with a Zabbix success/fail action to push a job's
   own result as a metric, rather than waiting for the next agent poll.

## Active checks (UserParameters)

Installed to `/etc/zabbix/zabbix_agent2.d/multiflexi.conf`; restart `zabbix-agent2` after
install/upgrade to pick them up.

| Key | Backed by | Reports |
| --- | --- | --- |
| `multiflexi.appstatus` | `multiflexi-cli status --format=json` | Versions, DB migration state, entity counts, job-rate summary, encryption/telemetry/executor/scheduler status |
| `multiflexi.jobstatus` | `multiflexi-cli job:status --format=json` | Success/fail/incomplete job counts, queue length |
| `multiflexi.queue` | `multiflexi-cli queue:list --format=json` | The currently queued jobs themselves |
| `multiflexi.schedule.stale` | `multiflexi-cli run-template:stale --format=json --tolerance-hours=6` | RunTemplates whose `next_schedule` is stuck in the past (silently dropped out of the cron rotation) |
| `multiflexi.company.lld` | `multiflexi-zabbix-lld-company` | LLD: discovers companies/tenants |
| `multiflexi.job.lld` | `multiflexi-zabbix-lld-tasks` | LLD: discovers scheduled tasks/jobs |
| `multiflexi.runtemplate.lld[*]` | `multiflexi-zabbix-lld` | LLD: discovers RunTemplates for a company |
| `multiflexi.action.lld` | `multiflexi-zabbix-lld-actions` | LLD: discovers RunTemplates with a Zabbix action configured |
| `multiflexi.credential.lld` | `multiflexi-zabbix-lld-credentials` | LLD: discovers every credential |
| `multiflexi.credential.check[*]` | `multiflexi-zabbix-lld-credentials <id>` | Per-credential availability: `Available`/`Degraded`/`Unavailable`/`Misconfigured`/`Unknown` |

Full field-by-field breakdown of each check's JSON output, the template's item/trigger structure, and
how to import and link the template: see
[Zabbix Integration](https://multiflexi.readthedocs.io/en/latest/integrations/zabbix.html) in the
MultiFlexi docs.

### Credential availability monitoring

`multiflexi.credential.lld` discovers every configured credential and reports its live availability
via each credential type's `checkAvailability()` implementation where one exists, falling back to a
required-field completeness check otherwise. States map to trigger severities:

| State | Meaning | Trigger severity |
| --- | --- | --- |
| Available | Reachable and usable | (no trigger) |
| Degraded | Reachable but impaired | Average |
| Unavailable | Configured but unreachable | **High** |
| Misconfigured | Required field(s) missing | Warning |
| Unknown | No live check implemented | (no trigger) |

Tunable via two template macros: `{$CRED.AVAILABILITY.INTERVAL}` (default `5m`, override per credential
type with a macro context) and `{$CRED.AVAILABILITY.EXCLUDE}` (default `^$` i.e. excludes nothing - a
regex against the credential type name to skip discovering it entirely).

## Configuration

The package can be configured using the following environment variables:

| Variable            | Description                                                              | Default         |
| ------------------- | ------------------------------------------------------------------------ | --------------- |
| `ZABBIX_SERVER`     | Hostname or IP address of the Zabbix server.                             |                 |
| `ZABBIX_HOST`       | Hostname of the monitored host in Zabbix.                                | `gethostname()` |
| `USE_ZABBIX_SENDER` | If set to `true`, the package will use the `/usr/bin/zabbix_sender` binary if available. | `false`         |

## Trapper library usage (RunTemplate actions)

```php
<?php

use MultiFlexi\Zabbix\ZabbixSender;
use MultiFlexi\Zabbix\Request\Packet;

$sender = new ZabbixSender('zabbix.example.com');
$packet = new Packet();
$packet->addMetric('system.cpu.load', '1m', 0.5);
$sender->send($packet);
```
