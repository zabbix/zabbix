
# Apache Cassandra by JMX

## Overview

For Zabbix version: 6.0 and higher  
Official JMX Template for Apache Cassandra DBSM.


This template was tested on:

- Apache Cassandra, version 3.11.8

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/jmx) for basic instructions.

This template works with standalone and cluster instances.
Metrics are collected by JMX.

1. Enable and configure JMX access to Apache cassandra.
 See documentation for [instructions](https://cassandra.apache.org/doc/latest/operating/security.html#jmx-access).
2. Set the user name and password in host macros {$CASSANDRA.USER} and {$CASSANDRA.PASSWORD}.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CASSANDRA.KEY_SPACE.MATCHES} |<p>Filter of discoverable key spaces</p> |`.*` |
|{$CASSANDRA.KEY_SPACE.NOT_MATCHES} |<p>Filter to exclude discovered key spaces</p> |`(system|system_auth|system_distributed|system_schema)` |
|{$CASSANDRA.PASSWORD} |<p>-</p> |`zabbix` |
|{$CASSANDRA.PENDING_TASKS.MAX.HIGH} |<p>-</p> |`500` |
|{$CASSANDRA.PENDING_TASKS.MAX.WARN} |<p>-</p> |`350` |
|{$CASSANDRA.USER} |<p>-</p> |`zabbix` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Tables |<p>Info about keyspaces and tables</p> |JMX |jmx.discovery[beans,"org.apache.cassandra.metrics:type=Table,keyspace=*,scope=*,name=ReadLatency"]<p>**Filter**:</p>AND <p>- {#JMXKEYSPACE} MATCHES_REGEX `{$CASSANDRA.KEY_SPACE.MATCHES}`</p><p>- {#JMXKEYSPACE} NOT_MATCHES_REGEX `{$CASSANDRA.KEY_SPACE.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Cassandra |Cluster: Nodes down |<p>-</p> |JMX |jmx["org.apache.cassandra.net:type=FailureDetector","DownEndpointCount"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Cassandra |Cluster: Nodes up |<p>-</p> |JMX |jmx["org.apache.cassandra.net:type=FailureDetector","UpEndpointCount"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Cassandra |Cluster: Name |<p>-</p> |JMX |jmx["org.apache.cassandra.db:type=StorageService","ClusterName"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Cassandra |Version |<p>-</p> |JMX |jmx["org.apache.cassandra.db:type=StorageService","ReleaseVersion"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Cassandra |Dropped messages: Write (Mutation) |<p>Number of dropped regular writes messages.</p> |JMX |jmx["org.apache.cassandra.metrics:type=DroppedMessage,scope=MUTATION,name=Dropped","Count"] |
|Cassandra |Dropped messages: Read |<p>Number of dropped regular reads messages.</p> |JMX |jmx["org.apache.cassandra.metrics:type=DroppedMessage,scope=READ,name=Dropped","Count"] |
|Cassandra |Storage: Used (bytes) |<p>Size, in bytes, of the on disk data size this node manages.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Storage,name=Load","Count"] |
|Cassandra |Storage: Errors |<p>Number of internal exceptions caught. Under normal exceptions this should be zero.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Storage,name=Exceptions","Count"] |
|Cassandra |Storage: Hints |<p>Number of hint messages written to this node since [re]start. Includes one entry for each host to be hinted per hint.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Storage,name=TotalHints","Count"] |
|Cassandra |Compaction: Number of completed tasks |<p>Number of completed compactions since server [re]start.</p> |JMX |jmx["org.apache.cassandra.metrics:name=CompletedTasks,type=Compaction","Value"] |
|Cassandra |Compaction: Total compactions completed |<p>Throughput of completed compactions since server [re]start.</p> |JMX |jmx["org.apache.cassandra.metrics:name=TotalCompactionsCompleted,type=Compaction","Count"] |
|Cassandra |Compaction: Pending tasks |<p>Estimated number of compactions remaining to perform.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Compaction,name=PendingTasks","Value"] |
|Cassandra |Commitlog: Pending tasks |<p>Number of commit log messages written but yet to be fsync'd.</p> |JMX |jmx["org.apache.cassandra.metrics:name=PendingTasks,type=CommitLog","Value"] |
|Cassandra |Commitlog: Total size |<p>Current size, in bytes, used by all the commit log segments.</p> |JMX |jmx["org.apache.cassandra.metrics:name=TotalCommitLogSize,type=CommitLog","Value"] |
|Cassandra |Latency: Read median |<p>Latency read from disk in milliseconds - median.</p> |JMX |jmx["org.apache.cassandra.metrics:name=ReadLatency,type=Table","50thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Read 75 percentile |<p>Latency read from disk in milliseconds - p75.</p> |JMX |jmx["org.apache.cassandra.metrics:name=ReadLatency,type=Table","75thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Read 95 percentile |<p>Latency read from disk in milliseconds - p95.</p> |JMX |jmx["org.apache.cassandra.metrics:name=ReadLatency,type=Table","95thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Write median |<p>Latency write to disk in milliseconds - median.</p> |JMX |jmx["org.apache.cassandra.metrics:name=WriteLatency,type=Table","50thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Write 75 percentile |<p>Latency write to disk in milliseconds - p75.</p> |JMX |jmx["org.apache.cassandra.metrics:name=WriteLatency,type=Table","75thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Write 95 percentile |<p>Latency write to disk in milliseconds - p95.</p> |JMX |jmx["org.apache.cassandra.metrics:name=WriteLatency,type=Table","95thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Client request read median |<p>Total latency serving data to clients in milliseconds - median.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Read,name=Latency","50thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Client request read 75 percentile |<p>Total latency serving data to clients in milliseconds - p75.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Read,name=Latency","75thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Client request read 95 percentile |<p>Total latency serving data to clients in milliseconds - p95.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Read,name=Latency","95thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Client request write median |<p>Total latency serving write requests from clients in milliseconds - median.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Latency","50thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Client request write 75 percentile |<p>Total latency serving write requests from clients in milliseconds - p75.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Latency","75thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |Latency: Client request write 95 percentile |<p>Total latency serving write requests from clients in milliseconds - p95.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Latency","95thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |KeyCache: Capacity |<p>Cache capacity in bytes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Capacity","Value"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Cassandra |KeyCache: Entries |<p>Total number of cache entries.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Entries","Value"] |
|Cassandra |KeyCache: HitRate |<p>All time cache hit rate.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=HitRate","Value"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `100`</p> |
|Cassandra |KeyCache: Hits per second |<p>Rate of cache hits.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Hits","Count"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Cassandra |KeyCache: requests per second |<p>Rate of cache requests.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Requests","Count"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Cassandra |KeyCache: Size |<p>Total size of occupied cache, in bytes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Size","Value"] |
|Cassandra |Client connections: Native |<p>Number of clients connected to this nodes native protocol server.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Client,name=connectedNativeClients","Value"] |
|Cassandra |Client connections: Trifts |<p>Number of connected to this nodes thrift clients.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Client,name=connectedThriftClients","Value"] |
|Cassandra |Client request: Read per second |<p>The number of client requests per second.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Read,name=Latency","Count"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Cassandra |Client request: Write per second |<p>The number of local write requests per second.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Latency","Count"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Cassandra |Client request: Write Timeouts |<p>Number of write requests timeouts encountered.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Timeouts","Count"] |
|Cassandra |Thread pool.MutationStage: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>MutationStage: Responsible for writes (exclude materialized and counter writes).</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=MutationStage,name=PendingTasks","Value"] |
|Cassandra |Thread pool MutationStage: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MutationStage: Responsible for writes (exclude materialized and counter writes).</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=MutationStage,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool MutationStage: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>MutationStage: Responsible for writes (exclude materialized and counter writes).</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=MutationStage,name=TotalBlockedTasks","Count"] |
|Cassandra |Thread pool CounterMutationStage: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>CounterMutationStage: Responsible for counter writes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=CounterMutationStage,name=PendingTasks","Value"] |
|Cassandra |Thread pool CounterMutationStage: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>CounterMutationStage: Responsible for counter writes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=CounterMutationStage,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool CounterMutationStage: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>CounterMutationStage: Responsible for counter writes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=CounterMutationStage,name=TotalBlockedTasks","Count"] |
|Cassandra |Thread pool ReadStage: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>ReadStage: Local reads run on this thread pool.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ReadStage,name=PendingTasks","Value"] |
|Cassandra |Thread pool ReadStage: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>ReadStage: Local reads run on this thread pool.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ReadStage,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool ReadStage: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>ReadStage: Local reads run on this thread pool.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ReadStage,name=TotalBlockedTasks","Count"] |
|Cassandra |Thread pool ViewMutationStage: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>ViewMutationStage: Responsible for materialized view writes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ViewMutationStage,name=PendingTasks","Value"] |
|Cassandra |Thread pool ViewMutationStage: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>ViewMutationStage: Responsible for materialized view writes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ViewMutationStage,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool ViewMutationStage: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>ViewMutationStage: Responsible for materialized view writes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ViewMutationStage,name=TotalBlockedTasks","Count"] |
|Cassandra |Thread pool MemtableFlushWriter: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>MemtableFlushWriter: Writes memtables to disk.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtableFlushWriter,name=PendingTasks","Value"] |
|Cassandra |Thread pool MemtableFlushWriter: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MemtableFlushWriter: Writes memtables to disk.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtableFlushWriter,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool MemtableFlushWriter: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>MemtableFlushWriter: Writes memtables to disk.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtableFlushWriter,name=TotalBlockedTasks","Count"] |
|Cassandra |Thread pool HintsDispatcher: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>HintsDispatcher: Performs hinted handoff.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=HintsDispatcher,name=PendingTasks","Value"] |
|Cassandra |Thread pool HintsDispatcher: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>HintsDispatcher: Performs hinted handoff.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=HintsDispatcher,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool HintsDispatcher: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>HintsDispatcher: Performs hinted handoff.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=HintsDispatcher,name=TotalBlockedTasks","Count"] |
|Cassandra |Thread pool MemtablePostFlush: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>MemtablePostFlush: Cleans up commit log after memtable is written to disk.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtablePostFlush,name=PendingTasks","Value"] |
|Cassandra |Thread pool MemtablePostFlush: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MemtablePostFlush: Cleans up commit log after memtable is written to disk.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtablePostFlush,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool MemtablePostFlush: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>MemtablePostFlush: Cleans up commit log after memtable is written to disk.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtablePostFlush,name=TotalBlockedTasks","Count"] |
|Cassandra |Thread pool MigrationStage: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>MigrationStage: Runs schema migrations.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MigrationStage,name=PendingTasks","Value"] |
|Cassandra |Thread pool MigrationStage: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MigrationStage: Runs schema migrations.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MigrationStage,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool MigrationStage: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>MigrationStage: Runs schema migrations.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MigrationStage,name=TotalBlockedTasks","Count"] |
|Cassandra |Thread pool MiscStage: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>MiscStage: Miscellaneous tasks run here.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MiscStage,name=PendingTasks","Value"] |
|Cassandra |Thread pool MiscStage: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MiscStage: Miscellaneous tasks run here.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MiscStage,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool MiscStage: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>MiscStage: Miscellaneous tasks run here.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MiscStage,name=TotalBlockedTasks","Count"] |
|Cassandra |Thread pool SecondaryIndexManagement: Pending tasks |<p>Number of queued tasks queued up on this pool.</p><p>SecondaryIndexManagement: Performs updates to secondary indexes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=SecondaryIndexManagement,name=PendingTasks","Value"] |
|Cassandra |Thread pool SecondaryIndexManagement: Currently blocked task |<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>SecondaryIndexManagement: Performs updates to secondary indexes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=SecondaryIndexManagement,name=CurrentlyBlockedTasks","Count"] |
|Cassandra |Thread pool SecondaryIndexManagement: Total blocked tasks |<p>Number of tasks that were blocked due to queue saturation.</p><p>SecondaryIndexManagement: Performs updates to secondary indexes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=SecondaryIndexManagement,name=TotalBlockedTasks","Count"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: SS Tables per read 75 percentile |<p>The number of SSTable data files accessed per read - p75.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=SSTablesPerReadHistogram","75thPercentile"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: SS Tables per read 95 percentile |<p>The number of SSTable data files accessed per read - p95.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=SSTablesPerReadHistogram","95thPercentile"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Tombstone scanned 75 percentile |<p>Number of tombstones scanned per read - p75.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=TombstoneScannedHistogram","75thPercentile"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Tombstone scanned 95 percentile |<p>Number of tombstones scanned per read - p95.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=TombstoneScannedHistogram","95thPercentile"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Waiting on free memtable space 75 percentile |<p>The time spent waiting for free memtable space either on- or off-heap - p75.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WaitingOnFreeMemtableSpace","75thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Waiting on free memtable space95 percentile |<p>The time spent waiting for free memtable space either on- or off-heap - p95.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WaitingOnFreeMemtableSpace","95thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Col update time delta75 percentile |<p>The column update time delta - p75.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ColUpdateTimeDeltaHistogram","75thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Col update time delta 95 percentile |<p>The column update time delta - p95.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ColUpdateTimeDeltaHistogram","95thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Bloom filter false ratio |<p>The ratio of Bloom filter false positives to total checks.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=BloomFilterFalseRatio","Value"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Compression ratio |<p>The compression ratio for all SSTables.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=CompressionRatio","Value"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: KeyCache hit rate |<p>The key cache hit rate.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=KeyCacheHitRate","Value"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Live SS Table |<p>Number of "live" (in use) SSTables.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=LiveSSTableCount","Value"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Max sartition size |<p>The size of the largest compacted partition.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=MaxPartitionSize","Value"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Mean partition size |<p>The average size of compacted partition.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=MeanPartitionSize","Value"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Pending compactions |<p>The number of pending compactions.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=PendingCompactions","Value"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Snapshots size |<p>The disk space truly used by snapshots.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=SnapshotsSize","Value"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Compaction bytes written |<p>The amount of data that was compacted since (re)start.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=CompactionBytesWritten","Count"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Bytes flushed |<p>The amount of data that was flushed since (re)start.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=BytesFlushed","Count"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Pending flushes |<p>The number of pending flushes.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=PendingFlushes","Count"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Live disk space used |<p>The disk space used by "live" SSTables (only counts in use files).</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=LiveDiskSpaceUsed","Count"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Disk space used |<p>Disk space used.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=TotalDiskSpaceUsed","Count"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Out of row cache hits |<p>The number of row cache hits that do not satisfy the query filter and went to disk.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=RowCacheHitOutOfRange","Count"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Row cache hits |<p>The number of row cache hits.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=RowCacheHit","Count"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Row cache misses |<p>The number of table row cache misses.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=RowCacheMiss","Count"] |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Read latency 75 percentile |<p>Latency read from disk in milliseconds.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ReadLatency","75thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Read latency 95 percentile |<p>Latency read from disk in milliseconds.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ReadLatency","95thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Read per second |<p>The number of client requests per second.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ReadLatency","Count"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Write latency 75 percentile |<p>Latency write to disk in milliseconds.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WriteLatency","75thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Write latency 95 percentile |<p>Latency write to disk in milliseconds.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WriteLatency","95thPercentile"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Cassandra |{#JMXKEYSPACE}.{#JMXSCOPE}: Write per second |<p>The number of local write requests per second.</p> |JMX |jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WriteLatency","Count"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|There are down nodes in cluster |<p>-</p> |`last(/Apache Cassandra by JMX/jmx["org.apache.cassandra.net:type=FailureDetector","DownEndpointCount"])>0` |AVERAGE | |
|Version has changed |<p>Cassandra version has changed. Ack to close.</p> |`last(/Apache Cassandra by JMX/jmx["org.apache.cassandra.db:type=StorageService","ReleaseVersion"],#1)<>last(/Apache Cassandra by JMX/jmx["org.apache.cassandra.db:type=StorageService","ReleaseVersion"],#2) and length(last(/Apache Cassandra by JMX/jmx["org.apache.cassandra.db:type=StorageService","ReleaseVersion"]))>0` |INFO |<p>Manual close: YES</p> |
|Failed to fetch info data |<p>Zabbix has not received data for items for the last 15 minutes</p> |`nodata(/Apache Cassandra by JMX/jmx["org.apache.cassandra.metrics:type=Storage,name=Load","Count"],15m)=1` |WARNING | |
|Too many storage exceptions |<p>-</p> |`min(/Apache Cassandra by JMX/jmx["org.apache.cassandra.metrics:type=Storage,name=Exceptions","Count"],5m)>0` |WARNING | |
|Many pending tasks |<p>-</p> |`min(/Apache Cassandra by JMX/jmx["org.apache.cassandra.metrics:type=Compaction,name=PendingTasks","Value"],15m)>{$CASSANDRA.PENDING_TASKS.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Too many pending tasks</p> |
|Too many pending tasks |<p>-</p> |`min(/Apache Cassandra by JMX/jmx["org.apache.cassandra.metrics:type=Compaction,name=PendingTasks","Value"],15m)>{$CASSANDRA.PENDING_TASKS.MAX.HIGH}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/410057-discussion-thread-for-official-zabbix-template-apache-cassandra).

