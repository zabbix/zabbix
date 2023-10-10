
# InfluxDB by HTTP

## Overview

This template is designed for the effortless deployment of InfluxDB monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- InfluxDB 2.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This template works with self-hosted InfluxDB instances. Internal service metrics are collected from InfluxDB /metrics endpoint.
For organization discovery template need to use Authorization via API token. See docs: https://docs.influxdata.com/influxdb/v2.0/security/tokens/

Don't forget to change the macros {$INFLUXDB.URL},  {$INFLUXDB.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.
*NOTE.* Some metrics may not be collected depending on your InfluxDB instance version and configuration.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$INFLUXDB.URL}|<p>InfluxDB instance URL</p>|`http://localhost:8086`|
|{$INFLUXDB.API.TOKEN}|<p>InfluxDB API Authorization Token</p>||
|{$INFLUXDB.ORG_NAME.MATCHES}|<p>Filter of discoverable organizations</p>|`.*`|
|{$INFLUXDB.ORG_NAME.NOT_MATCHES}|<p>Filter to exclude discovered organizations</p>|`CHANGE_IF_NEEDED`|
|{$INFLUXDB.TASK.RUN.FAIL.MAX.WARN}|<p>Maximum number of tasks runs failures for trigger expression.</p>|`2`|
|{$INFLUXDB.REQ.FAIL.MAX.WARN}|<p>Maximum number of query requests failures for trigger expression.</p>|`2`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|InfluxDB: Get instance metrics||HTTP agent|influx.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li>Prometheus to JSON</li></ul>|
|InfluxDB: Instance status|<p>Get the health of an instance.</p>|HTTP agent|influx.healthcheck<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"status":"fail"}]}`</p></li><li><p>JavaScript: `return JSON.parse(value).status == 'pass' ? 1: 0`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|InfluxDB: Boltdb reads, rate|<p>Total number of boltdb reads per second.</p>|Dependent item|influxdb.boltdb_reads.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="boltdb_reads_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|InfluxDB: Boltdb writes, rate|<p>Total number of boltdb writes per second.</p>|Dependent item|influxdb.boltdb_writes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="boltdb_writes_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|InfluxDB: Buckets, total|<p>Number of total buckets on the server.</p>|Dependent item|influxdb.buckets.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_buckets_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|InfluxDB: Dashboards, total|<p>Number of total dashboards on the server.</p>|Dependent item|influxdb.dashboards.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_dashboards_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|InfluxDB: Organizations, total|<p>Number of total organizations on the server.</p>|Dependent item|influxdb.organizations.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_organizations_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|InfluxDB: Scrapers, total|<p>Number of total scrapers on the server.</p>|Dependent item|influxdb.scrapers.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_scrapers_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|InfluxDB: Telegraf plugins, total|<p>Number of individual telegraf plugins configured.</p>|Dependent item|influxdb.telegraf_plugins.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_telegraf_plugins_count")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|InfluxDB: Telegrafs, total|<p>Number of total telegraf configurations on the server.</p>|Dependent item|influxdb.telegrafs.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_telegrafs_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|InfluxDB: Tokens, total|<p>Number of total tokens on the server.</p>|Dependent item|influxdb.tokens.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_tokens_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|InfluxDB: Users, total|<p>Number of total users on the server.</p>|Dependent item|influxdb.users.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_users_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|InfluxDB: Version|<p>Version of the InfluxDB instance.</p>|Dependent item|influxdb.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_info")].labels.version.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|InfluxDB: Uptime|<p>InfluxDB process uptime in seconds.</p>|Dependent item|influxdb.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="influxdb_uptime_seconds")].value.first()`</p></li></ul>|
|InfluxDB: Workers currently running|<p>Total number of workers currently running tasks.</p>|Dependent item|influxdb.task_executor_runs_active.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|InfluxDB: Workers busy, pct|<p>Percent of total available workers that are currently busy.</p>|Dependent item|influxdb.task_executor_workers_busy.pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="task_executor_workers_busy")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|InfluxDB: Task runs failed, rate|<p>Total number of failure runs across all tasks.</p>|Dependent item|influxdb.task_executor_complete.failed.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|InfluxDB: Task runs successful, rate|<p>Total number of runs successful completed across all tasks.</p>|Dependent item|influxdb.task_executor_complete.successful.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|InfluxDB: Health check was failed|<p>The InfluxDB instance is not available or unhealthy.</p>|`last(/InfluxDB by HTTP/influx.healthcheck)=0`|High||
|InfluxDB: Version has changed|<p>InfluxDB version has changed. Acknowledge to close the problem manually.</p>|`last(/InfluxDB by HTTP/influxdb.version,#1)<>last(/InfluxDB by HTTP/influxdb.version,#2) and length(last(/InfluxDB by HTTP/influxdb.version))>0`|Info|**Manual close**: Yes|
|InfluxDB: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/InfluxDB by HTTP/influxdb.uptime)<10m`|Info|**Manual close**: Yes|
|InfluxDB: Too many tasks failure runs|<p>"Number of failure runs completed across all tasks is too high."</p>|`min(/InfluxDB by HTTP/influxdb.task_executor_complete.failed.rate,5m)>{$INFLUXDB.TASK.RUN.FAIL.MAX.WARN}`|Warning||

### LLD rule Organizations discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Organizations discovery|<p>Discovery of organizations metrics.</p>|HTTP agent|influxdb.orgs.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Organizations discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|InfluxDB: [{#ORG_NAME}] Query requests bytes, success|<p>Count of bytes received with status 200 per second.</p>|Dependent item|influxdb.org.query_request_bytes.success.rate["{#ORG_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|InfluxDB: [{#ORG_NAME}] Query requests bytes, failed|<p>Count of bytes received with status not 200 per second.</p>|Dependent item|influxdb.org.query_request_bytes.failed.rate["{#ORG_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|InfluxDB: [{#ORG_NAME}] Query requests, failed|<p>Total number of query requests with status not 200 per second.</p>|Dependent item|influxdb.org.query_request.failed.rate["{#ORG_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|InfluxDB: [{#ORG_NAME}] Query requests, success|<p>Total number of query requests with status 200 per second.</p>|Dependent item|influxdb.org.query_request.success.rate["{#ORG_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|InfluxDB: [{#ORG_NAME}] Query response bytes, success|<p>Count of bytes returned with status 200 per second.</p>|Dependent item|influxdb.org.http_query_response_bytes.success.rate["{#ORG_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|InfluxDB: [{#ORG_NAME}] Query response bytes, failed|<p>Count of bytes returned with status not 200 per second.</p>|Dependent item|influxdb.org.http_query_response_bytes.failed.rate["{#ORG_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Organizations discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|InfluxDB: [{#ORG_NAME}]: Too many requests failures|<p>Too many query requests failed.</p>|`min(/InfluxDB by HTTP/influxdb.org.query_request.failed.rate["{#ORG_NAME}"],5m)>{$INFLUXDB.REQ.FAIL.MAX.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

