
# Redis by Zabbix agent 2

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Redis server by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Redis by Zabbix agent 2` — collects metrics by polling zabbix-agent2.



This template was tested on:

- Redis, version 5.0.6, 4.0.14, 3.0.6

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

Setup and configure zabbix-agent2 compiled with the Redis monitoring plugin (ZBXNEXT-5428-4.3).

Test availability: `zabbix_get -s redis-master -k redis.ping`


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$REDIS.CLIENTS.PRC.MAX.WARN} |<p>Maximum percentage of connected clients</p> |`80` |
|{$REDIS.CONN.URI} |<p>Connection string in the URI format (password is not used). This param overwrites a value configured in the "Server" option of the configuration file (if it's set), otherwise, the plugin's default value is used: "tcp://localhost:6379"</p> |`tcp://localhost:6379` |
|{$REDIS.LLD.FILTER.DB.MATCHES} |<p>Filter of discoverable databases</p> |`.*` |
|{$REDIS.LLD.FILTER.DB.NOT_MATCHES} |<p>Filter to exclude discovered databases</p> |`CHANGE_IF_NEEDED` |
|{$REDIS.LLD.PROCESS_NAME} |<p>Redis server process name for LLD</p> |`redis-server` |
|{$REDIS.MEM.FRAG_RATIO.MAX.WARN} |<p>Maximum memory fragmentation ratio</p> |`1.5` |
|{$REDIS.MEM.PUSED.MAX.WARN} |<p>Maximum percentage of memory used</p> |`90` |
|{$REDIS.PROCESS_NAME} |<p>Redis server process name</p> |`redis-server` |
|{$REDIS.REPL.LAG.MAX.WARN} |<p>Maximum replication lag in seconds</p> |`30s` |
|{$REDIS.SLOWLOG.COUNT.MAX.WARN} |<p>Maximum number of slowlog entries per second</p> |`1` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|AOF metrics discovery |<p>If AOF is activated, additional metrics will be added</p> |DEPENDENT |redis.persistence.aof.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Keyspace discovery |<p>Individual keyspace metrics</p> |DEPENDENT |redis.keyspace.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>**Filter**:</p>AND <p>- {#DB} MATCHES_REGEX `{$REDIS.LLD.FILTER.DB.MATCHES}`</p><p>- {#DB} NOT_MATCHES_REGEX `{$REDIS.LLD.FILTER.DB.NOT_MATCHES}`</p> |
|Process metrics discovery |<p>Collect metrics by Zabbix agent if it exists</p> |ZABBIX_PASSIVE |proc.num["{$REDIS.LLD.PROCESS_NAME}"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(value > 0 ? [{'{#SINGLETON}': ''}] : []);`</p> |
|Replication metrics discovery |<p>If the instance is the master and the slaves are connected, additional metrics are provided</p> |DEPENDENT |redis.replication.master.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Slave metrics discovery |<p>If the instance is a replica, additional metrics are provided</p> |DEPENDENT |redis.replication.slave.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Version 4+ metrics discovery |<p>Additional metrics for versions 4+</p> |DEPENDENT |redis.metrics.v4.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.Server.redis_version`</p><p>- JAVASCRIPT: `return JSON.stringify(parseInt(value.split('.')[0]) >= 4 ? [{'{#SINGLETON}': ''}] : []);`</p> |
|Version 5+ metrics discovery |<p>Additional metrics for versions 5+</p> |DEPENDENT |redis.metrics.v5.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.Server.redis_version`</p><p>- JAVASCRIPT: `return JSON.stringify(parseInt(value.split('.')[0]) >= 5 ? [{'{#SINGLETON}': ''}] : []);`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Redis |Redis: Ping | |ZABBIX_PASSIVE |redis.ping["{$REDIS.CONN.URI}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Redis |Redis: Slowlog entries per second | |ZABBIX_PASSIVE |redis.slowlog.count["{$REDIS.CONN.URI}"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Redis |Redis: CPU sys |<p>System CPU consumed by the Redis server</p> |DEPENDENT |redis.cpu.sys<p>**Preprocessing**:</p><p>- JSONPATH: `$.CPU.used_cpu_sys`</p> |
|Redis |Redis: CPU sys children |<p>System CPU consumed by the background processes</p> |DEPENDENT |redis.cpu.sys_children<p>**Preprocessing**:</p><p>- JSONPATH: `$.CPU.used_cpu_sys_children`</p> |
|Redis |Redis: CPU user |<p>User CPU consumed by the Redis server</p> |DEPENDENT |redis.cpu.user<p>**Preprocessing**:</p><p>- JSONPATH: `$.CPU.used_cpu_user`</p> |
|Redis |Redis: CPU user children |<p>User CPU consumed by the background processes</p> |DEPENDENT |redis.cpu.user_children<p>**Preprocessing**:</p><p>- JSONPATH: `$.CPU.used_cpu_user_children`</p> |
|Redis |Redis: Blocked clients |<p>The number of connections waiting on a blocking call</p> |DEPENDENT |redis.clients.blocked<p>**Preprocessing**:</p><p>- JSONPATH: `$.Clients.blocked_clients`</p> |
|Redis |Redis: Max input buffer |<p>The biggest input buffer among current client connections</p> |DEPENDENT |redis.clients.max_input_buffer<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Redis |Redis: Max output buffer |<p>The biggest output buffer among current client connections</p> |DEPENDENT |redis.clients.max_output_buffer<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Redis |Redis: Connected clients |<p>The number of connected clients</p> |DEPENDENT |redis.clients.connected<p>**Preprocessing**:</p><p>- JSONPATH: `$.Clients.connected_clients`</p> |
|Redis |Redis: Cluster enabled |<p>Indicate Redis cluster is enabled</p> |DEPENDENT |redis.cluster.enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.Cluster.cluster_enabled`</p> |
|Redis |Redis: Memory used |<p>Total number of bytes allocated by Redis using its allocator</p> |DEPENDENT |redis.memory.used_memory<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory`</p> |
|Redis |Redis: Memory used Lua |<p>Amount of memory used by the Lua engine</p> |DEPENDENT |redis.memory.used_memory_lua<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory_lua`</p> |
|Redis |Redis: Memory used peak |<p>Peak memory consumed by Redis (in bytes)</p> |DEPENDENT |redis.memory.used_memory_peak<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory_peak`</p> |
|Redis |Redis: Memory used RSS |<p>Number of bytes that Redis allocated as seen by the operating system</p> |DEPENDENT |redis.memory.used_memory_rss<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory_rss`</p> |
|Redis |Redis: Memory fragmentation ratio |<p>This ratio is an indication of memory mapping efficiency:</p><p>  — Value over 1.0 indicate that memory fragmentation is very likely. Consider restarting the Redis server so the operating system can recover fragmented memory, especially with a ratio over 1.5.</p><p>  — Value under 1.0 indicate that Redis likely has insufficient memory available. Consider optimizing memory usage or adding more RAM.</p><p>Note: If your peak memory usage is much higher than your current memory usage, the memory fragmentation ratio may be unreliable.</p><p>https://redis.io/topics/memory-optimization</p> |DEPENDENT |redis.memory.fragmentation_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.mem_fragmentation_ratio`</p> |
|Redis |Redis: AOF current rewrite time sec |<p>Duration of the on-going AOF rewrite operation if any</p> |DEPENDENT |redis.persistence.aof_current_rewrite_time_sec<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_current_rewrite_time_sec`</p> |
|Redis |Redis: AOF enabled |<p>Flag indicating AOF logging is activated</p> |DEPENDENT |redis.persistence.aof_enabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_enabled`</p> |
|Redis |Redis: AOF last bgrewrite status |<p>Status of the last AOF rewrite operation</p> |DEPENDENT |redis.persistence.aof_last_bgrewrite_status<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_last_bgrewrite_status`</p><p>- BOOL_TO_DECIMAL</p> |
|Redis |Redis: AOF last rewrite time sec |<p>Duration of the last AOF rewrite</p> |DEPENDENT |redis.persistence.aof_last_rewrite_time_sec<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_last_rewrite_time_sec`</p> |
|Redis |Redis: AOF last write status |<p>Status of the last write operation to the AOF</p> |DEPENDENT |redis.persistence.aof_last_write_status<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_last_write_status`</p><p>- BOOL_TO_DECIMAL</p> |
|Redis |Redis: AOF rewrite in progress |<p>Flag indicating a AOF rewrite operation is on-going</p> |DEPENDENT |redis.persistence.aof_rewrite_in_progress<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_rewrite_in_progress`</p> |
|Redis |Redis: AOF rewrite scheduled |<p>Flag indicating an AOF rewrite operation will be scheduled once the on-going RDB save is complete</p> |DEPENDENT |redis.persistence.aof_rewrite_scheduled<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_rewrite_scheduled`</p> |
|Redis |Redis: Dump loading |<p>Flag indicating if the load of a dump file is on-going</p> |DEPENDENT |redis.persistence.loading<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.loading`</p> |
|Redis |Redis: RDB bgsave in progress |<p>"1" if bgsave is in progress and "0" otherwise</p> |DEPENDENT |redis.persistence.rdb_bgsave_in_progress<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.rdb_bgsave_in_progress`</p> |
|Redis |Redis: RDB changes since last save |<p>Number of changes since the last background save</p> |DEPENDENT |redis.persistence.rdb_changes_since_last_save<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.rdb_changes_since_last_save`</p> |
|Redis |Redis: RDB current bgsave time sec |<p>Duration of the on-going RDB save operation if any</p> |DEPENDENT |redis.persistence.rdb_current_bgsave_time_sec<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.rdb_current_bgsave_time_sec`</p> |
|Redis |Redis: RDB last bgsave status |<p>Status of the last RDB save operation</p> |DEPENDENT |redis.persistence.rdb_last_bgsave_status<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.rdb_last_bgsave_status`</p><p>- BOOL_TO_DECIMAL</p> |
|Redis |Redis: RDB last bgsave time sec |<p>Duration of the last bg_save operation</p> |DEPENDENT |redis.persistence.rdb_last_bgsave_time_sec<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.rdb_last_bgsave_time_sec`</p> |
|Redis |Redis: RDB last save time |<p>Epoch-based timestamp of last successful RDB save</p> |DEPENDENT |redis.persistence.rdb_last_save_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.rdb_last_save_time`</p> |
|Redis |Redis: Connected slaves |<p>Number of connected slaves</p> |DEPENDENT |redis.replication.connected_slaves<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.connected_slaves`</p> |
|Redis |Redis: Replication backlog active |<p>Flag indicating replication backlog is active</p> |DEPENDENT |redis.replication.repl_backlog_active<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.repl_backlog_active`</p> |
|Redis |Redis: Replication backlog first byte offset |<p>The master offset of the replication backlog buffer</p> |DEPENDENT |redis.replication.repl_backlog_first_byte_offset<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.repl_backlog_first_byte_offset`</p> |
|Redis |Redis: Replication backlog history length |<p>Amount of data in the backlog sync buffer</p> |DEPENDENT |redis.replication.repl_backlog_histlen<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.repl_backlog_histlen`</p> |
|Redis |Redis: Replication backlog size |<p>Total size in bytes of the replication backlog buffer</p> |DEPENDENT |redis.replication.repl_backlog_size<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.repl_backlog_size`</p> |
|Redis |Redis: Replication role |<p>Value is "master" if the instance is replica of no one, or "slave" if the instance is a replica of some master instance. Note that a replica can be master of another replica (chained replication).</p> |DEPENDENT |redis.replication.role<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.role`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis: Master replication offset |<p>Replication offset reported by the master</p> |DEPENDENT |redis.replication.master_repl_offset<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.master_repl_offset`</p> |
|Redis |Redis: Process id |<p>PID of the server process</p> |DEPENDENT |redis.server.process_id<p>**Preprocessing**:</p><p>- JSONPATH: `$.Server.process_id`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis: Redis mode |<p>The server's mode ("standalone", "sentinel" or "cluster")</p> |DEPENDENT |redis.server.redis_mode<p>**Preprocessing**:</p><p>- JSONPATH: `$.Server.redis_mode`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis: Redis version |<p>Version of the Redis server</p> |DEPENDENT |redis.server.redis_version<p>**Preprocessing**:</p><p>- JSONPATH: `$.Server.redis_version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis: TCP port |<p>TCP/IP listen port</p> |DEPENDENT |redis.server.tcp_port<p>**Preprocessing**:</p><p>- JSONPATH: `$.Server.tcp_port`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis: Uptime |<p>Number of seconds since Redis server start</p> |DEPENDENT |redis.server.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.Server.uptime_in_seconds`</p> |
|Redis |Redis: Evicted keys |<p>Number of evicted keys due to maxmemory limit</p> |DEPENDENT |redis.stats.evicted_keys<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.evicted_keys`</p> |
|Redis |Redis: Expired keys |<p>Total number of key expiration events</p> |DEPENDENT |redis.stats.expired_keys<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.expired_keys`</p> |
|Redis |Redis: Instantaneous input bytes per second |<p>The network's read rate per second in KB/sec</p> |DEPENDENT |redis.stats.instantaneous_input.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.instantaneous_input_kbps`</p><p>- MULTIPLIER: `1024`</p> |
|Redis |Redis: Instantaneous operations per sec |<p>Number of commands processed per second</p> |DEPENDENT |redis.stats.instantaneous_ops.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.instantaneous_ops_per_sec`</p> |
|Redis |Redis: Instantaneous output bytes per second |<p>The network's write rate per second in KB/sec</p> |DEPENDENT |redis.stats.instantaneous_output.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.instantaneous_output_kbps`</p><p>- MULTIPLIER: `1024`</p> |
|Redis |Redis: Keyspace hits |<p>Number of successful lookup of keys in the main dictionary</p> |DEPENDENT |redis.stats.keyspace_hits<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.keyspace_hits`</p> |
|Redis |Redis: Keyspace misses |<p>Number of failed lookup of keys in the main dictionary</p> |DEPENDENT |redis.stats.keyspace_misses<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.keyspace_misses`</p> |
|Redis |Redis: Latest fork usec |<p>Duration of the latest fork operation in microseconds</p> |DEPENDENT |redis.stats.latest_fork_usec<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.latest_fork_usec`</p><p>- MULTIPLIER: `1.0E-5`</p> |
|Redis |Redis: Migrate cached sockets |<p>The number of sockets open for MIGRATE purposes</p> |DEPENDENT |redis.stats.migrate_cached_sockets<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.migrate_cached_sockets`</p> |
|Redis |Redis: Pubsub channels |<p>Global number of pub/sub channels with client subscriptions</p> |DEPENDENT |redis.stats.pubsub_channels<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.pubsub_channels`</p> |
|Redis |Redis: Pubsub patterns |<p>Global number of pub/sub pattern with client subscriptions</p> |DEPENDENT |redis.stats.pubsub_patterns<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.pubsub_patterns`</p> |
|Redis |Redis: Rejected connections |<p>Number of connections rejected because of maxclients limit</p> |DEPENDENT |redis.stats.rejected_connections<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.rejected_connections`</p> |
|Redis |Redis: Sync full |<p>The number of full resyncs with replicas</p> |DEPENDENT |redis.stats.sync_full<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.sync_full`</p> |
|Redis |Redis: Sync partial err |<p>The number of denied partial resync requests</p> |DEPENDENT |redis.stats.sync_partial_err<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.sync_partial_err`</p> |
|Redis |Redis: Sync partial ok |<p>The number of accepted partial resync requests</p> |DEPENDENT |redis.stats.sync_partial_ok<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.sync_partial_ok`</p> |
|Redis |Redis: Total commands processed |<p>Total number of commands processed by the server</p> |DEPENDENT |redis.stats.total_commands_processed<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.total_commands_processed`</p> |
|Redis |Redis: Total connections received |<p>Total number of connections accepted by the server</p> |DEPENDENT |redis.stats.total_connections_received<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.total_connections_received`</p> |
|Redis |Redis: Total net input bytes |<p>The total number of bytes read from the network</p> |DEPENDENT |redis.stats.total_net_input_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.total_net_input_bytes`</p> |
|Redis |Redis: Total net output bytes |<p>The total number of bytes written to the network</p> |DEPENDENT |redis.stats.total_net_output_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.total_net_output_bytes`</p> |
|Redis |Redis: Max clients |<p>Max number of connected clients at the same time.</p><p>Once the limit is reached Redis will close all the new connections sending an error "max number of clients reached".</p> |DEPENDENT |redis.config.maxclients<p>**Preprocessing**:</p><p>- JSONPATH: `$.maxclients`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|Redis |DB {#DB}: Average TTL |<p>Average TTL</p> |DEPENDENT |redis.db.avg_ttl["{#DB}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Keyspace["{#DB}"].avg_ttl`</p><p>- MULTIPLIER: `0.001`</p> |
|Redis |DB {#DB}: Expires |<p>Number of keys with an expiration</p> |DEPENDENT |redis.db.expires["{#DB}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Keyspace["{#DB}"].expires`</p> |
|Redis |DB {#DB}: Keys |<p>Total number of keys</p> |DEPENDENT |redis.db.keys["{#DB}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Keyspace["{#DB}"].keys`</p> |
|Redis |Redis: AOF current size{#SINGLETON} |<p>AOF current file size</p> |DEPENDENT |redis.persistence.aof_current_size[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_current_size`</p> |
|Redis |Redis: AOF base size{#SINGLETON} |<p>AOF file size on latest startup or rewrite</p> |DEPENDENT |redis.persistence.aof_base_size[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_base_size`</p> |
|Redis |Redis: AOF pending rewrite{#SINGLETON} |<p>Flag indicating an AOF rewrite operation will</p> |DEPENDENT |redis.persistence.aof_pending_rewrite[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_pending_rewrite`</p> |
|Redis |Redis: AOF buffer length{#SINGLETON} |<p>Size of the AOF buffer</p> |DEPENDENT |redis.persistence.aof_buffer_length[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_buffer_length`</p> |
|Redis |Redis: AOF rewrite buffer length{#SINGLETON} |<p>Size of the AOF rewrite buffer</p> |DEPENDENT |redis.persistence.aof_rewrite_buffer_length[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_rewrite_buffer_length`</p> |
|Redis |Redis: AOF pending background I/O fsync{#SINGLETON} |<p>Number of fsync pending jobs in background I/O queue</p> |DEPENDENT |redis.persistence.aof_pending_bio_fsync[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_pending_bio_fsync`</p> |
|Redis |Redis: AOF delayed fsync{#SINGLETON} |<p>Delayed fsync counter</p> |DEPENDENT |redis.persistence.aof_delayed_fsync[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_delayed_fsync`</p> |
|Redis |Redis: Master host{#SINGLETON} |<p>Host or IP address of the master</p> |DEPENDENT |redis.replication.master_host[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.master_host`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis: Master port{#SINGLETON} |<p>Master listening TCP port</p> |DEPENDENT |redis.replication.master_port[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.master_port`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis: Master link status{#SINGLETON} |<p>Status of the link (up/down)</p> |DEPENDENT |redis.replication.master_link_status[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.master_link_status`</p><p>- BOOL_TO_DECIMAL</p> |
|Redis |Redis: Master last I/O seconds ago{#SINGLETON} |<p>Number of seconds since the last interaction with master</p> |DEPENDENT |redis.replication.master_last_io_seconds_ago[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.master_last_io_seconds_ago`</p> |
|Redis |Redis: Master sync in progress{#SINGLETON} |<p>Indicate the master is syncing to the replica</p> |DEPENDENT |redis.replication.master_sync_in_progress[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.master_sync_in_progress`</p> |
|Redis |Redis: Slave replication offset{#SINGLETON} |<p>The replication offset of the replica instance</p> |DEPENDENT |redis.replication.slave_repl_offset[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.slave_repl_offset`</p> |
|Redis |Redis: Slave priority{#SINGLETON} |<p>The priority of the instance as a candidate for failover</p> |DEPENDENT |redis.replication.slave_priority[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.slave_priority`</p> |
|Redis |Redis: Slave priority{#SINGLETON} |<p>Flag indicating if the replica is read-only</p> |DEPENDENT |redis.replication.slave_read_only[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.slave_read_only`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis slave {#SLAVE_IP}:{#SLAVE_PORT}: Replication lag in bytes |<p>Replication lag in bytes</p> |DEPENDENT |redis.replication.lag_bytes["{#SLAVE_IP}:{#SLAVE_PORT}"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Redis |Redis: Number of processes running |<p>-</p> |ZABBIX_PASSIVE |proc.num["{$REDIS.PROCESS_NAME}{#SINGLETON}"] |
|Redis |Redis: Memory usage (rss) |<p>Resident set size memory used by process in bytes.</p> |ZABBIX_PASSIVE |proc.mem["{$REDIS.PROCESS_NAME}{#SINGLETON}",,,,rss] |
|Redis |Redis: Memory usage (vsize) |<p>Virtual memory size used by process in bytes.</p> |ZABBIX_PASSIVE |proc.mem["{$REDIS.PROCESS_NAME}{#SINGLETON}",,,,vsize] |
|Redis |Redis: CPU utilization |<p>Process CPU utilization percentage.</p> |ZABBIX_PASSIVE |proc.cpu.util["{$REDIS.PROCESS_NAME}{#SINGLETON}"] |
|Redis |Redis: Executable path{#SINGLETON} |<p>The path to the server's executable</p> |DEPENDENT |redis.server.executable[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Server.executable`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis: Memory used peak %{#SINGLETON} |<p>The percentage of used_memory_peak out of used_memory</p> |DEPENDENT |redis.memory.used_memory_peak_perc[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory_peak_perc`</p><p>- REGEX: `(.+)% \1`</p> |
|Redis |Redis: Memory used overhead{#SINGLETON} |<p>The sum in bytes of all overheads that the server allocated for managing its internal data structures</p> |DEPENDENT |redis.memory.used_memory_overhead[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory_overhead`</p> |
|Redis |Redis: Memory used startup{#SINGLETON} |<p>Initial amount of memory consumed by Redis at startup in bytes</p> |DEPENDENT |redis.memory.used_memory_startup[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory_startup`</p> |
|Redis |Redis: Memory used dataset{#SINGLETON} |<p>The size in bytes of the dataset</p> |DEPENDENT |redis.memory.used_memory_dataset[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory_dataset`</p> |
|Redis |Redis: Memory used dataset %{#SINGLETON} |<p>The percentage of used_memory_dataset out of the net memory usage (used_memory minus used_memory_startup)</p> |DEPENDENT |redis.memory.used_memory_dataset_perc[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory_dataset_perc`</p><p>- REGEX: `(.+)% \1`</p> |
|Redis |Redis: Total system memory{#SINGLETON} |<p>The total amount of memory that the Redis host has</p> |DEPENDENT |redis.memory.total_system_memory[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.total_system_memory`</p> |
|Redis |Redis: Max memory{#SINGLETON} |<p>Maximum amount of memory allocated to the Redisdb system</p> |DEPENDENT |redis.memory.maxmemory[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.maxmemory`</p> |
|Redis |Redis: Max memory policy{#SINGLETON} |<p>The value of the maxmemory-policy configuration directive</p> |DEPENDENT |redis.memory.maxmemory_policy[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.maxmemory_policy`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Redis |Redis: Active defrag running{#SINGLETON} |<p>Flag indicating if active defragmentation is active</p> |DEPENDENT |redis.memory.active_defrag_running[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.active_defrag_running`</p> |
|Redis |Redis: Lazyfree pending objects{#SINGLETON} |<p>The number of objects waiting to be freed (as a result of calling UNLINK, or FLUSHDB and FLUSHALL with the ASYNC option)</p> |DEPENDENT |redis.memory.lazyfree_pending_objects[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.lazyfree_pending_objects`</p> |
|Redis |Redis: RDB last CoW size{#SINGLETON} |<p>The size in bytes of copy-on-write allocations during the last RDB save operation</p> |DEPENDENT |redis.persistence.rdb_last_cow_size[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.rdb_last_cow_size`</p> |
|Redis |Redis: AOF last CoW size{#SINGLETON} |<p>The size in bytes of copy-on-write allocations during the last AOF rewrite operation</p> |DEPENDENT |redis.persistence.aof_last_cow_size[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Persistence.aof_last_cow_size`</p> |
|Redis |Redis: Expired stale %{#SINGLETON} |<p>-</p> |DEPENDENT |redis.stats.expired_stale_perc[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.expired_stale_perc`</p> |
|Redis |Redis: Expired time cap reached count{#SINGLETON} |<p>-</p> |DEPENDENT |redis.stats.expired_time_cap_reached_count[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.expired_time_cap_reached_count`</p> |
|Redis |Redis: Slave expires tracked keys{#SINGLETON} |<p>The number of keys tracked for expiry purposes (applicable only to writable replicas)</p> |DEPENDENT |redis.stats.slave_expires_tracked_keys[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.slave_expires_tracked_keys`</p> |
|Redis |Redis: Active defrag hits{#SINGLETON} |<p>Number of value reallocations performed by active the defragmentation process</p> |DEPENDENT |redis.stats.active_defrag_hits[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.active_defrag_hits`</p> |
|Redis |Redis: Active defrag misses{#SINGLETON} |<p>Number of aborted value reallocations started by the active defragmentation process</p> |DEPENDENT |redis.stats.active_defrag_misses[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.active_defrag_misses`</p> |
|Redis |Redis: Active defrag key hits{#SINGLETON} |<p>Number of keys that were actively defragmented</p> |DEPENDENT |redis.stats.active_defrag_key_hits[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.active_defrag_key_hits`</p> |
|Redis |Redis: Active defrag key misses{#SINGLETON} |<p>Number of keys that were skipped by the active defragmentation process</p> |DEPENDENT |redis.stats.active_defrag_key_misses[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Stats.active_defrag_key_misses`</p> |
|Redis |Redis: Replication second offset{#SINGLETON} |<p>Offset up to which replication IDs are accepted</p> |DEPENDENT |redis.replication.second_repl_offset[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Replication.second_repl_offset`</p> |
|Redis |Redis: Allocator active{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.allocator_active[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.allocator_active`</p> |
|Redis |Redis: Allocator allocated{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.allocator_allocated[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.allocator_allocated`</p> |
|Redis |Redis: Allocator resident{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.allocator_resident[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.allocator_resident`</p> |
|Redis |Redis: Memory used scripts{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.used_memory_scripts[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.used_memory_scripts`</p> |
|Redis |Redis: Memory number of cached scripts{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.number_of_cached_scripts[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.number_of_cached_scripts`</p> |
|Redis |Redis: Allocator fragmentation bytes{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.allocator_frag_bytes[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.allocator_frag_bytes`</p> |
|Redis |Redis: Allocator fragmentation ratio{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.allocator_frag_ratio[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.allocator_frag_ratio`</p> |
|Redis |Redis: Allocator RSS bytes{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.allocator_rss_bytes[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.allocator_rss_bytes`</p> |
|Redis |Redis: Allocator RSS ratio{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.allocator_rss_ratio[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.allocator_rss_ratio`</p> |
|Redis |Redis: Memory RSS overhead bytes{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.rss_overhead_bytes[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.rss_overhead_bytes`</p> |
|Redis |Redis: Memory RSS overhead ratio{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.rss_overhead_ratio[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.rss_overhead_ratio`</p> |
|Redis |Redis: Memory fragmentation bytes{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.fragmentation_bytes[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.mem_fragmentation_bytes`</p> |
|Redis |Redis: Memory not counted for evict{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.not_counted_for_evict[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.mem_not_counted_for_evict`</p> |
|Redis |Redis: Memory replication backlog{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.replication_backlog[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.mem_replication_backlog`</p> |
|Redis |Redis: Memory clients normal{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.mem_clients_normal[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.mem_clients_normal`</p> |
|Redis |Redis: Memory clients slaves{#SINGLETON} |<p>-</p> |DEPENDENT |redis.memory.mem_clients_slaves[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.mem_clients_slaves`</p> |
|Redis |Redis: Memory AOF buffer{#SINGLETON} |<p>Size of the AOF buffer</p> |DEPENDENT |redis.memory.mem_aof_buffer[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Memory.mem_aof_buffer`</p> |
|Zabbix raw items |Redis: Get info | |ZABBIX_PASSIVE |redis.info["{$REDIS.CONN.URI}"] |
|Zabbix raw items |Redis: Get config | |ZABBIX_PASSIVE |redis.config["{$REDIS.CONN.URI}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Redis: Service is down |<p>-</p> |`last(/Redis by Zabbix agent 2/redis.ping["{$REDIS.CONN.URI}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|Redis: Too many entries in the slowlog |<p>-</p> |`min(/Redis by Zabbix agent 2/redis.slowlog.count["{$REDIS.CONN.URI}"],5m)>{$REDIS.SLOWLOG.COUNT.MAX.WARN}` |INFO | |
|Redis: Total number of connected clients is too high |<p>When the number of clients reaches the value of the "maxclients" parameter, new connections will be rejected.</p><p>https://redis.io/topics/clients#maximum-number-of-clients</p> |`min(/Redis by Zabbix agent 2/redis.clients.connected,5m)/last(/Redis by Zabbix agent 2/redis.config.maxclients)*100>{$REDIS.CLIENTS.PRC.MAX.WARN}` |WARNING | |
|Redis: Memory fragmentation ratio is too high |<p>This ratio is an indication of memory mapping efficiency:</p><p>  — Value over 1.0 indicate that memory fragmentation is very likely. Consider restarting the Redis server so the operating system can recover fragmented memory, especially with a ratio over 1.5.</p><p>  — Value under 1.0 indicate that Redis likely has insufficient memory available. Consider optimizing memory usage or adding more RAM.</p><p>Note: If your peak memory usage is much higher than your current memory usage, the memory fragmentation ratio may be unreliable.</p><p>https://redis.io/topics/memory-optimization</p> |`min(/Redis by Zabbix agent 2/redis.memory.fragmentation_ratio,15m)>{$REDIS.MEM.FRAG_RATIO.MAX.WARN}` |WARNING | |
|Redis: Last AOF write operation failed |<p>Detailed information about persistence: https://redis.io/topics/persistence</p> |`last(/Redis by Zabbix agent 2/redis.persistence.aof_last_write_status)=0` |WARNING | |
|Redis: Last RDB save operation failed |<p>Detailed information about persistence: https://redis.io/topics/persistence</p> |`last(/Redis by Zabbix agent 2/redis.persistence.rdb_last_bgsave_status)=0` |WARNING | |
|Redis: Number of slaves has changed |<p>Redis number of slaves has changed. Ack to close.</p> |`last(/Redis by Zabbix agent 2/redis.replication.connected_slaves,#1)<>last(/Redis by Zabbix agent 2/redis.replication.connected_slaves,#2)` |INFO |<p>Manual close: YES</p> |
|Redis: Replication role has changed |<p>Redis replication role has changed. Ack to close.</p> |`last(/Redis by Zabbix agent 2/redis.replication.role,#1)<>last(/Redis by Zabbix agent 2/redis.replication.role,#2) and length(last(/Redis by Zabbix agent 2/redis.replication.role))>0` |WARNING |<p>Manual close: YES</p> |
|Redis: Version has changed |<p>Redis version has changed. Ack to close.</p> |`last(/Redis by Zabbix agent 2/redis.server.redis_version,#1)<>last(/Redis by Zabbix agent 2/redis.server.redis_version,#2) and length(last(/Redis by Zabbix agent 2/redis.server.redis_version))>0` |INFO |<p>Manual close: YES</p> |
|Redis: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Redis by Zabbix agent 2/redis.server.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Redis: Connections are rejected |<p>The number of connections has reached the value of "maxclients".</p><p>https://redis.io/topics/clients</p> |`last(/Redis by Zabbix agent 2/redis.stats.rejected_connections)>0` |HIGH | |
|Redis: Replication lag with master is too high |<p>-</p> |`min(/Redis by Zabbix agent 2/redis.replication.master_last_io_seconds_ago[{#SINGLETON}],5m)>{$REDIS.REPL.LAG.MAX.WARN}` |WARNING | |
|Redis: Process is not running |<p>-</p> |`last(/Redis by Zabbix agent 2/proc.num["{$REDIS.PROCESS_NAME}{#SINGLETON}"])=0` |HIGH | |
|Redis: Memory usage is too high |<p>-</p> |`last(/Redis by Zabbix agent 2/redis.memory.used_memory)/min(/Redis by Zabbix agent 2/redis.memory.maxmemory[{#SINGLETON}],5m)*100>{$REDIS.MEM.PUSED.MAX.WARN}` |WARNING | |
|Redis: Failed to fetch info data |<p>Zabbix has not received data for items for the last 30 minutes</p> |`nodata(/Redis by Zabbix agent 2/redis.info["{$REDIS.CONN.URI}"],30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Redis: Service is down</p> |
|Redis: Configuration has changed |<p>Redis configuration has changed. Ack to close.</p> |`last(/Redis by Zabbix agent 2/redis.config["{$REDIS.CONN.URI}"],#1)<>last(/Redis by Zabbix agent 2/redis.config["{$REDIS.CONN.URI}"],#2) and length(last(/Redis by Zabbix agent 2/redis.config["{$REDIS.CONN.URI}"]))>0` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/389050-discussion-thread-for-official-zabbix-template-redis).

