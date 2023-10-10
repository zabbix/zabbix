
# TiDB TiKV by HTTP

## Overview

The template to monitor TiKV server of TiDB cluster by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `TiDB TiKV by HTTP` — collects metrics by HTTP agent from TiKV /metrics endpoint.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- TiDB cluster 4.0.10, 6.5.1

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This template works with TiKV server of TiDB cluster.
Internal service metrics are collected from TiKV /metrics endpoint.
Don't forget to change the macros {$TIKV.URL}, {$TIKV.PORT}.
Also, see the Macros section for a list of macros used to set trigger values.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TIKV.PORT}|<p>The port of TiKV server metrics web endpoint</p>|`20180`|
|{$TIKV.URL}|<p>TiKV server URL</p>|`localhost`|
|{$TIKV.COPOCESSOR.ERRORS.MAX.WARN}|<p>Maximum number of coprocessor request errors</p>|`1`|
|{$TIKV.STORE.ERRORS.MAX.WARN}|<p>Maximum number of failure messages</p>|`1`|
|{$TIKV.PENDING_COMMANDS.MAX.WARN}|<p>Maximum number of pending commands</p>|`1`|
|{$TIKV.PENDING_TASKS.MAX.WARN}|<p>Maximum number of tasks currently running by the worker or pending</p>|`1`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiKV: Get instance metrics|<p>Get TiKV instance metrics.</p>|HTTP agent|tikv.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li>Prometheus to JSON</li></ul>|
|TiKV: Store size|<p>The storage size of TiKV instance.</p>|Dependent item|tikv.engine_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_engine_size_bytes")].value.sum()`</p></li></ul>|
|TiKV: Get store size metrics|<p>Get capacity metrics of TiKV instance.</p>|Dependent item|tikv.store_size.metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_store_size_bytes")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiKV: Available size|<p>The available capacity of TiKV instance.</p>|Dependent item|tikv.store_size.available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.type == "available")].value.first()`</p></li></ul>|
|TiKV: Capacity size|<p>The capacity size of TiKV instance.</p>|Dependent item|tikv.store_size.capacity<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.type == "capacity")].value.first()`</p></li></ul>|
|TiKV: Bytes read|<p>The total bytes of read in TiKV instance.</p>|Dependent item|tikv.engine_flow_bytes.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|TiKV: Bytes write|<p>The total bytes of write in TiKV instance.</p>|Dependent item|tikv.engine_flow_bytes.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|TiKV: Storage: commands total, rate|<p>Total number of commands received per second.</p>|Dependent item|tikv.storage_command.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_storage_command_total")].value.sum()`</p></li><li>Change per second</li></ul>|
|TiKV: CPU util|<p>The CPU usage ratio on TiKV instance.</p>|Dependent item|tikv.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_thread_cpu_seconds_total")].value.sum()`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|TiKV: RSS memory usage|<p>Resident memory size in bytes.</p>|Dependent item|tikv.rss_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|TiKV: Regions, count|<p>The number of regions collected in TiKV instance.</p>|Dependent item|tikv.region_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|TiKV: Regions, leader|<p>The number of leaders in TiKV instance.</p>|Dependent item|tikv.region_leader<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|TiKV: Get QPS metrics|<p>Get QPS metrics in TiKV instance.</p>|Dependent item|tikv.grpc_msgs.metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_grpc_msg_duration_seconds_count")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiKV: Total query, rate|<p>The total QPS in TiKV instance.</p>|Dependent item|tikv.grpc_msg.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..value.sum()`</p></li><li>Change per second</li></ul>|
|TiKV: Total query errors, rate|<p>The total number of gRPC message handling failure per second.</p>|Dependent item|tikv.grpc_msg_fail.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_grpc_msg_fail_total")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiKV: Coprocessor: Errors, rate|<p>Total number of push down request error per second.</p>|Dependent item|tikv.coprocessor_request_error.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_coprocessor_request_error")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiKV: Get coprocessor requests metrics|<p>Get metrics of coprocessor requests.</p>|Dependent item|tikv.coprocessor_requests.metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiKV: Coprocessor: Requests, rate|<p>Total number of coprocessor requests per second.</p>|Dependent item|tikv.coprocessor_request.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..value.sum()`</p></li><li>Change per second</li></ul>|
|TiKV: Coprocessor: Scan keys, rate|<p>Total number of scan keys observed per request per second.</p>|Dependent item|tikv.coprocessor_scan_keys_sum.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_coprocessor_scan_keys")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiKV: Coprocessor: RocksDB ops, rate|<p>Total number of RocksDB internal operations from PerfContext per second.</p>|Dependent item|tikv.coprocessor_rocksdb_perf.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_coprocessor_rocksdb_perf")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiKV: Coprocessor: Response size, rate|<p>The total size of coprocessor response per second.</p>|Dependent item|tikv.coprocessor_response_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiKV: Scheduler: Pending commands|<p>The total number of pending commands. The scheduler receives commands from clients, executes them against the MVCC layer storage engine.</p>|Dependent item|tikv.scheduler_contex<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_scheduler_contex_total")].value.first()`</p></li></ul>|
|TiKV: Scheduler: Busy, rate|<p>The total count of too busy schedulers per second.</p>|Dependent item|tikv.scheduler_too_busy.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_scheduler_too_busy_total")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiKV: Get scheduler metrics|<p>Get metrics of scheduler commands.</p>|Dependent item|tikv.scheduler.metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_scheduler_stage_total")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiKV: Scheduler: Commands total, rate|<p>Total number of commands per second.</p>|Dependent item|tikv.scheduler_commands.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|TiKV: Scheduler: Low priority commands total, rate|<p>Total count of low priority commands per second.</p>|Dependent item|tikv.commands_pri.low.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiKV: Scheduler: Normal priority commands total, rate|<p>Total count of normal priority commands per second.</p>|Dependent item|tikv.commands_pri.normal.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiKV: Scheduler: High priority commands total, rate|<p>Total count of high priority commands per second.</p>|Dependent item|tikv.commands_pri.high.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiKV: Snapshot: Pending tasks|<p>The number of tasks currently running by the worker or pending.</p>|Dependent item|tikv.worker_pending_task<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|TiKV: Snapshot: Sending|<p>The total amount of raftstore snapshot traffic.</p>|Dependent item|tikv.snapshot.sending<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|TiKV: Snapshot: Receiving|<p>The total amount of raftstore snapshot traffic.</p>|Dependent item|tikv.snapshot.receiving<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|TiKV: Snapshot: Applying|<p>The total amount of raftstore snapshot traffic.</p>|Dependent item|tikv.snapshot.applying<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiKV: Uptime|<p>The runtime of each TiKV instance.</p>|Dependent item|tikv.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="process_start_time_seconds")].value.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|TiKV: Get failure msg metrics|<p>Get metrics of reporting failure messages.</p>|Dependent item|tikv.messages.failure.metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_server_report_failure_msg_total")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiKV: Server: failure messages total, rate|<p>Total number of reporting failure messages per second.</p>|Dependent item|tikv.messages.failure.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TiKV: Too many coprocessor request error||`min(/TiDB TiKV by HTTP/tikv.coprocessor_request_error.rate,5m)>{$TIKV.COPOCESSOR.ERRORS.MAX.WARN}`|Warning||
|TiKV: Too many pending commands||`min(/TiDB TiKV by HTTP/tikv.scheduler_contex,5m)>{$TIKV.PENDING_COMMANDS.MAX.WARN}`|Average||
|TiKV: Too many pending tasks||`min(/TiDB TiKV by HTTP/tikv.worker_pending_task,5m)>{$TIKV.PENDING_TASKS.MAX.WARN}`|Average||
|TiKV: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/TiDB TiKV by HTTP/tikv.uptime)<10m`|Info|**Manual close**: Yes|

### LLD rule QPS metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|QPS metrics discovery|<p>Discovery QPS metrics.</p>|Dependent item|tikv.qps.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for QPS metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiKV: Query: {#TYPE}, rate|<p>The QPS per command in TiKV instance.</p>|Dependent item|tikv.grpc_msg.rate[{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.type == "{#TYPE}")].value.first()`</p><p>⛔️Custom on fail: Set value to</p></li></ul>|

