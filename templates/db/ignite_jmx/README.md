
# Ignite by JMX

## Overview

For Zabbix version: 6.0 and higher  
Official JMX Template for Apache Ignite computing platform.
This template is based on the original template developed by Igor Akkuratov, Senior Engineer at GridGain Systems and Apache Ignite Contributor.


This template was tested on:

- Ignite, version 2.9.0

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/jmx) for basic instructions.

This template works with standalone and cluster instances. Metrics are collected by JMX. All metrics are discoverable.

1. Enable and configure JMX access to Apache Ignite. See documentation for [instructions](https://docs.oracle.com/javase/8/docs/technotes/guides/management/agent.html). Current JMX tree hierarchy contains classloader by default. Add the following jvm option `-DIGNITE_MBEAN_APPEND_CLASS_LOADER_ID=false`to will exclude one level with Classloader name. You can configure Cache and Data Region metrics which you want using [official guide](https://ignite.apache.org/docs/latest/monitoring-metrics/configuring-metrics).
2. Set the user name and password in host macros {$IGNITE.USER} and {$IGNITE.PASSWORD}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IGNITE.CHECKPOINT.PUSED.MAX.HIGH} |<p>The maximum percent of checkpoint buffer utilization for high trigger expression.</p> |`80` |
|{$IGNITE.CHECKPOINT.PUSED.MAX.WARN} |<p>The maximum percent of checkpoint buffer utilization for warning trigger expression.</p> |`66` |
|{$IGNITE.DATA.REGION.PUSED.MAX.HIGH} |<p>The maximum percent of data region utilization for high trigger expression.</p> |`90` |
|{$IGNITE.DATA.REGION.PUSED.MAX.WARN} |<p>The maximum percent of data region utilization for warning trigger expression.</p> |`80` |
|{$IGNITE.JOBS.QUEUE.MAX.WARN} |<p>The maximum number of queued jobs for trigger expression.</p> |`10` |
|{$IGNITE.LLD.FILTER.CACHE.MATCHES} |<p>Filter of discoverable cache groups.</p> |`.*` |
|{$IGNITE.LLD.FILTER.CACHE.NOT_MATCHES} |<p>Filter to exclude discovered cache groups.</p> |`CHANGE_IF_NEEDED` |
|{$IGNITE.LLD.FILTER.DATA.REGION.MATCHES} |<p>Filter of discoverable data regions.</p> |`.*` |
|{$IGNITE.LLD.FILTER.DATA.REGION.NOT_MATCHES} |<p>Filter to exclude discovered data regions.</p> |`^(sysMemPlc|TxLog)$` |
|{$IGNITE.LLD.FILTER.THREAD.POOL.MATCHES} |<p>Filter of discoverable thread pools.</p> |`.*` |
|{$IGNITE.LLD.FILTER.THREAD.POOL.NOT_MATCHES} |<p>Filter to exclude discovered thread pools.</p> |`^(GridCallbackExecutor|GridRebalanceStripedExecutor|GridDataStreamExecutor|StripedExecutor)$` |
|{$IGNITE.PASSWORD} |<p>-</p> |`<secret>` |
|{$IGNITE.PME.DURATION.MAX.HIGH} |<p>The maximum PME duration in ms for high trigger expression.</p> |`60000` |
|{$IGNITE.PME.DURATION.MAX.WARN} |<p>The maximum PME duration in ms for warning trigger expression.</p> |`10000` |
|{$IGNITE.THREAD.QUEUE.MAX.WARN} |<p>Threshold for thread pool queue size. Can be used with thread pool name as context.</p> |`1000` |
|{$IGNITE.THREADS.COUNT.MAX.WARN} |<p>The maximum number of running threads for trigger expression.</p> |`1000` |
|{$IGNITE.USER} |<p>-</p> |`zabbix` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Cache groups |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:group=\"Cache groups\",*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Filter**:</p>AND <p>- {#JMXNAME} MATCHES_REGEX `{$IGNITE.LLD.FILTER.CACHE.MATCHES}`</p><p>- {#JMXNAME} NOT_MATCHES_REGEX `{$IGNITE.LLD.FILTER.CACHE.NOT_MATCHES}`</p> |
|Cache metrics |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:name=\"org.apache.ignite.internal.processors.cache.CacheLocalMetricsMXBeanImpl\",*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Filter**:</p>AND <p>- {#JMXGROUP} MATCHES_REGEX `{$IGNITE.LLD.FILTER.CACHE.MATCHES}`</p><p>- {#JMXGROUP} NOT_MATCHES_REGEX `{$IGNITE.LLD.FILTER.CACHE.NOT_MATCHES}`</p> |
|Cluster metrics |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:group=Kernal,name=ClusterMetricsMXBeanImpl,*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Data region metrics |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:group=DataRegionMetrics,*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Filter**:</p>AND <p>- {#JMXNAME} MATCHES_REGEX `{$IGNITE.LLD.FILTER.DATA.REGION.MATCHES}`</p><p>- {#JMXNAME} NOT_MATCHES_REGEX `{$IGNITE.LLD.FILTER.DATA.REGION.NOT_MATCHES}`</p> |
|Ignite kernal metrics |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:group=Kernal,name=IgniteKernal,*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Local node metrics |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:group=Kernal,name=ClusterLocalNodeMetricsMXBeanImpl,*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|TCP Communication SPI metrics |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:group=SPIs,name=TcpCommunicationSpi,*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|TCP discovery SPI |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:group=SPIs,name=TcpDiscoverySpi,*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Thread pool metrics |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:group=\"Thread Pools\",*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Filter**:</p>AND <p>- {#JMXNAME} MATCHES_REGEX `{$IGNITE.LLD.FILTER.THREAD.POOL.MATCHES}`</p><p>- {#JMXNAME} NOT_MATCHES_REGEX `{$IGNITE.LLD.FILTER.THREAD.POOL.NOT_MATCHES}`</p> |
|Transaction metrics |<p>-</p> |JMX |jmx.discovery[beans,"org.apache:group=TransactionMetrics,name=TransactionMetricsMxBeanImpl,*"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Uptime |<p>Uptime of Ignite instance.</p> |JMX |jmx["{#JMXOBJ}",UpTime]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Version |<p>Version of Ignite instance.</p> |JMX |jmx["{#JMXOBJ}",FullVersion]<p>**Preprocessing**:</p><p>- REGEX: `(.*)-\d+ \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Local node ID |<p>Unique identifier for this node within grid.</p> |JMX |jmx["{#JMXOBJ}",LocalNodeId]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Nodes, Baseline |<p>Total baseline nodes that are registered in the baseline topology.</p> |JMX |jmx["{#JMXOBJ}",TotalBaselineNodes]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Nodes, Active baseline |<p>The number of nodes that are currently active in the baseline topology.</p> |JMX |jmx["{#JMXOBJ}",ActiveBaselineNodes]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Nodes, Client |<p>The number of client nodes in the cluster.</p> |JMX |jmx["{#JMXOBJ}",TotalClientNodes]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Nodes, total |<p>Total number of nodes.</p> |JMX |jmx["{#JMXOBJ}",TotalNodes]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Nodes, Server |<p>The number of server nodes in the cluster.</p> |JMX |jmx["{#JMXOBJ}",TotalServerNodes]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Jobs cancelled, current |<p>Number of cancelled jobs that are still running.</p> |JMX |jmx["{#JMXOBJ}",CurrentCancelledJobs] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Jobs rejected, current |<p>Number of jobs rejected after more recent collision resolution operation.</p> |JMX |jmx["{#JMXOBJ}",CurrentRejectedJobs] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Jobs waiting, current |<p>Number of queued jobs currently waiting to be executed.</p> |JMX |jmx["{#JMXOBJ}",CurrentWaitingJobs] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Jobs active, current |<p>Number of currently active jobs concurrently executing on the node.</p> |JMX |jmx["{#JMXOBJ}",CurrentActiveJobs] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Jobs executed, rate |<p>Total number of jobs handled by the node per second.</p> |JMX |jmx["{#JMXOBJ}",TotalExecutedJobs]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Jobs cancelled, rate |<p>Total number of jobs cancelled by the node per second.</p> |JMX |jmx["{#JMXOBJ}",TotalCancelledJobs]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Jobs rejects, rate |<p>Total number of jobs this node rejects during collision resolution operations since node startup per second.</p> |JMX |jmx["{#JMXOBJ}",TotalRejectedJobs]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: PME duration, current |<p>Current PME duration in milliseconds.</p> |JMX |jmx["{#JMXOBJ}",CurrentPmeDuration] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Threads count, current |<p>Current number of live threads.</p> |JMX |jmx["{#JMXOBJ}",CurrentThreadCount] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Heap memory used |<p>Current heap size that is used for object allocation.</p> |JMX |jmx["{#JMXOBJ}",HeapMemoryUsed] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Coordinator |<p>Current coordinator UUID.</p> |JMX |jmx["{#JMXOBJ}",Coordinator]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Nodes left |<p>Nodes left count.</p> |JMX |jmx["{#JMXOBJ}",NodesLeft] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Nodes joined |<p>Nodes join count.</p> |JMX |jmx["{#JMXOBJ}",NodesJoined] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Nodes failed |<p>Nodes failed count.</p> |JMX |jmx["{#JMXOBJ}",NodesFailed] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Discovery message worker queue |<p>Message worker queue current size.</p> |JMX |jmx["{#JMXOBJ}",MessageWorkerQueueSize] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Discovery reconnect, rate |<p>Number of times node tries to (re)establish connection to another node per second.</p> |JMX |jmx["{#JMXOBJ}",ReconnectCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: TotalProcessedMessages |<p>The number of messages received per second.</p> |JMX |jmx["{#JMXOBJ}",TotalProcessedMessages]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Discovery messages received, rate |<p>The number of messages processed per second.</p> |JMX |jmx["{#JMXOBJ}",TotalReceivedMessages]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Communication outbound messages queue |<p>Outbound messages queue size.</p> |JMX |jmx["{#JMXOBJ}",OutboundMessagesQueueSize] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Communication messages received, rate |<p>The number of  messages received per second.</p> |JMX |jmx["{#JMXOBJ}",ReceivedMessagesCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Communication messages sent, rate |<p>The number of  messages sent per second.</p> |JMX |jmx["{#JMXOBJ}",SentMessagesCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Locked keys |<p>The number of keys locked on the node.</p> |JMX |jmx["{#JMXOBJ}",LockedKeysNumber] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Transactions owner, current |<p>The number of active transactions for which this node is the initiator.</p> |JMX |jmx["{#JMXOBJ}",OwnerTransactionsNumber] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Transactions holding lock, current |<p>The number of active transactions holding at least one key lock.</p> |JMX |jmx["{#JMXOBJ}",TransactionsHoldingLockNumber] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Transactions rolledback, rate |<p>The number of transactions which were rollback per second.</p> |JMX |jmx["{#JMXOBJ}",TransactionsRolledBackNumber] |
|Ignite |Ignite [{#JMXIGNITEINSTANCENAME}]: Transactions committed, rate |<p>The number of transactions which were committed per second.</p> |JMX |jmx["{#JMXOBJ}",TransactionsCommittedNumber] |
|Ignite |Cache group [{#JMXGROUP}]: Cache gets, rate |<p>The number of gets to the cache per second.</p> |JMX |jmx["{#JMXOBJ}",CacheGets]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Cache group [{#JMXGROUP}]: Cache puts, rate |<p>The number of puts to the cache per second.</p> |JMX |jmx["{#JMXOBJ}",CachePuts]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Cache group [{#JMXGROUP}]: Cache removals, rate |<p>The number of removals from the cache per second.</p> |JMX |jmx["{#JMXOBJ}",CacheRemovals]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Cache group [{#JMXGROUP}]: Cache hits, pct |<p>Percentage of successful hits.</p> |JMX |jmx["{#JMXOBJ}",CacheHitPercentage] |
|Ignite |Cache group [{#JMXGROUP}]: Cache misses, pct |<p>Percentage of accesses that failed to find anything.</p> |JMX |jmx["{#JMXOBJ}",CacheMissPercentage] |
|Ignite |Cache group [{#JMXGROUP}]: Cache transaction commits, rate |<p>The number of transaction commits per second.</p> |JMX |jmx["{#JMXOBJ}",CacheTxCommits]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Cache group [{#JMXGROUP}]: Cache transaction rollbacks, rate |<p>The number of transaction rollback per second.</p> |JMX |jmx["{#JMXOBJ}",CacheTxRollbacks]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Cache group [{#JMXGROUP}]: Cache size |<p>The number of non-null values in the cache as a long value.</p> |JMX |jmx["{#JMXOBJ}",CacheSize] |
|Ignite |Cache group [{#JMXGROUP}]: Cache heap entries |<p>The number of entries in heap memory.</p> |JMX |jmx["{#JMXOBJ}",HeapEntriesCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Ignite |Data region {#JMXNAME}: Allocation, rate |<p>Allocation rate (pages per second) averaged across rateTimeInternal.</p> |JMX |jmx["{#JMXOBJ}",AllocationRate] |
|Ignite |Data region {#JMXNAME}: Allocated, bytes |<p>Total size of memory allocated in bytes.</p> |JMX |jmx["{#JMXOBJ}",TotalAllocatedSize] |
|Ignite |Data region {#JMXNAME}: Dirty pages |<p>Number of pages in memory not yet synchronized with persistent storage.</p> |JMX |jmx["{#JMXOBJ}",DirtyPages] |
|Ignite |Data region {#JMXNAME}: Eviction, rate |<p>Eviction rate (pages per second).</p> |JMX |jmx["{#JMXOBJ}",EvictionRate] |
|Ignite |Data region {#JMXNAME}: Size, max |<p>Maximum memory region size defined by its data region.</p> |JMX |jmx["{#JMXOBJ}",MaxSize] |
|Ignite |Data region {#JMXNAME}: Offheap size |<p>Offheap size in bytes.</p> |JMX |jmx["{#JMXOBJ}",OffHeapSize] |
|Ignite |Data region {#JMXNAME}: Offheap used size |<p>Total used offheap size in bytes.</p> |JMX |jmx["{#JMXOBJ}",OffheapUsedSize] |
|Ignite |Data region {#JMXNAME}: Pages fill factor |<p>The percentage of the used space.</p> |JMX |jmx["{#JMXOBJ}",PagesFillFactor] |
|Ignite |Data region {#JMXNAME}: Pages replace, rate |<p>Rate at which pages in memory are replaced with pages from persistent storage (pages per second).</p> |JMX |jmx["{#JMXOBJ}",PagesReplaceRate] |
|Ignite |Data region {#JMXNAME}: Used checkpoint buffer size |<p>Used checkpoint buffer size in bytes.</p> |JMX |jmx["{#JMXOBJ}",UsedCheckpointBufferSize] |
|Ignite |Data region {#JMXNAME}: Checkpoint buffer size |<p>Total size in bytes for checkpoint buffer.</p> |JMX |jmx["{#JMXOBJ}",CheckpointBufferSize] |
|Ignite |Cache group [{#JMXNAME}]: Backups |<p>Count of backups configured for cache group.</p> |JMX |jmx["{#JMXOBJ}",Backups] |
|Ignite |Cache group [{#JMXNAME}]: Partitions |<p>Count of partitions for cache group.</p> |JMX |jmx["{#JMXOBJ}",Partitions] |
|Ignite |Cache group [{#JMXNAME}]: Caches |<p>List of caches.</p> |JMX |jmx["{#JMXOBJ}",Caches]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Ignite |Cache group [{#JMXNAME}]: Local node partitions, moving |<p>Count of partitions with state MOVING for this cache group located on this node.</p> |JMX |jmx["{#JMXOBJ}",LocalNodeMovingPartitionsCount] |
|Ignite |Cache group [{#JMXNAME}]: Local node partitions, renting |<p>Count of partitions with state RENTING for this cache group located on this node.</p> |JMX |jmx["{#JMXOBJ}",LocalNodeRentingPartitionsCount] |
|Ignite |Cache group [{#JMXNAME}]: Local node entries, renting |<p>Count of entries remains to evict in RENTING partitions located on this node for this cache group.</p> |JMX |jmx["{#JMXOBJ}",LocalNodeRentingEntriesCount] |
|Ignite |Cache group [{#JMXNAME}]: Local node partitions, owning |<p>Count of partitions with state OWNING for this cache group located on this node.</p> |JMX |jmx["{#JMXOBJ}",LocalNodeOwningPartitionsCount] |
|Ignite |Cache group [{#JMXNAME}]: Partition copies, min |<p>Minimum number of partition copies for all partitions of this cache group.</p> |JMX |jmx["{#JMXOBJ}",MinimumNumberOfPartitionCopies] |
|Ignite |Cache group [{#JMXNAME}]: Partition copies, max |<p>Maximum number of partition copies for all partitions of this cache group.</p> |JMX |jmx["{#JMXOBJ}",MaximumNumberOfPartitionCopies] |
|Ignite |Thread pool [{#JMXNAME}]: Queue size |<p>Current size of the execution queue.</p> |JMX |jmx["{#JMXOBJ}",QueueSize] |
|Ignite |Thread pool [{#JMXNAME}]: Pool size |<p>Current number of threads in the pool.</p> |JMX |jmx["{#JMXOBJ}",PoolSize] |
|Ignite |Thread pool [{#JMXNAME}]: Pool size, max |<p>The maximum allowed number of threads.</p> |JMX |jmx["{#JMXOBJ}",MaximumPoolSize] |
|Ignite |Thread pool [{#JMXNAME}]: Pool size, core |<p>The core number of threads.</p> |JMX |jmx["{#JMXOBJ}",CorePoolSize] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Ignite [{#JMXIGNITEINSTANCENAME}]: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Ignite by JMX/jmx["{#JMXOBJ}",UpTime])<10m` |INFO |<p>Manual close: YES</p> |
|Ignite [{#JMXIGNITEINSTANCENAME}]: Failed to fetch info data |<p>Zabbix has not received data for items for the last 10 minutes.</p> |`nodata(/Ignite by JMX/jmx["{#JMXOBJ}",UpTime],10m)=1` |WARNING |<p>Manual close: YES</p> |
|Ignite [{#JMXIGNITEINSTANCENAME}]: Version has changed |<p>Ignite [{#JMXIGNITEINSTANCENAME}] version has changed. Ack to close.</p> |`last(/Ignite by JMX/jmx["{#JMXOBJ}",FullVersion],#1)<>last(/Ignite by JMX/jmx["{#JMXOBJ}",FullVersion],#2) and length(last(/Ignite by JMX/jmx["{#JMXOBJ}",FullVersion]))>0` |INFO |<p>Manual close: YES</p> |
|Ignite [{#JMXIGNITEINSTANCENAME}]: Server node left the topology |<p>One or more server node left the topology. Ack to close.</p> |`change(/Ignite by JMX/jmx["{#JMXOBJ}",TotalServerNodes])<0` |WARNING |<p>Manual close: YES</p> |
|Ignite [{#JMXIGNITEINSTANCENAME}]: Server node added to the topology |<p>One or more server node added to the topology. Ack to close.</p> |`change(/Ignite by JMX/jmx["{#JMXOBJ}",TotalServerNodes])>0` |INFO |<p>Manual close: YES</p> |
|Ignite [{#JMXIGNITEINSTANCENAME}]: There are nodes is not in topology |<p>One or more server node left the topology. Ack to close.</p> |`last(/Ignite by JMX/jmx["{#JMXOBJ}",TotalServerNodes])>last(/Ignite by JMX/jmx["{#JMXOBJ}",TotalBaselineNodes])` |INFO |<p>Manual close: YES</p> |
|Ignite [{#JMXIGNITEINSTANCENAME}]: Number of queued jobs is too high |<p>Number of queued jobs is over {$IGNITE.JOBS.QUEUE.MAX.WARN}.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",CurrentWaitingJobs],15m) > {$IGNITE.JOBS.QUEUE.MAX.WARN}` |WARNING | |
|Ignite [{#JMXIGNITEINSTANCENAME}]: PME duration is too long |<p>PME duration is over {$IGNITE.PME.DURATION.MAX.WARN}ms.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",CurrentPmeDuration],5m) > {$IGNITE.PME.DURATION.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Ignite [{#JMXIGNITEINSTANCENAME}]: PME duration is too long</p> |
|Ignite [{#JMXIGNITEINSTANCENAME}]: PME duration is too long |<p>PME duration is over {$IGNITE.PME.DURATION.MAX.HIGH}ms. Looks like PME is hung.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",CurrentPmeDuration],5m) > {$IGNITE.PME.DURATION.MAX.HIGH}` |HIGH | |
|Ignite [{#JMXIGNITEINSTANCENAME}]: Number of running threads is too high |<p>Number of running threads is over {$IGNITE.THREADS.COUNT.MAX.WARN}.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",CurrentThreadCount],15m) > {$IGNITE.THREADS.COUNT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Ignite [{#JMXIGNITEINSTANCENAME}]: PME duration is too long</p> |
|Ignite [{#JMXIGNITEINSTANCENAME}]: Coordinator has changed |<p>Ignite [{#JMXIGNITEINSTANCENAME}] version has changed. Ack to close.</p> |`last(/Ignite by JMX/jmx["{#JMXOBJ}",Coordinator],#1)<>last(/Ignite by JMX/jmx["{#JMXOBJ}",Coordinator],#2) and length(last(/Ignite by JMX/jmx["{#JMXOBJ}",Coordinator]))>0` |WARNING |<p>Manual close: YES</p> |
|Cache group [{#JMXGROUP}]: There are no success transactions for cache for 5m |<p>-</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",CacheTxRollbacks],5m)>0 and max(/Ignite by JMX/jmx["{#JMXOBJ}",CacheTxCommits],5m)=0` |AVERAGE | |
|Cache group [{#JMXGROUP}]: Success transactions less than rollbacks for 5m |<p>-</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",CacheTxRollbacks],5m) > max(/Ignite by JMX/jmx["{#JMXOBJ}",CacheTxCommits],5m)` |WARNING |<p>**Depends on**:</p><p>- Cache group [{#JMXGROUP}]: There are no success transactions for cache for 5m</p> |
|Cache group [{#JMXGROUP}]: All entries are in heap |<p>All entries are in heap. Possibly you use eager queries it may cause out of memory exceptions for big caches. Ack to close.</p> |`last(/Ignite by JMX/jmx["{#JMXOBJ}",CacheSize])=last(/Ignite by JMX/jmx["{#JMXOBJ}",HeapEntriesCount])` |INFO |<p>Manual close: YES</p> |
|Data region {#JMXNAME}: Node started to evict pages |<p>You store more data than region can accommodate. Data started to move to disk it can make requests work slower. Ack to close.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",EvictionRate],5m)>0` |INFO |<p>Manual close: YES</p> |
|Data region {#JMXNAME}: Data region utilization is too high |<p>Data region utilization is high. Increase data region size or delete any data.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",OffheapUsedSize],5m)/last(/Ignite by JMX/jmx["{#JMXOBJ}",OffHeapSize])*100>{$IGNITE.DATA.REGION.PUSED.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Data region {#JMXNAME}: Data region utilization is too high</p> |
|Data region {#JMXNAME}: Data region utilization is too high |<p>Data region utilization is high. Increase data region size or delete any data.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",OffheapUsedSize],5m)/last(/Ignite by JMX/jmx["{#JMXOBJ}",OffHeapSize])*100>{$IGNITE.DATA.REGION.PUSED.MAX.HIGH}` |HIGH | |
|Data region {#JMXNAME}: Pages replace rate more than 0 |<p>There is more data than DataRegionMaxSize. Cluster started to replace pages in memory. Page replacement can slow down operations.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",PagesReplaceRate],5m)>0` |WARNING | |
|Data region {#JMXNAME}: Checkpoint buffer utilization is too high |<p>Checkpoint buffer utilization is high. Threads will be throttled to avoid buffer overflow. It can be caused by high disk utilization.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",UsedCheckpointBufferSize],5m)/last(/Ignite by JMX/jmx["{#JMXOBJ}",CheckpointBufferSize])*100>{$IGNITE.CHECKPOINT.PUSED.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Data region {#JMXNAME}: Checkpoint buffer utilization is too high</p> |
|Data region {#JMXNAME}: Checkpoint buffer utilization is too high |<p>Checkpoint buffer utilization is high. Threads will be throttled to avoid buffer overflow. It can be caused by high disk utilization.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",UsedCheckpointBufferSize],5m)/last(/Ignite by JMX/jmx["{#JMXOBJ}",CheckpointBufferSize])*100>{$IGNITE.CHECKPOINT.PUSED.MAX.HIGH}` |HIGH | |
|Cache group [{#JMXNAME}]: One or more backups are unavailable |<p>-</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",Backups],5m)>=max(/Ignite by JMX/jmx["{#JMXOBJ}",MinimumNumberOfPartitionCopies],5m)` |WARNING | |
|Cache group [{#JMXNAME}]: List of caches has changed |<p>List of caches has changed. Significant changes have occurred in the cluster. Ack to close.</p> |`last(/Ignite by JMX/jmx["{#JMXOBJ}",Caches],#1)<>last(/Ignite by JMX/jmx["{#JMXOBJ}",Caches],#2) and length(last(/Ignite by JMX/jmx["{#JMXOBJ}",Caches]))>0` |INFO |<p>Manual close: YES</p> |
|Cache group [{#JMXNAME}]: Rebalance in progress |<p>Ack to close.</p> |`max(/Ignite by JMX/jmx["{#JMXOBJ}",LocalNodeMovingPartitionsCount],30m)>0` |INFO |<p>Manual close: YES</p> |
|Cache group [{#JMXNAME}]: There is no copy for partitions |<p>-</p> |`max(/Ignite by JMX/jmx["{#JMXOBJ}",MinimumNumberOfPartitionCopies],30m)=0` |WARNING | |
|Thread pool [{#JMXNAME}]: Too many messages in queue |<p>Number of messages in queue more than {$IGNITE.THREAD.QUEUE.MAX.WARN:"{#JMXNAME}"}.</p> |`min(/Ignite by JMX/jmx["{#JMXOBJ}",QueueSize],5m) > {$IGNITE.THREAD.QUEUE.MAX.WARN:"{#JMXNAME}"}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

