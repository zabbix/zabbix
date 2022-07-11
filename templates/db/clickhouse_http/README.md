
# ClickHouse by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor ClickHouse by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.



This template was tested on:

- ClickHouse, version 19.14+, 20.3+

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Create a user to monitor the service:

```
create file /etc/clickhouse-server/users.d/zabbix.xml
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

Login and password are also set in macros:

- {$CLICKHOUSE.USER}
- {$CLICKHOUSE.PASSWORD}
If you don't need authentication - remove headers from HTTP-Agent type items


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CLICKHOUSE.DELAYED.FILES.DISTRIBUTED.COUNT.MAX.WARN} |<p>Maximum size of distributed files queue to insert for trigger expression.</p> |`600` |
|{$CLICKHOUSE.DELAYED.INSERTS.MAX.WARN} |<p>Maximum number of delayed inserts for trigger expression.</p> |`0` |
|{$CLICKHOUSE.LLD.FILTER.DB.MATCHES} |<p>Filter of discoverable databases</p> |`.*` |
|{$CLICKHOUSE.LLD.FILTER.DB.NOT_MATCHES} |<p>Filter to exclude discovered databases</p> |`CHANGE_IF_NEEDED` |
|{$CLICKHOUSE.LLD.FILTER.DICT.MATCHES} |<p>Filter of discoverable dictionaries</p> |`.*` |
|{$CLICKHOUSE.LLD.FILTER.DICT.NOT_MATCHES} |<p>Filter to exclude discovered dictionaries</p> |`CHANGE_IF_NEEDED` |
|{$CLICKHOUSE.LOG_POSITION.DIFF.MAX.WARN} |<p>Maximum diff between log_pointer and log_max_index.</p> |`30` |
|{$CLICKHOUSE.NETWORK.ERRORS.MAX.WARN} |<p>Maximum number of smth for trigger expression</p> |`5` |
|{$CLICKHOUSE.PARTS.PER.PARTITION.WARN} |<p>Maximum number of parts per partition for trigger expression.</p> |`300` |
|{$CLICKHOUSE.PASSWORD} |<p>-</p> |`zabbix_pass` |
|{$CLICKHOUSE.PORT} |<p>The port of ClickHouse HTTP endpoint</p> |`8123` |
|{$CLICKHOUSE.QUERY_TIME.MAX.WARN} |<p>Maximum ClickHouse query time in seconds for trigger expression</p> |`600` |
|{$CLICKHOUSE.QUEUE.SIZE.MAX.WARN} |<p>Maximum size of the queue for operations waiting to be performed for trigger expression.</p> |`20` |
|{$CLICKHOUSE.REPLICA.MAX.WARN} |<p>Replication lag across all tables for trigger expression.</p> |`600` |
|{$CLICKHOUSE.SCHEME} |<p>Request scheme which may be http or https</p> |`http` |
|{$CLICKHOUSE.USER} |<p>-</p> |`zabbix` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Dictionaries |<p>Info about dictionaries</p> |DEPENDENT |clickhouse.dictionaries.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$CLICKHOUSE.LLD.FILTER.DICT.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$CLICKHOUSE.LLD.FILTER.DICT.NOT_MATCHES}`</p> |
|Replicas |<p>Info about replicas</p> |DEPENDENT |clickhouse.replicas.discovery<p>**Filter**:</p>AND <p>- {#DB} MATCHES_REGEX `{$CLICKHOUSE.LLD.FILTER.DB.MATCHES}`</p><p>- {#DB} NOT_MATCHES_REGEX `{$CLICKHOUSE.LLD.FILTER.DB.NOT_MATCHES}`</p> |
|Tables |<p>Info about tables</p> |DEPENDENT |clickhouse.tables.discovery<p>**Filter**:</p>AND <p>- {#DB} MATCHES_REGEX `{$CLICKHOUSE.LLD.FILTER.DB.MATCHES}`</p><p>- {#DB} NOT_MATCHES_REGEX `{$CLICKHOUSE.LLD.FILTER.DB.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|ClickHouse |ClickHouse: Longest currently running query time |<p>Get longest running query.</p> |HTTP_AGENT |clickhouse.process.elapsed |
|ClickHouse |ClickHouse: Check port availability |<p>-</p> |SIMPLE |net.tcp.service[{$CLICKHOUSE.SCHEME},"{HOST.CONN}","{$CLICKHOUSE.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|ClickHouse |ClickHouse: Ping | |HTTP_AGENT |clickhouse.ping<p>**Preprocessing**:</p><p>- REGEX: `Ok\. 1`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|ClickHouse |ClickHouse: Version |<p>Version of the server</p> |HTTP_AGENT |clickhouse.version<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|ClickHouse |ClickHouse: Revision |<p>Revision of the server.</p> |DEPENDENT |clickhouse.revision<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "Revision")].value.first()`</p> |
|ClickHouse |ClickHouse: Uptime |<p>Number of seconds since ClickHouse server start</p> |DEPENDENT |clickhouse.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "Uptime")].value.first()`</p> |
|ClickHouse |ClickHouse: New queries per second |<p>Number of queries to be interpreted and potentially executed. Does not include queries that failed to parse or were rejected due to AST size limits, quota limits or limits on the number of simultaneously running queries. May include internal queries initiated by ClickHouse itself. Does not count subqueries.</p> |DEPENDENT |clickhouse.query.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.data.event == "Query")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: New SELECT queries per second |<p>Number of SELECT queries to be interpreted and potentially executed. Does not include queries that failed to parse or were rejected due to AST size limits, quota limits or limits on the number of simultaneously running queries. May include internal queries initiated by ClickHouse itself. Does not count subqueries.</p> |DEPENDENT |clickhouse.select_query.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "SelectQuery")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: New INSERT queries per second |<p>Number of INSERT queries to be interpreted and potentially executed. Does not include queries that failed to parse or were rejected due to AST size limits, quota limits or limits on the number of simultaneously running queries. May include internal queries initiated by ClickHouse itself. Does not count subqueries.</p> |DEPENDENT |clickhouse.insert_query.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "InsertQuery")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: Delayed insert queries |<p>"Number of INSERT queries that are throttled due to high number of active data parts for partition in a MergeTree table."</p> |DEPENDENT |clickhouse.insert.delay<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "DelayedInserts")].value.first()`</p> |
|ClickHouse |ClickHouse: Current running queries |<p>Number of executing queries</p> |DEPENDENT |clickhouse.query.current<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "Query")].value.first()`</p> |
|ClickHouse |ClickHouse: Current running merges |<p>Number of executing background merges</p> |DEPENDENT |clickhouse.merge.current<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "Merge")].value.first()`</p> |
|ClickHouse |ClickHouse: Inserted bytes per second |<p>The number of uncompressed bytes inserted in all tables.</p> |DEPENDENT |clickhouse.inserted_bytes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "InsertedBytes")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: Read bytes per second |<p>"Number of bytes (the number of bytes before decompression) read from compressed sources (files, network)."</p> |DEPENDENT |clickhouse.read_bytes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "ReadCompressedBytes")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: Inserted rows per second |<p>The number of rows inserted in all tables.</p> |DEPENDENT |clickhouse.inserted_rows.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "InsertedRows")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: Merged rows per second |<p>Rows read for background merges.</p> |DEPENDENT |clickhouse.merge_rows.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "MergedRows")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: Uncompressed bytes merged per second |<p>Uncompressed bytes that were read for background merges</p> |DEPENDENT |clickhouse.merge_bytes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "MergedUncompressedBytes")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: Max count of parts per partition across all tables |<p>Clickhouse MergeTree table engine split each INSERT query to partitions (PARTITION BY expression) and add one or more PARTS per INSERT inside each partition,</p><p>after that background merge process run.</p> |DEPENDENT |clickhouse.max.part.count.for.partition<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "MaxPartCountForPartition")].value.first()`</p> |
|ClickHouse |ClickHouse: Current TCP connections |<p>Number of connections to TCP server (clients with native interface).</p> |DEPENDENT |clickhouse.connections.tcp<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "TCPConnection")].value.first()`</p> |
|ClickHouse |ClickHouse: Current HTTP connections |<p>Number of connections to HTTP server.</p> |DEPENDENT |clickhouse.connections.http<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "HTTPConnection")].value.first()`</p> |
|ClickHouse |ClickHouse: Current distribute connections |<p>Number of connections to remote servers sending data that was INSERTed into Distributed tables.</p> |DEPENDENT |clickhouse.connections.distribute<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "DistributedSend")].value.first()`</p> |
|ClickHouse |ClickHouse: Current MySQL connections |<p>Number of connections to MySQL server.</p> |DEPENDENT |clickhouse.connections.mysql<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "MySQLConnection")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|ClickHouse |ClickHouse: Current Interserver connections |<p>Number of connections from other replicas to fetch parts.</p> |DEPENDENT |clickhouse.connections.interserver<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "InterserverConnection")].value.first()`</p> |
|ClickHouse |ClickHouse: Network errors per second |<p>Network errors (timeouts and connection failures) during query execution, background pool tasks and DNS cache update.</p> |DEPENDENT |clickhouse.network.error.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "NetworkErrors")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: Read syscalls in fly |<p>Number of read (read, pread, io_getevents, etc.) syscalls in fly</p> |DEPENDENT |clickhouse.read<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "Read")].value.first()`</p> |
|ClickHouse |ClickHouse: Write syscalls in fly |<p>Number of write (write, pwrite, io_getevents, etc.) syscalls in fly</p> |DEPENDENT |clickhouse.write<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "Write")].value.first()`</p> |
|ClickHouse |ClickHouse: Allocated bytes |<p>"Total number of bytes allocated by the application."</p> |DEPENDENT |clickhouse.jemalloc.allocated<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "jemalloc.allocated")].value.first()`</p> |
|ClickHouse |ClickHouse: Resident memory |<p>Maximum number of bytes in physically resident data pages mapped by the allocator,</p><p>comprising all pages dedicated to allocator metadata, pages backing active allocations,</p><p>and unused dirty pages.</p> |DEPENDENT |clickhouse.jemalloc.resident<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "jemalloc.resident")].value.first()`</p> |
|ClickHouse |ClickHouse: Mapped memory |<p>"Total number of bytes in active extents mapped by the allocator."</p> |DEPENDENT |clickhouse.jemalloc.mapped<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "jemalloc.mapped")].value.first()`</p> |
|ClickHouse |ClickHouse: Memory used for queries |<p>"Total amount of memory (bytes) allocated in currently executing queries."</p> |DEPENDENT |clickhouse.memory.tracking<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "MemoryTracking")].value.first()`</p> |
|ClickHouse |ClickHouse: Memory used for background merges |<p>"Total amount of memory (bytes) allocated in background processing pool (that is dedicated for background merges, mutations and fetches).</p><p> Note that this value may include a drift when the memory was allocated in a context of background processing pool and freed in other context or vice-versa. This happens naturally due to caches for tables indexes and doesn't indicate memory leaks."</p> |DEPENDENT |clickhouse.memory.tracking.background<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "MemoryTrackingInBackgroundProcessingPool")].value.first()`</p> |
|ClickHouse |ClickHouse: Memory used for background moves |<p>"Total amount of memory (bytes) allocated in background processing pool (that is dedicated for background moves). Note that this value may include a drift when the memory was allocated in a context of background processing pool and freed in other context or vice-versa.</p><p> This happens naturally due to caches for tables indexes and doesn't indicate memory leaks."</p> |DEPENDENT |clickhouse.memory.tracking.background.moves<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "MemoryTrackingInBackgroundMoveProcessingPool")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|ClickHouse |ClickHouse: Memory used for background schedule pool |<p>"Total amount of memory (bytes) allocated in background schedule pool (that is dedicated for bookkeeping tasks of Replicated tables)."</p> |DEPENDENT |clickhouse.memory.tracking.schedule.pool<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "MemoryTrackingInBackgroundSchedulePool")].value.first()`</p> |
|ClickHouse |ClickHouse: Memory used for merges |<p>Total amount of memory (bytes) allocated for background merges. Included in MemoryTrackingInBackgroundProcessingPool. Note that this value may include a drift when the memory was allocated in a context of background processing pool and freed in other context or vice-versa.</p><p>This happens naturally due to caches for tables indexes and doesn't indicate memory leaks.</p> |DEPENDENT |clickhouse.memory.tracking.merges<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "MemoryTrackingForMerges")].value.first()`</p> |
|ClickHouse |ClickHouse: Current distributed files to insert |<p>Number of pending files to process for asynchronous insertion into Distributed tables. Number of files for every shard is summed.</p> |DEPENDENT |clickhouse.distributed.files<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "DistributedFilesToInsert")].value.first()`</p> |
|ClickHouse |ClickHouse: Distributed connection fail with retry per second |<p>Connection retries in replicated DB connection pool</p> |DEPENDENT |clickhouse.distributed.files.retry.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "DistributedConnectionFailTry")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: Distributed connection fail with retry per second |<p>"Connection failures after all retries in replicated DB connection pool"</p> |DEPENDENT |clickhouse.distributed.files.fail.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "DistributedConnectionFailAtAll")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse |ClickHouse: Replication lag across all tables |<p>Maximum replica queue delay relative to current time</p> |DEPENDENT |clickhouse.replicas.max.absolute.delay<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "ReplicasMaxAbsoluteDelay")].value.first()`</p> |
|ClickHouse |ClickHouse: Total replication tasks in queue | |DEPENDENT |clickhouse.replicas.sum.queue.size<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "ReplicasSumQueueSize")].value.first()`</p> |
|ClickHouse |ClickHouse: Total number read-only Replicas |<p>Number of Replicated tables that are currently in readonly state</p><p>due to re-initialization after ZooKeeper session loss</p><p>or due to startup without ZooKeeper configured.</p> |DEPENDENT |clickhouse.replicas.readonly.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "ReadonlyReplica")].value.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Bytes |<p>Table size in bytes. Database: {#DB}, table: {#TABLE}</p> |DEPENDENT |clickhouse.table.bytes["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].bytes.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Parts |<p>Number of parts of the table. Database: {#DB}, table: {#TABLE}</p> |DEPENDENT |clickhouse.table.parts["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].parts.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Rows |<p>Number of rows in the table. Database: {#DB}, table: {#TABLE}</p> |DEPENDENT |clickhouse.table.rows["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].rows.first()`</p> |
|ClickHouse |ClickHouse: {#DB}: Bytes |<p>Database size in bytes.</p> |DEPENDENT |clickhouse.db.bytes["{#DB}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}")].bytes.sum()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica readonly |<p>Whether the replica is in read-only mode.</p><p>This mode is turned on if the config doesn't have sections with ZooKeeper, if an unknown error occurred when re-initializing sessions in ZooKeeper, and during session re-initialization in ZooKeeper.</p> |DEPENDENT |clickhouse.replica.is_readonly["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].is_readonly.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica session expired |<p>True if the ZooKeeper session expired</p> |DEPENDENT |clickhouse.replica.is_session_expired["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].is_session_expired.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica future parts |<p>Number of data parts that will appear as the result of INSERTs or merges that haven't been done yet.</p> |DEPENDENT |clickhouse.replica.future_parts["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].future_parts.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica parts to check |<p>Number of data parts in the queue for verification. A part is put in the verification queue if there is suspicion that it might be damaged.</p> |DEPENDENT |clickhouse.replica.parts_to_check["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].parts_to_check.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica queue size |<p>Size of the queue for operations waiting to be performed.</p> |DEPENDENT |clickhouse.replica.queue_size["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].queue_size.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica queue inserts size |<p>Number of inserts of blocks of data that need to be made.</p> |DEPENDENT |clickhouse.replica.inserts_in_queue["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].inserts_in_queue.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica queue merges size |<p>Number of merges waiting to be made. </p> |DEPENDENT |clickhouse.replica.merges_in_queue["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].merges_in_queue.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica log max index |<p>Maximum entry number in the log of general activity. (Have a non-zero value only where there is an active session with ZooKeeper).</p> |DEPENDENT |clickhouse.replica.log_max_index["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].log_max_index.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica log pointer |<p> Maximum entry number in the log of general activity that the replica copied to its execution queue, plus one. (Have a non-zero value only where there is an active session with ZooKeeper).</p> |DEPENDENT |clickhouse.replica.log_pointer["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].log_pointer.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Total replicas |<p>Total number of known replicas of this table. (Have a non-zero value only where there is an active session with ZooKeeper).</p> |DEPENDENT |clickhouse.replica.total_replicas["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].total_replicas.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Active replicas |<p>Number of replicas of this table that have a session in ZooKeeper (i.e., the number of functioning replicas). (Have a non-zero value only where there is an active session with ZooKeeper).</p> |DEPENDENT |clickhouse.replica.active_replicas["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].active_replicas.first()`</p> |
|ClickHouse |ClickHouse: {#DB}.{#TABLE}: Replica lag |<p>Difference between log_max_index and log_pointer</p> |DEPENDENT |clickhouse.replica.lag["{#DB}.{#TABLE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.database == "{#DB}" && @.table == "{#TABLE}")].replica_lag.first()`</p> |
|ClickHouse |ClickHouse: Dictionary {#NAME}: Bytes allocated |<p>The amount of RAM the dictionary uses.</p> |DEPENDENT |clickhouse.dictionary.bytes_allocated["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "{#NAME}")].bytes_allocated.first()`</p> |
|ClickHouse |ClickHouse: Dictionary {#NAME}: Element count |<p>Number of items stored in the dictionary.</p> |DEPENDENT |clickhouse.dictionary.element_count["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "{#NAME}")].element_count.first()`</p> |
|ClickHouse |ClickHouse: Dictionary {#NAME}: Load factor |<p>The percentage filled in the dictionary (for a hashed dictionary, the percentage filled in the hash table).</p> |DEPENDENT |clickhouse.dictionary.load_factor["{#NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "{#NAME}")].bytes_allocated.first()`</p><p>- MULTIPLIER: `100`</p> |
|ClickHouse ZooKeeper |ClickHouse: ZooKeeper sessions |<p>Number of sessions (connections) to ZooKeeper. Should be no more than one.</p> |DEPENDENT |clickhouse.zookeper.session<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "ZooKeeperSession")].value.first()`</p> |
|ClickHouse ZooKeeper |ClickHouse: ZooKeeper watches |<p>Number of watches (e.g., event subscriptions) in ZooKeeper.</p> |DEPENDENT |clickhouse.zookeper.watch<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "ZooKeeperWatch")].value.first()`</p> |
|ClickHouse ZooKeeper |ClickHouse: ZooKeeper requests |<p>Number of requests to ZooKeeper in progress.</p> |DEPENDENT |clickhouse.zookeper.request<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.metric == "ZooKeeperRequest")].value.first()`</p> |
|ClickHouse ZooKeeper |ClickHouse: ZooKeeper wait time |<p>Time spent in waiting for ZooKeeper operations.</p> |DEPENDENT |clickhouse.zookeper.wait.time<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "ZooKeeperWaitMicroseconds")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.000001`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse ZooKeeper |ClickHouse: ZooKeeper exceptions per second |<p>Count of ZooKeeper exceptions that does not belong to user/hardware exceptions.</p> |DEPENDENT |clickhouse.zookeper.exceptions.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "ZooKeeperOtherExceptions")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse ZooKeeper |ClickHouse: ZooKeeper hardware exceptions per second |<p>Count of ZooKeeper exceptions caused by session moved/expired, connection loss, marshalling error, operation timed out and invalid zhandle state.</p> |DEPENDENT |clickhouse.zookeper.hw_exceptions.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "ZooKeeperHardwareExceptions")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|ClickHouse ZooKeeper |ClickHouse: ZooKeeper user exceptions per second |<p>Count of ZooKeeper exceptions caused by no znodes, bad version, node exists, node empty and no children for ephemeral.</p> |DEPENDENT |clickhouse.zookeper.user_exceptions.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.event == "ZooKeeperUserExceptions")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix raw items |ClickHouse: Get system.events |<p>Get information about the number of events that have occurred in the system.</p> |HTTP_AGENT |clickhouse.system.events<p>**Preprocessing**:</p><p>- JSONPATH: `$.data`</p> |
|Zabbix raw items |ClickHouse: Get system.metrics |<p>Get metrics which can be calculated instantly, or have a current value format JSONEachRow</p> |HTTP_AGENT |clickhouse.system.metrics<p>**Preprocessing**:</p><p>- JSONPATH: `$.data`</p> |
|Zabbix raw items |ClickHouse: Get system.asynchronous_metrics |<p>Get metrics that are calculated periodically in the background</p> |HTTP_AGENT |clickhouse.system.asynchronous_metrics<p>**Preprocessing**:</p><p>- JSONPATH: `$.data`</p> |
|Zabbix raw items |ClickHouse: Get system.settings |<p>Get information about settings that are currently in use.</p> |HTTP_AGENT |clickhouse.system.settings<p>**Preprocessing**:</p><p>- JSONPATH: `$.data`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |ClickHouse: Get replicas info |<p>-</p> |HTTP_AGENT |clickhouse.replicas<p>**Preprocessing**:</p><p>- JSONPATH: `$.data`</p> |
|Zabbix raw items |ClickHouse: Get tables info |<p>-</p> |HTTP_AGENT |clickhouse.tables<p>**Preprocessing**:</p><p>- JSONPATH: `$.data`</p> |
|Zabbix raw items |ClickHouse: Get dictionaries info |<p>-</p> |HTTP_AGENT |clickhouse.dictionaries<p>**Preprocessing**:</p><p>- JSONPATH: `$.data`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|ClickHouse: There are queries running is long |<p>-</p> |`last(/ClickHouse by HTTP/clickhouse.process.elapsed)>{$CLICKHOUSE.QUERY_TIME.MAX.WARN}` |AVERAGE |<p>Manual close: YES</p> |
|ClickHouse: Port {$CLICKHOUSE.PORT} is unavailable |<p>-</p> |`last(/ClickHouse by HTTP/net.tcp.service[{$CLICKHOUSE.SCHEME},"{HOST.CONN}","{$CLICKHOUSE.PORT}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|ClickHouse: Service is down |<p>-</p> |`last(/ClickHouse by HTTP/clickhouse.ping)=0 or last(/ClickHouse by HTTP/net.tcp.service[{$CLICKHOUSE.SCHEME},"{HOST.CONN}","{$CLICKHOUSE.PORT}"]) = 0` |AVERAGE |<p>Manual close: YES</p><p>**Depends on**:</p><p>- ClickHouse: Port {$CLICKHOUSE.PORT} is unavailable</p> |
|ClickHouse: Version has changed |<p>ClickHouse version has changed. Ack to close.</p> |`last(/ClickHouse by HTTP/clickhouse.version,#1)<>last(/ClickHouse by HTTP/clickhouse.version,#2) and length(last(/ClickHouse by HTTP/clickhouse.version))>0` |INFO |<p>Manual close: YES</p> |
|ClickHouse: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/ClickHouse by HTTP/clickhouse.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|ClickHouse: Failed to fetch info data |<p>Zabbix has not received data for items for the last 30 minutes</p> |`nodata(/ClickHouse by HTTP/clickhouse.uptime,30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- ClickHouse: Service is down</p> |
|ClickHouse: Too many throttled insert queries |<p>Clickhouse have INSERT queries that are throttled due to high number of active data parts for partition in a MergeTree, please decrease INSERT frequency</p> |`min(/ClickHouse by HTTP/clickhouse.insert.delay,5m)>{$CLICKHOUSE.DELAYED.INSERTS.MAX.WARN}` |WARNING |<p>Manual close: YES</p> |
|ClickHouse: Too many MergeTree parts |<p>Descease INSERT queries frequency.</p><p>Clickhouse MergeTree table engine split each INSERT query to partitions (PARTITION BY expression)</p><p>and add one or more PARTS per INSERT inside each partition,</p><p>after that background merge process run, and when you have too much unmerged parts inside partition,</p><p>SELECT queries performance can significate degrade, so clickhouse try delay insert, or abort it.</p> |`min(/ClickHouse by HTTP/clickhouse.max.part.count.for.partition,5m)>{$CLICKHOUSE.PARTS.PER.PARTITION.WARN} * 0.9` |WARNING |<p>Manual close: YES</p> |
|ClickHouse: Too many network errors |<p>Number of errors (timeouts and connection failures) during query execution, background pool tasks and DNS cache update is too high.</p> |`min(/ClickHouse by HTTP/clickhouse.network.error.rate,5m)>{$CLICKHOUSE.NETWORK.ERRORS.MAX.WARN}` |WARNING | |
|ClickHouse: Too many distributed files to insert |<p>"Clickhouse servers and <remote_servers> in config.xml</p><p>https://clickhouse.tech/docs/en/operations/table_engines/distributed/"</p> |`min(/ClickHouse by HTTP/clickhouse.distributed.files,5m)>{$CLICKHOUSE.DELAYED.FILES.DISTRIBUTED.COUNT.MAX.WARN}` |WARNING |<p>Manual close: YES</p> |
|ClickHouse: Replication lag is too high |<p>When replica have too much lag, it can be skipped from Distributed SELECT Queries without errors</p><p>and you will have wrong query results.</p> |`min(/ClickHouse by HTTP/clickhouse.replicas.max.absolute.delay,5m)>{$CLICKHOUSE.REPLICA.MAX.WARN}` |WARNING |<p>Manual close: YES</p> |
|ClickHouse: {#DB}.{#TABLE} Replica is readonly |<p>This mode is turned on if the config doesn't have sections with ZooKeeper, if an unknown error occurred when re-initializing sessions in ZooKeeper, and during session re-initialization in ZooKeeper.</p> |`min(/ClickHouse by HTTP/clickhouse.replica.is_readonly["{#DB}.{#TABLE}"],5m)=1` |WARNING | |
|ClickHouse: {#DB}.{#TABLE} Replica session is expired |<p>This mode is turned on if the config doesn't have sections with ZooKeeper, if an unknown error occurred when re-initializing sessions in ZooKeeper, and during session re-initialization in ZooKeeper.</p> |`min(/ClickHouse by HTTP/clickhouse.replica.is_session_expired["{#DB}.{#TABLE}"],5m)=1` |WARNING | |
|ClickHouse: {#DB}.{#TABLE}: Too many operations in queue |<p>-</p> |`min(/ClickHouse by HTTP/clickhouse.replica.queue_size["{#DB}.{#TABLE}"],5m)>{$CLICKHOUSE.QUEUE.SIZE.MAX.WARN:"{#TABLE}"}` |WARNING | |
|ClickHouse: {#DB}.{#TABLE}: Number of active replicas less than number of total replicas |<p>-</p> |`max(/ClickHouse by HTTP/clickhouse.replica.active_replicas["{#DB}.{#TABLE}"],5m) < last(/ClickHouse by HTTP/clickhouse.replica.total_replicas["{#DB}.{#TABLE}"])` |WARNING | |
|ClickHouse: {#DB}.{#TABLE}: Difference between log_max_index and log_pointer is too high |<p>-</p> |`min(/ClickHouse by HTTP/clickhouse.replica.lag["{#DB}.{#TABLE}"],5m) > {$CLICKHOUSE.LOG_POSITION.DIFF.MAX.WARN}` |WARNING | |
|ClickHouse: Too many ZooKeeper sessions opened |<p>Number of sessions (connections) to ZooKeeper.</p><p>Should be no more than one, because using more than one connection to ZooKeeper may lead to bugs due to lack of linearizability (stale reads) that ZooKeeper consistency model allows.</p> |`min(/ClickHouse by HTTP/clickhouse.zookeper.session,5m)>1` |WARNING | |
|ClickHouse: Configuration has been changed |<p>ClickHouse configuration has been changed. Ack to close.</p> |`last(/ClickHouse by HTTP/clickhouse.system.settings,#1)<>last(/ClickHouse by HTTP/clickhouse.system.settings,#2) and length(last(/ClickHouse by HTTP/clickhouse.system.settings))>0` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