### LLD rule Coprocessor metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Coprocessor metrics discovery|<p>Discovery coprocessor metrics.</p>|Dependent item|tikv.coprocessor.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Coprocessor metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiKV: Coprocessor: {#REQ_TYPE} metrics|<p>Get metrics of {#REQ_TYPE} requests.</p>|Dependent item|tikv.coprocessor_request.metrics[{#REQ_TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.req == "{#REQ_TYPE}")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TiKV: Coprocessor: {#REQ_TYPE} errors, rate|<p>Total number of push down request error per second.</p>|Dependent item|tikv.coprocessor_request_error.rate[{#REQ_TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TiKV: Coprocessor: {#REQ_TYPE} requests, rate|<p>Total number of coprocessor requests per second.</p>|Dependent item|tikv.coprocessor_request.rate[{#REQ_TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiKV: Coprocessor: {#REQ_TYPE} scan keys, rate|<p>Total number of scan keys observed per request per second.</p>|Dependent item|tikv.coprocessor_scan_keys.rate[{#REQ_TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|TiKV: Coprocessor: {#REQ_TYPE} RocksDB ops, rate|<p>Total number of RocksDB internal operations from PerfContext per second.</p>|Dependent item|tikv.coprocessor_rocksdb_perf.rate[{#REQ_TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "tikv_coprocessor_rocksdb_perf")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule Scheduler metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Scheduler metrics discovery|<p>Discovery scheduler metrics.</p>|Dependent item|tikv.scheduler.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Scheduler metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiKV: Scheduler: commands {#STAGE}, rate|<p>Total number of commands on each stage per second.</p>|Dependent item|tikv.scheduler_stage.rate[{#STAGE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.labels.stage == "{#STAGE}")].value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|

### LLD rule Server errors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server errors discovery|<p>Discovery server errors metrics.</p>|Dependent item|tikv.server_report_failure.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Server errors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiKV: Store_id {#STORE_ID}: failure messages "{#TYPE}", rate|<p>Total number of reporting failure messages. The metric has two labels: type and store_id. type represents the failure type, and store_id represents the destination peer store id.</p>|Dependent item|tikv.messages.failure.rate[{#STORE_ID},{#TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Server errors discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TiKV: Store_id {#STORE_ID}: Too many failure messages "{#TYPE}"|<p>Indicates that the remote TiKV cannot be connected.</p>|`min(/TiDB TiKV by HTTP/tikv.messages.failure.rate[{#STORE_ID},{#TYPE}],5m)>{$TIKV.STORE.ERRORS.MAX.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

