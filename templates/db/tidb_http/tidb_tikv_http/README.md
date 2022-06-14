
# TiDB TiKV by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor TiKV server of TiDB cluster by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `TiDB TiKV by HTTP` — collects metrics by HTTP agent from TiKV /metrics endpoint.


This template was tested on:

- TiDB cluster, version 4.0.10

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

This template works with TiKV server of TiDB cluster.
Internal service metrics are collected from TiKV /metrics endpoint.
Don't forget to change the macros {$TIKV.URL}, {$TIKV.PORT}.
Also, see the Macros section for a list of macros used to set trigger values.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TIKV.COPOCESSOR.ERRORS.MAX.WARN} |<p>Maximum number of coprocessor request errors</p> |`1` |
|{$TIKV.PENDING_COMMANDS.MAX.WARN} |<p>Maximum number of pending commands</p> |`1` |
|{$TIKV.PENDING_TASKS.MAX.WARN} |<p>Maximum number of tasks currently running by the worker or pending</p> |`1` |
|{$TIKV.PORT} |<p>The port of TiKV server metrics web endpoint</p> |`20180` |
|{$TIKV.STORE.ERRORS.MAX.WARN} |<p>Maximum number of failure messages</p> |`1` |
|{$TIKV.URL} |<p>TiKV server URL</p> |`localhost` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Coprocessor metrics discovery |<p>Discovery coprocessor metrics.</p> |DEPENDENT |tikv.coprocessor.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_request_duration_seconds_count")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|QPS metrics discovery |<p>Discovery QPS metrics.</p> |DEPENDENT |tikv.qps.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_grpc_msg_duration_seconds_count")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Scheduler metrics discovery |<p>Discovery scheduler metrics.</p> |DEPENDENT |tikv.scheduler.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_scheduler_stage_total")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Server errors discovery |<p>Discovery server errors metrics.</p> |DEPENDENT |tikv.server_report_failure.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_server_report_failure_msg_total")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Overrides:**</p><p>Too many unreachable messages trigger<br> - {#TYPE} MATCHES_REGEX `unreachable`<br>  - TRIGGER_PROTOTYPE LIKE `Too many failure messages` - DISCOVER</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|TiKV node |TiKV: Store size |<p>The storage size of TiKV instance.</p> |DEPENDENT |tikv.engine_size<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_engine_size_bytes")].value.sum()`</p> |
|TiKV node |TiKV: Available size |<p>The available capacity of TiKV instance.</p> |DEPENDENT |tikv.store_size.available<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_store_size_bytes" && @.labels.type == "available")].value.first()`</p> |
|TiKV node |TiKV: Capacity size |<p>The capacity size of TiKV instance.</p> |DEPENDENT |tikv.store_size.capacity<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_store_size_bytes" && @.labels.type == "capacity")].value.first()`</p> |
|TiKV node |TiKV: Bytes read |<p>The total bytes of read in TiKV instance.</p> |DEPENDENT |tikv.engine_flow_bytes.read<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_engine_flow_bytes" && @.labels.db == "kv" && @.labels.type =~ "bytes_read|iter_bytes_read")].value.sum()`</p> |
|TiKV node |TiKV: Bytes write |<p>The total bytes of write in TiKV instance.</p> |DEPENDENT |tikv.engine_flow_bytes.write<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_engine_flow_bytes" && @.labels.db == "kv" && @.labels.type == "wal_file_bytes")].value.first()`</p> |
|TiKV node |TiKV: Storage: commands total, rate |<p>Total number of commands received per second.</p> |DEPENDENT |tikv.storage_command.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_storage_command_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: CPU util |<p>The CPU usage ratio on TiKV instance.</p> |DEPENDENT |tikv.cpu.util<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_thread_cpu_seconds_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|TiKV node |TiKV: RSS memory usage |<p>Resident memory size in bytes.</p> |DEPENDENT |tikv.rss_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "process_resident_memory_bytes")].value.first()`</p> |
|TiKV node |TiKV: Regions, count |<p>The number of regions collected in TiKV instance.</p> |DEPENDENT |tikv.region_count<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_raftstore_region_count" && @.labels.type == "region" )].value.first()`</p> |
|TiKV node |TiKV: Regions, leader |<p>The number of leaders in TiKV instance.</p> |DEPENDENT |tikv.region_leader<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_raftstore_region_count" && @.labels.type == "leader" )].value.first()`</p> |
|TiKV node |TiKV: Total query, rate |<p>The total QPS in TiKV instance.</p> |DEPENDENT |tikv.grpc_msg.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_grpc_msg_duration_seconds_count")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Total query errors, rate |<p>The total number of gRPC message handling failure per second.</p> |DEPENDENT |tikv.grpc_msg_fail.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_grpc_msg_fail_total")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Coprocessor: Errors, rate |<p>Total number of push down request error per second.</p> |DEPENDENT |tikv.coprocessor_request_error.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_request_error")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Coprocessor: Requests, rate |<p>Total number of coprocessor requests per second.</p> |DEPENDENT |tikv.coprocessor_request.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_request_duration_seconds_count")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Coprocessor: Scan keys, rate |<p>Total number of scan keys observed per request per second.</p> |DEPENDENT |tikv.coprocessor_scan_keys_sum.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_scan_keys")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Coprocessor: RocksDB ops, rate |<p>Total number of RocksDB internal operations from PerfContext per second.</p> |DEPENDENT |tikv.coprocessor_rocksdb_perf.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_rocksdb_perf")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Coprocessor: Response size, rate |<p>The total size of coprocessor response per second.</p> |DEPENDENT |tikv.coprocessor_response_bytes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_response_bytes")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Scheduler: Pending commands |<p>The total number of pending commands. The scheduler receives commands from clients, executes them against the MVCC layer storage engine.</p> |DEPENDENT |tikv.scheduler_contex<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_scheduler_contex_total")].value.first()`</p> |
|TiKV node |TiKV: Scheduler: Busy, rate |<p>The total count of too busy schedulers per second.</p> |DEPENDENT |tikv.scheduler_too_busy.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_scheduler_too_busy_total")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Scheduler: Commands total, rate |<p>Total number of commands per second.</p> |DEPENDENT |tikv.scheduler_commands.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_scheduler_stage_total")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Scheduler: Low priority commands total, rate |<p>Total count of low priority commands per second.</p> |DEPENDENT |tikv.commands_pri.low.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_scheduler_commands_pri_total" && @.labels.priority == "low")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Scheduler: Normal priority commands total, rate |<p>Total count of normal priority commands per second.</p> |DEPENDENT |tikv.commands_pri.normal.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_scheduler_commands_pri_total" && @.labels.priority == "normal")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Scheduler: High priority commands total, rate |<p>Total count of high priority commands per second.</p> |DEPENDENT |tikv.commands_pri.high.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_scheduler_commands_pri_total" && @.labels.priority == "high")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Snapshot: Pending tasks |<p>The number of tasks currently running by the worker or pending.</p> |DEPENDENT |tikv.worker_pending_task<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_worker_pending_task_total")].value.first()`</p> |
|TiKV node |TiKV: Snapshot: Sending |<p>The total amount of raftstore snapshot traffic.</p> |DEPENDENT |tikv.snapshot.sending<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_raftstore_snapshot_traffic_total" && @.labels.type == "sending")].value.first()`</p> |
|TiKV node |TiKV: Snapshot: Receiving |<p>The total amount of raftstore snapshot traffic.</p> |DEPENDENT |tikv.snapshot.receiving<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_raftstore_snapshot_traffic_total" && @.labels.type == "receiving")].value.first()`</p> |
|TiKV node |TiKV: Snapshot: Applying |<p>The total amount of raftstore snapshot traffic.</p> |DEPENDENT |tikv.snapshot.applying<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_raftstore_snapshot_traffic_total" && @.labels.type == "applying")].value.first()`</p> |
|TiKV node |TiKV: Uptime |<p>The runtime of each TiKV instance.</p> |DEPENDENT |tikv.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="process_start_time_seconds")].value.first()`</p><p>- JAVASCRIPT: `//use boottime to calculate uptime return (Math.floor(Date.now()/1000)-Number(value)); `</p> |
|TiKV node |TiKV: Server: failure messages total, rate |<p>Total number of reporting failure messages per second.</p> |DEPENDENT |tikv.messages.failure.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_server_report_failure_msg_total")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Query: {#TYPE}, rate |<p>The QPS per command in TiKV instance.</p> |DEPENDENT |tikv.grpc_msg.rate[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_grpc_msg_duration_seconds_count" && @.labels.type == "{#TYPE}")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> `</p> |
|TiKV node |TiKV: Coprocessor: {#REQ_TYPE} errors, rate |<p>Total number of push down request error per second.</p> |DEPENDENT |tikv.coprocessor_request_error.rate[{#REQ_TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_request_error" && @.labels.req == "{#REQ_TYPE}")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Coprocessor: {#REQ_TYPE} requests, rate |<p>Total number of coprocessor requests per second.</p> |DEPENDENT |tikv.coprocessor_request.rate[{#REQ_TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_request_duration_seconds_count" && @.labels.req == "{#REQ_TYPE}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Coprocessor: {#REQ_TYPE} scan keys, rate |<p>Total number of scan keys observed per request per second.</p> |DEPENDENT |tikv.coprocessor_scan_keys.rate[{#REQ_TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_scan_keys_count" && @.labels.req == "{#REQ_TYPE}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Coprocessor: {#REQ_TYPE} RocksDB ops, rate |<p>Total number of RocksDB internal operations from PerfContext per second.</p> |DEPENDENT |tikv.coprocessor_rocksdb_perf.rate[{#REQ_TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_coprocessor_rocksdb_perf" && @.labels.req == "{#REQ_TYPE}")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Scheduler: commands {#STAGE}, rate |<p>Total number of commands on each stage per second.</p> |DEPENDENT |tikv.scheduler_stage.rate[{#STAGE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_scheduler_stage_total" && @.labels.stage == "{#STAGE}")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|TiKV node |TiKV: Store_id {#STORE_ID}: failure messages "{#TYPE}", rate |<p>Total number of reporting failure messages. The metric has two labels: type and store_id. type represents the failure type, and store_id represents the destination peer store id.</p> |DEPENDENT |tikv.messages.failure.rate[{#STORE_ID},{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "tikv_server_report_failure_msg_total" && @.labels.store_id == "{#STORE_ID}"  && @.labels.type == "{#TYPE}")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix raw items |TiKV: Get instance metrics |<p>Get TiKV instance metrics.</p> |HTTP_AGENT |tikv.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- PROMETHEUS_TO_JSON</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|TiKV: Too many coprocessor request error |<p>-</p> |`min(/TiDB TiKV by HTTP/tikv.coprocessor_request_error.rate,5m)>{$TIKV.COPOCESSOR.ERRORS.MAX.WARN}` |WARNING | |
|TiKV: Too many pending commands |<p>-</p> |`min(/TiDB TiKV by HTTP/tikv.scheduler_contex,5m)>{$TIKV.PENDING_COMMANDS.MAX.WARN}` |AVERAGE | |
|TiKV: Too many pending tasks |<p>-</p> |`min(/TiDB TiKV by HTTP/tikv.worker_pending_task,5m)>{$TIKV.PENDING_TASKS.MAX.WARN}` |AVERAGE | |
|TiKV: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/TiDB TiKV by HTTP/tikv.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|TiKV: Store_id {#STORE_ID}: Too many failure messages "{#TYPE}" |<p>Indicates that the remote TiKV cannot be connected.</p> |`min(/TiDB TiKV by HTTP/tikv.messages.failure.rate[{#STORE_ID},{#TYPE}],5m)>{$TIKV.STORE.ERRORS.MAX.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

