
# Redis by Zabbix agent 2

## Overview

This template is designed to monitor Redis by Zabbix agent 2 by Zabbix without any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Redis, version 3.0.6, 4.0.14, 5.0.6, 7.0.8

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Setup and configure zabbix-agent2 compiled with the Redis monitoring plugin (ZBXNEXT-5428-4.3).

Test availability: `zabbix_get -s redis-master -k redis.ping`


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$REDIS.CONN.URI} |<p> Connection string in the URI format (password is not used). This param overwrites a value configured in the "Server" option of the configuration file (if it's set), otherwise, the plugin's default value is used: "tcp://localhost:6379" </p>|`tcp://localhost:6379`|
|{$REDIS.PROCESS_NAME} |<p> Redis server process name </p>|`redis-server`|
|{$REDIS.LLD.PROCESS_NAME} |<p> Redis server process name for LLD </p>|`redis-server`|
|{$REDIS.LLD.FILTER.DB.MATCHES} |<p> Filter of discoverable databases </p>|`.*`|
|{$REDIS.LLD.FILTER.DB.NOT_MATCHES} |<p> Filter to exclude discovered databases </p>|`CHANGE_IF_NEEDED`|
|{$REDIS.REPL.LAG.MAX.WARN} |<p> Maximum replication lag in seconds </p>|`30s`|
|{$REDIS.SLOWLOG.COUNT.MAX.WARN} |<p> Maximum number of slowlog entries per second </p>|`1`|
|{$REDIS.CLIENTS.PRC.MAX.WARN} |<p> Maximum percentage of connected clients </p>|`80`|
|{$REDIS.MEM.PUSED.MAX.WARN} |<p> Maximum percentage of memory used </p>|`90`|
|{$REDIS.MEM.FRAG_RATIO.MAX.WARN} |<p> Maximum memory fragmentation ratio </p>|`1.5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Get info||Zabbix passive|redis.info["{$REDIS.CONN.URI}"]|
|Redis: Get config||Zabbix passive|redis.config["{$REDIS.CONN.URI}"]<p>**Preprocessing**</p><ul><li>Discard unchanged heartbeat: `1h`</li></ul>|
|Redis: Ping||Zabbix passive|redis.ping["{$REDIS.CONN.URI}"]<p>**Preprocessing**</p><ul><li>Discard unchanged heartbeat: `10m`</li></ul>|
|Redis: Slowlog entries per second||Zabbix passive|redis.slowlog.count["{$REDIS.CONN.URI}"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Redis: Get Clients info||Dependent|redis.clients.info_raw<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.Clients`</p><p>⛔️On fail: Discard value</p></li></ul>|
|Redis: Get CPU info||Dependent|redis.cpu.info_raw<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.CPU`</p><p>⛔️On fail: Discard value</p></li></ul>|
|Redis: Get Keyspace info||Dependent|redis.keyspace.info_raw<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.Keyspace`</p><p>⛔️On fail: Discard value</p></li></ul>|
|Redis: Get Memory info||Dependent|redis.memory.info_raw<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.Memory`</p><p>⛔️On fail: Discard value</p></li></ul>|
|Redis: Get Persistence info||Dependent|redis.persistence.info_raw<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.Persistence`</p><p>⛔️On fail: Discard value</p></li></ul>|
|Redis: Get Replication info||Dependent|redis.replication.info_raw<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.Replication`</p><p>⛔️On fail: Discard value</p></li></ul>|
|Redis: Get Server info||Dependent|redis.server.info_raw<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.Server`</p><p>⛔️On fail: Discard value</p></li></ul>|
|Redis: Get Stats info||Dependent|redis.stats.info_raw<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.Stats`</p><p>⛔️On fail: Discard value</p></li></ul>|
|Redis: CPU sys|System CPU consumed by the Redis server|Dependent|redis.cpu.sys<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_cpu_sys`</li></ul>|
|Redis: CPU sys children|System CPU consumed by the background processes|Dependent|redis.cpu.sys_children<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_cpu_sys_children`</li></ul>|
|Redis: CPU user|User CPU consumed by the Redis server|Dependent|redis.cpu.user<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_cpu_user`</li></ul>|
|Redis: CPU user children|User CPU consumed by the background processes|Dependent|redis.cpu.user_children<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_cpu_user_children`</li></ul>|
|Redis: Blocked clients|The number of connections waiting on a blocking call|Dependent|redis.clients.blocked<p>**Preprocessing**</p><ul><li>JSONPath: `$.blocked_clients`</li></ul>|
|Redis: Max input buffer|The biggest input buffer among current client connections|Dependent|redis.clients.max_input_buffer<p>**Preprocessing**</p><ul><li>Javascript: `The text is too long. Please see the template.`</li></ul>|
|Redis: Max output buffer|The biggest output buffer among current client connections|Dependent|redis.clients.max_output_buffer<p>**Preprocessing**</p><ul><li>Javascript: `The text is too long. Please see the template.`</li></ul>|
|Redis: Connected clients|The number of connected clients|Dependent|redis.clients.connected<p>**Preprocessing**</p><ul><li>JSONPath: `$.connected_clients`</li></ul>|
|Redis: Cluster enabled|Indicate Redis cluster is enabled|Dependent|redis.cluster.enabled<p>**Preprocessing**</p><ul><li>JSONPath: `$.Cluster.cluster_enabled`</li></ul>|
|Redis: Memory used|Total number of bytes allocated by Redis using its allocator|Dependent|redis.memory.used_memory<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory`</li></ul>|
|Redis: Memory used Lua|Amount of memory used by the Lua engine|Dependent|redis.memory.used_memory_lua<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory_lua`</li></ul>|
|Redis: Memory used peak|Peak memory consumed by Redis (in bytes)|Dependent|redis.memory.used_memory_peak<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory_peak`</li></ul>|
|Redis: Memory used RSS|Number of bytes that Redis allocated as seen by the operating system|Dependent|redis.memory.used_memory_rss<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory_rss`</li></ul>|
|Redis: Memory fragmentation ratio|This ratio is an indication of memory mapping efficiency: - Value over 1.0 indicate that memory fragmentation is very likely. Consider restarting the Redis server so the operating system can recover fragmented memory, especially with a ratio over 1.5. - Value under 1.0 indicate that Redis likely has insufficient memory available. Consider optimizing memory usage or adding more RAM.  Note: If your peak memory usage is much higher than your current memory usage, the memory fragmentation ratio may be unreliable.  https://redis.io/topics/memory-optimization|Dependent|redis.memory.fragmentation_ratio<p>**Preprocessing**</p><ul><li>JSONPath: `$.mem_fragmentation_ratio`</li></ul>|
|Redis: AOF current rewrite time sec|Duration of the on-going AOF rewrite operation if any|Dependent|redis.persistence.aof_current_rewrite_time_sec<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_current_rewrite_time_sec`</li></ul>|
|Redis: AOF enabled|Flag indicating AOF logging is activated|Dependent|redis.persistence.aof_enabled<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_enabled`</li></ul>|
|Redis: AOF last bgrewrite status|Status of the last AOF rewrite operation|Dependent|redis.persistence.aof_last_bgrewrite_status<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_last_bgrewrite_status`</li><li>Bool to decimal</li></ul>|
|Redis: AOF last rewrite time sec|Duration of the last AOF rewrite|Dependent|redis.persistence.aof_last_rewrite_time_sec<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_last_rewrite_time_sec`</li></ul>|
|Redis: AOF last write status|Status of the last write operation to the AOF|Dependent|redis.persistence.aof_last_write_status<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_last_write_status`</li><li>Bool to decimal</li></ul>|
|Redis: AOF rewrite in progress|Flag indicating a AOF rewrite operation is on-going|Dependent|redis.persistence.aof_rewrite_in_progress<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_rewrite_in_progress`</li></ul>|
|Redis: AOF rewrite scheduled|Flag indicating an AOF rewrite operation will be scheduled once the on-going RDB save is complete|Dependent|redis.persistence.aof_rewrite_scheduled<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_rewrite_scheduled`</li></ul>|
|Redis: Dump loading|Flag indicating if the load of a dump file is on-going|Dependent|redis.persistence.loading<p>**Preprocessing**</p><ul><li>JSONPath: `$.loading`</li></ul>|
|Redis: RDB bgsave in progress|"1" if bgsave is in progress and "0" otherwise|Dependent|redis.persistence.rdb_bgsave_in_progress<p>**Preprocessing**</p><ul><li>JSONPath: `$.rdb_bgsave_in_progress`</li></ul>|
|Redis: RDB changes since last save|Number of changes since the last background save|Dependent|redis.persistence.rdb_changes_since_last_save<p>**Preprocessing**</p><ul><li>JSONPath: `$.rdb_changes_since_last_save`</li></ul>|
|Redis: RDB current bgsave time sec|Duration of the on-going RDB save operation if any|Dependent|redis.persistence.rdb_current_bgsave_time_sec<p>**Preprocessing**</p><ul><li>JSONPath: `$.rdb_current_bgsave_time_sec`</li></ul>|
|Redis: RDB last bgsave status|Status of the last RDB save operation|Dependent|redis.persistence.rdb_last_bgsave_status<p>**Preprocessing**</p><ul><li>JSONPath: `$.rdb_last_bgsave_status`</li><li>Bool to decimal</li></ul>|
|Redis: RDB last bgsave time sec|Duration of the last bg_save operation|Dependent|redis.persistence.rdb_last_bgsave_time_sec<p>**Preprocessing**</p><ul><li>JSONPath: `$.rdb_last_bgsave_time_sec`</li></ul>|
|Redis: RDB last save time|Epoch-based timestamp of last successful RDB save|Dependent|redis.persistence.rdb_last_save_time<p>**Preprocessing**</p><ul><li>JSONPath: `$.rdb_last_save_time`</li></ul>|
|Redis: Connected slaves|Number of connected slaves|Dependent|redis.replication.connected_slaves<p>**Preprocessing**</p><ul><li>JSONPath: `$.connected_slaves`</li></ul>|
|Redis: Replication backlog active|Flag indicating replication backlog is active|Dependent|redis.replication.repl_backlog_active<p>**Preprocessing**</p><ul><li>JSONPath: `$.repl_backlog_active`</li></ul>|
|Redis: Replication backlog first byte offset|The master offset of the replication backlog buffer|Dependent|redis.replication.repl_backlog_first_byte_offset<p>**Preprocessing**</p><ul><li>JSONPath: `$.repl_backlog_first_byte_offset`</li></ul>|
|Redis: Replication backlog history length|Amount of data in the backlog sync buffer|Dependent|redis.replication.repl_backlog_histlen<p>**Preprocessing**</p><ul><li>JSONPath: `$.repl_backlog_histlen`</li></ul>|
|Redis: Replication backlog size|Total size in bytes of the replication backlog buffer|Dependent|redis.replication.repl_backlog_size<p>**Preprocessing**</p><ul><li>JSONPath: `$.repl_backlog_size`</li></ul>|
|Redis: Replication role|Value is "master" if the instance is replica of no one, or "slave" if the instance is a replica of some master instance. Note that a replica can be master of another replica (chained replication).|Dependent|redis.replication.role<p>**Preprocessing**</p><ul><li>JSONPath: `$.role`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|
|Redis: Master replication offset|Replication offset reported by the master|Dependent|redis.replication.master_repl_offset<p>**Preprocessing**</p><ul><li>JSONPath: `$.master_repl_offset`</li></ul>|
|Redis: Process id|PID of the server process|Dependent|redis.server.process_id<p>**Preprocessing**</p><ul><li>JSONPath: `$.process_id`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|
|Redis: Redis mode|The server's mode ("standalone", "sentinel" or "cluster")|Dependent|redis.server.redis_mode<p>**Preprocessing**</p><ul><li>JSONPath: `$.redis_mode`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|
|Redis: Redis version|Version of the Redis server|Dependent|redis.server.redis_version<p>**Preprocessing**</p><ul><li>JSONPath: `$.redis_version`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|
|Redis: TCP port|TCP/IP listen port|Dependent|redis.server.tcp_port<p>**Preprocessing**</p><ul><li>JSONPath: `$.tcp_port`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|
|Redis: Uptime|Number of seconds since Redis server start|Dependent|redis.server.uptime<p>**Preprocessing**</p><ul><li>JSONPath: `$.uptime_in_seconds`</li></ul>|
|Redis: Evicted keys|Number of evicted keys due to maxmemory limit|Dependent|redis.stats.evicted_keys<p>**Preprocessing**</p><ul><li>JSONPath: `$.evicted_keys`</li></ul>|
|Redis: Expired keys|Total number of key expiration events|Dependent|redis.stats.expired_keys<p>**Preprocessing**</p><ul><li>JSONPath: `$.expired_keys`</li></ul>|
|Redis: Instantaneous input bytes per second|The network's read rate per second in KB/sec|Dependent|redis.stats.instantaneous_input.rate<p>**Preprocessing**</p><ul><li>JSONPath: `$.instantaneous_input_kbps`</li><li>Multiplier: `1024`</li></ul>|
|Redis: Instantaneous operations per sec|Number of commands processed per second|Dependent|redis.stats.instantaneous_ops.rate<p>**Preprocessing**</p><ul><li>JSONPath: `$.instantaneous_ops_per_sec`</li></ul>|
|Redis: Instantaneous output bytes per second|The network's write rate per second in KB/sec|Dependent|redis.stats.instantaneous_output.rate<p>**Preprocessing**</p><ul><li>JSONPath: `$.instantaneous_output_kbps`</li><li>Multiplier: `1024`</li></ul>|
|Redis: Keyspace hits|Number of successful lookup of keys in the main dictionary|Dependent|redis.stats.keyspace_hits<p>**Preprocessing**</p><ul><li>JSONPath: `$.keyspace_hits`</li></ul>|
|Redis: Keyspace misses|Number of failed lookup of keys in the main dictionary|Dependent|redis.stats.keyspace_misses<p>**Preprocessing**</p><ul><li>JSONPath: `$.keyspace_misses`</li></ul>|
|Redis: Latest fork usec|Duration of the latest fork operation in microseconds|Dependent|redis.stats.latest_fork_usec<p>**Preprocessing**</p><ul><li>JSONPath: `$.latest_fork_usec`</li><li>Multiplier: `1e-05`</li></ul>|
|Redis: Migrate cached sockets|The number of sockets open for MIGRATE purposes|Dependent|redis.stats.migrate_cached_sockets<p>**Preprocessing**</p><ul><li>JSONPath: `$.migrate_cached_sockets`</li></ul>|
|Redis: Pubsub channels|Global number of pub/sub channels with client subscriptions|Dependent|redis.stats.pubsub_channels<p>**Preprocessing**</p><ul><li>JSONPath: `$.pubsub_channels`</li></ul>|
|Redis: Pubsub patterns|Global number of pub/sub pattern with client subscriptions|Dependent|redis.stats.pubsub_patterns<p>**Preprocessing**</p><ul><li>JSONPath: `$.pubsub_patterns`</li></ul>|
|Redis: Rejected connections|Number of connections rejected because of maxclients limit|Dependent|redis.stats.rejected_connections<p>**Preprocessing**</p><ul><li>JSONPath: `$.rejected_connections`</li></ul>|
|Redis: Sync full|The number of full resyncs with replicas|Dependent|redis.stats.sync_full<p>**Preprocessing**</p><ul><li>JSONPath: `$.sync_full`</li></ul>|
|Redis: Sync partial err|The number of denied partial resync requests|Dependent|redis.stats.sync_partial_err<p>**Preprocessing**</p><ul><li>JSONPath: `$.sync_partial_err`</li></ul>|
|Redis: Sync partial ok|The number of accepted partial resync requests|Dependent|redis.stats.sync_partial_ok<p>**Preprocessing**</p><ul><li>JSONPath: `$.sync_partial_ok`</li></ul>|
|Redis: Total commands processed|Total number of commands processed by the server|Dependent|redis.stats.total_commands_processed<p>**Preprocessing**</p><ul><li>JSONPath: `$.total_commands_processed`</li></ul>|
|Redis: Total connections received|Total number of connections accepted by the server|Dependent|redis.stats.total_connections_received<p>**Preprocessing**</p><ul><li>JSONPath: `$.total_connections_received`</li></ul>|
|Redis: Total net input bytes|The total number of bytes read from the network|Dependent|redis.stats.total_net_input_bytes<p>**Preprocessing**</p><ul><li>JSONPath: `$.total_net_input_bytes`</li></ul>|
|Redis: Total net output bytes|The total number of bytes written to the network|Dependent|redis.stats.total_net_output_bytes<p>**Preprocessing**</p><ul><li>JSONPath: `$.total_net_output_bytes`</li></ul>|
|Redis: Max clients|Max number of connected clients at the same time. Once the limit is reached Redis will close all the new connections sending an error "max number of clients reached".|Dependent|redis.config.maxclients<p>**Preprocessing**</p><ul><li>JSONPath: `$.maxclients`</li><li>Discard unchanged heartbeat: `30m`</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Failed to fetch info data|Zabbix has not received data for items for the last 30 minutes|`nodata(/Redis by Zabbix agent 2/Redis: Get info,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Redis: Service is down</li></ul>|
|Redis: Configuration has changed|Redis configuration has changed. Ack to close.|`last(/Redis by Zabbix agent 2/Redis: Get config,#1)<>last(/Redis by Zabbix agent 2/Redis: Get config,#2) and length(last(/Redis by Zabbix agent 2/Redis: Get config))>0`|Info|**Manual close**: Yes|
|Redis: Service is down|-|`last(/Redis by Zabbix agent 2/Redis: Ping)=0`|Average|**Manual close**: Yes|
|Redis: Too many entries in the slowlog|-|`min(/Redis by Zabbix agent 2/Redis: Slowlog entries per second,5m)>{$REDIS.SLOWLOG.COUNT.MAX.WARN}`|Info||
|Redis: Total number of connected clients is too high|When the number of clients reaches the value of the "maxclients" parameter, new connections will be rejected.https://redis.io/topics/clients#maximum-number-of-clients|`min(/Redis by Zabbix agent 2/redis.clients.connected,5m)/last(/Redis by Zabbix agent 2/redis.config.maxclients)*100>{$REDIS.CLIENTS.PRC.MAX.WARN}`|Warning||
|Redis: Memory fragmentation ratio is too high|This ratio is an indication of memory mapping efficiency:  - Value over 1.0 indicate that memory fragmentation is very likely. Consider restarting the Redis server so the operating system can recover fragmented memory, especially with a ratio over 1.5.  - Value under 1.0 indicate that Redis likely has insufficient memory available. Consider optimizing memory usage or adding more RAM.Note: If your peak memory usage is much higher than your current memory usage, the memory fragmentation ratio may be unreliable.https://redis.io/topics/memory-optimization|`min(/Redis by Zabbix agent 2/Redis: Memory fragmentation ratio,15m)>{$REDIS.MEM.FRAG_RATIO.MAX.WARN}`|Warning||
|Redis: Last AOF write operation failed|Detailed information about persistence: https://redis.io/topics/persistence|`last(/Redis by Zabbix agent 2/Redis: AOF last write status)=0`|Warning||
|Redis: Last RDB save operation failed|Detailed information about persistence: https://redis.io/topics/persistence|`last(/Redis by Zabbix agent 2/Redis: RDB last bgsave status)=0`|Warning||
|Redis: Number of slaves has changed|Redis number of slaves has changed. Ack to close.|`last(/Redis by Zabbix agent 2/Redis: Connected slaves,#1)<>last(/Redis by Zabbix agent 2/Redis: Connected slaves,#2)`|Info|**Manual close**: Yes|
|Redis: Replication role has changed|Redis replication role has changed. Ack to close.|`last(/Redis by Zabbix agent 2/Redis: Replication role,#1)<>last(/Redis by Zabbix agent 2/Redis: Replication role,#2) and length(last(/Redis by Zabbix agent 2/Redis: Replication role))>0`|Warning|**Manual close**: Yes|
|Redis: Version has changed|The Redis version has changed. Acknowledge to close manually.|`last(/Redis by Zabbix agent 2/Redis: Redis version,#1)<>last(/Redis by Zabbix agent 2/Redis: Redis version,#2) and length(last(/Redis by Zabbix agent 2/Redis: Redis version))>0`|Info|**Manual close**: Yes|
|Redis: Host has been restarted|The host uptime is less than 10 minutes.|`last(/Redis by Zabbix agent 2/Redis: Uptime)<10m`|Info|**Manual close**: Yes|
|Redis: Connections are rejected|The number of connections has reached the value of "maxclients".https://redis.io/topics/clients|`last(/Redis by Zabbix agent 2/Redis: Rejected connections)>0`|High||

### LLD rule Keyspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Keyspace discovery|Individual keyspace metrics|Dependent|redis.keyspace.discovery<p>**Preprocessing**</p><ul><li>Javascript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for Keyspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB {#DB}: Get Keyspace info|The item gets information about keyspace of {#DB} database.|Dependent|redis.db.info_raw["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.Keyspace["{#DB}"]`</p><p>⛔️On fail: Discard value</p></li></ul>|
|DB {#DB}: Average TTL|Average TTL|Dependent|redis.db.avg_ttl["{#DB}"]<p>**Preprocessing**</p><ul><li>JSONPath: `$.avg_ttl`</li><li>Multiplier: `0.001`</li></ul>|
|DB {#DB}: Expires|Number of keys with an expiration|Dependent|redis.db.expires["{#DB}"]<p>**Preprocessing**</p><ul><li>JSONPath: `$.expires`</li></ul>|
|DB {#DB}: Keys|Total number of keys|Dependent|redis.db.keys["{#DB}"]<p>**Preprocessing**</p><ul><li>JSONPath: `$.keys`</li></ul>|

### LLD rule AOF metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AOF metrics discovery|If AOF is activated, additional metrics will be added|Dependent|redis.persistence.aof.discovery<p>**Preprocessing**</p><ul><li>Javascript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for AOF metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: AOF current size{#SINGLETON}|AOF current file size|Dependent|redis.persistence.aof_current_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_current_size`</li></ul>|
|Redis: AOF base size{#SINGLETON}|AOF file size on latest startup or rewrite|Dependent|redis.persistence.aof_base_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_base_size`</li></ul>|
|Redis: AOF pending rewrite{#SINGLETON}|Flag indicating an AOF rewrite operation will|Dependent|redis.persistence.aof_pending_rewrite[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_pending_rewrite`</li></ul>|
|Redis: AOF buffer length{#SINGLETON}|Size of the AOF buffer|Dependent|redis.persistence.aof_buffer_length[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_buffer_length`</li></ul>|
|Redis: AOF rewrite buffer length{#SINGLETON}|Size of the AOF rewrite buffer|Dependent|redis.persistence.aof_rewrite_buffer_length[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSONPath: `$.aof_rewrite_buffer_length`</p><p>⛔️On fail: Discard value</p></li></ul>|
|Redis: AOF pending background I/O fsync{#SINGLETON}|Number of fsync pending jobs in background I/O queue|Dependent|redis.persistence.aof_pending_bio_fsync[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_pending_bio_fsync`</li></ul>|
|Redis: AOF delayed fsync{#SINGLETON}|Delayed fsync counter|Dependent|redis.persistence.aof_delayed_fsync[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_delayed_fsync`</li></ul>|

### LLD rule Slave metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Slave metrics discovery|If the instance is a replica, additional metrics are provided|Dependent|redis.replication.slave.discovery<p>**Preprocessing**</p><ul><li>Javascript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for Slave metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Master host{#SINGLETON}|Host or IP address of the master|Dependent|redis.replication.master_host[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.master_host`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|
|Redis: Master port{#SINGLETON}|Master listening TCP port|Dependent|redis.replication.master_port[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.master_port`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|
|Redis: Master link status{#SINGLETON}|Status of the link (up/down)|Dependent|redis.replication.master_link_status[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.master_link_status`</li><li>Bool to decimal</li></ul>|
|Redis: Master last I/O seconds ago{#SINGLETON}|Number of seconds since the last interaction with master|Dependent|redis.replication.master_last_io_seconds_ago[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.master_last_io_seconds_ago`</li></ul>|
|Redis: Master sync in progress{#SINGLETON}|Indicate the master is syncing to the replica|Dependent|redis.replication.master_sync_in_progress[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.master_sync_in_progress`</li></ul>|
|Redis: Slave replication offset{#SINGLETON}|The replication offset of the replica instance|Dependent|redis.replication.slave_repl_offset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.slave_repl_offset`</li></ul>|
|Redis: Slave priority{#SINGLETON}|The priority of the instance as a candidate for failover|Dependent|redis.replication.slave_priority[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.slave_priority`</li></ul>|
|Redis: Slave priority{#SINGLETON}|Flag indicating if the replica is read-only|Dependent|redis.replication.slave_read_only[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.slave_read_only`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|

### Trigger prototypes for Slave metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Replication lag with master is too high|-|`min(/Redis by Zabbix agent 2/Redis: Master last I/O seconds ago{#SINGLETON},5m)>{$REDIS.REPL.LAG.MAX.WARN}`|Warning||

### LLD rule Replication metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication metrics discovery|If the instance is the master and the slaves are connected, additional metrics are provided|Dependent|redis.replication.master.discovery<p>**Preprocessing**</p><ul><li>Javascript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for Replication metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis slave {#SLAVE_IP}:{#SLAVE_PORT}: Replication lag in bytes|Replication lag in bytes|Dependent|redis.replication.lag_bytes["{#SLAVE_IP}:{#SLAVE_PORT}"]<p>**Preprocessing**</p><ul><li>Javascript: `The text is too long. Please see the template.`</li></ul>|

### LLD rule Process metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Process metrics discovery|Collect metrics by Zabbix agent if it exists|Zabbix passive|proc.num["{$REDIS.LLD.PROCESS_NAME}"]<p>**Preprocessing**</p><ul><li>Javascript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for Process metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Number of processes running| - |Zabbix passive|proc.num["{$REDIS.PROCESS_NAME}{#SINGLETON}"]|
|Redis: Memory usage (rss)|Resident set size memory used by process in bytes.|Zabbix passive|proc.mem["{$REDIS.PROCESS_NAME}{#SINGLETON}",,,,rss]|
|Redis: Memory usage (vsize)|Virtual memory size used by process in bytes.|Zabbix passive|proc.mem["{$REDIS.PROCESS_NAME}{#SINGLETON}",,,,vsize]|
|Redis: CPU utilization|Process CPU utilization percentage.|Zabbix passive|proc.cpu.util["{$REDIS.PROCESS_NAME}{#SINGLETON}"]|

### Trigger prototypes for Process metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Process is not running|-|`last(/Redis by Zabbix agent 2/Redis: Number of processes running)=0`|High||

### LLD rule Version 4+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Version 4+ metrics discovery|Additional metrics for versions 4+|Dependent|redis.metrics.v4.discovery<p>**Preprocessing**</p><ul><li>JSONPath: `$.redis_version`</li><li>Javascript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for Version 4+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Executable path{#SINGLETON}|The path to the server's executable|Dependent|redis.server.executable[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.executable`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|
|Redis: Memory used peak %{#SINGLETON}|The percentage of used_memory_peak out of used_memory|Dependent|redis.memory.used_memory_peak_perc[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory_peak_perc`</li><li>Regex: `(.+)% \1`</li></ul>|
|Redis: Memory used overhead{#SINGLETON}|The sum in bytes of all overheads that the server allocated for managing its internal data structures|Dependent|redis.memory.used_memory_overhead[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory_overhead`</li></ul>|
|Redis: Memory used startup{#SINGLETON}|Initial amount of memory consumed by Redis at startup in bytes|Dependent|redis.memory.used_memory_startup[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory_startup`</li></ul>|
|Redis: Memory used dataset{#SINGLETON}|The size in bytes of the dataset|Dependent|redis.memory.used_memory_dataset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory_dataset`</li></ul>|
|Redis: Memory used dataset %{#SINGLETON}|The percentage of used_memory_dataset out of the net memory usage (used_memory minus used_memory_startup)|Dependent|redis.memory.used_memory_dataset_perc[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory_dataset_perc`</li><li>Regex: `(.+)% \1`</li></ul>|
|Redis: Total system memory{#SINGLETON}|The total amount of memory that the Redis host has|Dependent|redis.memory.total_system_memory[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.total_system_memory`</li></ul>|
|Redis: Max memory{#SINGLETON}|Maximum amount of memory allocated to the Redisdb system|Dependent|redis.memory.maxmemory[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.maxmemory`</li></ul>|
|Redis: Max memory policy{#SINGLETON}|The value of the maxmemory-policy configuration directive|Dependent|redis.memory.maxmemory_policy[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.maxmemory_policy`</li><li>Discard unchanged heartbeat: `1d`</li></ul>|
|Redis: Active defrag running{#SINGLETON}|Flag indicating if active defragmentation is active|Dependent|redis.memory.active_defrag_running[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.active_defrag_running`</li></ul>|
|Redis: Lazyfree pending objects{#SINGLETON}|The number of objects waiting to be freed (as a result of calling UNLINK, or FLUSHDB and FLUSHALL with the ASYNC option)|Dependent|redis.memory.lazyfree_pending_objects[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.lazyfree_pending_objects`</li></ul>|
|Redis: RDB last CoW size{#SINGLETON}|The size in bytes of copy-on-write allocations during the last RDB save operation|Dependent|redis.persistence.rdb_last_cow_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.rdb_last_cow_size`</li></ul>|
|Redis: AOF last CoW size{#SINGLETON}|The size in bytes of copy-on-write allocations during the last AOF rewrite operation|Dependent|redis.persistence.aof_last_cow_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.aof_last_cow_size`</li></ul>|
|Redis: Expired stale %{#SINGLETON}||Dependent|redis.stats.expired_stale_perc[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.expired_stale_perc`</li></ul>|
|Redis: Expired time cap reached count{#SINGLETON}||Dependent|redis.stats.expired_time_cap_reached_count[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.expired_time_cap_reached_count`</li></ul>|
|Redis: Slave expires tracked keys{#SINGLETON}|The number of keys tracked for expiry purposes (applicable only to writable replicas)|Dependent|redis.stats.slave_expires_tracked_keys[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.slave_expires_tracked_keys`</li></ul>|
|Redis: Active defrag hits{#SINGLETON}|Number of value reallocations performed by active the defragmentation process|Dependent|redis.stats.active_defrag_hits[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.active_defrag_hits`</li></ul>|
|Redis: Active defrag misses{#SINGLETON}|Number of aborted value reallocations started by the active defragmentation process|Dependent|redis.stats.active_defrag_misses[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.active_defrag_misses`</li></ul>|
|Redis: Active defrag key hits{#SINGLETON}|Number of keys that were actively defragmented|Dependent|redis.stats.active_defrag_key_hits[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.active_defrag_key_hits`</li></ul>|
|Redis: Active defrag key misses{#SINGLETON}|Number of keys that were skipped by the active defragmentation process|Dependent|redis.stats.active_defrag_key_misses[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.active_defrag_key_misses`</li></ul>|
|Redis: Replication second offset{#SINGLETON}|Offset up to which replication IDs are accepted|Dependent|redis.replication.second_repl_offset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.second_repl_offset`</li></ul>|

### Trigger prototypes for Version 4+ metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Memory usage is too high|-|`last(/Redis by Zabbix agent 2/redis.memory.used_memory)/min(/Redis by Zabbix agent 2/redis.memory.maxmemory[{#SINGLETON}],5m)*100>{$REDIS.MEM.PUSED.MAX.WARN}`|Warning||

### LLD rule Version 5+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Version 5+ metrics discovery|Additional metrics for versions 5+|Dependent|redis.metrics.v5.discovery<p>**Preprocessing**</p><ul><li>JSONPath: `$.redis_version`</li><li>Javascript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for Version 5+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Allocator active{#SINGLETON}||Dependent|redis.memory.allocator_active[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.allocator_active`</li></ul>|
|Redis: Allocator allocated{#SINGLETON}||Dependent|redis.memory.allocator_allocated[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.allocator_allocated`</li></ul>|
|Redis: Allocator resident{#SINGLETON}||Dependent|redis.memory.allocator_resident[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.allocator_resident`</li></ul>|
|Redis: Memory used scripts{#SINGLETON}||Dependent|redis.memory.used_memory_scripts[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.used_memory_scripts`</li></ul>|
|Redis: Memory number of cached scripts{#SINGLETON}||Dependent|redis.memory.number_of_cached_scripts[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.number_of_cached_scripts`</li></ul>|
|Redis: Allocator fragmentation bytes{#SINGLETON}||Dependent|redis.memory.allocator_frag_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.allocator_frag_bytes`</li></ul>|
|Redis: Allocator fragmentation ratio{#SINGLETON}||Dependent|redis.memory.allocator_frag_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.allocator_frag_ratio`</li></ul>|
|Redis: Allocator RSS bytes{#SINGLETON}||Dependent|redis.memory.allocator_rss_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.allocator_rss_bytes`</li></ul>|
|Redis: Allocator RSS ratio{#SINGLETON}||Dependent|redis.memory.allocator_rss_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.allocator_rss_ratio`</li></ul>|
|Redis: Memory RSS overhead bytes{#SINGLETON}||Dependent|redis.memory.rss_overhead_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.rss_overhead_bytes`</li></ul>|
|Redis: Memory RSS overhead ratio{#SINGLETON}||Dependent|redis.memory.rss_overhead_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.rss_overhead_ratio`</li></ul>|
|Redis: Memory fragmentation bytes{#SINGLETON}||Dependent|redis.memory.fragmentation_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.mem_fragmentation_bytes`</li></ul>|
|Redis: Memory not counted for evict{#SINGLETON}||Dependent|redis.memory.not_counted_for_evict[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.mem_not_counted_for_evict`</li></ul>|
|Redis: Memory replication backlog{#SINGLETON}||Dependent|redis.memory.replication_backlog[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.mem_replication_backlog`</li></ul>|
|Redis: Memory clients normal{#SINGLETON}||Dependent|redis.memory.mem_clients_normal[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.mem_clients_normal`</li></ul>|
|Redis: Memory clients slaves{#SINGLETON}||Dependent|redis.memory.mem_clients_slaves[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.mem_clients_slaves`</li></ul>|
|Redis: Memory AOF buffer{#SINGLETON}|Size of the AOF buffer|Dependent|redis.memory.mem_aof_buffer[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSONPath: `$.mem_aof_buffer`</li></ul>|
## Feedback

Please report any issues with the template at `https://support.zabbix.com`.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
