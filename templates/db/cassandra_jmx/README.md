
# Apache Cassandra by JMX

## Overview

This template is designed for the effortless deployment of Apache Cassandra monitoring by Zabbix via JMX and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Apache Cassandra 3.11.8

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This template works with standalone and cluster instances.
Metrics are collected by JMX.

1. Enable and configure JMX access to Apache cassandra.
 See documentation for [instructions](https://cassandra.apache.org/doc/latest/operating/security.html#jmx-access).
2. Set the user name and password in host macros {$CASSANDRA.USER} and {$CASSANDRA.PASSWORD}.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CASSANDRA.USER}||`zabbix`|
|{$CASSANDRA.PASSWORD}||`zabbix`|
|{$CASSANDRA.KEY_SPACE.MATCHES}|<p>Filter of discoverable key spaces</p>|`.*`|
|{$CASSANDRA.KEY_SPACE.NOT_MATCHES}|<p>Filter to exclude discovered key spaces</p>|`(system\|system_auth\|system_distributed\|system_schema)`|
|{$CASSANDRA.PENDING_TASKS.MAX.HIGH}||`500`|
|{$CASSANDRA.PENDING_TASKS.MAX.WARN}||`350`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Apache Cassandra: Cluster - Nodes down||JMX agent|jmx["org.apache.cassandra.net:type=FailureDetector","DownEndpointCount"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Apache Cassandra: Cluster - Nodes up||JMX agent|jmx["org.apache.cassandra.net:type=FailureDetector","UpEndpointCount"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Apache Cassandra: Cluster - Name||JMX agent|jmx["org.apache.cassandra.db:type=StorageService","ClusterName"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Apache Cassandra: Version||JMX agent|jmx["org.apache.cassandra.db:type=StorageService","ReleaseVersion"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Apache Cassandra: Dropped messages - Write (Mutation)|<p>Number of dropped regular writes messages.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=DroppedMessage,scope=MUTATION,name=Dropped","Count"]|
|Apache Cassandra: Dropped messages - Read|<p>Number of dropped regular reads messages.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=DroppedMessage,scope=READ,name=Dropped","Count"]|
|Apache Cassandra: Storage - Used (bytes)|<p>Size, in bytes, of the on disk data size this node manages.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Storage,name=Load","Count"]|
|Apache Cassandra: Storage - Errors|<p>Number of internal exceptions caught. Under normal exceptions this should be zero.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Storage,name=Exceptions","Count"]|
|Apache Cassandra: Storage - Hints|<p>Number of hint messages written to this node since [re]start. Includes one entry for each host to be hinted per hint.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Storage,name=TotalHints","Count"]|
|Apache Cassandra: Compaction - Number of completed tasks|<p>Number of completed compactions since server [re]start.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=CompletedTasks,type=Compaction","Value"]|
|Apache Cassandra: Compaction - Total compactions completed|<p>Throughput of completed compactions since server [re]start.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=TotalCompactionsCompleted,type=Compaction","Count"]|
|Apache Cassandra: Compaction - Pending tasks|<p>Estimated number of compactions remaining to perform.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Compaction,name=PendingTasks","Value"]|
|Apache Cassandra: Commitlog - Pending tasks|<p>Number of commit log messages written but yet to be fsync'd.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=PendingTasks,type=CommitLog","Value"]|
|Apache Cassandra: Commitlog - Total size|<p>Current size, in bytes, used by all the commit log segments.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=TotalCommitLogSize,type=CommitLog","Value"]|
|Apache Cassandra: Latency - Read median|<p>Latency read from disk in milliseconds - median.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=ReadLatency,type=Table","50thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Read 75 percentile|<p>Latency read from disk in milliseconds - p75.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=ReadLatency,type=Table","75thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Read 95 percentile|<p>Latency read from disk in milliseconds - p95.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=ReadLatency,type=Table","95thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Write median|<p>Latency write to disk in milliseconds - median.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=WriteLatency,type=Table","50thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Write 75 percentile|<p>Latency write to disk in milliseconds - p75.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=WriteLatency,type=Table","75thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Write 95 percentile|<p>Latency write to disk in milliseconds - p95.</p>|JMX agent|jmx["org.apache.cassandra.metrics:name=WriteLatency,type=Table","95thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Client request read median|<p>Total latency serving data to clients in milliseconds - median.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Read,name=Latency","50thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Client request read 75 percentile|<p>Total latency serving data to clients in milliseconds - p75.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Read,name=Latency","75thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Client request read 95 percentile|<p>Total latency serving data to clients in milliseconds - p95.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Read,name=Latency","95thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Client request write median|<p>Total latency serving write requests from clients in milliseconds - median.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Latency","50thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Client request write 75 percentile|<p>Total latency serving write requests from clients in milliseconds - p75.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Latency","75thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: Latency - Client request write 95 percentile|<p>Total latency serving write requests from clients in milliseconds - p95.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Latency","95thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Apache Cassandra: KeyCache - Capacity|<p>Cache capacity in bytes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Capacity","Value"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Apache Cassandra: KeyCache - Entries|<p>Total number of cache entries.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Entries","Value"]|
|Apache Cassandra: KeyCache - HitRate|<p>All time cache hit rate.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=HitRate","Value"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `100`</p></li></ul>|
|Apache Cassandra: KeyCache - Hits per second|<p>Rate of cache hits.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Hits","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Apache Cassandra: KeyCache - requests per second|<p>Rate of cache requests.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Requests","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Apache Cassandra: KeyCache - Size|<p>Total size of occupied cache, in bytes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Cache,scope=KeyCache,name=Size","Value"]|
|Apache Cassandra: Client connections - Native|<p>Number of clients connected to this nodes native protocol server.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Client,name=connectedNativeClients","Value"]|
|Apache Cassandra: Client connections - Trifts|<p>Number of connected to this nodes thrift clients.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Client,name=connectedThriftClients","Value"]|
|Apache Cassandra: Client request - Read per second|<p>The number of client requests per second.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Read,name=Latency","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Apache Cassandra: Client request - Write per second|<p>The number of local write requests per second.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Latency","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Apache Cassandra: Client request - Write Timeouts|<p>Number of write requests timeouts encountered.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ClientRequest,scope=Write,name=Timeouts","Count"]|
|Apache Cassandra: Thread pool.MutationStage - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>MutationStage: Responsible for writes (exclude materialized and counter writes).</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=MutationStage,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool MutationStage - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MutationStage: Responsible for writes (exclude materialized and counter writes).</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=MutationStage,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool MutationStage - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>MutationStage: Responsible for writes (exclude materialized and counter writes).</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=MutationStage,name=TotalBlockedTasks","Count"]|
|Apache Cassandra: Thread pool CounterMutationStage - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>CounterMutationStage: Responsible for counter writes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=CounterMutationStage,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool CounterMutationStage - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>CounterMutationStage: Responsible for counter writes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=CounterMutationStage,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool CounterMutationStage - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>CounterMutationStage: Responsible for counter writes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=CounterMutationStage,name=TotalBlockedTasks","Count"]|
|Apache Cassandra: Thread pool ReadStage - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>ReadStage: Local reads run on this thread pool.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ReadStage,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool ReadStage - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>ReadStage: Local reads run on this thread pool.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ReadStage,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool ReadStage - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>ReadStage: Local reads run on this thread pool.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ReadStage,name=TotalBlockedTasks","Count"]|
|Apache Cassandra: Thread pool ViewMutationStage - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>ViewMutationStage: Responsible for materialized view writes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ViewMutationStage,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool ViewMutationStage - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>ViewMutationStage: Responsible for materialized view writes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ViewMutationStage,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool ViewMutationStage - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>ViewMutationStage: Responsible for materialized view writes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=request,scope=ViewMutationStage,name=TotalBlockedTasks","Count"]|
|Apache Cassandra: Thread pool MemtableFlushWriter - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>MemtableFlushWriter: Writes memtables to disk.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtableFlushWriter,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool MemtableFlushWriter - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MemtableFlushWriter: Writes memtables to disk.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtableFlushWriter,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool MemtableFlushWriter - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>MemtableFlushWriter: Writes memtables to disk.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtableFlushWriter,name=TotalBlockedTasks","Count"]|
|Apache Cassandra: Thread pool HintsDispatcher - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>HintsDispatcher: Performs hinted handoff.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=HintsDispatcher,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool HintsDispatcher - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>HintsDispatcher: Performs hinted handoff.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=HintsDispatcher,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool HintsDispatcher - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>HintsDispatcher: Performs hinted handoff.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=HintsDispatcher,name=TotalBlockedTasks","Count"]|
|Apache Cassandra: Thread pool MemtablePostFlush - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>MemtablePostFlush: Cleans up commit log after memtable is written to disk.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtablePostFlush,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool MemtablePostFlush - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MemtablePostFlush: Cleans up commit log after memtable is written to disk.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtablePostFlush,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool MemtablePostFlush - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>MemtablePostFlush: Cleans up commit log after memtable is written to disk.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MemtablePostFlush,name=TotalBlockedTasks","Count"]|
|Apache Cassandra: Thread pool MigrationStage - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>MigrationStage: Runs schema migrations.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MigrationStage,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool MigrationStage - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MigrationStage: Runs schema migrations.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MigrationStage,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool MigrationStage - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>MigrationStage: Runs schema migrations.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MigrationStage,name=TotalBlockedTasks","Count"]|
|Apache Cassandra: Thread pool MiscStage - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>MiscStage: Miscellaneous tasks run here.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MiscStage,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool MiscStage - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>MiscStage: Miscellaneous tasks run here.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MiscStage,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool MiscStage - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>MiscStage: Miscellaneous tasks run here.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=MiscStage,name=TotalBlockedTasks","Count"]|
|Apache Cassandra: Thread pool SecondaryIndexManagement - Pending tasks|<p>Number of queued tasks queued up on this pool.</p><p>SecondaryIndexManagement: Performs updates to secondary indexes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=SecondaryIndexManagement,name=PendingTasks","Value"]|
|Apache Cassandra: Thread pool SecondaryIndexManagement - Currently blocked task|<p>Number of tasks that are currently blocked due to queue saturation but on retry will become unblocked.</p><p>SecondaryIndexManagement: Performs updates to secondary indexes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=SecondaryIndexManagement,name=CurrentlyBlockedTasks","Count"]|
|Apache Cassandra: Thread pool SecondaryIndexManagement - Total blocked tasks|<p>Number of tasks that were blocked due to queue saturation.</p><p>SecondaryIndexManagement: Performs updates to secondary indexes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=ThreadPools,path=internal,scope=SecondaryIndexManagement,name=TotalBlockedTasks","Count"]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Apache Cassandra: There are down nodes in cluster||`last(/Apache Cassandra by JMX/jmx["org.apache.cassandra.net:type=FailureDetector","DownEndpointCount"])>0`|Average||
|Apache Cassandra: Version has changed|<p>Cassandra version has changed. Acknowledge to close the problem manually.</p>|`last(/Apache Cassandra by JMX/jmx["org.apache.cassandra.db:type=StorageService","ReleaseVersion"],#1)<>last(/Apache Cassandra by JMX/jmx["org.apache.cassandra.db:type=StorageService","ReleaseVersion"],#2) and length(last(/Apache Cassandra by JMX/jmx["org.apache.cassandra.db:type=StorageService","ReleaseVersion"]))>0`|Info|**Manual close**: Yes|
|Apache Cassandra: Failed to fetch info data|<p>Zabbix has not received data for items for the last 15 minutes</p>|`nodata(/Apache Cassandra by JMX/jmx["org.apache.cassandra.metrics:type=Storage,name=Load","Count"],15m)=1`|Warning||
|Apache Cassandra: Too many storage exceptions||`min(/Apache Cassandra by JMX/jmx["org.apache.cassandra.metrics:type=Storage,name=Exceptions","Count"],5m)>0`|Warning||
|Apache Cassandra: Many pending tasks||`min(/Apache Cassandra by JMX/jmx["org.apache.cassandra.metrics:type=Compaction,name=PendingTasks","Value"],15m)>{$CASSANDRA.PENDING_TASKS.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Apache Cassandra: Too many pending tasks</li></ul>|
|Apache Cassandra: Too many pending tasks||`min(/Apache Cassandra by JMX/jmx["org.apache.cassandra.metrics:type=Compaction,name=PendingTasks","Value"],15m)>{$CASSANDRA.PENDING_TASKS.MAX.HIGH}`|Average||

### LLD rule Tables

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tables|<p>Info about keyspaces and tables</p>|JMX agent|jmx.discovery[beans,"org.apache.cassandra.metrics:type=Table,keyspace=*,scope=*,name=ReadLatency"]|

### Item prototypes for Tables

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#JMXKEYSPACE}.{#JMXSCOPE}: SS Tables per read 75 percentile|<p>The number of SSTable data files accessed per read - p75.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=SSTablesPerReadHistogram","75thPercentile"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: SS Tables per read 95 percentile|<p>The number of SSTable data files accessed per read - p95.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=SSTablesPerReadHistogram","95thPercentile"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Tombstone scanned 75 percentile|<p>Number of tombstones scanned per read - p75.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=TombstoneScannedHistogram","75thPercentile"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Tombstone scanned 95 percentile|<p>Number of tombstones scanned per read - p95.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=TombstoneScannedHistogram","95thPercentile"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Waiting on free memtable space 75 percentile|<p>The time spent waiting for free memtable space either on- or off-heap - p75.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WaitingOnFreeMemtableSpace","75thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Waiting on free memtable space95 percentile|<p>The time spent waiting for free memtable space either on- or off-heap - p95.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WaitingOnFreeMemtableSpace","95thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Col update time delta75 percentile|<p>The column update time delta - p75.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ColUpdateTimeDeltaHistogram","75thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Col update time delta 95 percentile|<p>The column update time delta - p95.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ColUpdateTimeDeltaHistogram","95thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Bloom filter false ratio|<p>The ratio of Bloom filter false positives to total checks.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=BloomFilterFalseRatio","Value"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Compression ratio|<p>The compression ratio for all SSTables.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=CompressionRatio","Value"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: KeyCache hit rate|<p>The key cache hit rate.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=KeyCacheHitRate","Value"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Live SS Table|<p>Number of "live" (in use) SSTables.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=LiveSSTableCount","Value"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Max partition size|<p>The size of the largest compacted partition.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=MaxPartitionSize","Value"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Mean partition size|<p>The average size of compacted partition.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=MeanPartitionSize","Value"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Pending compactions|<p>The number of pending compactions.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=PendingCompactions","Value"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Snapshots size|<p>The disk space truly used by snapshots.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=SnapshotsSize","Value"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Compaction bytes written|<p>The amount of data that was compacted since (re)start.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=CompactionBytesWritten","Count"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Bytes flushed|<p>The amount of data that was flushed since (re)start.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=BytesFlushed","Count"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Pending flushes|<p>The number of pending flushes.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=PendingFlushes","Count"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Live disk space used|<p>The disk space used by "live" SSTables (only counts in use files).</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=LiveDiskSpaceUsed","Count"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Disk space used|<p>Disk space used.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=TotalDiskSpaceUsed","Count"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Out of row cache hits|<p>The number of row cache hits that do not satisfy the query filter and went to disk.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=RowCacheHitOutOfRange","Count"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Row cache hits|<p>The number of row cache hits.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=RowCacheHit","Count"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Row cache misses|<p>The number of table row cache misses.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=RowCacheMiss","Count"]|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Read latency 75 percentile|<p>Latency read from disk in milliseconds.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ReadLatency","75thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Read latency 95 percentile|<p>Latency read from disk in milliseconds.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ReadLatency","95thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Read per second|<p>The number of client requests per second.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=ReadLatency","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Write latency 75 percentile|<p>Latency write to disk in milliseconds.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WriteLatency","75thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Write latency 95 percentile|<p>Latency write to disk in milliseconds.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WriteLatency","95thPercentile"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#JMXKEYSPACE}.{#JMXSCOPE}: Write per second|<p>The number of local write requests per second.</p>|JMX agent|jmx["org.apache.cassandra.metrics:type=Table,keyspace={#JMXKEYSPACE},scope={#JMXSCOPE},name=WriteLatency","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

