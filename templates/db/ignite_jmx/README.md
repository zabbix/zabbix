
# Ignite by JMX

## Overview

Official JMX Template for Apache Ignite computing platform.
This template is based on the original template developed by Igor Akkuratov, Senior Engineer at GridGain Systems and Apache Ignite Contributor.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Ignite 2.9.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template works with standalone and cluster instances. Metrics are collected by JMX. All metrics are discoverable.

1. Enable and configure JMX access to Apache Ignite. See documentation for [instructions](https://docs.oracle.com/javase/8/docs/technotes/guides/management/agent.html). Current JMX tree hierarchy contains classloader by default. Add the following jvm option `-DIGNITE_MBEAN_APPEND_CLASS_LOADER_ID=false`to will exclude one level with Classloader name. You can configure Cache and Data Region metrics which you want using [official guide](https://ignite.apache.org/docs/latest/monitoring-metrics/configuring-metrics).
2. Set the user name and password in host macros {$IGNITE.USER} and {$IGNITE.PASSWORD}.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IGNITE.PASSWORD}|||
|{$IGNITE.USER}||`zabbix`|
|{$IGNITE.LLD.FILTER.THREAD.POOL.MATCHES}|<p>Filter of discoverable thread pools.</p>|`.*`|
|{$IGNITE.LLD.FILTER.THREAD.POOL.NOT_MATCHES}|<p>Filter to exclude discovered thread pools.</p>|`Macro too long. Please see the template.`|
|{$IGNITE.LLD.FILTER.DATA.REGION.MATCHES}|<p>Filter of discoverable data regions.</p>|`.*`|
|{$IGNITE.LLD.FILTER.DATA.REGION.NOT_MATCHES}|<p>Filter to exclude discovered data regions.</p>|`^(sysMemPlc\|TxLog)$`|
|{$IGNITE.LLD.FILTER.CACHE.MATCHES}|<p>Filter of discoverable cache groups.</p>|`.*`|
|{$IGNITE.LLD.FILTER.CACHE.NOT_MATCHES}|<p>Filter to exclude discovered cache groups.</p>|`CHANGE_IF_NEEDED`|
|{$IGNITE.THREAD.QUEUE.MAX.WARN}|<p>Threshold for thread pool queue size. Can be used with thread pool name as context.</p>|`1000`|
|{$IGNITE.PME.DURATION.MAX.WARN}|<p>The maximum PME duration in ms for warning trigger expression.</p>|`10000`|
|{$IGNITE.PME.DURATION.MAX.HIGH}|<p>The maximum PME duration in ms for high trigger expression.</p>|`60000`|
|{$IGNITE.THREADS.COUNT.MAX.WARN}|<p>The maximum number of running threads for trigger expression.</p>|`1000`|
|{$IGNITE.JOBS.QUEUE.MAX.WARN}|<p>The maximum number of queued jobs for trigger expression.</p>|`10`|
|{$IGNITE.CHECKPOINT.PUSED.MAX.HIGH}|<p>The maximum percent of checkpoint buffer utilization for high trigger expression.</p>|`80`|
|{$IGNITE.CHECKPOINT.PUSED.MAX.WARN}|<p>The maximum percent of checkpoint buffer utilization for warning trigger expression.</p>|`66`|
|{$IGNITE.DATA.REGION.PUSED.MAX.HIGH}|<p>The maximum percent of data region utilization for high trigger expression.</p>|`90`|
|{$IGNITE.DATA.REGION.PUSED.MAX.WARN}|<p>The maximum percent of data region utilization for warning trigger expression.</p>|`80`|

