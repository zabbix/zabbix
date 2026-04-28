
# Redis by Zabbix agent 2

## Overview

This template is designed for the effortless deployment of Redis monitoring by Zabbix via Zabbix agent 2 and doesn't require any external scripts.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Redis versions 3.0.6, 4.0.14, 5.0.6, 7.2.4

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

- Set up and configure `zabbix-agent2` compiled with the Redis monitoring plugin.

- The Redis default user should have permissions to run `CONFIG`, `INFO`, `PING`, `CLIENT` and `SLOWLOG` commands.

- Or, the default user ACL should have the `@admin`, `@slow`, `@dangerous`, `@fast` and `@connection` categories.

- Test availability: `zabbix_get -s 127.0.0.1 -k redis.ping[tcp://127.0.0.1:6379]`

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$REDIS.CONN.URI}|<p>Connection string in URI format.</p><p>When set, this parameter overrides the value specified in the `Server` option of the configuration file.</p><p>Otherwise, the plugin's default value is used: `tcp://localhost:6379`.</p>|`tcp://localhost:6379`|
|{$REDIS.USERNAME}|<p>Username for Redis server authentication.</p>||
|{$REDIS.PASSWORD}|<p>Password for Redis server authentication.</p>||
|{$REDIS.SECTION}|<p>Section of Redis server information to retrieve.</p>||
|{$REDIS.PATTERN}|<p>Pattern for filtering Redis server configuration parameters.</p>||
|{$REDIS.PROCESS_NAME}|<p>Name of the Redis server process to monitor.</p>|`redis-server`|
|{$REDIS.LLD.PROCESS_NAME}|<p>Redis process name used during LLD.</p>|`redis-server`|
|{$REDIS.LLD.FILTER.DB.MATCHES}|<p>Regex pattern for databases to include during discovery.</p>|`.*`|
|{$REDIS.LLD.FILTER.DB.NOT_MATCHES}|<p>Regex pattern for databases to exclude during discovery.</p>|`CHANGE_IF_NEEDED`|
|{$REDIS.REPL.LAG.MAX.WARN}|<p>Maximum replication lag allowed between Redis master and replica, in seconds.</p>|`30s`|
|{$REDIS.SLOWLOG.COUNT.MAX.WARN}|<p>Slowlog entry rate threshold that triggers a warning.</p>|`1`|
|{$REDIS.CLIENTS.PRC.MAX.WARN}|<p>Threshold for the maximum percentage of connected Redis clients.</p>|`80`|
|{$REDIS.MEM.PUSED.MAX.WARN}|<p>Maximum percentage of memory used by the Redis server.</p>|`90`|
|{$REDIS.MEM.FRAG_RATIO.MAX.WARN}|<p>Threshold for the Redis server memory fragmentation ratio.</p>|`1.5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get info|<p>Retrieves general Redis server information and statistics.</p>|Zabbix agent|redis.info["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.SECTION}","{$REDIS.USERNAME}"]|
|Get config|<p>Extracts Redis server info.</p>|Zabbix agent|redis.config["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.PATTERN}","{$REDIS.USERNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Ping|<p>Verifies Redis server connectivity.</p>|Zabbix agent|redis.ping["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.USERNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Slowlog entries per second|<p>Tracks `SLOWLOG` entry rate on the Redis server.</p>|Zabbix agent|redis.slowlog.count["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.USERNAME}"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Get Client info|<p>Extracts client-related information from the Redis server.</p>|Dependent item|redis.clients.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Clients`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get CPU info|<p>Extracts CPU usage data from the Redis server.</p>|Dependent item|redis.cpu.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CPU`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get Keyspace info|<p>Extracts keyspace data from the Redis server.</p>|Dependent item|redis.keyspace.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Keyspace`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get Memory info|<p>Extracts memory data from the Redis server.</p>|Dependent item|redis.memory.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Memory`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get Persistence info|<p>Collects persistence data from the Redis server.</p>|Dependent item|redis.persistence.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Persistence`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get Replication info|<p>Extracts replication data from the Redis server.</p>|Dependent item|redis.replication.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Replication`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get Server info|<p>Retrieves Redis server information.</p>|Dependent item|redis.server.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Server`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get Stats info|<p>Collects Redis server statistics.</p>|Dependent item|redis.stats.info_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Stats`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU sys|<p>Retrieves Redis Server CPU usage (system).</p>|Dependent item|redis.cpu.sys<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_cpu_sys`</p></li></ul>|
|CPU sys children|<p>Retrieves system CPU usage of the Redis server child processes.</p>|Dependent item|redis.cpu.sys_children<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_cpu_sys_children`</p></li></ul>|
|CPU user|<p>Collects user CPU usage data from the Redis server.</p>|Dependent item|redis.cpu.user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_cpu_user`</p></li></ul>|
|CPU user children|<p>Retrieves user-level CPU usage of Redis server child processes.</p>|Dependent item|redis.cpu.user_children<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_cpu_user_children`</p></li></ul>|
|Blocked clients|<p>Retrieves blocked clients from the Redis server.</p>|Dependent item|redis.clients.blocked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blocked_clients`</p></li></ul>|
|Max input buffer|<p>Monitors maximum input buffer size of Redis server clients.</p>|Dependent item|redis.clients.max_input_buffer<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Max output buffer|<p>Monitors maximum output buffer size of Redis server clients.</p>|Dependent item|redis.clients.max_output_buffer<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Connected clients|<p>Monitors the number of active Redis server clients.</p>|Dependent item|redis.clients.connected<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connected_clients`</p></li></ul>|
|Cluster enabled|<p>Indicates if Redis server cluster mode is enabled.</p>|Dependent item|redis.cluster.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Cluster.cluster_enabled`</p></li></ul>|
|Memory used|<p>Monitors the memory used by the Redis server.</p>|Dependent item|redis.memory.used_memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory`</p></li></ul>|
|Memory used Lua|<p>Monitors the memory used by Lua scripts in the Redis server.</p>|Dependent item|redis.memory.used_memory_lua<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_lua`</p></li></ul>|
|Memory used peak|<p>Monitors peak memory usage of the Redis server.</p>|Dependent item|redis.memory.used_memory_peak<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_peak`</p></li></ul>|
|Memory used RSS|<p>Monitors resident memory (RSS) used by the Redis server.</p>|Dependent item|redis.memory.used_memory_rss<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_rss`</p></li></ul>|
|Memory fragmentation ratio|<p>Shows the memory fragmentation ratio of the Redis server instance.</p><p> This value indicates the efficiency of Redis memory mapping:</p><p>   Above 1.0 – Memory fragmentation is likely. Consider restarting the Redis server to allow the operating system to reclaim fragmented memory, especially if the ratio exceeds 1.5.</p><p>   Below 1.0 – Redis may have insufficient available memory. Consider optimizing memory usage or adding more RAM.</p><p></p><p> Note: If peak memory usage is significantly higher than current memory usage, the memory fragmentation ratio may be unreliable.</p><p> More information: https://redis.io/topics/memory-optimization</p>|Dependent item|redis.memory.fragmentation_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_fragmentation_ratio`</p></li></ul>|
|AOF current rewrite time sec|<p>Monitors the current AOF rewrite time on the Redis server.</p>|Dependent item|redis.persistence.aof_current_rewrite_time_sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_current_rewrite_time_sec`</p></li></ul>|
|AOF enabled|<p>Indicates if AOF persistence is enabled on the Redis server.</p>|Dependent item|redis.persistence.aof_enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_enabled`</p></li></ul>|
|AOF last bgrewrite status|<p>Shows the status of the last AOF background rewrite on the Redis server.</p>|Dependent item|redis.persistence.aof_last_bgrewrite_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_last_bgrewrite_status`</p></li><li>Boolean to decimal</li></ul>|
|AOF last rewrite time sec|<p>Shows the duration of the last AOF rewrite in seconds on the Redis server.</p>|Dependent item|redis.persistence.aof_last_rewrite_time_sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_last_rewrite_time_sec`</p></li></ul>|
|AOF last write status|<p>Shows the status of the last AOF write on the Redis server.</p>|Dependent item|redis.persistence.aof_last_write_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_last_write_status`</p></li><li>Boolean to decimal</li></ul>|
|AOF rewrite in progress|<p>Indicates if an AOF rewrite is currently in progress on the Redis server.</p>|Dependent item|redis.persistence.aof_rewrite_in_progress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_rewrite_in_progress`</p></li></ul>|
|AOF rewrite scheduled|<p>Indicates if an AOF rewrite is scheduled on the Redis server.</p>|Dependent item|redis.persistence.aof_rewrite_scheduled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_rewrite_scheduled`</p></li></ul>|
|Dump loading|<p>Indicates if the Redis server is currently loading a dump file.</p>|Dependent item|redis.persistence.loading<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.loading`</p></li></ul>|
|RDB bgsave in progress|<p>Indicates if an RDB `BGSAVE` is currently in progress on the Redis server.</p>|Dependent item|redis.persistence.rdb_bgsave_in_progress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_bgsave_in_progress`</p></li></ul>|
|RDB changes since last save|<p>Shows the number of changes since the last RDB save on the Redis server.</p>|Dependent item|redis.persistence.rdb_changes_since_last_save<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_changes_since_last_save`</p></li></ul>|
|RDB current bgsave time sec|<p>Monitors the current RDB `BGSAVE` duration in the Redis server.</p>|Dependent item|redis.persistence.rdb_current_bgsave_time_sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_current_bgsave_time_sec`</p></li></ul>|
|RDB last bgsave status|<p>Shows the status of the last RDB `BGSAVE` operation in the Redis server.</p>|Dependent item|redis.persistence.rdb_last_bgsave_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_last_bgsave_status`</p></li><li>Boolean to decimal</li></ul>|
|RDB last bgsave time sec|<p>Monitors the last RDB `BGSAVE` duration in the Redis server.</p>|Dependent item|redis.persistence.rdb_last_bgsave_time_sec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_last_bgsave_time_sec`</p></li></ul>|
|RDB last save time|<p>Shows the timestamp of the last RDB save on the Redis server.</p>|Dependent item|redis.persistence.rdb_last_save_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_last_save_time`</p></li></ul>|
|Connected slaves|<p>Shows the number of connected Redis slave servers.</p>|Dependent item|redis.replication.connected_slaves<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connected_slaves`</p></li></ul>|
|Replication backlog active|<p>Indicates if the replication backlog is active on the Redis server.</p>|Dependent item|redis.replication.repl_backlog_active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repl_backlog_active`</p></li></ul>|
|Replication backlog first byte offset|<p>Shows the offset of the first byte in the Redis server replication backlog.</p>|Dependent item|redis.replication.repl_backlog_first_byte_offset<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repl_backlog_first_byte_offset`</p></li></ul>|
|Replication backlog history length|<p>Shows the length of the Redis server replication backlog history.</p>|Dependent item|redis.replication.repl_backlog_histlen<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repl_backlog_histlen`</p></li></ul>|
|Replication backlog size|<p>Shows the size of the Redis server replication backlog.</p>|Dependent item|redis.replication.repl_backlog_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repl_backlog_size`</p></li></ul>|
|Replication role|<p>Shows the Redis server replication role (master or slave) from the replication info.</p>|Dependent item|redis.replication.role<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.role`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Master replication offset|<p>Shows the master replication offset on the Redis server.</p>|Dependent item|redis.replication.master_repl_offset<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_repl_offset`</p></li></ul>|
|Process id|<p>Retrieves the Redis server process identifier.</p>|Dependent item|redis.server.process_id<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.process_id`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis mode|<p>Retrieves the current Redis server mode (`standalone`, `sentinel`, or `cluster`).</p>|Dependent item|redis.server.redis_mode<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.redis_mode`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Redis version|<p>Version of the Redis server.</p>|Dependent item|redis.server.redis_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.redis_version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|TCP port|<p>Shows the TCP port used by the Redis server.</p>|Dependent item|redis.server.tcp_port<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tcp_port`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Uptime|<p>Shows Redis server uptime.</p>|Dependent item|redis.server.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime_in_seconds`</p></li></ul>|
|Evicted keys|<p>Number of keys evicted by the Redis server due to the `maxmemory` limit.</p>|Dependent item|redis.stats.evicted_keys<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.evicted_keys`</p></li></ul>|
|Expired keys|<p>Total number of keys expired on the Redis server.</p>|Dependent item|redis.stats.expired_keys<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expired_keys`</p></li></ul>|
|Instantaneous input bytes per second|<p>Instantaneous network read rate in KB/sec on the Redis server.</p>|Dependent item|redis.stats.instantaneous_input.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instantaneous_input_kbps`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Instantaneous operations per sec|<p>Shows the number of operations processed per second by the Redis server.</p>|Dependent item|redis.stats.instantaneous_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instantaneous_ops_per_sec`</p></li></ul>|
|Instantaneous output bytes per second|<p>Instantaneous network write rate in KB/sec on the Redis server.</p>|Dependent item|redis.stats.instantaneous_output.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instantaneous_output_kbps`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Keyspace hits|<p>Number of successful key lookups in the Redis main dictionary.</p>|Dependent item|redis.stats.keyspace_hits<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.keyspace_hits`</p></li></ul>|
|Keyspace misses|<p>Number of failed key lookups in the Redis server main dictionary.</p>|Dependent item|redis.stats.keyspace_misses<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.keyspace_misses`</p></li></ul>|
|Latest fork usec|<p>Duration of the latest Redis server fork operation in microseconds.</p>|Dependent item|redis.stats.latest_fork_usec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.latest_fork_usec`</p></li><li><p>Custom multiplier: `1e-05`</p></li></ul>|
|Migrate cached sockets|<p>Number of sockets currently open for the Redis server `MIGRATE` operations.</p>|Dependent item|redis.stats.migrate_cached_sockets<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.migrate_cached_sockets`</p></li></ul>|
|Pubsub channels|<p>Total number of Redis server pub/sub channels with active client subscriptions.</p>|Dependent item|redis.stats.pubsub_channels<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pubsub_channels`</p></li></ul>|
|Pubsub patterns|<p>Total number of Redis pub/sub patterns with active client subscriptions.</p>|Dependent item|redis.stats.pubsub_patterns<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pubsub_patterns`</p></li></ul>|
|Rejected connections|<p>Number of connections rejected by the Redis server due to the `maxclients` limit.</p>|Dependent item|redis.stats.rejected_connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rejected_connections`</p></li></ul>|
|Sync full|<p>Number of full resynchronizations with Redis server replicas.</p>|Dependent item|redis.stats.sync_full<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sync_full`</p></li></ul>|
|Sync partial err|<p>Number of denied partial resynchronization requests on the Redis server.</p>|Dependent item|redis.stats.sync_partial_err<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sync_partial_err`</p></li></ul>|
|Sync partial ok|<p>Number of accepted partial resynchronization requests on the Redis server.</p>|Dependent item|redis.stats.sync_partial_ok<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sync_partial_ok`</p></li></ul>|
|Total commands processed|<p>Total number of commands processed by the Redis server.</p>|Dependent item|redis.stats.total_commands_processed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_commands_processed`</p></li></ul>|
|Total connections received|<p>Total number of connections received by the Redis server.</p>|Dependent item|redis.stats.total_connections_received<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_connections_received`</p></li></ul>|
|Total net input bytes|<p>Total number of bytes read by the Redis server from the network.</p>|Dependent item|redis.stats.total_net_input_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_net_input_bytes`</p></li></ul>|
|Total net output bytes|<p>Total number of bytes written by the Redis server to the network.</p>|Dependent item|redis.stats.total_net_output_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_net_output_bytes`</p></li></ul>|
|Max clients|<p>Max number of connected clients at the same time.</p><p>Once the limit is reached Redis will close all the new connections sending an error "max number of clients reached".</p>|Dependent item|redis.config.maxclients<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxclients`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Failed to fetch INFO|<p>Zabbix has not received any item data for the last 30 minutes.</p>|`nodata(/Redis by Zabbix agent 2/redis.info["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.SECTION}","{$REDIS.USERNAME}"],30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Redis: Service is down</li></ul>|
|Redis: Configuration has changed|<p>Redis configuration has changed. Acknowledge to close the problem manually.</p>|`last(/Redis by Zabbix agent 2/redis.config["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.PATTERN}","{$REDIS.USERNAME}"],#1)<>last(/Redis by Zabbix agent 2/redis.config["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.PATTERN}","{$REDIS.USERNAME}"],#2) and length(last(/Redis by Zabbix agent 2/redis.config["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.PATTERN}","{$REDIS.USERNAME}"]))>0`|Info|**Manual close**: Yes|
|Redis: Service is down||`last(/Redis by Zabbix agent 2/redis.ping["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.USERNAME}"])=0`|Average|**Manual close**: Yes|
|Redis: Too many entries in the slowlog||`min(/Redis by Zabbix agent 2/redis.slowlog.count["{$REDIS.CONN.URI}","{$REDIS.PASSWORD}","{$REDIS.USERNAME}"],5m)>{$REDIS.SLOWLOG.COUNT.MAX.WARN}`|Info||
|Redis: Total number of connected clients is too high|<p>When the number of clients reaches the value of the `maxclients` parameter, new connections will be rejected.<br><br>https://redis.io/topics/clients#maximum-number-of-clients</p>|`min(/Redis by Zabbix agent 2/redis.clients.connected,5m)/last(/Redis by Zabbix agent 2/redis.config.maxclients)*100>{$REDIS.CLIENTS.PRC.MAX.WARN}`|Warning||
|Redis: Memory fragmentation ratio is too high|<p>This ratio is an indication of memory mapping efficiency:<br>  - Value over 1.0 indicates that memory fragmentation is very likely. Consider restarting the Redis server so the operating system can recover fragmented memory, especially with a ratio over 1.5.<br>  - Value under 1.0 indicates that Redis likely has insufficient memory available. Consider optimizing memory usage or adding more RAM.<br><br>Note: If your peak memory usage is much higher than your current memory usage, the memory fragmentation ratio may be unreliable.<br><br>https://redis.io/topics/memory-optimization</p>|`min(/Redis by Zabbix agent 2/redis.memory.fragmentation_ratio,15m)>{$REDIS.MEM.FRAG_RATIO.MAX.WARN}`|Warning||
|Redis: Last AOF write operation failed|<p>Detailed information about persistence: https://redis.io/topics/persistence</p>|`last(/Redis by Zabbix agent 2/redis.persistence.aof_last_write_status)=0`|Warning||
|Redis: Last RDB save operation failed|<p>Detailed information about persistence: https://redis.io/topics/persistence</p>|`last(/Redis by Zabbix agent 2/redis.persistence.rdb_last_bgsave_status)=0`|Warning||
|Redis: Number of slaves has changed|<p>Redis number of slaves has changed. Acknowledge to close the problem manually.</p>|`last(/Redis by Zabbix agent 2/redis.replication.connected_slaves,#1)<>last(/Redis by Zabbix agent 2/redis.replication.connected_slaves,#2)`|Info|**Manual close**: Yes|
|Redis: Replication role has changed|<p>Redis replication role has changed. Acknowledge to close the problem manually.</p>|`last(/Redis by Zabbix agent 2/redis.replication.role,#1)<>last(/Redis by Zabbix agent 2/redis.replication.role,#2) and length(last(/Redis by Zabbix agent 2/redis.replication.role))>0`|Warning|**Manual close**: Yes|
|Redis: Version has changed|<p>The Redis version has changed. Acknowledge to close the problem manually.</p>|`last(/Redis by Zabbix agent 2/redis.server.redis_version,#1)<>last(/Redis by Zabbix agent 2/redis.server.redis_version,#2) and length(last(/Redis by Zabbix agent 2/redis.server.redis_version))>0`|Info|**Manual close**: Yes|
|Redis: Host has been restarted|<p>The host uptime is less than 10 minutes.</p>|`last(/Redis by Zabbix agent 2/redis.server.uptime)<10m`|Info|**Manual close**: Yes|
|Redis: Connections are rejected|<p>Number of connections rejected after Redis server reached the `maxclients` limit.<br><br>https://redis.io/topics/clients</p>|`last(/Redis by Zabbix agent 2/redis.stats.rejected_connections)>0`|High||

### LLD rule Keyspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Keyspace discovery|<p>Metrics for individual Redis server keyspaces.</p>|Dependent item|redis.keyspace.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Keyspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB {#DB}: Get Keyspace info|<p>The item gets information about the keyspace of database `{#DB}`.</p>|Dependent item|redis.db.info_raw["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DB}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB {#DB}: Average TTL|<p>Average TTL.</p>|Dependent item|redis.db.avg_ttl["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avg_ttl`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|DB {#DB}: Expires|<p>Number of keys with an expiration set.</p>|Dependent item|redis.db.expires["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expires`</p></li></ul>|
|DB {#DB}: Keys|<p>Total number of keys.</p>|Dependent item|redis.db.keys["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.keys`</p></li></ul>|

### LLD rule AOF metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AOF metrics discovery|<p>If AOF is activated, additional metrics will be added.</p>|Dependent item|redis.persistence.aof.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for AOF metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AOF current size{#SINGLETON}|<p>AOF current file size.</p>|Dependent item|redis.persistence.aof_current_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_current_size`</p></li></ul>|
|AOF base size{#SINGLETON}|<p>AOF file size on latest startup or rewrite.</p>|Dependent item|redis.persistence.aof_base_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_base_size`</p></li></ul>|
|AOF pending rewrite{#SINGLETON}|<p>Flag indicating an AOF rewrite operation is pending.</p>|Dependent item|redis.persistence.aof_pending_rewrite[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_pending_rewrite`</p></li></ul>|
|AOF buffer length{#SINGLETON}|<p>Size of the AOF buffer.</p>|Dependent item|redis.persistence.aof_buffer_length[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_buffer_length`</p></li></ul>|
|AOF rewrite buffer length{#SINGLETON}|<p>Size of the AOF rewrite buffer.</p>|Dependent item|redis.persistence.aof_rewrite_buffer_length[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_rewrite_buffer_length`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AOF pending background I/O fsync{#SINGLETON}|<p>Number of pending `fsync` jobs in the background I/O queue.</p>|Dependent item|redis.persistence.aof_pending_bio_fsync[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_pending_bio_fsync`</p></li></ul>|
|AOF delayed fsync{#SINGLETON}|<p>Count of delayed `fsync` jobs.</p>|Dependent item|redis.persistence.aof_delayed_fsync[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_delayed_fsync`</p></li></ul>|

### LLD rule Slave metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Slave metrics discovery|<p>If the instance is a replica, additional metrics are provided.</p>|Dependent item|redis.replication.slave.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Slave metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Master host{#SINGLETON}|<p>Host or IP address of the master.</p>|Dependent item|redis.replication.master_host[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_host`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Master port{#SINGLETON}|<p>TCP port the master is listening on.</p>|Dependent item|redis.replication.master_port[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_port`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Master link status{#SINGLETON}|<p>Status of the link (up/down).</p>|Dependent item|redis.replication.master_link_status[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_link_status`</p></li><li>Boolean to decimal</li></ul>|
|Master last I/O seconds ago{#SINGLETON}|<p>Number of seconds since the last interaction with the master.</p>|Dependent item|redis.replication.master_last_io_seconds_ago[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_last_io_seconds_ago`</p></li></ul>|
|Master sync in progress{#SINGLETON}|<p>Indicates the master is syncing to the replica.</p>|Dependent item|redis.replication.master_sync_in_progress[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.master_sync_in_progress`</p></li></ul>|
|Slave replication offset{#SINGLETON}|<p>The replication offset of the replica instance.</p>|Dependent item|redis.replication.slave_repl_offset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_repl_offset`</p></li></ul>|
|Slave priority{#SINGLETON}|<p>The priority of the instance as a candidate for failover.</p>|Dependent item|redis.replication.slave_priority[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_priority`</p></li></ul>|
|Slave priority{#SINGLETON}|<p>Flag indicating if the replica is read-only.</p>|Dependent item|redis.replication.slave_read_only[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_read_only`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Slave metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Replication lag with master is too high||`min(/Redis by Zabbix agent 2/redis.replication.master_last_io_seconds_ago[{#SINGLETON}],5m)>{$REDIS.REPL.LAG.MAX.WARN}`|Warning||

### LLD rule Replication metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication metrics discovery|<p>If the instance is the master and the slaves are connected, additional metrics are provided.</p>|Dependent item|redis.replication.master.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Replication metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redis slave {#SLAVE_IP}:{#SLAVE_PORT}: Replication lag in bytes|<p>Replication lag, in bytes.</p>|Dependent item|redis.replication.lag_bytes["{#SLAVE_IP}:{#SLAVE_PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Process metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Process metrics discovery|<p>Collect metrics by Zabbix agent if it exists.</p>|Zabbix agent|proc.num["{$REDIS.LLD.PROCESS_NAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Process metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Number of running processes|<p>Number of running processes on the Redis server.</p>|Zabbix agent|proc.num["{$REDIS.PROCESS_NAME}{#SINGLETON}"]|
|Memory usage (rss)|<p>Resident Set Size memory used by the process, in bytes.</p>|Zabbix agent|proc.mem["{$REDIS.PROCESS_NAME}{#SINGLETON}",,,,rss]|
|Memory usage (vsize)|<p>Virtual memory size used by the process, in bytes.</p>|Zabbix agent|proc.mem["{$REDIS.PROCESS_NAME}{#SINGLETON}",,,,vsize]|
|CPU utilization|<p>Process CPU utilization percentage.</p>|Zabbix agent|proc.cpu.util["{$REDIS.PROCESS_NAME}{#SINGLETON}"]|

### Trigger prototypes for Process metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Process is not running||`last(/Redis by Zabbix agent 2/proc.num["{$REDIS.PROCESS_NAME}{#SINGLETON}"])=0`|High||

### LLD rule Version 4+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Version 4+ metrics discovery|<p>Additional metrics for versions 4+.</p>|Dependent item|redis.metrics.v4.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.redis_version`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Version 4+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Executable path{#SINGLETON}|<p>The path to the server's executable.</p>|Dependent item|redis.server.executable[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.executable`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Memory used peak %{#SINGLETON}|<p>The percentage of `used_memory_peak` out of `used_memory`.</p>|Dependent item|redis.memory.used_memory_peak_perc[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_peak_perc`</p></li><li><p>Regular expression: `(.+)% \1`</p></li></ul>|
|Memory used overhead{#SINGLETON}|<p>The sum in bytes of all overheads that the server allocated for managing its internal data structures.</p>|Dependent item|redis.memory.used_memory_overhead[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_overhead`</p></li></ul>|
|Memory used startup{#SINGLETON}|<p>Initial amount of memory consumed by Redis at startup, in bytes.</p>|Dependent item|redis.memory.used_memory_startup[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_startup`</p></li></ul>|
|Memory used dataset{#SINGLETON}|<p>The size in bytes of the dataset.</p>|Dependent item|redis.memory.used_memory_dataset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_dataset`</p></li></ul>|
|Memory used dataset %{#SINGLETON}|<p>The percentage of `used_memory_dataset` out of the net memory usage (`used_memory` minus `used_memory_startup`).</p>|Dependent item|redis.memory.used_memory_dataset_perc[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_dataset_perc`</p></li><li><p>Regular expression: `(.+)% \1`</p></li></ul>|
|Total system memory{#SINGLETON}|<p>The total amount of memory of the Redis host.</p>|Dependent item|redis.memory.total_system_memory[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_system_memory`</p></li></ul>|
|Max memory{#SINGLETON}|<p>Maximum amount of memory allocated to the Redis server.</p>|Dependent item|redis.memory.maxmemory[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxmemory`</p></li></ul>|
|Max memory policy{#SINGLETON}|<p>The value of the `maxmemory-policy` configuration directive.</p>|Dependent item|redis.memory.maxmemory_policy[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxmemory_policy`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Active defrag running{#SINGLETON}|<p>Flag indicating if active defragmentation is active.</p>|Dependent item|redis.memory.active_defrag_running[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_running`</p></li></ul>|
|Lazyfree pending objects{#SINGLETON}|<p>The number of objects waiting to be freed (as a result of calling `UNLINK`, or `FLUSHDB` and `FLUSHALL` with the `ASYNC` option).</p>|Dependent item|redis.memory.lazyfree_pending_objects[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lazyfree_pending_objects`</p></li></ul>|
|RDB last CoW size{#SINGLETON}|<p>The size in bytes of copy-on-write allocations during the last RDB save operation.</p>|Dependent item|redis.persistence.rdb_last_cow_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rdb_last_cow_size`</p></li></ul>|
|AOF last CoW size{#SINGLETON}|<p>The size in bytes of copy-on-write allocations during the last AOF rewrite operation.</p>|Dependent item|redis.persistence.aof_last_cow_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aof_last_cow_size`</p></li></ul>|
|Expired stale %{#SINGLETON}|<p>Number of stale keys expired by the Redis server.</p>|Dependent item|redis.stats.expired_stale_perc[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expired_stale_perc`</p></li></ul>|
|Expired time cap reached count{#SINGLETON}|<p>Number of keys expired due to reaching the time limit on the Redis server.</p>|Dependent item|redis.stats.expired_time_cap_reached_count[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expired_time_cap_reached_count`</p></li></ul>|
|Slave expires tracked keys{#SINGLETON}|<p>The number of keys tracked for expiry purposes (applicable only to writable replicas).</p>|Dependent item|redis.stats.slave_expires_tracked_keys[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slave_expires_tracked_keys`</p></li></ul>|
|Active defrag hits{#SINGLETON}|<p>Number of value reallocations performed by the active defragmentation process.</p>|Dependent item|redis.stats.active_defrag_hits[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_hits`</p></li></ul>|
|Active defrag misses{#SINGLETON}|<p>Number of aborted value reallocations started by the active defragmentation process.</p>|Dependent item|redis.stats.active_defrag_misses[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_misses`</p></li></ul>|
|Active defrag key hits{#SINGLETON}|<p>Number of keys that were actively defragmented.</p>|Dependent item|redis.stats.active_defrag_key_hits[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_key_hits`</p></li></ul>|
|Active defrag key misses{#SINGLETON}|<p>Number of keys that were skipped by the active defragmentation process.</p>|Dependent item|redis.stats.active_defrag_key_misses[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_defrag_key_misses`</p></li></ul>|
|Replication second offset{#SINGLETON}|<p>Offset up to which replication IDs are accepted.</p>|Dependent item|redis.replication.second_repl_offset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.second_repl_offset`</p></li></ul>|

### Trigger prototypes for Version 4+ metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Redis: Memory usage is too high||`last(/Redis by Zabbix agent 2/redis.memory.used_memory)/min(/Redis by Zabbix agent 2/redis.memory.maxmemory[{#SINGLETON}],5m)*100>{$REDIS.MEM.PUSED.MAX.WARN}`|Warning||

### LLD rule Version 5+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Version 5+ metrics discovery|<p>Additional metrics for versions 5+.</p>|Dependent item|redis.metrics.v5.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.redis_version`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Version 5+ metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Allocator active{#SINGLETON}|<p>Active memory allocated by the Redis server allocator.</p>|Dependent item|redis.memory.allocator_active[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_active`</p></li></ul>|
|Allocator allocated{#SINGLETON}|<p>Total memory allocated by the Redis server allocator.</p>|Dependent item|redis.memory.allocator_allocated[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_allocated`</p></li></ul>|
|Allocator resident{#SINGLETON}|<p>Resident memory used by the Redis server allocator.</p>|Dependent item|redis.memory.allocator_resident[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_resident`</p></li></ul>|
|Memory used scripts{#SINGLETON}|<p>Memory used by scripts on the Redis server.</p>|Dependent item|redis.memory.used_memory_scripts[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_scripts`</p></li></ul>|
|Memory number of cached scripts{#SINGLETON}|<p>Number of scripts cached in the Redis server.</p>|Dependent item|redis.memory.number_of_cached_scripts[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.number_of_cached_scripts`</p></li></ul>|
|Allocator fragmentation bytes{#SINGLETON}|<p>Memory fragmentation in bytes reported by the Redis server allocator.</p>|Dependent item|redis.memory.allocator_frag_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_frag_bytes`</p></li></ul>|
|Allocator fragmentation ratio{#SINGLETON}|<p>Memory fragmentation ratio of the Redis server allocator.</p>|Dependent item|redis.memory.allocator_frag_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_frag_ratio`</p></li></ul>|
|Allocator RSS bytes{#SINGLETON}|<p>Resident Set Size (RSS) in bytes used by the Redis server allocator.</p>|Dependent item|redis.memory.allocator_rss_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_rss_bytes`</p></li></ul>|
|Allocator RSS ratio{#SINGLETON}|<p>RSS ratio of the Redis server allocator.</p>|Dependent item|redis.memory.allocator_rss_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocator_rss_ratio`</p></li></ul>|
|Memory RSS overhead bytes{#SINGLETON}|<p>Memory RSS overhead in bytes on the Redis server.</p>|Dependent item|redis.memory.rss_overhead_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rss_overhead_bytes`</p></li></ul>|
|Memory RSS overhead ratio{#SINGLETON}|<p>Memory RSS overhead ratio on the Redis server.</p>|Dependent item|redis.memory.rss_overhead_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rss_overhead_ratio`</p></li></ul>|
|Memory fragmentation bytes{#SINGLETON}|<p>Total memory fragmentation in bytes on the Redis server.</p>|Dependent item|redis.memory.fragmentation_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_fragmentation_bytes`</p></li></ul>|
|Memory not counted for evict{#SINGLETON}|<p>Memory not counted for eviction on the Redis server.</p>|Dependent item|redis.memory.not_counted_for_evict[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_not_counted_for_evict`</p></li></ul>|
|Memory replication backlog{#SINGLETON}|<p>Memory used by the Redis server replication backlog.</p>|Dependent item|redis.memory.replication_backlog[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_replication_backlog`</p></li></ul>|
|Memory clients normal{#SINGLETON}|<p>Memory used by normal client connections on the Redis server.</p>|Dependent item|redis.memory.mem_clients_normal[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_clients_normal`</p></li></ul>|
|Memory clients slaves{#SINGLETON}|<p>Memory used by slave client connections on the Redis server.</p>|Dependent item|redis.memory.mem_clients_slaves[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_clients_slaves`</p></li></ul>|
|Memory AOF buffer{#SINGLETON}|<p>Size of the AOF buffer on the Redis server.</p>|Dependent item|redis.memory.mem_aof_buffer[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_aof_buffer`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

