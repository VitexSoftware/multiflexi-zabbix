# multiflexi-zabbix
multiflexi to zabbix integration package

## Configuration

The package can be configured using the following environment variables:

| Variable | Description | Default |
| --- | --- | --- |
| `ZABBIX_SERVER` | Hostname or IP address of the Zabbix server. | |
| `ZABBIX_HOST` | Hostname of the monitored host in Zabbix. | `gethostname()` |
| `USE_ZABBIX_SENDER` | If set to `true`, the package will use the `/usr/bin/zabbix_sender` binary if available. | `false` |