### LLD rule Ignite kernal metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ignite kernal metrics||JMX agent|jmx.discovery[beans,"org.apache:group=Kernal,name=IgniteKernal,*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Ignite kernal metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#JMXIGNITEINSTANCENAME}]: Uptime|<p>Uptime of Ignite instance.</p>|JMX agent|jmx["{#JMXOBJ}",UpTime]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Version|<p>Version of Ignite instance.</p>|JMX agent|jmx["{#JMXOBJ}",FullVersion]<p>**Preprocessing**</p><ul><li><p>Regular expression: `(.*)-\d+ \1`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Local node ID|<p>Unique identifier for this node within grid.</p>|JMX agent|jmx["{#JMXOBJ}",LocalNodeId]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Ignite kernal metrics

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ignite: [{#JMXIGNITEINSTANCENAME}]: Instance has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Ignite by JMX/jmx["{#JMXOBJ}",UpTime])<10m`|Info|**Manual close**: Yes|
|Ignite: [{#JMXIGNITEINSTANCENAME}]: Failed to fetch info data|<p>Zabbix has not received data for items for the last 10 minutes.</p>|`nodata(/Ignite by JMX/jmx["{#JMXOBJ}",UpTime],10m)=1`|Warning|**Manual close**: Yes|
|Ignite: [{#JMXIGNITEINSTANCENAME}]: Version has changed|<p>[{#JMXIGNITEINSTANCENAME}] version has changed. Acknowledge to close the problem manually.</p>|`last(/Ignite by JMX/jmx["{#JMXOBJ}",FullVersion],#1)<>last(/Ignite by JMX/jmx["{#JMXOBJ}",FullVersion],#2) and length(last(/Ignite by JMX/jmx["{#JMXOBJ}",FullVersion]))>0`|Info|**Manual close**: Yes|

### LLD rule Cluster metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster metrics||JMX agent|jmx.discovery[beans,"org.apache:group=Kernal,name=ClusterMetricsMXBeanImpl,*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Cluster metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#JMXIGNITEINSTANCENAME}]: Nodes, Baseline|<p>Total baseline nodes that are registered in the baseline topology.</p>|JMX agent|jmx["{#JMXOBJ}",TotalBaselineNodes]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Nodes, Active baseline|<p>The number of nodes that are currently active in the baseline topology.</p>|JMX agent|jmx["{#JMXOBJ}",ActiveBaselineNodes]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Nodes, Client|<p>The number of client nodes in the cluster.</p>|JMX agent|jmx["{#JMXOBJ}",TotalClientNodes]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Nodes, total|<p>Total number of nodes.</p>|JMX agent|jmx["{#JMXOBJ}",TotalNodes]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Nodes, Server|<p>The number of server nodes in the cluster.</p>|JMX agent|jmx["{#JMXOBJ}",TotalServerNodes]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Cluster metrics

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ignite: [{#JMXIGNITEINSTANCENAME}]: Server node left the topology|<p>One or more server node left the topology. Acknowledge to close the problem manually.</p>|`change(/Ignite by JMX/jmx["{#JMXOBJ}",TotalServerNodes])<0`|Warning|**Manual close**: Yes|
|Ignite: [{#JMXIGNITEINSTANCENAME}]: Server node added to the topology|<p>One or more server node added to the topology. Acknowledge to close the problem manually.</p>|`change(/Ignite by JMX/jmx["{#JMXOBJ}",TotalServerNodes])>0`|Info|**Manual close**: Yes|
|Ignite: [{#JMXIGNITEINSTANCENAME}]: There are nodes is not in topology|<p>One or more server node left the topology. Acknowledge to close the problem manually.</p>|`last(/Ignite by JMX/jmx["{#JMXOBJ}",TotalServerNodes])>last(/Ignite by JMX/jmx["{#JMXOBJ}",TotalBaselineNodes])`|Info|**Manual close**: Yes|

### LLD rule Local node metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Local node metrics||JMX agent|jmx.discovery[beans,"org.apache:group=Kernal,name=ClusterLocalNodeMetricsMXBeanImpl,*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Local node metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#JMXIGNITEINSTANCENAME}]: Jobs cancelled, current|<p>Number of cancelled jobs that are still running.</p>|JMX agent|jmx["{#JMXOBJ}",CurrentCancelledJobs]|
|[{#JMXIGNITEINSTANCENAME}]: Jobs rejected, current|<p>Number of jobs rejected after more recent collision resolution operation.</p>|JMX agent|jmx["{#JMXOBJ}",CurrentRejectedJobs]|
|[{#JMXIGNITEINSTANCENAME}]: Jobs waiting, current|<p>Number of queued jobs currently waiting to be executed.</p>|JMX agent|jmx["{#JMXOBJ}",CurrentWaitingJobs]|
|[{#JMXIGNITEINSTANCENAME}]: Jobs active, current|<p>Number of currently active jobs concurrently executing on the node.</p>|JMX agent|jmx["{#JMXOBJ}",CurrentActiveJobs]|
|[{#JMXIGNITEINSTANCENAME}]: Jobs executed, rate|<p>Total number of jobs handled by the node per second.</p>|JMX agent|jmx["{#JMXOBJ}",TotalExecutedJobs]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Jobs cancelled, rate|<p>Total number of jobs cancelled by the node per second.</p>|JMX agent|jmx["{#JMXOBJ}",TotalCancelledJobs]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Jobs rejects, rate|<p>Total number of jobs this node rejects during collision resolution operations since node startup per second.</p>|JMX agent|jmx["{#JMXOBJ}",TotalRejectedJobs]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: PME duration, current|<p>Current PME duration in milliseconds.</p>|JMX agent|jmx["{#JMXOBJ}",CurrentPmeDuration]|
|[{#JMXIGNITEINSTANCENAME}]: Threads count, current|<p>Current number of live threads.</p>|JMX agent|jmx["{#JMXOBJ}",CurrentThreadCount]|
|[{#JMXIGNITEINSTANCENAME}]: Heap memory used|<p>Current heap size that is used for object allocation.</p>|JMX agent|jmx["{#JMXOBJ}",HeapMemoryUsed]|

### Trigger prototypes for Local node metrics

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ignite: [{#JMXIGNITEINSTANCENAME}]: Number of queued jobs is too high|<p>Number of queued jobs is over {$IGNITE.JOBS.QUEUE.MAX.WARN}.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",CurrentWaitingJobs],15m) > {$IGNITE.JOBS.QUEUE.MAX.WARN}`|Warning||
|Ignite: [{#JMXIGNITEINSTANCENAME}]: PME duration is too long|<p>PME duration is over {$IGNITE.PME.DURATION.MAX.WARN}ms.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",CurrentPmeDuration],5m) > {$IGNITE.PME.DURATION.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Ignite: [{#JMXIGNITEINSTANCENAME}]: PME duration is too long</li></ul>|
|Ignite: [{#JMXIGNITEINSTANCENAME}]: PME duration is too long|<p>PME duration is over {$IGNITE.PME.DURATION.MAX.HIGH}ms. Looks like PME is hung.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",CurrentPmeDuration],5m) > {$IGNITE.PME.DURATION.MAX.HIGH}`|High||
|Ignite: [{#JMXIGNITEINSTANCENAME}]: Number of running threads is too high|<p>Number of running threads is over {$IGNITE.THREADS.COUNT.MAX.WARN}.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",CurrentThreadCount],15m) > {$IGNITE.THREADS.COUNT.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Ignite: [{#JMXIGNITEINSTANCENAME}]: PME duration is too long</li></ul>|

### LLD rule TCP discovery SPI

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TCP discovery SPI||JMX agent|jmx.discovery[beans,"org.apache:group=SPIs,name=TcpDiscoverySpi,*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for TCP discovery SPI

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#JMXIGNITEINSTANCENAME}]: Coordinator|<p>Current coordinator UUID.</p>|JMX agent|jmx["{#JMXOBJ}",Coordinator]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Nodes left|<p>Nodes left count.</p>|JMX agent|jmx["{#JMXOBJ}",NodesLeft]|
|[{#JMXIGNITEINSTANCENAME}]: Nodes joined|<p>Nodes join count.</p>|JMX agent|jmx["{#JMXOBJ}",NodesJoined]|
|[{#JMXIGNITEINSTANCENAME}]: Nodes failed|<p>Nodes failed count.</p>|JMX agent|jmx["{#JMXOBJ}",NodesFailed]|
|[{#JMXIGNITEINSTANCENAME}]: Discovery message worker queue|<p>Message worker queue current size.</p>|JMX agent|jmx["{#JMXOBJ}",MessageWorkerQueueSize]|
|[{#JMXIGNITEINSTANCENAME}]: Discovery reconnect, rate|<p>Number of times node tries to (re)establish connection to another node per second.</p>|JMX agent|jmx["{#JMXOBJ}",ReconnectCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: TotalProcessedMessages|<p>The number of messages received per second.</p>|JMX agent|jmx["{#JMXOBJ}",TotalProcessedMessages]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Discovery messages received, rate|<p>The number of messages processed per second.</p>|JMX agent|jmx["{#JMXOBJ}",TotalReceivedMessages]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Trigger prototypes for TCP discovery SPI

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ignite: [{#JMXIGNITEINSTANCENAME}]: Coordinator has changed|<p>[{#JMXIGNITEINSTANCENAME}] version has changed. Acknowledge to close the problem manually.</p>|`last(/Ignite by JMX/jmx["{#JMXOBJ}",Coordinator],#1)<>last(/Ignite by JMX/jmx["{#JMXOBJ}",Coordinator],#2) and length(last(/Ignite by JMX/jmx["{#JMXOBJ}",Coordinator]))>0`|Warning|**Manual close**: Yes|

### LLD rule TCP Communication SPI metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TCP Communication SPI metrics||JMX agent|jmx.discovery[beans,"org.apache:group=SPIs,name=TcpCommunicationSpi,*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for TCP Communication SPI metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#JMXIGNITEINSTANCENAME}]: Communication outbound messages queue|<p>Outbound messages queue size.</p>|JMX agent|jmx["{#JMXOBJ}",OutboundMessagesQueueSize]|
|[{#JMXIGNITEINSTANCENAME}]: Communication messages received, rate|<p>The number of  messages received per second.</p>|JMX agent|jmx["{#JMXOBJ}",ReceivedMessagesCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|[{#JMXIGNITEINSTANCENAME}]: Communication messages sent, rate|<p>The number of  messages sent per second.</p>|JMX agent|jmx["{#JMXOBJ}",SentMessagesCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### LLD rule Transaction metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Transaction metrics||JMX agent|jmx.discovery[beans,"org.apache:group=TransactionMetrics,name=TransactionMetricsMxBeanImpl,*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Transaction metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#JMXIGNITEINSTANCENAME}]: Locked keys|<p>The number of keys locked on the node.</p>|JMX agent|jmx["{#JMXOBJ}",LockedKeysNumber]|
|[{#JMXIGNITEINSTANCENAME}]: Transactions owner, current|<p>The number of active transactions for which this node is the initiator.</p>|JMX agent|jmx["{#JMXOBJ}",OwnerTransactionsNumber]|
|[{#JMXIGNITEINSTANCENAME}]: Transactions holding lock, current|<p>The number of active transactions holding at least one key lock.</p>|JMX agent|jmx["{#JMXOBJ}",TransactionsHoldingLockNumber]|
|[{#JMXIGNITEINSTANCENAME}]: Transactions rolledback, rate|<p>The number of transactions which were rollback per second.</p>|JMX agent|jmx["{#JMXOBJ}",TransactionsRolledBackNumber]|
|[{#JMXIGNITEINSTANCENAME}]: Transactions committed, rate|<p>The number of transactions which were committed per second.</p>|JMX agent|jmx["{#JMXOBJ}",TransactionsCommittedNumber]|

### LLD rule Cache metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cache metrics||JMX agent|jmx.discovery[beans,"org.apache:name=\"org.apache.ignite.internal.processors.cache.CacheLocalMetricsMXBeanImpl\",*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Cache metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cache group [{#JMXGROUP}]: Cache gets, rate|<p>The number of gets to the cache per second.</p>|JMX agent|jmx["{#JMXOBJ}",CacheGets]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Cache group [{#JMXGROUP}]: Cache puts, rate|<p>The number of puts to the cache per second.</p>|JMX agent|jmx["{#JMXOBJ}",CachePuts]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Cache group [{#JMXGROUP}]: Cache removals, rate|<p>The number of removals from the cache per second.</p>|JMX agent|jmx["{#JMXOBJ}",CacheRemovals]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Cache group [{#JMXGROUP}]: Cache hits, pct|<p>Percentage of successful hits.</p>|JMX agent|jmx["{#JMXOBJ}",CacheHitPercentage]|
|Cache group [{#JMXGROUP}]: Cache misses, pct|<p>Percentage of accesses that failed to find anything.</p>|JMX agent|jmx["{#JMXOBJ}",CacheMissPercentage]|
|Cache group [{#JMXGROUP}]: Cache transaction commits, rate|<p>The number of transaction commits per second.</p>|JMX agent|jmx["{#JMXOBJ}",CacheTxCommits]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Cache group [{#JMXGROUP}]: Cache transaction rollbacks, rate|<p>The number of transaction rollback per second.</p>|JMX agent|jmx["{#JMXOBJ}",CacheTxRollbacks]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Cache group [{#JMXGROUP}]: Cache size|<p>The number of non-null values in the cache as a long value.</p>|JMX agent|jmx["{#JMXOBJ}",CacheSize]|
|Cache group [{#JMXGROUP}]: Cache heap entries|<p>The number of entries in heap memory.</p>|JMX agent|jmx["{#JMXOBJ}",HeapEntriesCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Trigger prototypes for Cache metrics

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ignite: Cache group [{#JMXGROUP}]: There are no success transactions for cache for 5m||`min(/Ignite by JMX/jmx["{#JMXOBJ}",CacheTxRollbacks],5m)>0 and max(/Ignite by JMX/jmx["{#JMXOBJ}",CacheTxCommits],5m)=0`|Average||
|Ignite: Cache group [{#JMXGROUP}]: Success transactions less than rollbacks for 5m||`min(/Ignite by JMX/jmx["{#JMXOBJ}",CacheTxRollbacks],5m) > max(/Ignite by JMX/jmx["{#JMXOBJ}",CacheTxCommits],5m)`|Warning|**Depends on**:<br><ul><li>Ignite: Cache group [{#JMXGROUP}]: There are no success transactions for cache for 5m</li></ul>|
|Ignite: Cache group [{#JMXGROUP}]: All entries are in heap|<p>All entries are in heap. Possibly you use eager queries it may cause out of memory exceptions for big caches. Acknowledge to close the problem manually.</p>|`last(/Ignite by JMX/jmx["{#JMXOBJ}",CacheSize])=last(/Ignite by JMX/jmx["{#JMXOBJ}",HeapEntriesCount])`|Info|**Manual close**: Yes|

### LLD rule Data region metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Data region metrics||JMX agent|jmx.discovery[beans,"org.apache:group=DataRegionMetrics,*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Data region metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Data region {#JMXNAME}: Allocation, rate|<p>Allocation rate (pages per second) averaged across rateTimeInternal.</p>|JMX agent|jmx["{#JMXOBJ}",AllocationRate]|
|Data region {#JMXNAME}: Allocated, bytes|<p>Total size of memory allocated in bytes.</p>|JMX agent|jmx["{#JMXOBJ}",TotalAllocatedSize]|
|Data region {#JMXNAME}: Dirty pages|<p>Number of pages in memory not yet synchronized with persistent storage.</p>|JMX agent|jmx["{#JMXOBJ}",DirtyPages]|
|Data region {#JMXNAME}: Eviction, rate|<p>Eviction rate (pages per second).</p>|JMX agent|jmx["{#JMXOBJ}",EvictionRate]|
|Data region {#JMXNAME}: Size, max|<p>Maximum memory region size defined by its data region.</p>|JMX agent|jmx["{#JMXOBJ}",MaxSize]|
|Data region {#JMXNAME}: Offheap size|<p>Offheap size in bytes.</p>|JMX agent|jmx["{#JMXOBJ}",OffHeapSize]|
|Data region {#JMXNAME}: Offheap used size|<p>Total used offheap size in bytes.</p>|JMX agent|jmx["{#JMXOBJ}",OffheapUsedSize]|
|Data region {#JMXNAME}: Pages fill factor|<p>The percentage of the used space.</p>|JMX agent|jmx["{#JMXOBJ}",PagesFillFactor]|
|Data region {#JMXNAME}: Pages replace, rate|<p>Rate at which pages in memory are replaced with pages from persistent storage (pages per second).</p>|JMX agent|jmx["{#JMXOBJ}",PagesReplaceRate]|
|Data region {#JMXNAME}: Used checkpoint buffer size|<p>Used checkpoint buffer size in bytes.</p>|JMX agent|jmx["{#JMXOBJ}",UsedCheckpointBufferSize]|
|Data region {#JMXNAME}: Checkpoint buffer size|<p>Total size in bytes for checkpoint buffer.</p>|JMX agent|jmx["{#JMXOBJ}",CheckpointBufferSize]|

### Trigger prototypes for Data region metrics

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ignite: Data region {#JMXNAME}: Node started to evict pages|<p>You store more data than region can accommodate. Data started to move to disk it can make requests work slower. Acknowledge to close the problem manually.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",EvictionRate],5m)>0`|Info|**Manual close**: Yes|
|Ignite: Data region {#JMXNAME}: Data region utilization is too high|<p>Data region utilization is high. Increase data region size or delete any data.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",OffheapUsedSize],5m)/last(/Ignite by JMX/jmx["{#JMXOBJ}",OffHeapSize])*100>{$IGNITE.DATA.REGION.PUSED.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Ignite: Data region {#JMXNAME}: Data region utilization is too high</li></ul>|
|Ignite: Data region {#JMXNAME}: Data region utilization is too high|<p>Data region utilization is high. Increase data region size or delete any data.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",OffheapUsedSize],5m)/last(/Ignite by JMX/jmx["{#JMXOBJ}",OffHeapSize])*100>{$IGNITE.DATA.REGION.PUSED.MAX.HIGH}`|High||
|Ignite: Data region {#JMXNAME}: Pages replace rate more than 0|<p>There is more data than DataRegionMaxSize. Cluster started to replace pages in memory. Page replacement can slow down operations.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",PagesReplaceRate],5m)>0`|Warning||
|Ignite: Data region {#JMXNAME}: Checkpoint buffer utilization is too high|<p>Checkpoint buffer utilization is high. Threads will be throttled to avoid buffer overflow. It can be caused by high disk utilization.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",UsedCheckpointBufferSize],5m)/last(/Ignite by JMX/jmx["{#JMXOBJ}",CheckpointBufferSize])*100>{$IGNITE.CHECKPOINT.PUSED.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Ignite: Data region {#JMXNAME}: Checkpoint buffer utilization is too high</li></ul>|
|Ignite: Data region {#JMXNAME}: Checkpoint buffer utilization is too high|<p>Checkpoint buffer utilization is high. Threads will be throttled to avoid buffer overflow. It can be caused by high disk utilization.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",UsedCheckpointBufferSize],5m)/last(/Ignite by JMX/jmx["{#JMXOBJ}",CheckpointBufferSize])*100>{$IGNITE.CHECKPOINT.PUSED.MAX.HIGH}`|High||

### LLD rule Cache groups

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cache groups||JMX agent|jmx.discovery[beans,"org.apache:group=\"Cache groups\",*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Cache groups

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cache group [{#JMXNAME}]: Backups|<p>Count of backups configured for cache group.</p>|JMX agent|jmx["{#JMXOBJ}",Backups]|
|Cache group [{#JMXNAME}]: Partitions|<p>Count of partitions for cache group.</p>|JMX agent|jmx["{#JMXOBJ}",Partitions]|
|Cache group [{#JMXNAME}]: Caches|<p>List of caches.</p>|JMX agent|jmx["{#JMXOBJ}",Caches]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Cache group [{#JMXNAME}]: Local node partitions, moving|<p>Count of partitions with state MOVING for this cache group located on this node.</p>|JMX agent|jmx["{#JMXOBJ}",LocalNodeMovingPartitionsCount]|
|Cache group [{#JMXNAME}]: Local node partitions, renting|<p>Count of partitions with state RENTING for this cache group located on this node.</p>|JMX agent|jmx["{#JMXOBJ}",LocalNodeRentingPartitionsCount]|
|Cache group [{#JMXNAME}]: Local node entries, renting|<p>Count of entries remains to evict in RENTING partitions located on this node for this cache group.</p>|JMX agent|jmx["{#JMXOBJ}",LocalNodeRentingEntriesCount]|
|Cache group [{#JMXNAME}]: Local node partitions, owning|<p>Count of partitions with state OWNING for this cache group located on this node.</p>|JMX agent|jmx["{#JMXOBJ}",LocalNodeOwningPartitionsCount]|
|Cache group [{#JMXNAME}]: Partition copies, min|<p>Minimum number of partition copies for all partitions of this cache group.</p>|JMX agent|jmx["{#JMXOBJ}",MinimumNumberOfPartitionCopies]|
|Cache group [{#JMXNAME}]: Partition copies, max|<p>Maximum number of partition copies for all partitions of this cache group.</p>|JMX agent|jmx["{#JMXOBJ}",MaximumNumberOfPartitionCopies]|

### Trigger prototypes for Cache groups

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ignite: Cache group [{#JMXNAME}]: One or more backups are unavailable||`min(/Ignite by JMX/jmx["{#JMXOBJ}",Backups],5m)>=max(/Ignite by JMX/jmx["{#JMXOBJ}",MinimumNumberOfPartitionCopies],5m)`|Warning||
|Ignite: Cache group [{#JMXNAME}]: List of caches has changed|<p>List of caches has changed. Significant changes have occurred in the cluster. Acknowledge to close the problem manually.</p>|`last(/Ignite by JMX/jmx["{#JMXOBJ}",Caches],#1)<>last(/Ignite by JMX/jmx["{#JMXOBJ}",Caches],#2) and length(last(/Ignite by JMX/jmx["{#JMXOBJ}",Caches]))>0`|Info|**Manual close**: Yes|
|Ignite: Cache group [{#JMXNAME}]: Rebalance in progress|<p>Acknowledge to close the problem manually.</p>|`max(/Ignite by JMX/jmx["{#JMXOBJ}",LocalNodeMovingPartitionsCount],30m)>0`|Info|**Manual close**: Yes|
|Ignite: Cache group [{#JMXNAME}]: There is no copy for partitions||`max(/Ignite by JMX/jmx["{#JMXOBJ}",MinimumNumberOfPartitionCopies],30m)=0`|Warning||

### LLD rule Thread pool metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Thread pool metrics||JMX agent|jmx.discovery[beans,"org.apache:group=\"Thread Pools\",*"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Thread pool metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Thread pool [{#JMXNAME}]: Queue size|<p>Current size of the execution queue.</p>|JMX agent|jmx["{#JMXOBJ}",QueueSize]|
|Thread pool [{#JMXNAME}]: Pool size|<p>Current number of threads in the pool.</p>|JMX agent|jmx["{#JMXOBJ}",PoolSize]|
|Thread pool [{#JMXNAME}]: Pool size, max|<p>The maximum allowed number of threads.</p>|JMX agent|jmx["{#JMXOBJ}",MaximumPoolSize]|
|Thread pool [{#JMXNAME}]: Pool size, core|<p>The core number of threads.</p>|JMX agent|jmx["{#JMXOBJ}",CorePoolSize]|

### Trigger prototypes for Thread pool metrics

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ignite: Thread pool [{#JMXNAME}]: Too many messages in queue|<p>Number of messages in queue more than {$IGNITE.THREAD.QUEUE.MAX.WARN:"{#JMXNAME}"}.</p>|`min(/Ignite by JMX/jmx["{#JMXOBJ}",QueueSize],5m) > {$IGNITE.THREAD.QUEUE.MAX.WARN:"{#JMXNAME}"}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

