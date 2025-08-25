
# ClickHouse by HTTP

## Overview

This template is designed for the effortless deployment of ClickHouse monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- ClickHouse 20.3+, 21.3+, 22.12+

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a user to monitor the service. For example, you could create a file `/etc/clickhouse-server/users.d/zabbix.xml` with the following content:

```
<yandex>
  <users>
    <zabbix>
      <password>zabbix_pass</password>
      <networks incl="networks" />
      <profile>web</profile>
      <quota>default</quota>
      <allow_databases>
        <database>test</database>
      </allow_databases>
    </zabbix>
  </users>
</yandex>
```

2. Set the hostname or IP address of the ClickHouse HTTP endpoint in the `{$CLICKHOUSE.HOST}` macro. You can also change the port in the `{$CLICKHOUSE.PORT}` macro and scheme in the `{$CLICKHOUSE.SCHEME}` macro if necessary.

3. Set the login and password in the macros `{$CLICKHOUSE.USER}` and `{$CLICKHOUSE.PASSWORD}`. If you don't need an authentication - remove headers from HTTP-Agent type items.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CLICKHOUSE.USER}||`zabbix`|
|{$CLICKHOUSE.PASSWORD}||`zabbix_pass`|
|{$CLICKHOUSE.NETWORK.ERRORS.MAX.WARN}|<p>Maximum number of network errors for trigger expression</p>|`5`|
|{$CLICKHOUSE.HOST}|<p>The hostname or IP address of the ClickHouse HTTP endpoint.</p>||
|{$CLICKHOUSE.PORT}|<p>The port of ClickHouse HTTP endpoint</p>|`8123`|
|{$CLICKHOUSE.SCHEME}|<p>Request scheme which may be http or https</p>|`http`|
|{$CLICKHOUSE.LLD.FILTER.DB.MATCHES}|<p>Filter of discoverable databases</p>|`.*`|
|{$CLICKHOUSE.LLD.FILTER.DB.NOT_MATCHES}|<p>Filter to exclude discovered databases</p>|`CHANGE_IF_NEEDED`|
|{$CLICKHOUSE.LLD.FILTER.DICT.MATCHES}|<p>Filter of discoverable dictionaries</p>|`.*`|
|{$CLICKHOUSE.LLD.FILTER.DICT.NOT_MATCHES}|<p>Filter to exclude discovered dictionaries</p>|`CHANGE_IF_NEEDED`|
|{$CLICKHOUSE.LLD.FILTER.TABLE.MATCHES}|<p>Filter of discoverable tables</p>|`.*`|
|{$CLICKHOUSE.LLD.FILTER.TABLE.NOT_MATCHES}|<p>Filter to exclude discovered tables</p>|`CHANGE_IF_NEEDED`|
|{$CLICKHOUSE.QUERY_TIME.MAX.WARN}|<p>Maximum ClickHouse query time in seconds for trigger expression</p>|`600`|
|{$CLICKHOUSE.QUEUE.SIZE.MAX.WARN}|<p>Maximum size of the queue for operations waiting to be performed for trigger expression.</p>|`20`|
|{$CLICKHOUSE.LOG_POSITION.DIFF.MAX.WARN}|<p>Maximum diff between log_pointer and log_max_index.</p>|`30`|
|{$CLICKHOUSE.REPLICA.MAX.WARN}|<p>Replication lag across all tables for trigger expression.</p>|`600`|
|{$CLICKHOUSE.DELAYED.FILES.DISTRIBUTED.COUNT.MAX.WARN}|<p>Maximum size of distributed files queue to insert for trigger expression.</p>|`600`|
|{$CLICKHOUSE.PARTS.PER.PARTITION.WARN}|<p>Maximum number of parts per partition for trigger expression.</p>|`300`|
|{$CLICKHOUSE.DELAYED.INSERTS.MAX.WARN}|<p>Maximum number of delayed inserts for trigger expression.</p>|`0`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get system.events|<p>Get information about the number of events that have occurred in the system.</p>|HTTP agent|clickhouse.system.events<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|
|Get system.metrics|<p>Get metrics which can be calculated instantly, or have a current value format JSONEachRow</p>|HTTP agent|clickhouse.system.metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|
|Get system.asynchronous_metrics|<p>Get metrics that are calculated periodically in the background</p>|HTTP agent|clickhouse.system.asynchronous_metrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|
|Get system.settings|<p>Get information about settings that are currently in use.</p>|HTTP agent|clickhouse.system.settings<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Longest currently running query time|<p>Get longest running query.</p>|HTTP agent|clickhouse.process.elapsed|
|Check port availability||Simple check|net.tcp.service[{$CLICKHOUSE.SCHEME},"{$CLICKHOUSE.HOST}","{$CLICKHOUSE.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Ping||HTTP agent|clickhouse.ping<p>**Preprocessing**</p><ul><li><p>Regular expression: `Ok\. 1`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Version|<p>Version of the server</p>|HTTP agent|clickhouse.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Revision|<p>Revision of the server.</p>|Dependent item|clickhouse.revision<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "Revision")].value.first()`</p></li></ul>|
|Uptime|<p>Number of seconds since ClickHouse server start</p>|Dependent item|clickhouse.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "Uptime")].value.first()`</p></li></ul>|
|New queries per second|<p>Number of queries to be interpreted and potentially executed. Does not include queries that failed to parse or were rejected due to AST size limits, quota limits or limits on the number of simultaneously running queries. May include internal queries initiated by ClickHouse itself. Does not count subqueries.</p>|Dependent item|clickhouse.query.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.data.event == "Query")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|New SELECT queries per second|<p>Number of SELECT queries to be interpreted and potentially executed. Does not include queries that failed to parse or were rejected due to AST size limits, quota limits or limits on the number of simultaneously running queries. May include internal queries initiated by ClickHouse itself. Does not count subqueries.</p>|Dependent item|clickhouse.select_query.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "SelectQuery")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|New INSERT queries per second|<p>Number of INSERT queries to be interpreted and potentially executed. Does not include queries that failed to parse or were rejected due to AST size limits, quota limits or limits on the number of simultaneously running queries. May include internal queries initiated by ClickHouse itself. Does not count subqueries.</p>|Dependent item|clickhouse.insert_query.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "InsertQuery")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Delayed insert queries|<p>Number of INSERT queries that are throttled due to high number of active data parts for partition in a MergeTree table.</p>|Dependent item|clickhouse.insert.delay<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "DelayedInserts")].value.first()`</p></li></ul>|
|Current running queries|<p>Number of executing queries</p>|Dependent item|clickhouse.query.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "Query")].value.first()`</p></li></ul>|
|Current running merges|<p>Number of executing background merges</p>|Dependent item|clickhouse.merge.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "Merge")].value.first()`</p></li></ul>|
|Inserted bytes per second|<p>The number of uncompressed bytes inserted in all tables.</p>|Dependent item|clickhouse.inserted_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "InsertedBytes")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Read bytes per second|<p>Number of bytes (the number of bytes before decompression) read from compressed sources (files, network).</p>|Dependent item|clickhouse.read_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "ReadCompressedBytes")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Inserted rows per second|<p>The number of rows inserted in all tables.</p>|Dependent item|clickhouse.inserted_rows.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "InsertedRows")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Merged rows per second|<p>Rows read for background merges.</p>|Dependent item|clickhouse.merge_rows.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "MergedRows")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Uncompressed bytes merged per second|<p>Uncompressed bytes that were read for background merges</p>|Dependent item|clickhouse.merge_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "MergedUncompressedBytes")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Max count of parts per partition across all tables|<p>Clickhouse MergeTree table engine split each INSERT query to partitions (PARTITION BY expression) and add one or more PARTS per INSERT inside each partition, after that background merge process run.</p>|Dependent item|clickhouse.max.part.count.for.partition<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "MaxPartCountForPartition")].value.first()`</p></li></ul>|
|Current TCP connections|<p>Number of connections to TCP server (clients with native interface).</p>|Dependent item|clickhouse.connections.tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "TCPConnection")].value.first()`</p></li></ul>|
|Current HTTP connections|<p>Number of connections to HTTP server.</p>|Dependent item|clickhouse.connections.http<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "HTTPConnection")].value.first()`</p></li></ul>|
|Current distribute connections|<p>Number of connections to remote servers sending data that was INSERTed into Distributed tables.</p>|Dependent item|clickhouse.connections.distribute<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "DistributedSend")].value.first()`</p></li></ul>|
|Current MySQL connections|<p>Number of connections to MySQL server.</p>|Dependent item|clickhouse.connections.mysql<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "MySQLConnection")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Current Interserver connections|<p>Number of connections from other replicas to fetch parts.</p>|Dependent item|clickhouse.connections.interserver<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "InterserverConnection")].value.first()`</p></li></ul>|
|Network errors per second|<p>Network errors (timeouts and connection failures) during query execution, background pool tasks and DNS cache update.</p>|Dependent item|clickhouse.network.error.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "NetworkErrors")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|ZooKeeper sessions|<p>Number of sessions (connections) to ZooKeeper. Should be no more than one.</p>|Dependent item|clickhouse.zookeeper.session<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "ZooKeeperSession")].value.first()`</p></li></ul>|
|ZooKeeper watches|<p>Number of watches (e.g., event subscriptions) in ZooKeeper.</p>|Dependent item|clickhouse.zookeeper.watch<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "ZooKeeperWatch")].value.first()`</p></li></ul>|
|ZooKeeper requests|<p>Number of requests to ZooKeeper in progress.</p>|Dependent item|clickhouse.zookeeper.request<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "ZooKeeperRequest")].value.first()`</p></li></ul>|
|ZooKeeper wait time|<p>Time spent in waiting for ZooKeeper operations.</p>|Dependent item|clickhouse.zookeeper.wait.time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "ZooKeeperWaitMicroseconds")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `1.0E-6`</p></li><li>Change per second</li></ul>|
|ZooKeeper exceptions per second|<p>Count of ZooKeeper exceptions that does not belong to user/hardware exceptions.</p>|Dependent item|clickhouse.zookeeper.exceptions.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "ZooKeeperOtherExceptions")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|ZooKeeper hardware exceptions per second|<p>Count of ZooKeeper exceptions caused by session moved/expired, connection loss, marshalling error, operation timed out and invalid zhandle state.</p>|Dependent item|clickhouse.zookeeper.hw_exceptions.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "ZooKeeperHardwareExceptions")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|ZooKeeper user exceptions per second|<p>Count of ZooKeeper exceptions caused by no znodes, bad version, node exists, node empty and no children for ephemeral.</p>|Dependent item|clickhouse.zookeeper.user_exceptions.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.event == "ZooKeeperUserExceptions")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Read syscalls in fly|<p>Number of read (read, pread, io_getevents, etc.) syscalls in fly</p>|Dependent item|clickhouse.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "Read")].value.first()`</p></li></ul>|
|Write syscalls in fly|<p>Number of write (write, pwrite, io_getevents, etc.) syscalls in fly</p>|Dependent item|clickhouse.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "Write")].value.first()`</p></li></ul>|
|Allocated bytes|<p>Total number of bytes allocated by the application.</p>|Dependent item|clickhouse.jemalloc.allocated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "jemalloc.allocated")].value.first()`</p></li></ul>|
|Resident memory|<p>Maximum number of bytes in physically resident data pages mapped by the allocator,</p><p>comprising all pages dedicated to allocator metadata, pages backing active allocations,</p><p>and unused dirty pages.</p>|Dependent item|clickhouse.jemalloc.resident<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "jemalloc.resident")].value.first()`</p></li></ul>|
|Mapped memory|<p>Total number of bytes in active extents mapped by the allocator.</p>|Dependent item|clickhouse.jemalloc.mapped<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "jemalloc.mapped")].value.first()`</p></li></ul>|
|Memory used for queries|<p>Total amount of memory (bytes) allocated in currently executing queries.</p>|Dependent item|clickhouse.memory.tracking<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "MemoryTracking")].value.first()`</p></li></ul>|
|Memory used for background merges|<p>Total amount of memory (bytes) allocated in background processing pool (that is dedicated for background merges, mutations and fetches).</p><p> Note that this value may include a drift when the memory was allocated in a context of background processing pool and freed in other context or vice-versa. This happens naturally due to caches for tables indexes and doesn't indicate memory leaks.</p>|Dependent item|clickhouse.memory.tracking.background<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Memory used for background moves|<p>Total amount of memory (bytes) allocated in background processing pool (that is dedicated for background moves). Note that this value may include a drift when the memory was allocated in a context of background processing pool and freed in other context or vice-versa.</p><p> This happens naturally due to caches for tables indexes and doesn't indicate memory leaks.</p>|Dependent item|clickhouse.memory.tracking.background.moves<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Memory used for background schedule pool|<p>Total amount of memory (bytes) allocated in background schedule pool (that is dedicated for bookkeeping tasks of Replicated tables).</p>|Dependent item|clickhouse.memory.tracking.schedule.pool<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Memory used for merges|<p>Total amount of memory (bytes) allocated for background merges. Included in MemoryTrackingInBackgroundProcessingPool. Note that this value may include a drift when the memory was allocated in a context of background processing pool and freed in other context or vice-versa.</p><p>This happens naturally due to caches for tables indexes and doesn't indicate memory leaks.</p>|Dependent item|clickhouse.memory.tracking.merges<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "MemoryTrackingForMerges")].value.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Current distributed files to insert|<p>Number of pending files to process for asynchronous insertion into Distributed tables. Number of files for every shard is summed.</p>|Dependent item|clickhouse.distributed.files<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "DistributedFilesToInsert")].value.first()`</p></li></ul>|
|Distributed connection fail with retry per second|<p>Connection retries in replicated DB connection pool</p>|Dependent item|clickhouse.distributed.files.retry.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Distributed connection fail with retry per second|<p>Connection failures after all retries in replicated DB connection pool</p>|Dependent item|clickhouse.distributed.files.fail.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Replication lag across all tables|<p>Maximum replica queue delay relative to current time</p>|Dependent item|clickhouse.replicas.max.absolute.delay<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "ReplicasMaxAbsoluteDelay")].value.first()`</p></li></ul>|
|Total replication tasks in queue|<p>Number of replication tasks in queue</p>|Dependent item|clickhouse.replicas.sum.queue.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "ReplicasSumQueueSize")].value.first()`</p></li></ul>|
|Total number read-only Replicas|<p>Number of Replicated tables that are currently in readonly state due to re-initialization after ZooKeeper session loss or due to startup without ZooKeeper configured.</p>|Dependent item|clickhouse.replicas.readonly.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "ReadonlyReplica")].value.first()`</p></li></ul>|
|Get replicas info|<p>Get information about replicas.</p>|HTTP agent|clickhouse.replicas<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|
|Get databases info|<p>Get information about databases.</p>|HTTP agent|clickhouse.databases<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|
|Get tables info|<p>Get information about tables.</p>|HTTP agent|clickhouse.tables<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|
|Get dictionaries info|<p>Get information about dictionaries.</p>|HTTP agent|clickhouse.dictionaries<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ClickHouse: Configuration has been changed|<p>ClickHouse configuration has been changed. Acknowledge to close the problem manually.</p>|`last(/ClickHouse by HTTP/clickhouse.system.settings,#1)<>last(/ClickHouse by HTTP/clickhouse.system.settings,#2) and length(last(/ClickHouse by HTTP/clickhouse.system.settings))>0`|Info|**Manual close**: Yes|
|ClickHouse: There are queries running is long||`last(/ClickHouse by HTTP/clickhouse.process.elapsed)>{$CLICKHOUSE.QUERY_TIME.MAX.WARN}`|Average|**Manual close**: Yes|
|ClickHouse: Port {$CLICKHOUSE.PORT} is unavailable||`last(/ClickHouse by HTTP/net.tcp.service[{$CLICKHOUSE.SCHEME},"{$CLICKHOUSE.HOST}","{$CLICKHOUSE.PORT}"])=0`|Average|**Manual close**: Yes|
|ClickHouse: Service is down||`last(/ClickHouse by HTTP/clickhouse.ping)=0 or last(/ClickHouse by HTTP/net.tcp.service[{$CLICKHOUSE.SCHEME},"{$CLICKHOUSE.HOST}","{$CLICKHOUSE.PORT}"]) = 0`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>ClickHouse: Port {$CLICKHOUSE.PORT} is unavailable</li></ul>|
|ClickHouse: Version has changed|<p>The ClickHouse version has changed. Acknowledge to close the problem manually.</p>|`last(/ClickHouse by HTTP/clickhouse.version,#1)<>last(/ClickHouse by HTTP/clickhouse.version,#2) and length(last(/ClickHouse by HTTP/clickhouse.version))>0`|Info|**Manual close**: Yes|
|ClickHouse: Host has been restarted|<p>The host uptime is less than 10 minutes.</p>|`last(/ClickHouse by HTTP/clickhouse.uptime)<10m`|Info|**Manual close**: Yes|
|ClickHouse: Failed to fetch info data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/ClickHouse by HTTP/clickhouse.uptime,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>ClickHouse: Service is down</li></ul>|
|ClickHouse: Too many throttled insert queries|<p>Clickhouse have INSERT queries that are throttled due to high number of active data parts for partition in a MergeTree, please decrease INSERT frequency</p>|`min(/ClickHouse by HTTP/clickhouse.insert.delay,5m)>{$CLICKHOUSE.DELAYED.INSERTS.MAX.WARN}`|Warning|**Manual close**: Yes|
|ClickHouse: Too many MergeTree parts|<p>Descease INSERT queries frequency.<br>Clickhouse MergeTree table engine split each INSERT query to partitions (PARTITION BY expression)<br>and add one or more PARTS per INSERT inside each partition,<br>after that background merge process run, and when you have too much unmerged parts inside partition,<br>SELECT queries performance can significate degrade, so clickhouse try delay insert, or abort it.</p>|`min(/ClickHouse by HTTP/clickhouse.max.part.count.for.partition,5m)>{$CLICKHOUSE.PARTS.PER.PARTITION.WARN} * 0.9`|Warning|**Manual close**: Yes|
|ClickHouse: Too many network errors|<p>Number of errors (timeouts and connection failures) during query execution, background pool tasks and DNS cache update is too high.</p>|`min(/ClickHouse by HTTP/clickhouse.network.error.rate,5m)>{$CLICKHOUSE.NETWORK.ERRORS.MAX.WARN}`|Warning||
|ClickHouse: Too many ZooKeeper sessions opened|<p>Number of sessions (connections) to ZooKeeper.<br>Should be no more than one, because using more than one connection to ZooKeeper may lead to bugs due to lack of linearizability (stale reads) that ZooKeeper consistency model allows.</p>|`min(/ClickHouse by HTTP/clickhouse.zookeeper.session,5m)>1`|Warning||
|ClickHouse: Too many distributed files to insert|<p>Clickhouse servers and <remote_servers> in config.xml (https://clickhouse.tech/docs/en/operations/table_engines/distributed/)</p>|`min(/ClickHouse by HTTP/clickhouse.distributed.files,5m)>{$CLICKHOUSE.DELAYED.FILES.DISTRIBUTED.COUNT.MAX.WARN}`|Warning|**Manual close**: Yes|
|ClickHouse: Replication lag is too high|<p>When replica have too much lag, it can be skipped from Distributed SELECT Queries without errors<br>and you will have wrong query results.</p>|`min(/ClickHouse by HTTP/clickhouse.replicas.max.absolute.delay,5m)>{$CLICKHOUSE.REPLICA.MAX.WARN}`|Warning|**Manual close**: Yes|

### LLD rule Tables

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tables|<p>Info about tables</p>|Dependent item|clickhouse.tables.discovery|

### Item prototypes for Tables

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DB}.{#TABLE}: Get table info|<p>The item gets information about {#TABLE} table of {#DB} database.</p>|Dependent item|clickhouse.table.info_raw["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#DB}.{#TABLE}: Bytes|<p>Table size in bytes. Database: {#DB}, table: {#TABLE}</p>|Dependent item|clickhouse.table.bytes["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes`</p></li></ul>|
|{#DB}.{#TABLE}: Parts|<p>Number of parts of the table. Database: {#DB}, table: {#TABLE}</p>|Dependent item|clickhouse.table.parts["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.parts`</p></li></ul>|
|{#DB}.{#TABLE}: Rows|<p>Number of rows in the table. Database: {#DB}, table: {#TABLE}</p>|Dependent item|clickhouse.table.rows["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rows`</p></li></ul>|

### LLD rule Replicas

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replicas|<p>Info about replicas</p>|Dependent item|clickhouse.replicas.discovery|

### Item prototypes for Replicas

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DB}.{#TABLE}: Get replicas info|<p>The item gets information about replicas of {#TABLE} table of {#DB} database.</p>|Dependent item|clickhouse.replica.info_raw["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#DB}.{#TABLE}: Replica readonly|<p>Whether the replica is in read-only mode.</p><p>This mode is turned on if the config doesn't have sections with ZooKeeper, if an unknown error occurred when re-initializing sessions in ZooKeeper, and during session re-initialization in ZooKeeper.</p>|Dependent item|clickhouse.replica.is_readonly["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_readonly`</p></li></ul>|
|{#DB}.{#TABLE}: Replica session expired|<p>True if the ZooKeeper session expired</p>|Dependent item|clickhouse.replica.is_session_expired["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_session_expired`</p></li></ul>|
|{#DB}.{#TABLE}: Replica future parts|<p>Number of data parts that will appear as the result of INSERTs or merges that haven't been done yet.</p>|Dependent item|clickhouse.replica.future_parts["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.future_parts`</p></li></ul>|
|{#DB}.{#TABLE}: Replica parts to check|<p>Number of data parts in the queue for verification. A part is put in the verification queue if there is suspicion that it might be damaged.</p>|Dependent item|clickhouse.replica.parts_to_check["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.parts_to_check`</p></li></ul>|
|{#DB}.{#TABLE}: Replica queue size|<p>Size of the queue for operations waiting to be performed.</p>|Dependent item|clickhouse.replica.queue_size["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue_size`</p></li></ul>|
|{#DB}.{#TABLE}: Replica queue inserts size|<p>Number of inserts of blocks of data that need to be made.</p>|Dependent item|clickhouse.replica.inserts_in_queue["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.inserts_in_queue`</p></li></ul>|
|{#DB}.{#TABLE}: Replica queue merges size|<p>Number of merges waiting to be made.</p>|Dependent item|clickhouse.replica.merges_in_queue["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.merges_in_queue`</p></li></ul>|
|{#DB}.{#TABLE}: Replica log max index|<p>Maximum entry number in the log of general activity. (Have a non-zero value only where there is an active session with ZooKeeper).</p>|Dependent item|clickhouse.replica.log_max_index["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.log_max_index`</p></li></ul>|
|{#DB}.{#TABLE}: Replica log pointer|<p>Maximum entry number in the log of general activity that the replica copied to its execution queue, plus one. (Have a non-zero value only where there is an active session with ZooKeeper).</p>|Dependent item|clickhouse.replica.log_pointer["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.log_pointer`</p></li></ul>|
|{#DB}.{#TABLE}: Total replicas|<p>Total number of known replicas of this table. (Have a non-zero value only where there is an active session with ZooKeeper).</p>|Dependent item|clickhouse.replica.total_replicas["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_replicas`</p></li></ul>|
|{#DB}.{#TABLE}: Active replicas|<p>Number of replicas of this table that have a session in ZooKeeper (i.e., the number of functioning replicas). (Have a non-zero value only where there is an active session with ZooKeeper).</p>|Dependent item|clickhouse.replica.active_replicas["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_replicas`</p></li></ul>|
|{#DB}.{#TABLE}: Replica lag|<p>Difference between log_max_index and log_pointer</p>|Dependent item|clickhouse.replica.lag["{#DB}.{#TABLE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replica_lag`</p></li></ul>|

### Trigger prototypes for Replicas

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ClickHouse: {#DB}.{#TABLE} Replica is readonly|<p>This mode is turned on if the config doesn't have sections with ZooKeeper, if an unknown error occurred when re-initializing sessions in ZooKeeper, and during session re-initialization in ZooKeeper.</p>|`min(/ClickHouse by HTTP/clickhouse.replica.is_readonly["{#DB}.{#TABLE}"],5m)=1`|Warning||
|ClickHouse: {#DB}.{#TABLE} Replica session is expired|<p>This mode is turned on if the config doesn't have sections with ZooKeeper, if an unknown error occurred when re-initializing sessions in ZooKeeper, and during session re-initialization in ZooKeeper.</p>|`min(/ClickHouse by HTTP/clickhouse.replica.is_session_expired["{#DB}.{#TABLE}"],5m)=1`|Warning||
|ClickHouse: {#DB}.{#TABLE}: Too many operations in queue||`min(/ClickHouse by HTTP/clickhouse.replica.queue_size["{#DB}.{#TABLE}"],5m)>{$CLICKHOUSE.QUEUE.SIZE.MAX.WARN:"{#TABLE}"}`|Warning||
|ClickHouse: {#DB}.{#TABLE}: Number of active replicas less than number of total replicas||`max(/ClickHouse by HTTP/clickhouse.replica.active_replicas["{#DB}.{#TABLE}"],5m) < last(/ClickHouse by HTTP/clickhouse.replica.total_replicas["{#DB}.{#TABLE}"])`|Warning||
|ClickHouse: {#DB}.{#TABLE}: Difference between log_max_index and log_pointer is too high||`min(/ClickHouse by HTTP/clickhouse.replica.lag["{#DB}.{#TABLE}"],5m) > {$CLICKHOUSE.LOG_POSITION.DIFF.MAX.WARN}`|Warning||

### LLD rule Dictionaries

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dictionaries|<p>Info about dictionaries</p>|Dependent item|clickhouse.dictionaries.discovery|

### Item prototypes for Dictionaries

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dictionary {#NAME}: Get dictionary info|<p>The item gets information about {#NAME} dictionary.</p>|Dependent item|clickhouse.dictionary.info_raw["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Dictionary {#NAME}: Bytes allocated|<p>The amount of RAM the dictionary uses.</p>|Dependent item|clickhouse.dictionary.bytes_allocated["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes_allocated`</p></li></ul>|
|Dictionary {#NAME}: Element count|<p>Number of items stored in the dictionary.</p>|Dependent item|clickhouse.dictionary.element_count["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.element_count`</p></li></ul>|
|Dictionary {#NAME}: Load factor|<p>The percentage filled in the dictionary (for a hashed dictionary, the percentage filled in the hash table).</p>|Dependent item|clickhouse.dictionary.load_factor["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes_allocated`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|

### LLD rule Databases

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Databases|<p>Info about databases</p>|Dependent item|clickhouse.db.discovery|

### Item prototypes for Databases

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DB}: Get DB info|<p>The item gets information about {#DB} database.</p>|Dependent item|clickhouse.db.info_raw["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.database == "{#DB}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#DB}: Bytes|<p>Database size in bytes.</p>|Dependent item|clickhouse.db.bytes["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes`</p></li></ul>|
|{#DB}: Tables|<p>Number of tables in {#DB} database.</p>|Dependent item|clickhouse.db.tables["{#DB}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tables`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

