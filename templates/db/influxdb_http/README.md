
# InfluxDB by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor InfluxDB by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `InfluxDB by HTTP` — collects metrics by HTTP agent from InfluxDB /metrics endpoint.
See:



This template was tested on:

- InfluxDB, version 2.0

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

This template works with self-hosted InfluxDB instances. Internal service metrics are collected from InfluxDB /metrics endpoint.
For organization discovery template need to use Authorization via API token. See docs: https://docs.influxdata.com/influxdb/v2.0/security/tokens/

Don't forget to change the macros {$INFLUXDB.URL},  {$INFLUXDB.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.
*NOTE.* Some metrics may not be collected depending on your InfluxDB instance version and configuration.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$INFLUXDB.API.TOKEN} |<p>InfluxDB API Authorization Token</p> |`` |
|{$INFLUXDB.ORG_NAME.MATCHES} |<p>Filter of discoverable organizations</p> |`.*` |
|{$INFLUXDB.ORG_NAME.NOT_MATCHES} |<p>Filter to exclude discovered organizations</p> |`CHANGE_IF_NEEDED` |
|{$INFLUXDB.REQ.FAIL.MAX.WARN} |<p>Maximum number of query requests failures for trigger expression.</p> |`2` |
|{$INFLUXDB.TASK.RUN.FAIL.MAX.WARN} |<p>Maximum number of tasks runs failures for trigger expression.</p> |`2` |
|{$INFLUXDB.URL} |<p>InfluxDB instance URL</p> |`http://localhost:8086` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Organizations discovery |<p>Discovery of organizations metrics.</p> |HTTP_AGENT |influxdb.orgs.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- {#ORG_NAME} NOT_MATCHES_REGEX `{$INFLUXDB.ORG_NAME.NOT_MATCHES}`</p><p>- {#ORG_NAME} MATCHES_REGEX `{$INFLUXDB.ORG_NAME.MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|InfluxDB |InfluxDB: Instance status |<p>Get the health of an instance.</p> |HTTP_AGENT |influx.healthcheck<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"status":"fail"}]}`</p><p>- JAVASCRIPT: `return JSON.parse(value).status == 'pass' ? 1: 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|InfluxDB |InfluxDB: Boltdb reads, rate |<p>Total number of boltdb reads per second.</p> |DEPENDENT |influxdb.boltdb_reads.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="boltdb_reads_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|InfluxDB |InfluxDB: Boltdb writes, rate |<p>Total number of boltdb writes per second.</p> |DEPENDENT |influxdb.boltdb_writes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="boltdb_writes_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|InfluxDB |InfluxDB: Buckets, total |<p>Number of total buckets on the server.</p> |DEPENDENT |influxdb.buckets.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_buckets_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|InfluxDB |InfluxDB: Dashboards, total |<p>Number of total dashboards on the server.</p> |DEPENDENT |influxdb.dashboards.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_dashboards_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|InfluxDB |InfluxDB: Organizations, total |<p>Number of total organizations on the server.</p> |DEPENDENT |influxdb.organizations.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_organizations_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|InfluxDB |InfluxDB: Scrapers, total |<p>Number of total scrapers on the server.</p> |DEPENDENT |influxdb.scrapers.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_scrapers_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|InfluxDB |InfluxDB: Telegraf plugins, total |<p>Number of individual telegraf plugins configured.</p> |DEPENDENT |influxdb.telegraf_plugins.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_telegraf_plugins_count")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|InfluxDB |InfluxDB: Telegrafs, total |<p>Number of total telegraf configurations on the server.</p> |DEPENDENT |influxdb.telegrafs.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_telegrafs_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|InfluxDB |InfluxDB: Tokens, total |<p>Number of total tokens on the server.</p> |DEPENDENT |influxdb.tokens.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_tokens_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|InfluxDB |InfluxDB: Users, total |<p>Number of total users on the server.</p> |DEPENDENT |influxdb.users.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_users_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|InfluxDB |InfluxDB: Version |<p>Version of the InfluxDB instance.</p> |DEPENDENT |influxdb.version<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_info")].labels.version.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|InfluxDB |InfluxDB: Uptime |<p>InfluxDB process uptime in seconds.</p> |DEPENDENT |influxdb.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="influxdb_uptime_seconds")].value.first()`</p> |
|InfluxDB |InfluxDB: Workers currently running |<p>Total number of workers currently running tasks.</p> |DEPENDENT |influxdb.task_executor_runs_active.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="task_executor_total_runs_active")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|InfluxDB |InfluxDB: Workers busy, pct |<p>Percent of total available workers that are currently busy.</p> |DEPENDENT |influxdb.task_executor_workers_busy.pct<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="task_executor_workers_busy")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|InfluxDB |InfluxDB: Task runs failed, rate |<p>Total number of failure runs across all tasks.</p> |DEPENDENT |influxdb.task_executor_complete.failed.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="task_executor_total_runs_complete" && @.labels.status == "failed")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|InfluxDB |InfluxDB: Task runs successful, rate |<p>Total number of runs successful completed across all tasks.</p> |DEPENDENT |influxdb.task_executor_complete.successful.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="task_executor_total_runs_complete" && @.labels.status == "success")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|InfluxDB |InfluxDB: [{#ORG_NAME}] Query requests bytes, success |<p>Count of bytes received with status 200 per second.</p> |DEPENDENT |influxdb.org.query_request_bytes.success.rate["{#ORG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="http_query_request_bytes" && @.labels.status == "200" && @.labels.endpoint == "/api/v2/query" && @.labels.org_id == "{#ORG_ID}") ].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|InfluxDB |InfluxDB: [{#ORG_NAME}] Query requests bytes, failed |<p>Count of bytes received with status not 200 per second.</p> |DEPENDENT |influxdb.org.query_request_bytes.failed.rate["{#ORG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="http_query_request_bytes" && @.labels.status != "200" && @.labels.endpoint == "/api/v2/query" && @.labels.org_id == "{#ORG_ID}") ].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|InfluxDB |InfluxDB: [{#ORG_NAME}] Query requests, failed |<p>Total number of query requests with status not 200 per second.</p> |DEPENDENT |influxdb.org.query_request.failed.rate["{#ORG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="http_query_request_count" && @.labels.status != "200" && @.labels.endpoint == "/api/v2/query" && @.labels.org_id == "{#ORG_ID}") ].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|InfluxDB |InfluxDB: [{#ORG_NAME}] Query requests, success |<p>Total number of query requests with status 200 per second.</p> |DEPENDENT |influxdb.org.query_request.success.rate["{#ORG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="http_query_request_count" && @.labels.status == "200" && @.labels.endpoint == "/api/v2/query" && @.labels.org_id == "{#ORG_ID}") ].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|InfluxDB |InfluxDB: [{#ORG_NAME}] Query response bytes, success |<p>Count of bytes returned with status 200 per second.</p> |DEPENDENT |influxdb.org.http_query_response_bytes.success.rate["{#ORG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="http_query_response_bytes" && @.labels.status == "200" && @.labels.endpoint == "/api/v2/query" && @.labels.org_id == "{#ORG_ID}") ].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|InfluxDB |InfluxDB: [{#ORG_NAME}] Query response bytes, failed |<p>Count of bytes returned with status not 200 per second.</p> |DEPENDENT |influxdb.org.http_query_response_bytes.failed.rate["{#ORG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="http_query_response_bytes" && @.labels.status != "200" && @.labels.endpoint == "/api/v2/query" && @.labels.org_id == "{#ORG_ID}") ].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix raw items |InfluxDB: Get instance metrics |<p>-</p> |HTTP_AGENT |influx.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- PROMETHEUS_TO_JSON</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|InfluxDB: Health check was failed |<p>The InfluxDB instance is not available or unhealthy.</p> |`last(/InfluxDB by HTTP/influx.healthcheck)=0` |HIGH | |
|InfluxDB: Version has changed |<p>InfluxDB version has changed. Ack to close.</p> |`last(/InfluxDB by HTTP/influxdb.version,#1)<>last(/InfluxDB by HTTP/influxdb.version,#2) and length(last(/InfluxDB by HTTP/influxdb.version))>0` |INFO |<p>Manual close: YES</p> |
|InfluxDB: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/InfluxDB by HTTP/influxdb.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|InfluxDB: Too many tasks failure runs |<p>"Number of failure runs completed across all tasks is too high."</p> |`min(/InfluxDB by HTTP/influxdb.task_executor_complete.failed.rate,5m)>{$INFLUXDB.TASK.RUN.FAIL.MAX.WARN}` |WARNING | |
|InfluxDB: [{#ORG_NAME}]: Too many requests failures |<p>Too many query requests failed.</p> |`min(/InfluxDB by HTTP/influxdb.org.query_request.failed.rate["{#ORG_NAME}"],5m)>{$INFLUXDB.REQ.FAIL.MAX.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

