
# Redis by Zabbix agent 2

## Overview

This template is designed for the effortless deployment of Redis monitoring by Zabbix via Zabbix agent 2 and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Redis, version 3.0.6, 4.0.14, 5.0.6, 7.0.8

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Setup and configure zabbix-agent2 compiled with the Redis monitoring plugin (ZBXNEXT-5428-4.3).

Test availability: `zabbix_get -s redis-master -k redis.ping`


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$REDIS.CONN.URI}|<p>Connection string in the URI format (password is not used). This param overwrites a value configured in the "Server" option of the configuration file (if it's set), otherwise, the plugin's default value is used: "tcp://localhost:6379"</p>|`tcp://localhost:6379`|
|{$REDIS.PROCESS_NAME}|<p>Redis server process name</p>|`redis-server`|
|{$REDIS.LLD.PROCESS_NAME}|<p>Redis server process name for LLD</p>|`redis-server`|
|{$REDIS.LLD.FILTER.DB.MATCHES}|<p>Filter of discoverable databases</p>|`.*`|
|{$REDIS.LLD.FILTER.DB.NOT_MATCHES}|<p>Filter to exclude discovered databases</p>|`CHANGE_IF_NEEDED`|
|{$REDIS.REPL.LAG.MAX.WARN}|<p>Maximum replication lag in seconds</p>|`30s`|
|{$REDIS.SLOWLOG.COUNT.MAX.WARN}|<p>Maximum number of slowlog entries per second</p>|`1`|
|{$REDIS.CLIENTS.PRC.MAX.WARN}|<p>Maximum percentage of connected clients</p>|`80`|
|{$REDIS.MEM.PUSED.MAX.WARN}|<p>Maximum percentage of memory used</p>|`90`|
|{$REDIS.MEM.FRAG_RATIO.MAX.WARN}|<p>Maximum memory fragmentation ratio</p>|`1.5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Get info||Zabbix agent|redis.info["{$REDIS.CONN.URI}"]|
|Redis: Get config||Zabbix agent|redis.config["{$REDIS.CONN.URI}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Redis: Ping||Zabbix agent|redis.ping["{$REDIS.CONN.URI}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Redis: Slowlog entries per second||Zabbix agent|redis.slowlog.count["{$REDIS.CONN.URI}"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Redis: Get Clients info||Dependent item|redis.clients.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Clients`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Redis: Get CPU info||Dependent item|redis.cpu.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CPU`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Redis: Get Keyspace info||Dependent item|redis.keyspace.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Keyspace`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Redis: Get Memory info||Dependent item|redis.memory.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Memory`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Redis: Get Persistence info||Dependent item|redis.persistence.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Persistence`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Redis: Get Replication info||Dependent item|redis.replication.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Replication`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Redis: Get Server info||Dependent item|redis.server.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Server`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Redis: Get Stats info||Dependent item|redis.stats.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Stats`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Redis: CPU sys|<p>System CPU consumed by the Redis server</p>|Dependent item|redis.cpu.sys<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_cpu_sys`</p></li></ul>|
|Redis: CPU sys children|<p>System CPU consumed by the background processes</p>|Dependent item|redis.cpu.sys_children<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_cpu_sys_children`</p></li></ul>|
|Redis: CPU user|<p>User CPU consumed by the Redis server</p>|Dependent item|redis.cpu.user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_cpu_user`</p></li></ul>|
|Redis: CPU user children|<p>User CPU consumed by the background processes</p>|Dependent item|redis.cpu.user_children<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_cpu_user_children`</p></li></ul>|
|Redis: Blocked clients|<p>The number of connections waiting on a blocking call</p>|Dependent item|redis.clients.blocked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blocked_clients`</p></li></ul>|
|Redis: Max input buffer|<p>The biggest input buffer among current client connections</p>|Dependent item|redis.clients.max_input_buffer<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Redis: Max output buffer|<p>The biggest output buffer among current client connections</p>|Dependent item|redis.clients.max_output_buffer<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Redis: Connected clients|<p>The number of connected clients</p>|Dependent item|redis.clients.connected<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connected_clients`</p></li></ul>|
|Redis: Cluster enabled|<p>Indicate Redis cluster is enabled</p>|Dependent item|redis.cluster.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Cluster.cluster_enabled`</p></li></ul>|
|Redis: Memory used|<p>Total number of bytes allocated by Redis using its allocator</p>|Dependent item|redis.memory.used_memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory`</p></li></ul>|
|Redis: Memory used Lua|<p>Amount of memory used by the Lua engine</p>|Dependent item|redis.memory.used_memory_lua<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_lua`</p></li></ul>|
|Redis: Memory used peak|<p>Peak memory consumed by Redis (in bytes)</p>|Dependent item|redis.memory.used_memory_peak<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_peak`</p></li></ul>|
|Redis: Memory used RSS|<p>Number of bytes that Redis allocated as seen by the operating system</p>|Dependent item|redis.memory.used_memory_rss<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_rss`</p></li></ul>|
|Redis: Memory fragmentation ratio|<p>This ratio is an indication of memory mapping efficiency:</p><p>  - Value over 1.0 indicate that memory fragmentation is very likely. Consider restarting the Redis server so the operating system can recover fragmented memory, especially with a ratio over 1.5.</p><p>  - Value under 1.0 indicate that Redis likely has insufficient memory available. Consider optimizing memory usage or adding more RAM.</p><p></p><p>Note: If your peak memory usage is much higher than your current memory usage, the memory fragmentation ratio may be unreliable.</p><p></p><p>https://redis.io/topics/memory-optimization</p>|Dependent item|redis.memory.fragmentation_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_fragmentation_ratio`</p></li></ul>|
|Redis: AOF current rewrite time sec|<p>Duration of the on-going AOF rewrite operation if any</p>|Dependent item|redis.persistence.aof_current_rewrite_time_sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_current_rewrite_time_sec`</p></li></ul>|
|Redis: AOF enabled|<p>Flag indicating AOF logging is activated</p>|Dependent item|redis.persistence.aof_enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_enabled`</p></li></ul>|
|Redis: AOF last bgrewrite status|<p>Status of the last AOF rewrite operation</p>|Dependent item|redis.persistence.aof_last_bgrewrite_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_last_bgrewrite_status`</p></li><li>Boolean to decimal</li></ul>|
|Redis: AOF last rewrite time sec|<p>Duration of the last AOF rewrite</p>|Dependent item|redis.persistence.aof_last_rewrite_time_sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_last_rewrite_time_sec`</p></li></ul>|
|Redis: AOF last write status|<p>Status of the last write operation to the AOF</p>|Dependent item|redis.persistence.aof_last_write_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_last_write_status`</p></li><li>Boolean to decimal</li></ul>|
|Redis: AOF rewrite in progress|<p>Flag indicating a AOF rewrite operation is on-going</p>|Dependent item|redis.persistence.aof_rewrite_in_progress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_rewrite_in_progress`</p></li></ul>|
|Redis: AOF rewrite scheduled|<p>Flag indicating an AOF rewrite operation will be scheduled once the on-going RDB save is complete</p>|Dependent item|redis.persistence.aof_rewrite_scheduled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_rewrite_scheduled`</p></li></ul>|
|Redis: Dump loading|<p>Flag indicating if the load of a dump file is on-going</p>|Dependent item|redis.persistence.loading<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.loading`</p></li></ul>|
|Redis: RDB bgsave in progress|<p>"1" if bgsave is in progress and "0" otherwise</p>|Dependent item|redis.persistence.rdb_bgsave_in_progress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_bgsave_in_progress`</p></li></ul>|
|Redis: RDB changes since last save|<p>Number of changes since the last background save</p>|Dependent item|redis.persistence.rdb_changes_since_last_save<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_changes_since_last_save`</p></li></ul>|
|Redis: RDB current bgsave time sec|<p>Duration of the on-going RDB save operation if any</p>|Dependent item|redis.persistence.rdb_current_bgsave_time_sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_current_bgsave_time_sec`</p></li></ul>|
|Redis: RDB last bgsave status|<p>Status of the last RDB save operation</p>|Dependent item|redis.persistence.rdb_last_bgsave_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_last_bgsave_status`</p></li><li>Boolean to decimal</li></ul>|
|Redis: RDB last bgsave time sec|<p>Duration of the last bg_save operation</p>|Dependent item|redis.persistence.rdb_last_bgsave_time_sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_last_bgsave_time_sec`</p></li></ul>|
|Redis: RDB last save time|<p>Epoch-based timestamp of last successful RDB save</p>|Dependent item|redis.persistence.rdb_last_save_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_last_save_time`</p></li></ul>|
|Redis: Connected slaves|<p>Number of connected slaves</p>|Dependent item|redis.replication.connected_slaves<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connected_slaves`</p></li></ul>|
|Redis: Replication backlog active|<p>Flag indicating replication backlog is active</p>|Dependent item|redis.replication.repl_backlog_active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repl_backlog_active`</p></li></ul>|
|Redis: Replication backlog first byte offset|<p>The master offset of the replication backlog buffer</p>|Dependent item|redis.replication.repl_backlog_first_byte_offset<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repl_backlog_first_byte_offset`</p></li></ul>|
|Redis: Replication backlog history length|<p>Amount of data in the backlog sync buffer</p>|Dependent item|redis.replication.repl_backlog_histlen<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repl_backlog_histlen`</p></li></ul>|
|Redis: Replication backlog size|<p>Total size in bytes of the replication backlog buffer</p>|Dependent item|redis.replication.repl_backlog_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repl_backlog_size`</p></li></ul>|
|Redis: Replication role|<p>Value is "master" if the instance is replica of no one, or "slave" if the instance is a replica of some master instance. Note that a replica can be master of another replica (chained replication).</p>|Dependent item|redis.replication.role<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.role`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis: Master replication offset|<p>Replication offset reported by the master</p>|Dependent item|redis.replication.master_repl_offset<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_repl_offset`</p></li></ul>|
|Redis: Process id|<p>PID of the server process</p>|Dependent item|redis.server.process_id<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.process_id`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis: Redis mode|<p>The server's mode ("standalone", "sentinel" or "cluster")</p>|Dependent item|redis.server.redis_mode<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.redis_mode`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis: Redis version|<p>Version of the Redis server</p>|Dependent item|redis.server.redis_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.redis_version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis: TCP port|<p>TCP/IP listen port</p>|Dependent item|redis.server.tcp_port<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tcp_port`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis: Uptime|<p>Number of seconds since Redis server start</p>|Dependent item|redis.server.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime_in_seconds`</p></li></ul>|
|Redis: Evicted keys|<p>Number of evicted keys due to maxmemory limit</p>|Dependent item|redis.stats.evicted_keys<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.evicted_keys`</p></li></ul>|
|Redis: Expired keys|<p>Total number of key expiration events</p>|Dependent item|redis.stats.expired_keys<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expired_keys`</p></li></ul>|
|Redis: Instantaneous input bytes per second|<p>The network's read rate per second in KB/sec</p>|Dependent item|redis.stats.instantaneous_input.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instantaneous_input_kbps`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Redis: Instantaneous operations per sec|<p>Number of commands processed per second</p>|Dependent item|redis.stats.instantaneous_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instantaneous_ops_per_sec`</p></li></ul>|
|Redis: Instantaneous output bytes per second|<p>The network's write rate per second in KB/sec</p>|Dependent item|redis.stats.instantaneous_output.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instantaneous_output_kbps`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Redis: Keyspace hits|<p>Number of successful lookup of keys in the main dictionary</p>|Dependent item|redis.stats.keyspace_hits<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.keyspace_hits`</p></li></ul>|
|Redis: Keyspace misses|<p>Number of failed lookup of keys in the main dictionary</p>|Dependent item|redis.stats.keyspace_misses<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.keyspace_misses`</p></li></ul>|
|Redis: Latest fork usec|<p>Duration of the latest fork operation in microseconds</p>|Dependent item|redis.stats.latest_fork_usec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.latest_fork_usec`</p></li><li><p>Custom multiplier: `1e-05`</p></li></ul>|
|Redis: Migrate cached sockets|<p>The number of sockets open for MIGRATE purposes</p>|Dependent item|redis.stats.migrate_cached_sockets<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.migrate_cached_sockets`</p></li></ul>|
|Redis: Pubsub channels|<p>Global number of pub/sub channels with client subscriptions</p>|Dependent item|redis.stats.pubsub_channels<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pubsub_channels`</p></li></ul>|
|Redis: Pubsub patterns|<p>Global number of pub/sub pattern with client subscriptions</p>|Dependent item|redis.stats.pubsub_patterns<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pubsub_patterns`</p></li></ul>|
|Redis: Rejected connections|<p>Number of connections rejected because of maxclients limit</p>|Dependent item|redis.stats.rejected_connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rejected_connections`</p></li></ul>|
|Redis: Sync full|<p>The number of full resyncs with replicas</p>|Dependent item|redis.stats.sync_full<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sync_full`</p></li></ul>|
|Redis: Sync partial err|<p>The number of denied partial resync requests</p>|Dependent item|redis.stats.sync_partial_err<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sync_partial_err`</p></li></ul>|
|Redis: Sync partial ok|<p>The number of accepted partial resync requests</p>|Dependent item|redis.stats.sync_partial_ok<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sync_partial_ok`</p></li></ul>|
|Redis: Total commands processed|<p>Total number of commands processed by the server</p>|Dependent item|redis.stats.total_commands_processed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_commands_processed`</p></li></ul>|
|Redis: Total connections received|<p>Total number of connections accepted by the server</p>|Dependent item|redis.stats.total_connections_received<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_connections_received`</p></li></ul>|
|Redis: Total net input bytes|<p>The total number of bytes read from the network</p>|Dependent item|redis.stats.total_net_input_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_net_input_bytes`</p></li></ul>|
|Redis: Total net output bytes|<p>The total number of bytes written to the network</p>|Dependent item|redis.stats.total_net_output_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_net_output_bytes`</p></li></ul>|
|Redis: Max clients|<p>Max number of connected clients at the same time.</p><p>Once the limit is reached Redis will close all the new connections sending an error "max number of clients reached".</p>|Dependent item|redis.config.maxclients<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxclients`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Failed to fetch info data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Redis by Zabbix agent 2/redis.info["{$REDIS.CONN.URI}"],30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Redis: Service is down</li></ul>|
|Redis: Configuration has changed|<p>Redis configuration has changed. Acknowledge to close the problem manually.</p>|`last(/Redis by Zabbix agent 2/redis.config["{$REDIS.CONN.URI}"],#1)<>last(/Redis by Zabbix agent 2/redis.config["{$REDIS.CONN.URI}"],#2) and length(last(/Redis by Zabbix agent 2/redis.config["{$REDIS.CONN.URI}"]))>0`|Info|**Manual close**: Yes|
|Redis: Service is down||`last(/Redis by Zabbix agent 2/redis.ping["{$REDIS.CONN.URI}"])=0`|Average|**Manual close**: Yes|
|Redis: Too many entries in the slowlog||`min(/Redis by Zabbix agent 2/redis.slowlog.count["{$REDIS.CONN.URI}"],5m)>{$REDIS.SLOWLOG.COUNT.MAX.WARN}`|Info||
|Redis: Total number of connected clients is too high|<p>When the number of clients reaches the value of the "maxclients" parameter, new connections will be rejected.<br><br>https://redis.io/topics/clients#maximum-number-of-clients</p>|`min(/Redis by Zabbix agent 2/redis.clients.connected,5m)/last(/Redis by Zabbix agent 2/redis.config.maxclients)*100>{$REDIS.CLIENTS.PRC.MAX.WARN}`|Warning||
|Redis: Memory fragmentation ratio is too high|<p>This ratio is an indication of memory mapping efficiency:<br>  - Value over 1.0 indicate that memory fragmentation is very likely. Consider restarting the Redis server so the operating system can recover fragmented memory, especially with a ratio over 1.5.<br>  - Value under 1.0 indicate that Redis likely has insufficient memory available. Consider optimizing memory usage or adding more RAM.<br><br>Note: If your peak memory usage is much higher than your current memory usage, the memory fragmentation ratio may be unreliable.<br><br>https://redis.io/topics/memory-optimization</p>|`min(/Redis by Zabbix agent 2/redis.memory.fragmentation_ratio,15m)>{$REDIS.MEM.FRAG_RATIO.MAX.WARN}`|Warning||
|Redis: Last AOF write operation failed|<p>Detailed information about persistence: https://redis.io/topics/persistence</p>|`last(/Redis by Zabbix agent 2/redis.persistence.aof_last_write_status)=0`|Warning||
|Redis: Last RDB save operation failed|<p>Detailed information about persistence: https://redis.io/topics/persistence</p>|`last(/Redis by Zabbix agent 2/redis.persistence.rdb_last_bgsave_status)=0`|Warning||
|Redis: Number of slaves has changed|<p>Redis number of slaves has changed. Acknowledge to close the problem manually.</p>|`last(/Redis by Zabbix agent 2/redis.replication.connected_slaves,#1)<>last(/Redis by Zabbix agent 2/redis.replication.connected_slaves,#2)`|Info|**Manual close**: Yes|
|Redis: Replication role has changed|<p>Redis replication role has changed. Acknowledge to close the problem manually.</p>|`last(/Redis by Zabbix agent 2/redis.replication.role,#1)<>last(/Redis by Zabbix agent 2/redis.replication.role,#2) and length(last(/Redis by Zabbix agent 2/redis.replication.role))>0`|Warning|**Manual close**: Yes|
|Redis: Version has changed|<p>The Redis version has changed. Acknowledge to close the problem manually.</p>|`last(/Redis by Zabbix agent 2/redis.server.redis_version,#1)<>last(/Redis by Zabbix agent 2/redis.server.redis_version,#2) and length(last(/Redis by Zabbix agent 2/redis.server.redis_version))>0`|Info|**Manual close**: Yes|
|Redis: Host has been restarted|<p>The host uptime is less than 10 minutes.</p>|`last(/Redis by Zabbix agent 2/redis.server.uptime)<10m`|Info|**Manual close**: Yes|
|Redis: Connections are rejected|<p>The number of connections has reached the value of "maxclients".<br><br>https://redis.io/topics/clients</p>|`last(/Redis by Zabbix agent 2/redis.stats.rejected_connections)>0`|High||

### LLD rule Keyspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Keyspace discovery|<p>Individual keyspace metrics</p>|Dependent item|redis.keyspace.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Keyspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB {#DB}: Get Keyspace info|<p>The item gets information about keyspace of {#DB} database.</p>|Dependent item|redis.db.info_raw["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Keyspace["{#DB}"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB {#DB}: Average TTL|<p>Average TTL</p>|Dependent item|redis.db.avg_ttl["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avg_ttl`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|DB {#DB}: Expires|<p>Number of keys with an expiration</p>|Dependent item|redis.db.expires["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expires`</p></li></ul>|
|DB {#DB}: Keys|<p>Total number of keys</p>|Dependent item|redis.db.keys["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.keys`</p></li></ul>|

### LLD rule AOF metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AOF metrics discovery|<p>If AOF is activated, additional metrics will be added</p>|Dependent item|redis.persistence.aof.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for AOF metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: AOF current size{#SINGLETON}|<p>AOF current file size</p>|Dependent item|redis.persistence.aof_current_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_current_size`</p></li></ul>|
|Redis: AOF base size{#SINGLETON}|<p>AOF file size on latest startup or rewrite</p>|Dependent item|redis.persistence.aof_base_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_base_size`</p></li></ul>|
|Redis: AOF pending rewrite{#SINGLETON}|<p>Flag indicating an AOF rewrite operation will</p>|Dependent item|redis.persistence.aof_pending_rewrite[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_pending_rewrite`</p></li></ul>|
|Redis: AOF buffer length{#SINGLETON}|<p>Size of the AOF buffer</p>|Dependent item|redis.persistence.aof_buffer_length[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_buffer_length`</p></li></ul>|
|Redis: AOF rewrite buffer length{#SINGLETON}|<p>Size of the AOF rewrite buffer</p>|Dependent item|redis.persistence.aof_rewrite_buffer_length[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_rewrite_buffer_length`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Redis: AOF pending background I/O fsync{#SINGLETON}|<p>Number of fsync pending jobs in background I/O queue</p>|Dependent item|redis.persistence.aof_pending_bio_fsync[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_pending_bio_fsync`</p></li></ul>|
|Redis: AOF delayed fsync{#SINGLETON}|<p>Delayed fsync counter</p>|Dependent item|redis.persistence.aof_delayed_fsync[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_delayed_fsync`</p></li></ul>|

### LLD rule Slave metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Slave metrics discovery|<p>If the instance is a replica, additional metrics are provided</p>|Dependent item|redis.replication.slave.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Slave metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Master host{#SINGLETON}|<p>Host or IP address of the master</p>|Dependent item|redis.replication.master_host[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_host`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis: Master port{#SINGLETON}|<p>Master listening TCP port</p>|Dependent item|redis.replication.master_port[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_port`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis: Master link status{#SINGLETON}|<p>Status of the link (up/down)</p>|Dependent item|redis.replication.master_link_status[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_link_status`</p></li><li>Boolean to decimal</li></ul>|
|Redis: Master last I/O seconds ago{#SINGLETON}|<p>Number of seconds since the last interaction with master</p>|Dependent item|redis.replication.master_last_io_seconds_ago[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_last_io_seconds_ago`</p></li></ul>|
|Redis: Master sync in progress{#SINGLETON}|<p>Indicate the master is syncing to the replica</p>|Dependent item|redis.replication.master_sync_in_progress[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_sync_in_progress`</p></li></ul>|
|Redis: Slave replication offset{#SINGLETON}|<p>The replication offset of the replica instance</p>|Dependent item|redis.replication.slave_repl_offset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_repl_offset`</p></li></ul>|
|Redis: Slave priority{#SINGLETON}|<p>The priority of the instance as a candidate for failover</p>|Dependent item|redis.replication.slave_priority[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_priority`</p></li></ul>|
|Redis: Slave priority{#SINGLETON}|<p>Flag indicating if the replica is read-only</p>|Dependent item|redis.replication.slave_read_only[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_read_only`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Slave metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Replication lag with master is too high||`min(/Redis by Zabbix agent 2/redis.replication.master_last_io_seconds_ago[{#SINGLETON}],5m)>{$REDIS.REPL.LAG.MAX.WARN}`|Warning||

### LLD rule Replication metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication metrics discovery|<p>If the instance is the master and the slaves are connected, additional metrics are provided</p>|Dependent item|redis.replication.master.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Replication metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis slave {#SLAVE_IP}:{#SLAVE_PORT}: Replication lag in bytes|<p>Replication lag in bytes</p>|Dependent item|redis.replication.lag_bytes["{#SLAVE_IP}:{#SLAVE_PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Process metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Process metrics discovery|<p>Collect metrics by Zabbix agent if it exists</p>|Zabbix agent|proc.num["{$REDIS.LLD.PROCESS_NAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Process metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Number of running processes||Zabbix agent|proc.num["{$REDIS.PROCESS_NAME}{#SINGLETON}"]|
|Redis: Memory usage (rss)|<p>Resident set size memory used by process in bytes.</p>|Zabbix agent|proc.mem["{$REDIS.PROCESS_NAME}{#SINGLETON}",,,,rss]|
|Redis: Memory usage (vsize)|<p>Virtual memory size used by process in bytes.</p>|Zabbix agent|proc.mem["{$REDIS.PROCESS_NAME}{#SINGLETON}",,,,vsize]|
|Redis: CPU utilization|<p>Process CPU utilization percentage.</p>|Zabbix agent|proc.cpu.util["{$REDIS.PROCESS_NAME}{#SINGLETON}"]|

### Trigger prototypes for Process metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Process is not running||`last(/Redis by Zabbix agent 2/proc.num["{$REDIS.PROCESS_NAME}{#SINGLETON}"])=0`|High||

### LLD rule Version 4+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Version 4+ metrics discovery|<p>Additional metrics for versions 4+</p>|Dependent item|redis.metrics.v4.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.redis_version`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Version 4+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Executable path{#SINGLETON}|<p>The path to the server's executable</p>|Dependent item|redis.server.executable[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.executable`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis: Memory used peak %{#SINGLETON}|<p>The percentage of used_memory_peak out of used_memory</p>|Dependent item|redis.memory.used_memory_peak_perc[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_peak_perc`</p></li><li><p>Regular expression: `(.+)% \1`</p></li></ul>|
|Redis: Memory used overhead{#SINGLETON}|<p>The sum in bytes of all overheads that the server allocated for managing its internal data structures</p>|Dependent item|redis.memory.used_memory_overhead[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_overhead`</p></li></ul>|
|Redis: Memory used startup{#SINGLETON}|<p>Initial amount of memory consumed by Redis at startup in bytes</p>|Dependent item|redis.memory.used_memory_startup[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_startup`</p></li></ul>|
|Redis: Memory used dataset{#SINGLETON}|<p>The size in bytes of the dataset</p>|Dependent item|redis.memory.used_memory_dataset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_dataset`</p></li></ul>|
|Redis: Memory used dataset %{#SINGLETON}|<p>The percentage of used_memory_dataset out of the net memory usage (used_memory minus used_memory_startup)</p>|Dependent item|redis.memory.used_memory_dataset_perc[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_dataset_perc`</p></li><li><p>Regular expression: `(.+)% \1`</p></li></ul>|
|Redis: Total system memory{#SINGLETON}|<p>The total amount of memory that the Redis host has</p>|Dependent item|redis.memory.total_system_memory[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_system_memory`</p></li></ul>|
|Redis: Max memory{#SINGLETON}|<p>Maximum amount of memory allocated to the Redisdb system</p>|Dependent item|redis.memory.maxmemory[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxmemory`</p></li></ul>|
|Redis: Max memory policy{#SINGLETON}|<p>The value of the maxmemory-policy configuration directive</p>|Dependent item|redis.memory.maxmemory_policy[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxmemory_policy`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis: Active defrag running{#SINGLETON}|<p>Flag indicating if active defragmentation is active</p>|Dependent item|redis.memory.active_defrag_running[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_running`</p></li></ul>|
|Redis: Lazyfree pending objects{#SINGLETON}|<p>The number of objects waiting to be freed (as a result of calling UNLINK, or FLUSHDB and FLUSHALL with the ASYNC option)</p>|Dependent item|redis.memory.lazyfree_pending_objects[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lazyfree_pending_objects`</p></li></ul>|
|Redis: RDB last CoW size{#SINGLETON}|<p>The size in bytes of copy-on-write allocations during the last RDB save operation</p>|Dependent item|redis.persistence.rdb_last_cow_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_last_cow_size`</p></li></ul>|
|Redis: AOF last CoW size{#SINGLETON}|<p>The size in bytes of copy-on-write allocations during the last AOF rewrite operation</p>|Dependent item|redis.persistence.aof_last_cow_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_last_cow_size`</p></li></ul>|
|Redis: Expired stale %{#SINGLETON}||Dependent item|redis.stats.expired_stale_perc[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expired_stale_perc`</p></li></ul>|
|Redis: Expired time cap reached count{#SINGLETON}||Dependent item|redis.stats.expired_time_cap_reached_count[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expired_time_cap_reached_count`</p></li></ul>|
|Redis: Slave expires tracked keys{#SINGLETON}|<p>The number of keys tracked for expiry purposes (applicable only to writable replicas)</p>|Dependent item|redis.stats.slave_expires_tracked_keys[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_expires_tracked_keys`</p></li></ul>|
|Redis: Active defrag hits{#SINGLETON}|<p>Number of value reallocations performed by active the defragmentation process</p>|Dependent item|redis.stats.active_defrag_hits[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_hits`</p></li></ul>|
|Redis: Active defrag misses{#SINGLETON}|<p>Number of aborted value reallocations started by the active defragmentation process</p>|Dependent item|redis.stats.active_defrag_misses[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_misses`</p></li></ul>|
|Redis: Active defrag key hits{#SINGLETON}|<p>Number of keys that were actively defragmented</p>|Dependent item|redis.stats.active_defrag_key_hits[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_key_hits`</p></li></ul>|
|Redis: Active defrag key misses{#SINGLETON}|<p>Number of keys that were skipped by the active defragmentation process</p>|Dependent item|redis.stats.active_defrag_key_misses[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_key_misses`</p></li></ul>|
|Redis: Replication second offset{#SINGLETON}|<p>Offset up to which replication IDs are accepted</p>|Dependent item|redis.replication.second_repl_offset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.second_repl_offset`</p></li></ul>|

### Trigger prototypes for Version 4+ metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Memory usage is too high||`last(/Redis by Zabbix agent 2/redis.memory.used_memory)/min(/Redis by Zabbix agent 2/redis.memory.maxmemory[{#SINGLETON}],5m)*100>{$REDIS.MEM.PUSED.MAX.WARN}`|Warning||

### LLD rule Version 5+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Version 5+ metrics discovery|<p>Additional metrics for versions 5+</p>|Dependent item|redis.metrics.v5.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.redis_version`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Version 5+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis: Allocator active{#SINGLETON}||Dependent item|redis.memory.allocator_active[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_active`</p></li></ul>|
|Redis: Allocator allocated{#SINGLETON}||Dependent item|redis.memory.allocator_allocated[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_allocated`</p></li></ul>|
|Redis: Allocator resident{#SINGLETON}||Dependent item|redis.memory.allocator_resident[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_resident`</p></li></ul>|
|Redis: Memory used scripts{#SINGLETON}||Dependent item|redis.memory.used_memory_scripts[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_scripts`</p></li></ul>|
|Redis: Memory number of cached scripts{#SINGLETON}||Dependent item|redis.memory.number_of_cached_scripts[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.number_of_cached_scripts`</p></li></ul>|
|Redis: Allocator fragmentation bytes{#SINGLETON}||Dependent item|redis.memory.allocator_frag_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_frag_bytes`</p></li></ul>|
|Redis: Allocator fragmentation ratio{#SINGLETON}||Dependent item|redis.memory.allocator_frag_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_frag_ratio`</p></li></ul>|
|Redis: Allocator RSS bytes{#SINGLETON}||Dependent item|redis.memory.allocator_rss_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_rss_bytes`</p></li></ul>|
|Redis: Allocator RSS ratio{#SINGLETON}||Dependent item|redis.memory.allocator_rss_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_rss_ratio`</p></li></ul>|
|Redis: Memory RSS overhead bytes{#SINGLETON}||Dependent item|redis.memory.rss_overhead_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rss_overhead_bytes`</p></li></ul>|
|Redis: Memory RSS overhead ratio{#SINGLETON}||Dependent item|redis.memory.rss_overhead_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rss_overhead_ratio`</p></li></ul>|
|Redis: Memory fragmentation bytes{#SINGLETON}||Dependent item|redis.memory.fragmentation_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_fragmentation_bytes`</p></li></ul>|
|Redis: Memory not counted for evict{#SINGLETON}||Dependent item|redis.memory.not_counted_for_evict[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_not_counted_for_evict`</p></li></ul>|
|Redis: Memory replication backlog{#SINGLETON}||Dependent item|redis.memory.replication_backlog[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_replication_backlog`</p></li></ul>|
|Redis: Memory clients normal{#SINGLETON}||Dependent item|redis.memory.mem_clients_normal[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_clients_normal`</p></li></ul>|
|Redis: Memory clients slaves{#SINGLETON}||Dependent item|redis.memory.mem_clients_slaves[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_clients_slaves`</p></li></ul>|
|Redis: Memory AOF buffer{#SINGLETON}|<p>Size of the AOF buffer</p>|Dependent item|redis.memory.mem_aof_buffer[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_aof_buffer`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

