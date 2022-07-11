
# CockroachDB by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor CockroachDB nodes by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `CockroacDB node by HTTP` — collects metrics by HTTP agent from Prometheus endpoint and health endpoints.


This template was tested on:

- CockroachDB, version 21.2.8

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Internal node metrics are collected from Prometheus /_status/vars endpoint.
Node health metrics are collected from /health and /health?ready=1 endpoints.
Template doesn't require usage of session token.

Don't forget change macros {$COCKROACHDB.API.SCHEME} according to your situation (secure/insecure node).
Also, see the Macros section for a list of macros used to set trigger values.

*NOTE.* Some metrics may not be collected depending on your CockroachDB version and configuration.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$COCKROACHDB.API.PORT} |<p>The port of CockroachDB API and Prometheus endpoint.</p> |`8080` |
|{$COCKROACHDB.API.SCHEME} |<p>Request scheme which may be http or https.</p> |`http` |
|{$COCKROACHDB.CERT.CA.EXPIRY.WARN} |<p>Number of days until the CA certificate expires.</p> |`90` |
|{$COCKROACHDB.CERT.NODE.EXPIRY.WARN} |<p>Number of days until the node certificate expires.</p> |`30` |
|{$COCKROACHDB.CLOCK.OFFSET.MAX.WARN} |<p>Maximum clock offset of the node against the rest of the cluster in milliseconds for trigger expression.</p> |`300` |
|{$COCKROACHDB.OPEN.FDS.MAX.WARN} |<p>Maximum percentage of used file descriptors.</p> |`80` |
|{$COCKROACHDB.STATEMENTS.ERRORS.MAX.WARN} |<p>Maximum number of SQL statements errors for trigger expression.</p> |`2` |
|{$COCKROACHDB.STORE.USED.MIN.CRIT} |<p>The critical threshold of the available disk space in percent.</p> |`10` |
|{$COCKROACHDB.STORE.USED.MIN.WARN} |<p>The warning threshold of the available disk space in percent.</p> |`20` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Storage metrics discovery |<p>Discover per store metrics.</p> |DEPENDENT |cockroachdb.store.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `capacity`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CockroachDB |CockroachDB: Service ping |<p>Check if HTTP/HTTPS service accepts TCP connections.</p> |SIMPLE |net.tcp.service["{$COCKROACHDB.API.SCHEME}","{HOST.CONN}","{$COCKROACHDB.API.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|CockroachDB |CockroachDB: Clock offset |<p>Mean clock offset of the node against the rest of the cluster.</p> |DEPENDENT |cockroachdb.clock.offset<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `clock_offset_meannanos`: `value`: ``</p><p>- MULTIPLIER: `0.000000001`</p> |
|CockroachDB |CockroachDB: Version |<p>Build information.</p> |DEPENDENT |cockroachdb.version<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `build_timestamp`: `label`: `tag`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|CockroachDB |CockroachDB: CPU: System time |<p>System CPU time.</p> |DEPENDENT |cockroachdb.cpu.system_time<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_cpu_sys_ns`: `value`: ``</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `0.000000001`</p> |
|CockroachDB |CockroachDB: CPU: User time |<p>User CPU time.</p> |DEPENDENT |cockroachdb.cpu.user_time<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_cpu_user_ns`: `value`: ``</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `0.000000001`</p> |
|CockroachDB |CockroachDB: CPU: Utilization |<p>CPU utilization in %.</p> |DEPENDENT |cockroachdb.cpu.util<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_cpu_combined_percent_normalized`: `value`: ``</p><p>- MULTIPLIER: `100`</p> |
|CockroachDB |CockroachDB: Disk: IOPS in progress, rate |<p>Number of disk IO operations currently in progress on this host.</p> |DEPENDENT |cockroachdb.disk.iops.in_progress.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_host_disk_iopsinprogress`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Disk: Reads, rate |<p>Bytes read from all disks per second since this process started</p> |DEPENDENT |cockroachdb.disk.read.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_host_disk_read_bytes`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Disk: Read IOPS, rate |<p>Number of disk read operations per second across all disks since this process started.</p> |DEPENDENT |cockroachdb.disk.iops.read.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_host_disk_read_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Disk: Writes, rate |<p>Bytes written to all disks per second since this process started.</p> |DEPENDENT |cockroachdb.disk.write.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_host_disk_write_bytes`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Disk: Write IOPS, rate |<p>Disk write operations per second across all disks since this process started.</p> |DEPENDENT |cockroachdb.disk.iops.write.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_host_disk_write_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: File descriptors: Limit |<p>Open file descriptors soft limit of the process.</p> |DEPENDENT |cockroachdb.descriptors.limit<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_fd_softlimit`: `value`: ``</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|CockroachDB |CockroachDB: File descriptors: Open |<p>The number of open file descriptors.</p> |DEPENDENT |cockroachdb.descriptors.open<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_fd_open`: `value`: ``</p> |
|CockroachDB |CockroachDB: GC: Pause time |<p>The amount of processor time used by Go's garbage collector across all nodes. During garbage collection, application code execution is paused.</p> |DEPENDENT |cockroachdb.gc.pause_time<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_gc_pause_ns`: `value`: ``</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `0.000000001`</p> |
|CockroachDB |CockroachDB: GC: Runs, rate |<p>The number of times that Go's garbage collector was invoked per second across all nodes.</p> |DEPENDENT |cockroachdb.gc.runs.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_gc_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Go: Goroutines count |<p>Current number of Goroutines. This count should rise and fall based on load.</p> |DEPENDENT |cockroachdb.go.goroutines.count<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_goroutines`: `value`: ``</p> |
|CockroachDB |CockroachDB: KV transactions: Aborted, rate |<p>Number of aborted KV transactions per second.</p> |DEPENDENT |cockroachdb.kv.transactions.aborted.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `txn_aborts`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: KV transactions: Committed, rate |<p>Number of KV transactions (including 1PC) committed per second.</p> |DEPENDENT |cockroachdb.kv.transactions.committed.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `txn_commits`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Live nodes count |<p>The number of live nodes in the cluster (will be 0 if this node is not itself live).</p> |DEPENDENT |cockroachdb.live_count<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `liveness_livenodes`: `value`: ``</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|CockroachDB |CockroachDB: Liveness heartbeats, rate |<p>Number of successful node liveness heartbeats per second from this node.</p> |DEPENDENT |cockroachdb.heartbeaths.success.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `liveness_heartbeatsuccesses`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Memory: Allocated by Cgo |<p>Current bytes of memory allocated by the C layer.</p> |DEPENDENT |cockroachdb.memory.cgo.allocated<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_cgo_allocbytes`: `value`: ``</p> |
|CockroachDB |CockroachDB: Memory: Allocated by Go |<p>Current bytes of memory allocated by the Go layer.</p> |DEPENDENT |cockroachdb.memory.go.allocated<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_go_allocbytes`: `value`: ``</p> |
|CockroachDB |CockroachDB: Memory: Managed by Cgo |<p>Total bytes of memory managed by the C layer.</p> |DEPENDENT |cockroachdb.memory.cgo.managed<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_cgo_totalbytes`: `value`: ``</p> |
|CockroachDB |CockroachDB: Memory: Managed by Go |<p>Total bytes of memory managed by the Go layer.</p> |DEPENDENT |cockroachdb.memory.go.managed<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_go_totalbytes`: `value`: ``</p> |
|CockroachDB |CockroachDB: Memory: Total usage |<p>Resident set size (RSS) of memory in use by the node.</p> |DEPENDENT |cockroachdb.memory.total<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_rss`: `value`: ``</p> |
|CockroachDB |CockroachDB: Network: Bytes received, rate |<p>Bytes received per second on all network interfaces since this process started.</p> |DEPENDENT |cockroachdb.network.bytes.received.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_host_net_recv_bytes`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Network: Bytes sent, rate |<p>Bytes sent per second on all network interfaces since this process started.</p> |DEPENDENT |cockroachdb.network.bytes.sent.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_host_net_send_bytes`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Time series: Sample errors, rate |<p>The number of errors encountered while attempting to write metrics to disk, per second.</p> |DEPENDENT |cockroachdb.ts.samples.errors.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `timeseries_write_errors`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Time series: Samples written, rate |<p>The number of successfully written metric samples per second.</p> |DEPENDENT |cockroachdb.ts.samples.written.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `timeseries_write_samples`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Slow requests: DistSender RPCs |<p>Number of RPCs stuck or retrying for a long time.</p> |DEPENDENT |cockroachdb.slow_requests.rpc<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `requests_slow_distsender`: `value`: ``</p> |
|CockroachDB |CockroachDB: SQL: Bytes received, rate |<p>Total amount of incoming SQL client network traffic in bytes per second.</p> |DEPENDENT |cockroachdb.sql.bytes.received.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_bytesin`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL: Bytes sent, rate |<p>Total amount of outgoing SQL client network traffic in bytes per second.</p> |DEPENDENT |cockroachdb.sql.bytes.sent.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_bytesout`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Memory: Allocated by SQL |<p>Current SQL statement memory usage for root.</p> |DEPENDENT |cockroachdb.memory.sql<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_mem_root_current`: `value`: ``</p> |
|CockroachDB |CockroachDB: SQL: Schema changes, rate |<p>Total number of SQL DDL statements successfully executed per second.</p> |DEPENDENT |cockroachdb.sql.schema_changes.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_ddl_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL sessions: Open |<p>Total number of open SQL sessions.</p> |DEPENDENT |cockroachdb.sql.sessions<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_conns`: `value`: ``</p> |
|CockroachDB |CockroachDB: SQL statements: Active |<p>Total number of SQL statements currently active.</p> |DEPENDENT |cockroachdb.sql.statements.active<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_distsql_queries_active`: `value`: ``</p> |
|CockroachDB |CockroachDB: SQL statements: DELETE, rate |<p>A moving average of the number of DELETE statements successfully executed per second.</p> |DEPENDENT |cockroachdb.sql.statements.delete.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_delete_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL statements: Executed, rate |<p>Number of SQL queries executed per second.</p> |DEPENDENT |cockroachdb.sql.statements.executed.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_query_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL statements: Denials, rate |<p>The number of statements denied per second by a feature flag.</p> |DEPENDENT |cockroachdb.sql.statements.denials.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_feature_flag_denial`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL statements: Active flows distributed, rate |<p>The number of distributed SQL flows currently active per second.</p> |DEPENDENT |cockroachdb.sql.statements.flows.active.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_distsql_flows_active`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL statements: INSERT, rate |<p>A moving average of the number of INSERT statements successfully executed per second.</p> |DEPENDENT |cockroachdb.sql.statements.insert.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_insert_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL statements: SELECT, rate |<p>A moving average of the number of SELECT statements successfully executed per second.</p> |DEPENDENT |cockroachdb.sql.statements.select.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_select_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL statements: UPDATE, rate |<p>A moving average of the number of UPDATE statements successfully executed per second.</p> |DEPENDENT |cockroachdb.sql.statements.update.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_update_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL statements: Contention, rate |<p>Total number of SQL statements that experienced contention per second.</p> |DEPENDENT |cockroachdb.sql.statements.contention.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_distsql_contended_queries_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL statements: Errors, rate |<p>Total number of statements which returned a planning or runtime error per second.</p> |DEPENDENT |cockroachdb.sql.statements.errors.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_failure_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL transactions: Open |<p>Total number of currently open SQL transactions.</p> |DEPENDENT |cockroachdb.sql.transactions.open<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_txns_open`: `value`: ``</p> |
|CockroachDB |CockroachDB: SQL transactions: Aborted, rate |<p>Total number of SQL transaction abort errors per second.</p> |DEPENDENT |cockroachdb.sql.transactions.aborted.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_txn_abort_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL transactions: Committed, rate |<p>Total number of SQL transaction COMMIT statements successfully executed per second.</p> |DEPENDENT |cockroachdb.sql.transactions.committed.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_txn_commit_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL transactions: Initiated, rate |<p>Total number of SQL transaction BEGIN statements successfully executed per second.</p> |DEPENDENT |cockroachdb.sql.transactions.initiated.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_txn_begin_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: SQL transactions: Rolled back, rate |<p>Total number of SQL transaction ROLLBACK statements successfully executed per second.</p> |DEPENDENT |cockroachdb.sql.transactions.rollbacks.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sql_txn_rollback_count`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Uptime |<p>Process uptime.</p> |DEPENDENT |cockroachdb.uptime<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sys_uptime`: `value`: ``</p> |
|CockroachDB |CockroachDB: Node certificate expiration date |<p>Node certificate expires at that date.</p> |DEPENDENT |cockroachdb.cert.expire_date.node<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `security_certificate_expiration_node`: `value`: ``</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|CockroachDB |CockroachDB: CA certificate expiration date |<p>CA certificate expires at that date.</p> |DEPENDENT |cockroachdb.cert.expire_date.ca<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `security_certificate_expiration_ca`: `value`: ``</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Bytes: Live |<p>Number of logical bytes stored in live key-value pairs on this node. Live data excludes historical and deleted data.</p> |DEPENDENT |cockroachdb.storage.bytes.[{#STORE},live]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `livebytes{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Bytes: System |<p>Number of physical bytes stored in system key-value pairs.</p> |DEPENDENT |cockroachdb.storage.bytes.[{#STORE},system]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `sysbytes{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Capacity available |<p>Available storage capacity.</p> |DEPENDENT |cockroachdb.storage.capacity.[{#STORE},available]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `capacity_available{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Capacity total |<p>Total storage capacity. This value may be explicitly set using --store. If a store size has not been set, this metric displays the actual disk capacity.</p> |DEPENDENT |cockroachdb.storage.capacity.[{#STORE},total]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `capacity{store="{#STORE}"}`: `value`: ``</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Capacity used |<p>Disk space in use by CockroachDB data on this node. This excludes the Cockroach binary, operating system, and other system files.</p> |DEPENDENT |cockroachdb.storage.capacity.[{#STORE},used]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `capacity_used{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Capacity available in % |<p>Available storage capacity in %.</p> |CALCULATED |cockroachdb.storage.capacity.[{#STORE},available_percent]<p>**Expression**:</p>`last(//cockroachdb.storage.capacity.[{#STORE},available]) / last(//cockroachdb.storage.capacity.[{#STORE},total]) * 100` |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Replication: Lease holders |<p>Number of lease holders.</p> |DEPENDENT |cockroachdb.replication.[{#STORE},lease_holders]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `replicas_leaseholders{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Bytes: Logical |<p>Number of logical bytes stored in key-value pairs on this node. This includes historical and deleted data.</p> |DEPENDENT |cockroachdb.storage.bytes.[{#STORE},logical]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `totalbytes{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Rebalancing: Average queries, rate |<p>Number of kv-level requests received per second by the store, averaged over a large time period as used in rebalancing decisions.</p> |DEPENDENT |cockroachdb.rebalancing.queries.average.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rebalancing_queriespersecond{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Rebalancing: Average writes, rate |<p>Number of keys written (i.e. applied by raft) per second to the store, averaged over a large time period as used in rebalancing decisions.</p> |DEPENDENT |cockroachdb.rebalancing.writes.average.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rebalancing_writespersecond{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Queue processing failures: Consistency, rate |<p>Number of replicas which failed processing in the consistency checker queue per second.</p> |DEPENDENT |cockroachdb.queue.processing_failures.consistency.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `queue_consistency_process_failure{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Queue processing failures: GC, rate |<p>Number of replicas which failed processing in the GC queue per second.</p> |DEPENDENT |cockroachdb.queue.processing_failures.gc.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `queue_gc_process_failure{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Queue processing failures: Raft log, rate |<p>Number of replicas which failed processing in the Raft log queue per second.</p> |DEPENDENT |cockroachdb.queue.processing_failures.raftlog.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `queue_raftlog_process_failure{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Queue processing failures: Raft snapshot, rate |<p>Number of replicas which failed processing in the Raft repair queue per second.</p> |DEPENDENT |cockroachdb.queue.processing_failures.raftsnapshot.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `queue_raftsnapshot_process_failure{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Queue processing failures: Replica GC, rate |<p>Number of replicas which failed processing in the replica GC queue per second.</p> |DEPENDENT |cockroachdb.queue.processing_failures.gc_replica.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `queue_replicagc_process_failure{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Queue processing failures: Replicate, rate |<p>Number of replicas which failed processing in the replicate queue per second.</p> |DEPENDENT |cockroachdb.queue.processing_failures.replicate.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `queue_replicate_process_failure{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Queue processing failures: Split, rate |<p>Number of replicas which failed processing in the split queue per second.</p> |DEPENDENT |cockroachdb.queue.processing_failures.split.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `queue_split_process_failure{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Queue processing failures: Time series maintenance, rate |<p>Number of replicas which failed processing in the time series maintenance queue per second.</p> |DEPENDENT |cockroachdb.queue.processing_failures.tsmaintenance.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `queue_tsmaintenance_process_failure{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Ranges count |<p>Number of ranges.</p> |DEPENDENT |cockroachdb.ranges.[{#STORE},count]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `ranges{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Ranges unavailable |<p>Number of ranges with fewer live replicas than needed for quorum.</p> |DEPENDENT |cockroachdb.ranges.[{#STORE},unavailable]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `ranges_unavailable{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Ranges underreplicated |<p>Number of ranges with fewer live replicas than the replication target.</p> |DEPENDENT |cockroachdb.ranges.[{#STORE},underreplicated]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `ranges_underreplicated{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: RocksDB read amplification |<p>The average number of real read operations executed per logical read operation.</p> |DEPENDENT |cockroachdb.rocksdb.[{#STORE},read_amp]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rocksdb_read_amplification{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: RocksDB cache hits, rate |<p>Count of block cache hits per second.</p> |DEPENDENT |cockroachdb.rocksdb.cache.hits.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rocksdb_block_cache_hits{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: RocksDB cache misses, rate |<p>Count of block cache misses per second.</p> |DEPENDENT |cockroachdb.rocksdb.cache.misses.[{#STORE},rate]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rocksdb_block_cache_misses{store="{#STORE}"}`: `value`: ``</p><p>- CHANGE_PER_SECOND</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: RocksDB cache hit ratio |<p>Block cache hit ratio in %.</p> |CALCULATED |cockroachdb.rocksdb.cache.[{#STORE},hit_ratio]<p>**Expression**:</p>`last(//cockroachdb.rocksdb.cache.hits.[{#STORE},rate]) / (last(//cockroachdb.rocksdb.cache.hits.[{#STORE},rate]) + last(//cockroachdb.rocksdb.cache.misses.[{#STORE},rate])) * 100` |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Replication: Replicas |<p>Number of replicas.</p> |DEPENDENT |cockroachdb.replication.replicas.[{#STORE},count]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `replicas{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Replication: Replicas quiesced |<p>Number of quiesced replicas.</p> |DEPENDENT |cockroachdb.replication.replicas.[{#STORE},quiesced]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `replicas_quiescent{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Slow requests: Latch acquisitions |<p>Number of requests that have been stuck for a long time acquiring latches.</p> |DEPENDENT |cockroachdb.slow_requests.[{#STORE},latch_acquisitions]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `requests_slow_latch{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Slow requests: Lease acquisitions |<p>Number of requests that have been stuck for a long time acquiring a lease.</p> |DEPENDENT |cockroachdb.slow_requests.[{#STORE},lease_acquisitions]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `requests_slow_lease{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: Slow requests: Raft proposals |<p>Number of requests that have been stuck for a long time in raft.</p> |DEPENDENT |cockroachdb.slow_requests.[{#STORE},raft_proposals]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `requests_slow_raft{store="{#STORE}"}`: `value`: ``</p> |
|CockroachDB |CockroachDB: Storage [{#STORE}]: RocksDB SSTables |<p>The number of SSTables in use.</p> |DEPENDENT |cockroachdb.rocksdb.[{#STORE},sstables]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rocksdb_num_sstables{store="{#STORE}"}`: `value`: ``</p> |
|Zabbix raw items |CockroachDB: Get metrics |<p>Get raw metrics from the Prometheus endpoint.</p> |HTTP_AGENT |cockroachdb.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Zabbix raw items |CockroachDB: Get health |<p>Get node /health endpoint</p> |HTTP_AGENT |cockroachdb.get_health<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- REGEX: `HTTP.*\s(\d+)`: `\1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Zabbix raw items |CockroachDB: Get readiness |<p>Get node /health?ready=1 endpoint</p> |HTTP_AGENT |cockroachdb.get_readiness<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- REGEX: `HTTP.*\s(\d+)`: `\1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|CockroachDB: Service is down |<p>-</p> |`last(/CockroachDB by HTTP/net.tcp.service["{$COCKROACHDB.API.SCHEME}","{HOST.CONN}","{$COCKROACHDB.API.PORT}"]) = 0` |AVERAGE | |
|CockroachDB: Clock offset is too high |<p>Cockroach-measured clock offset is nearing limit (by default, servers kill themselves at 400ms from the mean).</p> |`min(/CockroachDB by HTTP/cockroachdb.clock.offset,5m) > {$COCKROACHDB.CLOCK.OFFSET.MAX.WARN} * 0.001` |WARNING | |
|CockroachDB: Version has changed |<p>-</p> |`last(/CockroachDB by HTTP/cockroachdb.version) <> last(/CockroachDB by HTTP/cockroachdb.version,#2) and length(last(/CockroachDB by HTTP/cockroachdb.version)) > 0` |INFO | |
|CockroachDB: Current number of open files is too high |<p>Getting close to open file descriptor limit.</p> |`min(/CockroachDB by HTTP/cockroachdb.descriptors.open,10m) / last(/CockroachDB by HTTP/cockroachdb.descriptors.limit) * 100 > {$COCKROACHDB.OPEN.FDS.MAX.WARN}` |WARNING | |
|CockroachDB: Node is not executing SQL |<p>Node is not executing SQL despite having connections.</p> |`last(/CockroachDB by HTTP/cockroachdb.sql.sessions) > 0 and last(/CockroachDB by HTTP/cockroachdb.sql.statements.executed.rate) = 0` |WARNING | |
|CockroachDB: SQL statements errors rate is too high |<p>-</p> |`min(/CockroachDB by HTTP/cockroachdb.sql.statements.errors.rate,5m) > {$COCKROACHDB.STATEMENTS.ERRORS.MAX.WARN}` |WARNING | |
|CockroachDB: Node has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/CockroachDB by HTTP/cockroachdb.uptime) < 10m` |INFO | |
|CockroachDB: Failed to fetch node data |<p>Zabbix has not received data for items for the last 5 minutes.</p> |`nodata(/CockroachDB by HTTP/cockroachdb.uptime,5m) = 1` |WARNING |<p>**Depends on**:</p><p>- CockroachDB: Service is down</p> |
|CockroachDB: Node certificate expires soon |<p>Node certificate expires soon.</p> |`(last(/CockroachDB by HTTP/cockroachdb.cert.expire_date.node) - now()) / 86400 < {$COCKROACHDB.CERT.NODE.EXPIRY.WARN}` |WARNING | |
|CockroachDB: CA certificate expires soon |<p>CA certificate expires soon.</p> |`(last(/CockroachDB by HTTP/cockroachdb.cert.expire_date.ca) - now()) / 86400 < {$COCKROACHDB.CERT.CA.EXPIRY.WARN}` |WARNING | |
|CockroachDB: Storage [{#STORE}]: Available storage capacity is low |<p>Storage is running low on free space (less than {$COCKROACHDB.STORE.USED.MIN.WARN}% available).</p> |`max(/CockroachDB by HTTP/cockroachdb.storage.capacity.[{#STORE},available_percent],5m) < {$COCKROACHDB.STORE.USED.MIN.WARN}`<p>Recovery expression:</p>`min(/CockroachDB by HTTP/cockroachdb.storage.capacity.[{#STORE},available_percent],5m) > {$COCKROACHDB.STORE.USED.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- CockroachDB: Storage [{#STORE}]: Available storage capacity is critically low</p> |
|CockroachDB: Storage [{#STORE}]: Available storage capacity is critically low |<p>Storage is running critically low on free space (less than {$COCKROACHDB.STORE.USED.MIN.CRIT}% available).</p> |`max(/CockroachDB by HTTP/cockroachdb.storage.capacity.[{#STORE},available_percent],5m) < {$COCKROACHDB.STORE.USED.MIN.CRIT}`<p>Recovery expression:</p>`min(/CockroachDB by HTTP/cockroachdb.storage.capacity.[{#STORE},available_percent],5m) > {$COCKROACHDB.STORE.USED.MIN.CRIT}` |AVERAGE | |
|CockroachDB: Node is unhealthy |<p>Node's /health endpoint has returned HTTP 500 Internal Server Error which indicates unhealthy mode.</p> |`last(/CockroachDB by HTTP/cockroachdb.get_health) = 500` |AVERAGE |<p>**Depends on**:</p><p>- CockroachDB: Service is down</p> |
|CockroachDB: Node is not ready |<p>Node's /health?ready=1 endpoint has returned HTTP 503 Service Unavailable. Possible reasons:</p><p>- node is in the wait phase of the node shutdown sequence;</p><p>- node is unable to communicate with a majority of the other nodes in the cluster, likely because the cluster is unavailable due to too many nodes being down.</p> |`last(/CockroachDB by HTTP/cockroachdb.get_readiness) = 503 and last(/CockroachDB by HTTP/cockroachdb.uptime) > 5m` |AVERAGE |<p>**Depends on**:</p><p>- CockroachDB: Service is down</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

