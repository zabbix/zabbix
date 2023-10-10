
# TiDB by HTTP

## Overview

The template to monitor TiDB server of TiDB cluster by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `TiDB by HTTP` — collects metrics by HTTP agent from PD /metrics endpoint and from monitoring API.
See https://docs.pingcap.com/tidb/stable/tidb-monitoring-api.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- TiDB cluster 4.0.10, 6.5.1

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This template works with TiDB server of TiDB cluster.
Internal service metrics are collected from TiDB /metrics endpoint and from monitoring API.
See https://docs.pingcap.com/tidb/stable/tidb-monitoring-api.
Don't forget to change the macros {$TIDB.URL}, {$TIDB.PORT}.
Also, see the Macros section for a list of macros used to set trigger values.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TIDB.PORT}|<p>The port of TiDB server metrics web endpoint</p>|`10080`|
|{$TIDB.URL}|<p>TiDB server URL</p>|`localhost`|
|{$TIDB.OPEN.FDS.MAX.WARN}|<p>Maximum percentage of used file descriptors</p>|`90`|
|{$TIDB.HEAP.USAGE.MAX.WARN}|<p>Maximum heap memory used</p>|`10G`|
|{$TIDB.DDL.WAITING.MAX.WARN}|<p>Maximum number of DDL tasks that are waiting</p>|`5`|
|{$TIDB.TIME_JUMP_BACK.MAX.WARN}|<p>Maximum number of times that the operating system rewinds every second</p>|`1`|
|{$TIDB.SCHEMA_LEASE_ERRORS.MAX.WARN}|<p>Maximum number of schema lease errors</p>|`0`|
|{$TIDB.SCHEMA_LOAD_ERRORS.MAX.WARN}|<p>Maximum number of load schema errors</p>|`1`|
|{$TIDB.GC_ACTIONS.ERRORS.MAX.WARN}|<p>Maximum number of GC-related operations failures</p>|`1`|
|{$TIDB.REGION_ERROR.MAX.WARN}|<p>Maximum number of region related errors</p>|`50`|
|{$TIDB.MONITOR_KEEP_ALIVE.MAX.WARN}|<p>Minimum number of keep alive operations</p>|`10`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB: Get instance metrics|<p>Get TiDB instance metrics.</p>|HTTP agent|tidb.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li>Prometheus to JSON</li></ul>|
|TiDB: Get instance status|<p>Get TiDB instance status info.</p>|HTTP agent|tidb.get_status<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"status": "0"}`</p></li></ul>|
|TiDB: Status|<p>Status of PD instance.</p>|Dependent item|tidb.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set value to: `1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|TiDB: Get total server query metrics|<p>Get information about server queries.</p>|Dependent item|tidb.server_query.get_metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tidb_server_query_total")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiDB: Total "error" server query, rate|<p>The number of queries on TiDB instance per second with failure of command execution results.</p>|Dependent item|tidb.server_query.error.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.result == "Error")].value.sum()`</p></li><li>Change per second</li></ul>|
|TiDB: Total "ok" server query, rate|<p>The number of queries on TiDB instance per second with success of command execution results.</p>|Dependent item|tidb.server_query.ok.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.result == "OK")].value.sum()`</p></li><li>Change per second</li></ul>|
|TiDB: Total server query, rate|<p>The number of queries per second on TiDB instance.</p>|Dependent item|tidb.server_query.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..value.sum()`</p></li><li>Change per second</li></ul>|
|TiDB: Get SQL statements metrics|<p>Get SQL statements metrics.</p>|Dependent item|tidb.statement_total.get_metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_executor_statement_total")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiDB: SQL statements, rate|<p>The total number of SQL statements executed per second.</p>|Dependent item|tidb.statement_total.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..value.sum()`</p></li><li>Change per second</li></ul>|
|TiDB: Failed Query, rate|<p>The number of error occurred when executing SQL statements per second (such as syntax errors and primary key conflicts).</p>|Dependent item|tidb.execute_error.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_server_execute_error_total")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiDB: Get TiKV client metrics|<p>Get TiKV client metrics.</p>|Dependent item|tidb.tikvclient.get_metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=~"tidb_tikvclient_*")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiDB: KV commands, rate|<p>The number of executed KV commands per second.</p>|Dependent item|tidb.tikvclient_txn.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiDB: PD TSO commands, rate|<p>The number of TSO commands that TiDB obtains from PD per second.</p>|Dependent item|tidb.pd_tso_cmd.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiDB: PD TSO requests, rate|<p>The number of TSO requests that TiDB obtains from PD per second.</p>|Dependent item|tidb.pd_tso_request.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiDB: TiClient region errors, rate|<p>The number of region related errors returned by TiKV per second.</p>|Dependent item|tidb.tikvclient_region_err.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_tikvclient_region_err_total")].value.sum()`</p></li><li>Change per second</li></ul>|
|TiDB: Lock resolves, rate|<p>The number of DDL tasks that are waiting.</p>|Dependent item|tidb.tikvclient_lock_resolver_action.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiDB: DDL waiting jobs|<p>The number of TiDB operations that resolve locks per second. When TiDB's read or write request encounters a lock, it tries to resolve the lock.</p>|Dependent item|tidb.ddl_waiting_jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_ddl_waiting_jobs")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|TiDB: Load schema total, rate|<p>The statistics of the schemas that TiDB obtains from TiKV per second.</p>|Dependent item|tidb.domain_load_schema.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_domain_load_schema_total")].value.sum()`</p></li><li>Change per second</li></ul>|
|TiDB: Load schema failed, rate|<p>The total number of failures to reload the latest schema information in TiDB per second.</p>|Dependent item|tidb.domain_load_schema.failed.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiDB: Schema lease "outdate" errors , rate|<p>The number of schema lease errors per second.</p><p>"outdate" errors means that the schema cannot be updated, which is a more serious error and triggers an alert.</p>|Dependent item|tidb.session_schema_lease_error.outdate.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiDB: Schema lease "change" errors, rate|<p>The number of schema lease errors per second.</p><p>"change" means that the schema has changed</p>|Dependent item|tidb.session_schema_lease_error.change.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiDB: KV backoff, rate|<p>The number of errors returned by TiKV.</p>|Dependent item|tidb.tikvclient_backoff.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_tikvclient_backoff_total")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiDB: Keep alive, rate|<p>The number of times that the metrics are refreshed on TiDB instance per minute.</p>|Dependent item|tidb.monitor_keep_alive.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_monitor_keep_alive_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Simple change</li></ul>|
|TiDB: Server connections|<p>The connection number of current TiDB instance.</p>|Dependent item|tidb.tidb_server_connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_server_connections")].value.first()`</p></li></ul>|
|TiDB: Heap memory usage|<p>Number of heap bytes that are in use.</p>|Dependent item|tidb.heap_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="go_memstats_heap_inuse_bytes")].value.first()`</p></li></ul>|
|TiDB: RSS memory usage|<p>Resident memory size in bytes.</p>|Dependent item|tidb.rss_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="process_resident_memory_bytes")].value.first()`</p></li></ul>|
|TiDB: Goroutine count|<p>The number of Goroutines on TiDB instance.</p>|Dependent item|tidb.goroutines<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="go_goroutines")].value.first()`</p></li></ul>|
|TiDB: Open file descriptors|<p>Number of open file descriptors.</p>|Dependent item|tidb.process_open_fds<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="process_open_fds")].value.first()`</p></li></ul>|
|TiDB: Open file descriptors, max|<p>Maximum number of open file descriptors.</p>|Dependent item|tidb.process_max_fds<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="process_max_fds")].value.first()`</p></li></ul>|
|TiDB: CPU|<p>Total user and system CPU usage ratio.</p>|Dependent item|tidb.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="process_cpu_seconds_total")].value.first()`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|TiDB: Uptime|<p>The runtime of each TiDB instance.</p>|Dependent item|tidb.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="process_start_time_seconds")].value.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|TiDB: Version|<p>Version of the TiDB instance.</p>|Dependent item|tidb.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|TiDB: Time jump back, rate|<p>The number of times that the operating system rewinds every second.</p>|Dependent item|tidb.monitor_time_jump_back.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiDB: Server critical error, rate|<p>The number of critical errors occurred in TiDB per second.</p>|Dependent item|tidb.tidb_server_critical_error_total.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiDB: Server panic, rate|<p>The number of panics occurred in TiDB per second.</p>|Dependent item|tidb.tidb_server_panic_total.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_server_panic_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TiDB: Instance is not responding||`last(/TiDB by HTTP/tidb.status)=0`|Average||
|TiDB: Too many region related errors||`min(/TiDB by HTTP/tidb.tikvclient_region_err.rate,5m)>{$TIDB.REGION_ERROR.MAX.WARN}`|Average||
|TiDB: Too many DDL waiting jobs||`min(/TiDB by HTTP/tidb.ddl_waiting_jobs,5m)>{$TIDB.DDL.WAITING.MAX.WARN}`|Warning||
|TiDB: Too many schema lease errors||`min(/TiDB by HTTP/tidb.domain_load_schema.failed.rate,5m)>{$TIDB.SCHEMA_LOAD_ERRORS.MAX.WARN}`|Average||
|TiDB: Too many schema lease errors|<p>The latest schema information is not reloaded in TiDB within one lease.</p>|`min(/TiDB by HTTP/tidb.session_schema_lease_error.outdate.rate,5m)>{$TIDB.SCHEMA_LEASE_ERRORS.MAX.WARN}`|Average||
|TiDB: Too few keep alive operations|<p>Indicates whether the TiDB process still exists. If the number of times for tidb_monitor_keep_alive_total increases less than 10 per minute, the TiDB process might already exit and an alert is triggered.</p>|`max(/TiDB by HTTP/tidb.monitor_keep_alive.rate,5m)<{$TIDB.MONITOR_KEEP_ALIVE.MAX.WARN}`|Average||
|TiDB: Heap memory usage is too high||`min(/TiDB by HTTP/tidb.heap_bytes,5m)>{$TIDB.HEAP.USAGE.MAX.WARN}`|Warning||
|TiDB: Current number of open files is too high|<p>Heavy file descriptor usage (i.e., near the process's file descriptor limit) indicates a potential file descriptor exhaustion issue.</p>|`min(/TiDB by HTTP/tidb.process_open_fds,5m)/last(/TiDB by HTTP/tidb.process_max_fds)*100>{$TIDB.OPEN.FDS.MAX.WARN}`|Warning||
|TiDB: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/TiDB by HTTP/tidb.uptime)<10m`|Info|**Manual close**: Yes|
|TiDB: Version has changed|<p>TiDB version has changed. Acknowledge to close the problem manually.</p>|`last(/TiDB by HTTP/tidb.version,#1)<>last(/TiDB by HTTP/tidb.version,#2) and length(last(/TiDB by HTTP/tidb.version))>0`|Info|**Manual close**: Yes|
|TiDB: Too many time jump backs||`min(/TiDB by HTTP/tidb.monitor_time_jump_back.rate,5m)>{$TIDB.TIME_JUMP_BACK.MAX.WARN}`|Warning||
|TiDB: There are panicked TiDB threads|<p>When a panic occurs, an alert is triggered. The thread is often recovered, otherwise, TiDB will frequently restart.</p>|`last(/TiDB by HTTP/tidb.tidb_server_panic_total.rate)>0`|Average||

### LLD rule QPS metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|QPS metrics discovery|<p>Discovery QPS specific metrics.</p>|Dependent item|tidb.qps.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for QPS metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB: Get QPS metrics: {#TYPE}|<p>Get QPS metrics of {#TYPE}.</p>|Dependent item|tidb.qps.get_metrics[{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.type == "{#TYPE}")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiDB: Server query "OK": {#TYPE}, rate|<p>The number of queries on TiDB instance per second with success of command execution results.</p>|Dependent item|tidb.server_query.ok.rate[{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.result == "OK")].value.first()`</p></li><li>Change per second</li></ul>|
|TiDB: Server query "Error": {#TYPE}, rate|<p>The number of queries on TiDB instance per second with failure of command execution results.</p>|Dependent item|tidb.server_query.error.rate[{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.result == "Error")].value.first()`</p></li><li>Change per second</li></ul>|

### LLD rule Statement metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Statement metrics discovery|<p>Discovery statement specific metrics.</p>|Dependent item|tidb.statement.discover<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Statement metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB: SQL statements: {#TYPE}, rate|<p>The number of SQL statements executed per second.</p>|Dependent item|tidb.statement.rate[{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.type == "{#TYPE}")].value.first()`</p></li><li>Change per second</li></ul>|

### LLD rule KV metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|KV metrics discovery|<p>Discovery KV specific metrics.</p>|Dependent item|tidb.kv_ops.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for KV metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB: KV Commands: {#TYPE}, rate|<p>The number of executed KV commands per second.</p>|Dependent item|tidb.tikvclient_txn.rate[{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|

### LLD rule Lock resolves discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Lock resolves discovery|<p>Discovery lock resolves specific metrics.</p>|Dependent item|tidb.tikvclient_lock_resolver_action.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_tikvclient_lock_resolver_actions_total")]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Lock resolves discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB: Lock resolves: {#TYPE}, rate|<p>The number of TiDB operations that resolve locks per second. When TiDB's read or write request encounters a lock, it tries to resolve the lock.</p>|Dependent item|tidb.tikvclient_lock_resolver_action.rate[{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|

### LLD rule KV backoff discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|KV backoff discovery|<p>Discovery KV backoff specific metrics.</p>|Dependent item|tidb.tikvclient_backoff.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_tikvclient_backoff_total")]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for KV backoff discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB: KV backoff: {#TYPE}, rate|<p>The number of TiDB operations that resolve locks per second. When TiDB's read or write request encounters a lock, it tries to resolve the lock.</p>|Dependent item|tidb.tikvclient_backoff.rate[{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|

### LLD rule GC action results discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GC action results discovery|<p>Discovery GC action results metrics.</p>|Dependent item|tidb.tikvclient_gc_action.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="tidb_tikvclient_gc_action_result")]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for GC action results discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB: GC action result: {#TYPE}, rate|<p>The number of results of GC-related operations per second.</p>|Dependent item|tidb.tikvclient_gc_action.rate[{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for GC action results discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TiDB: Too many failed GC-related operations||`min(/TiDB by HTTP/tidb.tikvclient_gc_action.rate[{#TYPE}],5m)>{$TIDB.GC_ACTIONS.ERRORS.MAX.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

