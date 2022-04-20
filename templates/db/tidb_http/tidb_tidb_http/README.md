
# TiDB by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor TiDB server of TiDB cluster by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `TiDB by HTTP` — collects metrics by HTTP agent from PD /metrics endpoint and from monitoring API.
See https://docs.pingcap.com/tidb/stable/tidb-monitoring-api.


This template was tested on:

- TiDB cluster, version 4.0.10

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

This template works with TiDB server of TiDB cluster.
Internal service metrics are collected from TiDB /metrics endpoint and from monitoring API.
See https://docs.pingcap.com/tidb/stable/tidb-monitoring-api.
Don't forget to change the macros {$TIDB.URL}, {$TIDB.PORT}.
Also, see the Macros section for a list of macros used to set trigger values.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TIDB.DDL.WAITING.MAX.WARN} |<p>Maximum number of DDL tasks that are waiting</p> |`5` |
|{$TIDB.GC_ACTIONS.ERRORS.MAX.WARN} |<p>Maximum number of GC-related operations failures</p> |`1` |
|{$TIDB.HEAP.USAGE.MAX.WARN} |<p>Maximum heap memory used</p> |`10G` |
|{$TIDB.MONITOR_KEEP_ALIVE.MAX.WARN} |<p>Minimum number of keep alive operations</p> |`10` |
|{$TIDB.OPEN.FDS.MAX.WARN} |<p>Maximum percentage of used file descriptors</p> |`90` |
|{$TIDB.PORT} |<p>The port of TiDB server metrics web endpoint</p> |`10080` |
|{$TIDB.REGION_ERROR.MAX.WARN} |<p>Maximum number of region related errors</p> |`50` |
|{$TIDB.SCHEMA_LEASE_ERRORS.MAX.WARN} |<p>Maximum number of schema lease errors</p> |`0` |
|{$TIDB.SCHEMA_LOAD_ERRORS.MAX.WARN} |<p>Maximum number of load schema errors</p> |`1` |
|{$TIDB.TIME_JUMP_BACK.MAX.WARN} |<p>Maximum number of times that the operating system rewinds every second</p> |`1` |
|{$TIDB.URL} |<p>TiDB server URL</p> |`localhost` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|GC action results discovery |<p>Discovery GC action results metrics.</p> |DEPENDENT |tidb.tikvclient_gc_action.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_gc_action_result")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Overrides:**</p><p>Failed GC-related operations trigger<br> - {#TYPE} MATCHES_REGEX `failed`<br>  - TRIGGER_PROTOTYPE LIKE `Too many failed GC-related operations` - DISCOVER</p> |
|KV backoff discovery |<p>Discovery KV backoff specific metrics.</p> |DEPENDENT |tidb.tikvclient_backoff.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_backoff_total")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|KV metrics discovery |<p>Discovery KV specific metrics.</p> |DEPENDENT |tidb.kv_ops.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_txn_cmd_duration_seconds_count")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Lock resolves discovery |<p>Discovery lock resolves specific metrics.</p> |DEPENDENT |tidb.tikvclient_lock_resolver_action.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_lock_resolver_actions_total")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|QPS metrics discovery |<p>Discovery QPS specific metrics.</p> |DEPENDENT |tidb.qps.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_server_query_total")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Statement metrics discovery |<p>Discovery statement specific metrics.</p> |DEPENDENT |tidb.statement.discover<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_executor_statement_total")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|TiDB node |TiDB: Status |<p>Status of PD instance.</p> |DEPENDENT |tidb.status<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TiDB node |TiDB: Total "error" server query, rate |<p>The number of queries on TiDB instance per second with failure of command execution results.</p> |DEPENDENT |tidb.server_query.error.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tidb_server_query_total" && @.labels.result == "Error")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Total "ok" server query, rate |<p>The number of queries on TiDB instance per second with success of command execution results.</p> |DEPENDENT |tidb.server_query.ok.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tidb_server_query_total" && @.labels.result == "OK")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Total server query, rate |<p>The number of queries per second on TiDB instance.</p> |DEPENDENT |tidb.server_query.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tidb_server_query_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: SQL statements, rate |<p>The total number of SQL statements executed per second.</p> |DEPENDENT |tidb.statement_total.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_executor_statement_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Failed Query, rate |<p>The number of error occurred when executing SQL statements per second (such as syntax errors and primary key conflicts).</p> |DEPENDENT |tidb.execute_error.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_server_execute_error_total")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: KV commands, rate |<p>The number of executed KV commands per second.</p> |DEPENDENT |tidb.tikvclient_txn.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_txn_cmd_duration_seconds_count")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: PD TSO commands, rate |<p>The number of TSO commands that TiDB obtains from PD per second.</p> |DEPENDENT |tidb.pd_tso_cmd.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="pd_client_cmd_handle_cmds_duration_seconds_count" && @.labels.type == "tso")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: PD TSO requests, rate |<p>The number of TSO requests that TiDB obtains from PD per second.</p> |DEPENDENT |tidb.pd_tso_request.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="pd_client_request_handle_requests_duration_seconds_count" && @.labels.type == "tso")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: TiClient region errors, rate |<p>The number of region related errors returned by TiKV per second.</p> |DEPENDENT |tidb.tikvclient_region_err.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_region_err_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Lock resolves, rate |<p>The number of DDL tasks that are waiting.</p> |DEPENDENT |tidb.tikvclient_lock_resolver_action.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_lock_resolver_actions_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: DDL waiting jobs |<p>The number of TiDB operations that resolve locks per second. When TiDB's read or write request encounters a lock, it tries to resolve the lock.</p> |DEPENDENT |tidb.ddl_waiting_jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_ddl_waiting_jobs")].value.sum()`</p> |
|TiDB node |TiDB: Load schema total, rate |<p>The statistics of the schemas that TiDB obtains from TiKV per second.</p> |DEPENDENT |tidb.domain_load_schema.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_domain_load_schema_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Load schema failed, rate |<p>The total number of failures to reload the latest schema information in TiDB per second.</p> |DEPENDENT |tidb.domain_load_schema.failed.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_domain_load_schema_total && @.labels.type == "failed"")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Schema lease "outdate" errors , rate |<p>The number of schema lease errors per second.</p><p>"outdate" errors means that the schema cannot be updated, which is a more serious error and triggers an alert.</p> |DEPENDENT |tidb.session_schema_lease_error.outdate.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_session_schema_lease_error_total && @.labels.type == "outdate"")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Schema lease "change" errors, rate |<p>The number of schema lease errors per second.</p><p>"change" means that the schema has changed</p> |DEPENDENT |tidb.session_schema_lease_error.change.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_session_schema_lease_error_total && @.labels.type == "change"")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: KV backoff, rate |<p>The number of errors returned by TiKV.</p> |DEPENDENT |tidb.tikvclient_backoff.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_backoff_total")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Keep alive, rate |<p>The number of times that the metrics are refreshed on TiDB instance per minute.</p> |DEPENDENT |tidb.monitor_keep_alive.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_monitor_keep_alive_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- SIMPLE_CHANGE</p> |
|TiDB node |TiDB: Server connections |<p>The connection number of current TiDB instance.</p> |DEPENDENT |tidb.tidb_server_connections<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_server_connections")].value.first()`</p> |
|TiDB node |TiDB: Heap memory usage |<p>Number of heap bytes that are in use.</p> |DEPENDENT |tidb.heap_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="go_memstats_heap_inuse_bytes")].value.first()`</p> |
|TiDB node |TiDB: RSS memory usage |<p>Resident memory size in bytes.</p> |DEPENDENT |tidb.rss_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="process_resident_memory_bytes")].value.first()`</p> |
|TiDB node |TiDB: Goroutine count |<p>The number of Goroutines on TiDB instance.</p> |DEPENDENT |tidb.goroutines<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="go_goroutines")].value.first()`</p> |
|TiDB node |TiDB: Open file descriptors |<p>Number of open file descriptors.</p> |DEPENDENT |tidb.process_open_fds<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="process_open_fds")].value.first()`</p> |
|TiDB node |TiDB: Open file descriptors, max |<p>Maximum number of open file descriptors.</p> |DEPENDENT |tidb.process_max_fds<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="process_max_fds")].value.first()`</p> |
|TiDB node |TiDB: CPU |<p>Total user and system CPU usage ratio.</p> |DEPENDENT |tidb.cpu.util<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="process_cpu_seconds_total")].value.first()`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|TiDB node |TiDB: Uptime |<p>The runtime of each TiDB instance.</p> |DEPENDENT |tidb.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="process_start_time_seconds")].value.first()`</p><p>- JAVASCRIPT: `//use boottime to calculate uptime return (Math.floor(Date.now()/1000)-Number(value)); `</p> |
|TiDB node |TiDB: Version |<p>Version of the TiDB instance.</p> |DEPENDENT |tidb.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|TiDB node |TiDB: Time jump back, rate |<p>The number of times that the operating system rewinds every second.</p> |DEPENDENT |tidb.monitor_time_jump_back.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_monitor_time_jump_back_total")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Server critical error, rate |<p>The number of critical errors occurred in TiDB per second.</p> |DEPENDENT |tidb.tidb_server_critical_error_total.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_server_critical_error_total")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Server panic, rate |<p>The number of panics occurred in TiDB per second.</p> |DEPENDENT |tidb.tidb_server_panic_total.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_server_panic_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Server query "OK": {#TYPE}, rate |<p>The number of queries on TiDB instance per second with success of command execution results.</p> |DEPENDENT |tidb.server_query.ok.rate[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tidb_server_query_total" && @.labels.result == "OK" && @.labels.type == "{#TYPE}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Server query "Error": {#TYPE}, rate |<p>The number of queries on TiDB instance per second with failure of command execution results.</p> |DEPENDENT |tidb.server_query.error.rate[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tidb_server_query_total" && @.labels.result == "Error" && @.labels.type == "{#TYPE}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: SQL statements: {#TYPE}, rate |<p>The number of SQL statements executed per second.</p> |DEPENDENT |tidb.statement.rate[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_executor_statement_total" && @.labels.type == "{#TYPE}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: KV Commands: {#TYPE}, rate |<p>The number of executed KV commands per second.</p> |DEPENDENT |tidb.tikvclient_txn.rate[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_txn_cmd_duration_seconds_count" && @.labels.type == "{#TYPE}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: Lock resolves: {#TYPE}, rate |<p>The number of TiDB operations that resolve locks per second. When TiDB's read or write request encounters a lock, it tries to resolve the lock.</p> |DEPENDENT |tidb.tikvclient_lock_resolver_action.rate[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_lock_resolver_actions_total" && @.labels.type == "{#TYPE}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: KV backoff: {#TYPE}, rate |<p>The number of TiDB operations that resolve locks per second. When TiDB's read or write request encounters a lock, it tries to resolve the lock.</p> |DEPENDENT |tidb.tikvclient_backoff.rate[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_backoff_total" && @.labels.type == "{#TYPE}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB node |TiDB: GC action result: {#TYPE}, rate |<p>The number of results of GC-related operations per second.</p> |DEPENDENT |tidb.tikvclient_gc_action.rate[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="tidb_tikvclient_gc_action_result" && @.labels.type == "{#TYPE}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix raw items |TiDB: Get instance metrics |<p>Get TiDB instance metrics.</p> |HTTP_AGENT |tidb.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- PROMETHEUS_TO_JSON</p> |
|Zabbix raw items |TiDB: Get instance status |<p>Get TiDB instance status info.</p> |HTTP_AGENT |tidb.get_status<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"status": "0"}`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|TiDB: Instance is not responding |<p>-</p> |`last(/TiDB by HTTP/tidb.status)=0` |AVERAGE | |
|TiDB: Too many region related errors |<p>-</p> |`min(/TiDB by HTTP/tidb.tikvclient_region_err.rate,5m)>{$TIDB.REGION_ERROR.MAX.WARN}` |AVERAGE | |
|TiDB: Too many DDL waiting jobs |<p>-</p> |`min(/TiDB by HTTP/tidb.ddl_waiting_jobs,5m)>{$TIDB.DDL.WAITING.MAX.WARN}` |WARNING | |
|TiDB: Too many schema lease errors |<p>-</p> |`min(/TiDB by HTTP/tidb.domain_load_schema.failed.rate,5m)>{$TIDB.SCHEMA_LOAD_ERRORS.MAX.WARN}` |AVERAGE | |
|TiDB: Too many schema lease errors |<p>The latest schema information is not reloaded in TiDB within one lease.</p> |`min(/TiDB by HTTP/tidb.session_schema_lease_error.outdate.rate,5m)>{$TIDB.SCHEMA_LEASE_ERRORS.MAX.WARN}` |AVERAGE | |
|TiDB: Too few keep alive operations |<p>Indicates whether the TiDB process still exists. If the number of times for tidb_monitor_keep_alive_total increases less than 10 per minute, the TiDB process might already exit and an alert is triggered.</p> |`max(/TiDB by HTTP/tidb.monitor_keep_alive.rate,5m)<{$TIDB.MONITOR_KEEP_ALIVE.MAX.WARN}` |AVERAGE | |
|TiDB: Heap memory usage is too high |<p>-</p> |`min(/TiDB by HTTP/tidb.heap_bytes,5m)>{$TIDB.HEAP.USAGE.MAX.WARN}` |WARNING | |
|TiDB: Current number of open files is too high |<p>Heavy file descriptor usage (i.e., near the process's file descriptor limit) indicates a potential file descriptor exhaustion issue.</p> |`min(/TiDB by HTTP/tidb.process_open_fds,5m)/last(/TiDB by HTTP/tidb.process_max_fds)*100>{$TIDB.OPEN.FDS.MAX.WARN}` |WARNING | |
|TiDB: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/TiDB by HTTP/tidb.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|TiDB: Version has changed |<p>TiDB version has changed. Ack to close.</p> |`last(/TiDB by HTTP/tidb.version,#1)<>last(/TiDB by HTTP/tidb.version,#2) and length(last(/TiDB by HTTP/tidb.version))>0` |INFO |<p>Manual close: YES</p> |
|TiDB: Too many time jump backs |<p>-</p> |`min(/TiDB by HTTP/tidb.monitor_time_jump_back.rate,5m)>{$TIDB.TIME_JUMP_BACK.MAX.WARN}` |WARNING | |
|TiDB: There are panicked TiDB threads |<p>When a panic occurs, an alert is triggered. The thread is often recovered, otherwise, TiDB will frequently restart.</p> |`last(/TiDB by HTTP/tidb.tidb_server_panic_total.rate)>0` |AVERAGE | |
|TiDB: Too many failed GC-related operations |<p>-</p> |`min(/TiDB by HTTP/tidb.tikvclient_gc_action.rate[{#TYPE}],5m)>{$TIDB.GC_ACTIONS.ERRORS.MAX.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

