
# CockroachDB by HTTP

## Overview

The template to monitor CockroachDB nodes by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template collects metrics by HTTP agent from Prometheus endpoint and health endpoints.

Internal node metrics are collected from Prometheus /_status/vars endpoint.
Node health metrics are collected from /health and /health?ready=1 endpoints.
The template doesn't require usage of session token.

**Note**, that some metrics may not be collected depending on your CockroachDB version and configuration.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- CockroachDB 21.2.8

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Set the hostname or IP address of the CockroachDB node host in the `{$COCKROACHDB.API.HOST}` macro. You can also change the port in the `{$COCKROACHDB.API.PORT}` macro and the scheme in the `{$COCKROACHDB.API.SCHEME}` macro if necessary.

Also, see the Macros section for a list of macros used to set trigger values.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$COCKROACHDB.API.HOST}|<p>The hostname or IP address of the CockroachDB host.</p>||
|{$COCKROACHDB.API.PORT}|<p>The port of CockroachDB API and Prometheus endpoint.</p>|`8080`|
|{$COCKROACHDB.API.SCHEME}|<p>Request scheme which may be http or https.</p>|`http`|
|{$COCKROACHDB.STORE.USED.MIN.WARN}|<p>The warning threshold of the available disk space in percent.</p>|`20`|
|{$COCKROACHDB.STORE.USED.MIN.CRIT}|<p>The critical threshold of the available disk space in percent.</p>|`10`|
|{$COCKROACHDB.OPEN.FDS.MAX.WARN}|<p>Maximum percentage of used file descriptors.</p>|`80`|
|{$COCKROACHDB.CERT.NODE.EXPIRY.WARN}|<p>Number of days until the node certificate expires.</p>|`30`|
|{$COCKROACHDB.CERT.CA.EXPIRY.WARN}|<p>Number of days until the CA certificate expires.</p>|`90`|
|{$COCKROACHDB.CLOCK.OFFSET.MAX.WARN}|<p>Maximum clock offset of the node against the rest of the cluster in milliseconds for trigger expression.</p>|`300`|
|{$COCKROACHDB.STATEMENTS.ERRORS.MAX.WARN}|<p>Maximum number of SQL statements errors for trigger expression.</p>|`2`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get metrics|<p>Get raw metrics from the Prometheus endpoint.</p>|HTTP agent|cockroachdb.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get health|<p>Get node /health endpoint</p>|HTTP agent|cockroachdb.get_health<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Regular expression: `HTTP.*\s(\d+) \1`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get readiness|<p>Get node /health?ready=1 endpoint</p>|HTTP agent|cockroachdb.get_readiness<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Regular expression: `HTTP.*\s(\d+) \1`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Service ping|<p>Check if HTTP/HTTPS service accepts TCP connections.</p>|Simple check|net.tcp.service["{$COCKROACHDB.API.SCHEME}","{$COCKROACHDB.API.HOST}","{$COCKROACHDB.API.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Clock offset|<p>Mean clock offset of the node against the rest of the cluster.</p>|Dependent item|cockroachdb.clock.offset<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(clock_offset_meannanos)`</p></li><li><p>Custom multiplier: `0.000000001`</p></li></ul>|
|Version|<p>Build information.</p>|Dependent item|cockroachdb.version<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `build_timestamp` label `tag`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|CPU: System time|<p>System CPU time.</p>|Dependent item|cockroachdb.cpu.system_time<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_cpu_sys_ns)`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.000000001`</p></li></ul>|
|CPU: User time|<p>User CPU time.</p>|Dependent item|cockroachdb.cpu.user_time<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_cpu_user_ns)`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.000000001`</p></li></ul>|
|CPU: Utilization|<p>The CPU utilization expressed in %.</p>|Dependent item|cockroachdb.cpu.util<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_cpu_combined_percent_normalized)`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Disk: IOPS in progress, rate|<p>Number of disk IO operations currently in progress on this host.</p>|Dependent item|cockroachdb.disk.iops.in_progress.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_host_disk_iopsinprogress)`</p></li><li>Change per second</li></ul>|
|Disk: Reads, rate|<p>Bytes read from all disks per second since this process started</p>|Dependent item|cockroachdb.disk.read.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_host_disk_read_bytes)`</p></li><li>Change per second</li></ul>|
|Disk: Read IOPS, rate|<p>Number of disk read operations per second across all disks since this process started.</p>|Dependent item|cockroachdb.disk.iops.read.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_host_disk_read_count)`</p></li><li>Change per second</li></ul>|
|Disk: Writes, rate|<p>Bytes written to all disks per second since this process started.</p>|Dependent item|cockroachdb.disk.write.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_host_disk_write_bytes)`</p></li><li>Change per second</li></ul>|
|Disk: Write IOPS, rate|<p>Disk write operations per second across all disks since this process started.</p>|Dependent item|cockroachdb.disk.iops.write.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_host_disk_write_count)`</p></li><li>Change per second</li></ul>|
|File descriptors: Limit|<p>Open file descriptors soft limit of the process.</p>|Dependent item|cockroachdb.descriptors.limit<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_fd_softlimit)`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|File descriptors: Open|<p>The number of open file descriptors.</p>|Dependent item|cockroachdb.descriptors.open<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_fd_open)`</p></li></ul>|
|GC: Pause time|<p>The amount of processor time used by Go's garbage collector across all nodes. During garbage collection, application code execution is paused.</p>|Dependent item|cockroachdb.gc.pause_time<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_gc_pause_ns)`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.000000001`</p></li></ul>|
|GC: Runs, rate|<p>The number of times that Go's garbage collector was invoked per second across all nodes.</p>|Dependent item|cockroachdb.gc.runs.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_gc_count)`</p></li><li>Change per second</li></ul>|
|Go: Goroutines count|<p>Current number of Goroutines. This count should rise and fall based on load.</p>|Dependent item|cockroachdb.go.goroutines.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_goroutines)`</p></li></ul>|
|KV transactions: Aborted, rate|<p>Number of aborted KV transactions per second.</p>|Dependent item|cockroachdb.kv.transactions.aborted.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(txn_aborts)`</p></li><li>Change per second</li></ul>|
|KV transactions: Committed, rate|<p>Number of KV transactions (including 1PC) committed per second.</p>|Dependent item|cockroachdb.kv.transactions.committed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(txn_commits)`</p></li><li>Change per second</li></ul>|
|Live nodes count|<p>The number of live nodes in the cluster (will be 0 if this node is not itself live).</p>|Dependent item|cockroachdb.live_count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(liveness_livenodes)`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Liveness heartbeats, rate|<p>Number of successful node liveness heartbeats per second from this node.</p>|Dependent item|cockroachdb.heartbeaths.success.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(liveness_heartbeatsuccesses)`</p></li><li>Change per second</li></ul>|
|Memory: Allocated by Cgo|<p>Current bytes of memory allocated by the C layer.</p>|Dependent item|cockroachdb.memory.cgo.allocated<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_cgo_allocbytes)`</p></li></ul>|
|Memory: Allocated by Go|<p>Current bytes of memory allocated by the Go layer.</p>|Dependent item|cockroachdb.memory.go.allocated<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_go_allocbytes)`</p></li></ul>|
|Memory: Managed by Cgo|<p>Total bytes of memory managed by the C layer.</p>|Dependent item|cockroachdb.memory.cgo.managed<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_cgo_totalbytes)`</p></li></ul>|
|Memory: Managed by Go|<p>Total bytes of memory managed by the Go layer.</p>|Dependent item|cockroachdb.memory.go.managed<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_go_totalbytes)`</p></li></ul>|
|Memory: Total usage|<p>Resident set size (RSS) of memory in use by the node.</p>|Dependent item|cockroachdb.memory.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_rss)`</p></li></ul>|
|Network: Bytes received, rate|<p>Bytes received per second on all network interfaces since this process started.</p>|Dependent item|cockroachdb.network.bytes.received.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_host_net_recv_bytes)`</p></li><li>Change per second</li></ul>|
|Network: Bytes sent, rate|<p>Bytes sent per second on all network interfaces since this process started.</p>|Dependent item|cockroachdb.network.bytes.sent.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_host_net_send_bytes)`</p></li><li>Change per second</li></ul>|
|Time series: Sample errors, rate|<p>The number of errors encountered while attempting to write metrics to disk, per second.</p>|Dependent item|cockroachdb.ts.samples.errors.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(timeseries_write_errors)`</p></li><li>Change per second</li></ul>|
|Time series: Samples written, rate|<p>The number of successfully written metric samples per second.</p>|Dependent item|cockroachdb.ts.samples.written.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(timeseries_write_samples)`</p></li><li>Change per second</li></ul>|
|Slow requests: DistSender RPCs|<p>Number of RPCs stuck or retrying for a long time.</p>|Dependent item|cockroachdb.slow_requests.rpc<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(requests_slow_distsender)`</p></li></ul>|
|SQL: Bytes received, rate|<p>Total amount of incoming SQL client network traffic in bytes per second.</p>|Dependent item|cockroachdb.sql.bytes.received.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_bytesin)`</p></li><li>Change per second</li></ul>|
|SQL: Bytes sent, rate|<p>Total amount of outgoing SQL client network traffic in bytes per second.</p>|Dependent item|cockroachdb.sql.bytes.sent.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_bytesout)`</p></li><li>Change per second</li></ul>|
|Memory: Allocated by SQL|<p>Current SQL statement memory usage for root.</p>|Dependent item|cockroachdb.memory.sql<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_mem_root_current)`</p></li></ul>|
|SQL: Schema changes, rate|<p>Total number of SQL DDL statements successfully executed per second.</p>|Dependent item|cockroachdb.sql.schema_changes.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_ddl_count)`</p></li><li>Change per second</li></ul>|
|SQL sessions: Open|<p>Total number of open SQL sessions.</p>|Dependent item|cockroachdb.sql.sessions<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_conns)`</p></li></ul>|
|SQL statements: Active|<p>Total number of SQL statements currently active.</p>|Dependent item|cockroachdb.sql.statements.active<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_distsql_queries_active)`</p></li></ul>|
|SQL statements: DELETE, rate|<p>A moving average of the number of DELETE statements successfully executed per second.</p>|Dependent item|cockroachdb.sql.statements.delete.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_delete_count)`</p></li><li>Change per second</li></ul>|
|SQL statements: Executed, rate|<p>Number of SQL queries executed per second.</p>|Dependent item|cockroachdb.sql.statements.executed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_query_count)`</p></li><li>Change per second</li></ul>|
|SQL statements: Denials, rate|<p>The number of statements denied per second by a feature flag.</p>|Dependent item|cockroachdb.sql.statements.denials.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_feature_flag_denial)`</p></li><li>Change per second</li></ul>|
|SQL statements: Active flows distributed, rate|<p>The number of distributed SQL flows currently active per second.</p>|Dependent item|cockroachdb.sql.statements.flows.active.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_distsql_flows_active)`</p></li><li>Change per second</li></ul>|
|SQL statements: INSERT, rate|<p>A moving average of the number of INSERT statements successfully executed per second.</p>|Dependent item|cockroachdb.sql.statements.insert.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_insert_count)`</p></li><li>Change per second</li></ul>|
|SQL statements: SELECT, rate|<p>A moving average of the number of SELECT statements successfully executed per second.</p>|Dependent item|cockroachdb.sql.statements.select.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_select_count)`</p></li><li>Change per second</li></ul>|
|SQL statements: UPDATE, rate|<p>A moving average of the number of UPDATE statements successfully executed per second.</p>|Dependent item|cockroachdb.sql.statements.update.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_update_count)`</p></li><li>Change per second</li></ul>|
|SQL statements: Contention, rate|<p>Total number of SQL statements that experienced contention per second.</p>|Dependent item|cockroachdb.sql.statements.contention.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_distsql_contended_queries_count)`</p></li><li>Change per second</li></ul>|
|SQL statements: Errors, rate|<p>Total number of statements which returned a planning or runtime error per second.</p>|Dependent item|cockroachdb.sql.statements.errors.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_failure_count)`</p></li><li>Change per second</li></ul>|
|SQL transactions: Open|<p>Total number of currently open SQL transactions.</p>|Dependent item|cockroachdb.sql.transactions.open<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_txns_open)`</p></li></ul>|
|SQL transactions: Aborted, rate|<p>Total number of SQL transaction abort errors per second.</p>|Dependent item|cockroachdb.sql.transactions.aborted.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_txn_abort_count)`</p></li><li>Change per second</li></ul>|
|SQL transactions: Committed, rate|<p>Total number of SQL transaction COMMIT statements successfully executed per second.</p>|Dependent item|cockroachdb.sql.transactions.committed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_txn_commit_count)`</p></li><li>Change per second</li></ul>|
|SQL transactions: Initiated, rate|<p>Total number of SQL transaction BEGIN statements successfully executed per second.</p>|Dependent item|cockroachdb.sql.transactions.initiated.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_txn_begin_count)`</p></li><li>Change per second</li></ul>|
|SQL transactions: Rolled back, rate|<p>Total number of SQL transaction ROLLBACK statements successfully executed per second.</p>|Dependent item|cockroachdb.sql.transactions.rollbacks.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sql_txn_rollback_count)`</p></li><li>Change per second</li></ul>|
|Uptime|<p>Process uptime.</p>|Dependent item|cockroachdb.uptime<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sys_uptime)`</p></li></ul>|
|Node certificate expiration date|<p>Node certificate expires at that date.</p>|Dependent item|cockroachdb.cert.expire_date.node<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(security_certificate_expiration_node)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|CA certificate expiration date|<p>CA certificate expires at that date.</p>|Dependent item|cockroachdb.cert.expire_date.ca<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(security_certificate_expiration_ca)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|CockroachDB: Node is unhealthy|<p>Node's /health endpoint has returned HTTP 500 Internal Server Error which indicates unhealthy mode.</p>|`last(/CockroachDB by HTTP/cockroachdb.get_health) = 500`|Average|**Depends on**:<br><ul><li>CockroachDB: Service is down</li></ul>|
|CockroachDB: Node is not ready|<p>Node's /health?ready=1 endpoint has returned HTTP 503 Service Unavailable. Possible reasons:<br>- node is in the wait phase of the node shutdown sequence;<br>- node is unable to communicate with a majority of the other nodes in the cluster, likely because the cluster is unavailable due to too many nodes being down.</p>|`last(/CockroachDB by HTTP/cockroachdb.get_readiness) = 503 and last(/CockroachDB by HTTP/cockroachdb.uptime) > 5m`|Average|**Depends on**:<br><ul><li>CockroachDB: Service is down</li></ul>|
|CockroachDB: Service is down||`last(/CockroachDB by HTTP/net.tcp.service["{$COCKROACHDB.API.SCHEME}","{$COCKROACHDB.API.HOST}","{$COCKROACHDB.API.PORT}"]) = 0`|Average||
|CockroachDB: Clock offset is too high|<p>Cockroach-measured clock offset is nearing limit (by default, servers kill themselves at 400ms from the mean).</p>|`min(/CockroachDB by HTTP/cockroachdb.clock.offset,5m) > {$COCKROACHDB.CLOCK.OFFSET.MAX.WARN} * 0.001`|Warning||
|CockroachDB: Version has changed||`last(/CockroachDB by HTTP/cockroachdb.version) <> last(/CockroachDB by HTTP/cockroachdb.version,#2) and length(last(/CockroachDB by HTTP/cockroachdb.version)) > 0`|Info||
|CockroachDB: Current number of open files is too high|<p>Getting close to open file descriptor limit.</p>|`min(/CockroachDB by HTTP/cockroachdb.descriptors.open,10m) / last(/CockroachDB by HTTP/cockroachdb.descriptors.limit) * 100 > {$COCKROACHDB.OPEN.FDS.MAX.WARN}`|Warning||
|CockroachDB: Node is not executing SQL|<p>Node is not executing SQL despite having connections.</p>|`last(/CockroachDB by HTTP/cockroachdb.sql.sessions) > 0 and last(/CockroachDB by HTTP/cockroachdb.sql.statements.executed.rate) = 0`|Warning||
|CockroachDB: SQL statements errors rate is too high||`min(/CockroachDB by HTTP/cockroachdb.sql.statements.errors.rate,5m) > {$COCKROACHDB.STATEMENTS.ERRORS.MAX.WARN}`|Warning||
|CockroachDB: Node has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/CockroachDB by HTTP/cockroachdb.uptime) < 10m`|Info||
|CockroachDB: Failed to fetch node data|<p>Zabbix has not received data for items for the last 5 minutes.</p>|`nodata(/CockroachDB by HTTP/cockroachdb.uptime,5m) = 1`|Warning|**Depends on**:<br><ul><li>CockroachDB: Service is down</li></ul>|
|CockroachDB: Node certificate expires soon|<p>Node certificate expires soon.</p>|`(last(/CockroachDB by HTTP/cockroachdb.cert.expire_date.node) - now()) / 86400 < {$COCKROACHDB.CERT.NODE.EXPIRY.WARN}`|Warning||
|CockroachDB: CA certificate expires soon|<p>CA certificate expires soon.</p>|`(last(/CockroachDB by HTTP/cockroachdb.cert.expire_date.ca) - now()) / 86400 < {$COCKROACHDB.CERT.CA.EXPIRY.WARN}`|Warning||

### LLD rule Storage metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage metrics discovery|<p>Discover per store metrics.</p>|Dependent item|cockroachdb.store.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `capacity`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Storage metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage [{#STORE}]: Bytes: Live|<p>Number of logical bytes stored in live key-value pairs on this node. Live data excludes historical and deleted data.</p>|Dependent item|cockroachdb.storage.bytes.[{#STORE},live]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(livebytes{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Bytes: System|<p>Number of physical bytes stored in system key-value pairs.</p>|Dependent item|cockroachdb.storage.bytes.[{#STORE},system]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(sysbytes{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Capacity available|<p>Available storage capacity.</p>|Dependent item|cockroachdb.storage.capacity.[{#STORE},available]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(capacity_available{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Capacity total|<p>Total storage capacity. This value may be explicitly set using --store. If a store size has not been set, this metric displays the actual disk capacity.</p>|Dependent item|cockroachdb.storage.capacity.[{#STORE},total]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(capacity{store="{#STORE}"})`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage [{#STORE}]: Capacity used|<p>Disk space in use by CockroachDB data on this node. This excludes the Cockroach binary, operating system, and other system files.</p>|Dependent item|cockroachdb.storage.capacity.[{#STORE},used]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(capacity_used{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Capacity available in %|<p>Available storage capacity in %.</p>|Calculated|cockroachdb.storage.capacity.[{#STORE},available_percent]|
|Storage [{#STORE}]: Replication: Lease holders|<p>Number of lease holders.</p>|Dependent item|cockroachdb.replication.[{#STORE},lease_holders]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(replicas_leaseholders{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Bytes: Logical|<p>Number of logical bytes stored in key-value pairs on this node. This includes historical and deleted data.</p>|Dependent item|cockroachdb.storage.bytes.[{#STORE},logical]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(totalbytes{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Rebalancing: Average queries, rate|<p>Number of kv-level requests received per second by the store, averaged over a large time period as used in rebalancing decisions.</p>|Dependent item|cockroachdb.rebalancing.queries.average.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(rebalancing_queriespersecond{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Rebalancing: Average writes, rate|<p>Number of keys written (i.e. applied by raft) per second to the store, averaged over a large time period as used in rebalancing decisions.</p>|Dependent item|cockroachdb.rebalancing.writes.average.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(rebalancing_writespersecond{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Queue processing failures: Consistency, rate|<p>Number of replicas which failed processing in the consistency checker queue per second.</p>|Dependent item|cockroachdb.queue.processing_failures.consistency.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(queue_consistency_process_failure{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: Queue processing failures: GC, rate|<p>Number of replicas which failed processing in the GC queue per second.</p>|Dependent item|cockroachdb.queue.processing_failures.gc.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(queue_gc_process_failure{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: Queue processing failures: Raft log, rate|<p>Number of replicas which failed processing in the Raft log queue per second.</p>|Dependent item|cockroachdb.queue.processing_failures.raftlog.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(queue_raftlog_process_failure{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: Queue processing failures: Raft snapshot, rate|<p>Number of replicas which failed processing in the Raft repair queue per second.</p>|Dependent item|cockroachdb.queue.processing_failures.raftsnapshot.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(queue_raftsnapshot_process_failure{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: Queue processing failures: Replica GC, rate|<p>Number of replicas which failed processing in the replica GC queue per second.</p>|Dependent item|cockroachdb.queue.processing_failures.gc_replica.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(queue_replicagc_process_failure{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: Queue processing failures: Replicate, rate|<p>Number of replicas which failed processing in the replicate queue per second.</p>|Dependent item|cockroachdb.queue.processing_failures.replicate.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(queue_replicate_process_failure{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: Queue processing failures: Split, rate|<p>Number of replicas which failed processing in the split queue per second.</p>|Dependent item|cockroachdb.queue.processing_failures.split.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(queue_split_process_failure{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: Queue processing failures: Time series maintenance, rate|<p>Number of replicas which failed processing in the time series maintenance queue per second.</p>|Dependent item|cockroachdb.queue.processing_failures.tsmaintenance.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(queue_tsmaintenance_process_failure{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: Ranges count|<p>Number of ranges.</p>|Dependent item|cockroachdb.ranges.[{#STORE},count]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(ranges{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Ranges unavailable|<p>Number of ranges with fewer live replicas than needed for quorum.</p>|Dependent item|cockroachdb.ranges.[{#STORE},unavailable]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(ranges_unavailable{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Ranges underreplicated|<p>Number of ranges with fewer live replicas than the replication target.</p>|Dependent item|cockroachdb.ranges.[{#STORE},underreplicated]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(ranges_underreplicated{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: RocksDB read amplification|<p>The average number of real read operations executed per logical read operation.</p>|Dependent item|cockroachdb.rocksdb.[{#STORE},read_amp]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(rocksdb_read_amplification{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: RocksDB cache hits, rate|<p>Count of block cache hits per second.</p>|Dependent item|cockroachdb.rocksdb.cache.hits.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(rocksdb_block_cache_hits{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: RocksDB cache misses, rate|<p>Count of block cache misses per second.</p>|Dependent item|cockroachdb.rocksdb.cache.misses.[{#STORE},rate]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(rocksdb_block_cache_misses{store="{#STORE}"})`</p></li><li>Change per second</li></ul>|
|Storage [{#STORE}]: RocksDB cache hit ratio|<p>Block cache hit ratio in %.</p>|Calculated|cockroachdb.rocksdb.cache.[{#STORE},hit_ratio]|
|Storage [{#STORE}]: Replication: Replicas|<p>Number of replicas.</p>|Dependent item|cockroachdb.replication.replicas.[{#STORE},count]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(replicas{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Replication: Replicas quiesced|<p>Number of quiesced replicas.</p>|Dependent item|cockroachdb.replication.replicas.[{#STORE},quiesced]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(replicas_quiescent{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Slow requests: Latch acquisitions|<p>Number of requests that have been stuck for a long time acquiring latches.</p>|Dependent item|cockroachdb.slow_requests.[{#STORE},latch_acquisitions]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(requests_slow_latch{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Slow requests: Lease acquisitions|<p>Number of requests that have been stuck for a long time acquiring a lease.</p>|Dependent item|cockroachdb.slow_requests.[{#STORE},lease_acquisitions]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(requests_slow_lease{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: Slow requests: Raft proposals|<p>Number of requests that have been stuck for a long time in raft.</p>|Dependent item|cockroachdb.slow_requests.[{#STORE},raft_proposals]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(requests_slow_raft{store="{#STORE}"})`</p></li></ul>|
|Storage [{#STORE}]: RocksDB SSTables|<p>The number of SSTables in use.</p>|Dependent item|cockroachdb.rocksdb.[{#STORE},sstables]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(rocksdb_num_sstables{store="{#STORE}"})`</p></li></ul>|

### Trigger prototypes for Storage metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|CockroachDB: Storage [{#STORE}]: Available storage capacity is low|<p>Storage is running low on free space (less than {$COCKROACHDB.STORE.USED.MIN.WARN}% available).</p>|`max(/CockroachDB by HTTP/cockroachdb.storage.capacity.[{#STORE},available_percent],5m) < {$COCKROACHDB.STORE.USED.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>CockroachDB: Storage [{#STORE}]: Available storage capacity is critically low</li></ul>|
|CockroachDB: Storage [{#STORE}]: Available storage capacity is critically low|<p>Storage is running critically low on free space (less than {$COCKROACHDB.STORE.USED.MIN.CRIT}% available).</p>|`max(/CockroachDB by HTTP/cockroachdb.storage.capacity.[{#STORE},available_percent],5m) < {$COCKROACHDB.STORE.USED.MIN.CRIT}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

